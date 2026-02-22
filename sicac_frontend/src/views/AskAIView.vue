<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { Icon } from "@iconify/vue";
import { type CatalogProduct } from '@/services/DataService';
import { useCartStore } from '@/store/cartStore'; 
import { useRouter } from 'vue-router';
import { config_app } from '@/config/app';

// Form Data Structure aligned with Catalog
const formData = reactive({
    budget: {
        min: null as number | null,
        max: null as number | null,
    },
    productTypes: [] as string[],
    // CCTV Specific
    cctv: {
        usage: '', // interior, exterior
        technology: [] as string[], // IP, TVI, WIFI
        resolution: '', // 2MP, 4MP,etc
        nightVision: false,
        audio: false, 
    },
    // Alarm Home Specific
    alarmHome: {
        type: [] as string[], // Kit, Central, Sensor, Sirena
        connectivity: [] as string[], // WiFi, GSM, Linea
        environment: '',
        pets: false, // "Tenes mascotas?"
        protectionPoints: [] as string[], // Puertas/Ventanas, Interior, Exterior
    },
    // Alarm Auto Specific
    alarmAuto: {
        vehicleType: '', // Auto, Camioneta, Moto
    },
    // Access Control (Porteros, etc)
    access: {
        subfamily: [] as string[], // Portero visor, Control de acceso, Automatización
        entryMethods: [] as string[], // Face, Fingerprint, Card, Code, App
    }
});

const loading = ref(false);
const recommendation = ref<string | null>(null);
const recommendedProducts = ref<CatalogProduct[]>([]);
const cartStore = useCartStore();
const router = useRouter();
const aiBaseUrl = config_app.ai_url;

// Chat State
const chatMessages = ref<Array<{role: string, content: string}>>([]);
const userMessage = ref('');
const sendingMessage = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const systemPromptUsed = ref<string>("");

const normalizeText = (value: string) => {
    return value
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9]+/g, " ")
        .trim();
};

const accessoryKeywords = [
    "memoria",
    "memory",
    "micro sd",
    "sd",
    "disco",
    "hdd",
    "ssd",
    "almacen",
    "grabador",
    "dvr",
    "nvr",
    "cable",
    "conector",
    "fuente",
    "power",
    "poe",
    "switch",
    "inyector",
    "soporte",
    "montaje",
    "gabinete"
];

const isAccessoryProduct = (product: CatalogProduct) => {
    const text = normalizeText(`${product.Nombre || ""} ${product.subfamilia || ""} ${product.familia || ""}`);
    return accessoryKeywords.some(keyword => text.includes(keyword));
};

const isProductNamedInRecommendation = (recText: string, product: CatalogProduct) => {
    const name = normalizeText(product.Nombre || "");
    const sku = normalizeText(product["Modelo/SKU"] || "");
    if (name && recText.includes(name)) return true;
    if (sku && recText.includes(sku)) return true;
    return false;
};

const displayedRecommendedProducts = computed(() => {
    if (!recommendation.value) return recommendedProducts.value;
    const recText = normalizeText(recommendation.value);
    if (!recText) return recommendedProducts.value;

    const named = recommendedProducts.value.filter(product =>
        isProductNamedInRecommendation(recText, product)
    );

    if (named.length === 0) return recommendedProducts.value;

    const namedFamilies = new Set(named.map(product => normalizeText(product.familia || "")));
    const namedIds = new Set(named.map(product => product.ID));
    const accessories = recommendedProducts.value.filter(product => {
        if (namedIds.has(product.ID)) return false;
        if (!isAccessoryProduct(product)) return false;

        const fam = normalizeText(product.familia || "");
        const sub = normalizeText(product.subfamilia || "");
        if (namedFamilies.has(fam)) return true;
        if (namedFamilies.has("cctv") && (fam.includes("infra") || sub.includes("almacen") || sub.includes("acces"))) {
            return true;
        }
        return false;
    });

    return [...named, ...accessories];
});

// Helper to scroll chat to bottom
const scrollToBottom = () => {
    setTimeout(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    }, 100);
};

const submitForm = async () => {
    loading.value = true;
    recommendation.value = null;
    recommendedProducts.value = [];
    chatMessages.value = [];

    try {
        const requests = [];

        // 1. CCTV Request
        if (formData.productTypes.includes('cctv')) {
            requests.push({
                category: "CCTV", // Matches "CCTV" family
                subcategory: formData.cctv.usage,
                price_min: formData.budget.min,
                price_max: formData.budget.max,
                attributes: {
                    ...formData.cctv,
                    type: "CCTV"
                }
            });
        }

        // 2. Alarm Home Request
        if (formData.productTypes.includes('alarm_home')) {
            requests.push({
                category: "Alarma",
                subcategory: "Casa",
                price_min: formData.budget.min,
                price_max: formData.budget.max,
                attributes: {
                    ...formData.alarmHome,
                    type: "Alarma Domiciliaria"
                }
            });
        }

        // 3. Alarm Auto Request
        if (formData.productTypes.includes('alarm_auto')) {
            requests.push({
                category: "Alarma",
                subcategory: formData.alarmAuto.vehicleType || "Vehículo", 
                price_min: formData.budget.min,
                price_max: formData.budget.max,
                attributes: {
                    ...formData.alarmAuto,
                    type: "Alarma Vehicular"
                }
            });
        }

        // 4. Access Control Request
        if (formData.productTypes.includes('access')) {
            requests.push({
                category: "Acceso", // Matches "Control de Acceso"
                price_min: formData.budget.min,
                price_max: formData.budget.max,
                attributes: {
                    ...formData.access,
                    subfamilies: formData.access.subfamily, // map explicit array
                    type: "Control de Acceso"
                }
            });
        }

        if (requests.length === 0) {
            recommendation.value = "Por favor seleccion? al menos una familia de productos.";
            loading.value = false;
            return;
        }

        // Call Backend
        const response = await fetch(`${aiBaseUrl}/recommender/recommend_multi`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                requests: requests,
                user_name: "Cliente" // Could grab from auth store if available
            })
        });

        if (!response.ok) {
            throw new Error(`API Error: ${response.statusText}`);
        }

        const data = await response.json();
        recommendation.value = data.response;
        recommendedProducts.value = data.products || [];
        systemPromptUsed.value = data.system_prompt_used || "";

         // Initialize Chat History
        chatMessages.value.push({ role: 'assistant', content: data.response });

    } catch (error) {
        console.error("Error submitting AI request:", error);
        recommendation.value = "Hubo un error al procesar tu solicitud. Por favor intenta de nuevo.";
    } finally {
        loading.value = false;
    }
};

const sendChatMessage = async () => {
    if (!userMessage.value.trim()) return;

    // Add user message
    const msg = { role: 'user', content: userMessage.value };
    chatMessages.value.push(msg);
    userMessage.value = ''; // clear input
    sendingMessage.value = true;
    scrollToBottom();

    try {
        // Construct full history for context
        const history: any[] = [];
        if (systemPromptUsed.value) {
            history.push({ role: 'system', content: systemPromptUsed.value });
        } else {
             history.push({ role: 'system', content: "Sos Gustavo, vendedor experto." });
        }

        // Append visible chat history (assistant and user exchanges)
        chatMessages.value.forEach(m => {
            history.push({ role: m.role, content: m.content });
        });

        // Backend call
        const response = await fetch(`${aiBaseUrl}/recommender/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: history })
        });

        if (!response.ok) throw new Error("Chat Error");
        
        const data = await response.json();
        chatMessages.value.push({ role: 'assistant', content: data.response });

    } catch (e) {
        chatMessages.value.push({ role: 'assistant', content: "Lo siento, tuve un problema de conexión. ¿Podés repetir?" });
    } finally {
        sendingMessage.value = false;
        scrollToBottom();
    }
};

const addToCart = (product: CatalogProduct) => {
     let category: 'camera' | 'alarm' | 'sensor' = 'sensor';
    const fam = product.familia.toLowerCase();
    const sub = product.subfamilia?.toLowerCase() || '';

    if (fam.includes('alarm') || sub.includes('sirena') || sub.includes('central')) category = 'alarm';
    else if (fam.includes('acceso') || sub.includes('camera') || sub.includes('portero')) category = 'camera';
    else if (sub.includes('sensor') || sub.includes('detector')) category = 'sensor';

    cartStore.addItem({
        id: product.ID,
        name: product.Nombre,
        description: product.Texto_RAG,
        price: product["Precio (ARS)"],
        category: category
    });
};

const getProductQuantity = (productId: string) => {
    return cartStore.items.filter(item => item.id === productId).length;
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(price);
};
</script>

<template>
    <div class="p-6 space-y-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Recomendación IA</h1>
                <p class="text-muted-foreground">Seleccioná las características deseadas para cada familia de productos.</p>
            </div>
            <router-link to="/budget" class="group relative inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90">
                <Icon icon="mdi:cart-outline" class="mr-2 h-5 w-5" />
                Presupuesto ({{ cartStore.items.length }})
            </router-link>
        </div>

        <div class="grid gap-8 md:grid-cols-1 lg:grid-cols-2">
            <!-- Form Section -->
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm p-6 h-fit">
                <form @submit.prevent="submitForm" class="space-y-8">
                    
                    <!-- 1. System Family Selection -->
                    <div class="space-y-4">
                        <label class="text-base font-semibold leading-none flex items-center gap-2">
                            <Icon icon="mdi:shape-outline" class="text-primary" />
                            Familias de Productos
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-accent transition-all flex flex-col items-center gap-2 text-center text-sm', formData.productTypes.includes('cctv') ? 'border-primary bg-primary/5' : 'border-transparent bg-secondary']">
                                <input type="checkbox" value="cctv" v-model="formData.productTypes" class="sr-only" />
                                <Icon icon="mdi:cctv" class="h-6 w-6 mb-1" />
                                CCTV
                            </label>
                            <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-accent transition-all flex flex-col items-center gap-2 text-center text-sm', formData.productTypes.includes('alarm_home') ? 'border-primary bg-primary/5' : 'border-transparent bg-secondary']">
                                <input type="checkbox" value="alarm_home" v-model="formData.productTypes" class="sr-only" />
                                <Icon icon="mdi:home-alert" class="h-6 w-6 mb-1" />
                                Alarma Casa
                            </label>
                            <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-accent transition-all flex flex-col items-center gap-2 text-center text-sm', formData.productTypes.includes('alarm_auto') ? 'border-primary bg-primary/5' : 'border-transparent bg-secondary']">
                                <input type="checkbox" value="alarm_auto" v-model="formData.productTypes" class="sr-only" />
                                <Icon icon="mdi:car-emergency" class="h-6 w-6 mb-1" />
                                Alarma Auto
                            </label>
                            <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-accent transition-all flex flex-col items-center gap-2 text-center text-sm', formData.productTypes.includes('access') ? 'border-primary bg-primary/5' : 'border-transparent bg-secondary']">
                                <input type="checkbox" value="access" v-model="formData.productTypes" class="sr-only" />
                                <Icon icon="mdi:shield-account" class="h-6 w-6 mb-1" />
                                Accesos
                            </label>
                        </div>
                    </div>

                    <!-- Budget Section -->
                    <div class="space-y-4">
                        <label class="text-base font-semibold leading-none flex items-center gap-2">
                            <Icon icon="mdi:cash-multiple" class="text-primary" />
                            Rango de Precio (Opcional)
                        </label>
                        <div class="flex gap-4">
                            <div class="w-full">
                                <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">Mínimo</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-muted-foreground">$</span>
                                    <input type="number" v-model="formData.budget.min" placeholder="Ej: 10000" class="flex h-10 w-full rounded-md border border-input bg-background pl-7 pr-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" />
                                </div>
                            </div>
                            <div class="w-full">
                                <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">Máximo</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-muted-foreground">$</span>
                                    <input type="number" v-model="formData.budget.max" placeholder="Ej: 500000" class="flex h-10 w-full rounded-md border border-input bg-background pl-7 pr-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. CCTV Configuration -->
                    <div v-if="formData.productTypes.includes('cctv')" class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-5 space-y-6 animate-in fade-in slide-in-from-top-4">
                        <h4 class="text-base font-semibold flex items-center gap-2 text-emerald-700">
                            <Icon icon="mdi:cctv" class="h-6 w-6" />
                            Configuración CCTV
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <!-- Usage -->
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">¿Dónde las vas a instalar?</label>
                                <select v-model="formData.cctv.usage" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                    <option value="" disabled>Seleccionar ambiente</option>
                                    <option value="interior">Interior (Hogar/Oficina)</option>
                                    <option value="exterior">Exterior (Intemperie/Jardín)</option>
                                    <option value="mixto">Ambos</option>
                                </select>
                            </div>
                             <!-- Resolution -->
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">Calidad de Imagen</label>
                                <select v-model="formData.cctv.resolution" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                    <option value="" disabled>Seleccionar resolución</option>
                                    <option value="2mp">2 MP (Full HD) - Estándar</option>
                                    <option value="4mp">4 MP (2K) - Buena definición</option>
                                    <option value="5mp">5 MP - Alta definición</option>
                                    <option value="8mp">8 MP (4K) - Máxima calidad</option>
                                </select>
                            </div>
                        </div>

                         <!-- Technology & Extra Features -->
                        <div>
                             <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-3">Tecnologías y Funciones</label>
                             <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <label class="flex items-center space-x-2 border rounded-md p-2 hover:bg-emerald-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="IP" v-model="formData.cctv.technology" class="rounded border-emerald-500 text-emerald-600 focus:ring-emerald-500"/>
                                    <span class="text-sm">IP (Digital / PoE)</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 hover:bg-emerald-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="WIFI" v-model="formData.cctv.technology" class="rounded border-emerald-500 text-emerald-600 focus:ring-emerald-500"/>
                                    <span class="text-sm">WiFi (Inalámbricas)</span>
                                </label>
                                 <label class="flex items-center space-x-2 border rounded-md p-2 hover:bg-emerald-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="Analog" v-model="formData.cctv.technology" class="rounded border-emerald-500 text-emerald-600 focus:ring-emerald-500"/>
                                    <span class="text-sm">Analógico (Cableado)</span>
                                </label>
                                 <label class="flex items-center space-x-2 border rounded-md p-2 hover:bg-emerald-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="ColorVu" v-model="formData.cctv.technology" class="rounded border-emerald-500 text-emerald-600 focus:ring-emerald-500"/>
                                    <span class="text-sm">ColorVu (Color 24/7)</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 hover:bg-emerald-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" v-model="formData.cctv.audio" class="rounded border-emerald-500 text-emerald-600 focus:ring-emerald-500"/>
                                    <span class="text-sm">Audio (Micrófono)</span>
                                </label>
                             </div>
                        </div>
                    </div>

                    <!-- 3. Alarm Home Configuration -->
                    <div v-if="formData.productTypes.includes('alarm_home')" class="rounded-lg border border-red-200 bg-red-50/50 p-5 space-y-6 animate-in fade-in slide-in-from-top-4">
                        <h4 class="text-base font-semibold flex items-center gap-2 text-red-700">
                            <Icon icon="mdi:home-alert" class="h-6 w-6" />
                            Configuración Alarma Casa
                        </h4>
                        
                        <!-- Pets Question -->
                        <div class="bg-white/50 p-4 rounded-lg border border-red-100">
                             <div class="flex items-start gap-3">
                                <div class="bg-red-100 p-2 rounded-full text-red-600 mt-1">
                                    <Icon icon="mdi:paw" class="h-5 w-5" />
                                </div>
                                <div>
                                    <label class="text-sm font-bold text-foreground block">¿Tenés mascotas en la propiedad?</label>
                                    <p class="text-xs text-muted-foreground mb-3">Esto es importante para recomendar sensores "Antimascota" que evitan falsas alarmas.</p>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" :value="true" v-model="formData.alarmHome.pets" class="text-red-600 focus:ring-red-500" />
                                            <span class="text-sm font-medium">Sí, tengo mascotas</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" :value="false" v-model="formData.alarmHome.pets" class="text-red-600 focus:ring-red-500" />
                                            <span class="text-sm font-medium">No</span>
                                        </label>
                                    </div>
                                </div>
                             </div>
                        </div>

                         <!-- Protection Points -->
                        <div>
                             <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-3">¿Qué áreas o accesos querés proteger?</label>
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="flex items-center space-x-3 border rounded-md p-3 bg-white/50 hover:bg-red-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="doorsWindows" v-model="formData.alarmHome.protectionPoints" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium flex items-center gap-2">
                                            <Icon icon="mdi:door-open" class="text-red-500"/> Puertas y Ventanas
                                        </span>
                                        <span class="text-xs text-muted-foreground">Detectores de apertura (Magnéticos)</span>
                                    </div>
                                </label>
                                <label class="flex items-center space-x-3 border rounded-md p-3 bg-white/50 hover:bg-red-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="interior" v-model="formData.alarmHome.protectionPoints" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium flex items-center gap-2">
                                            <Icon icon="mdi:motion-sensor" class="text-red-500"/> Interior
                                        </span>
                                        <span class="text-xs text-muted-foreground">Detectores de movimiento volumétricos</span>
                                    </div>
                                </label>
                                <label class="flex items-center space-x-3 border rounded-md p-3 bg-white/50 hover:bg-red-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="exterior" v-model="formData.alarmHome.protectionPoints" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium flex items-center gap-2">
                                            <Icon icon="mdi:tree" class="text-red-500"/> Exterior / Perímetro
                                        </span>
                                        <span class="text-xs text-muted-foreground">Barreras o sensores de exterior</span>
                                    </div>
                                </label>
                                <label class="flex items-center space-x-3 border rounded-md p-3 bg-white/50 hover:bg-red-100/50 cursor-pointer transition-colors">
                                    <input type="checkbox" value="smoke" v-model="formData.alarmHome.protectionPoints" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium flex items-center gap-2">
                                            <Icon icon="mdi:fire" class="text-red-500"/> Incendio / Humo
                                        </span>
                                    </div>
                                </label>
                             </div>
                        </div>

                         <!-- Connectivity -->
                        <div>
                             <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">Conectividad y Aviso</label>
                             <div class="flex flex-wrap gap-3">
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-red-100/50 cursor-pointer">
                                    <input type="checkbox" value="WIFI" v-model="formData.alarmHome.connectivity" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <span class="text-sm">WiFi (App Celular)</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-red-100/50 cursor-pointer">
                                    <input type="checkbox" value="GSM" v-model="formData.alarmHome.connectivity" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <span class="text-sm">Chip Celular (4G/GSM)</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-red-100/50 cursor-pointer">
                                    <input type="checkbox" value="Telephone" v-model="formData.alarmHome.connectivity" class="rounded border-red-500 text-red-600 focus:ring-red-500"/>
                                    <span class="text-sm">Línea Telefónica Fija</span>
                                </label>
                             </div>
                        </div>
                    </div>

                    <!-- 4. Alarm Auto Configuration -->
                    <div v-if="formData.productTypes.includes('alarm_auto')" class="rounded-lg border border-orange-200 bg-orange-50/50 p-5 space-y-6 animate-in fade-in slide-in-from-top-4">
                        <h4 class="text-base font-semibold flex items-center gap-2 text-orange-700">
                            <Icon icon="mdi:car-emergency" class="h-6 w-6" />
                            Configuración Alarma Automotor
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">Vehículo</label>
                                <select v-model="formData.alarmAuto.vehicleType" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                    <option value="" disabled>Seleccionar tipo</option>
                                    <option value="auto">Auto / Camioneta</option>
                                    <option value="moto">Moto</option>
                                    <option value="otros">Otros</option>
                                </select>
                            </div>
                        </div>
                    </div>

                     <!-- 4. Access Configuration -->
                    <div v-if="formData.productTypes.includes('access')" class="rounded-lg border border-blue-200 bg-blue-50/50 p-5 space-y-6 animate-in fade-in slide-in-from-top-4">
                        <h4 class="text-base font-semibold flex items-center gap-2 text-blue-700">
                            <Icon icon="mdi:shield-account" class="h-6 w-6" />
                            Configuración de Acceso
                        </h4>
                        
                         <!-- Subfamilies -->
                        <div>
                             <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-3">¿Qué estás buscando?</label>
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-blue-100/50 transition-all flex items-center gap-3', formData.access.subfamily.includes('Portero visor') ? 'border-blue-500 bg-blue-50' : 'border-transparent bg-white/50']">
                                    <input type="checkbox" value="Portero visor" v-model="formData.access.subfamily" class="sr-only"/>
                                    <Icon icon="mdi:doorbell-video" class="h-6 w-6 text-blue-600" />
                                    <div>
                                        <span class="block text-sm font-bold">Portero Visor</span>
                                        <span class="text-xs text-muted-foreground">Ver quién toca el timbre</span>
                                    </div>
                                </label>
                                <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-blue-100/50 transition-all flex items-center gap-3', formData.access.subfamily.includes('Control de acceso') ? 'border-blue-500 bg-blue-50' : 'border-transparent bg-white/50']">
                                    <input type="checkbox" value="Control de acceso" v-model="formData.access.subfamily" class="sr-only"/>
                                    <Icon icon="mdi:id-card" class="h-6 w-6 text-blue-600" />
                                    <div>
                                        <span class="block text-sm font-bold">Control de Acceso</span>
                                        <span class="text-xs text-muted-foreground">Ingreso personal/empleados</span>
                                    </div>
                                </label>
                                <label :class="['cursor-pointer border-2 rounded-lg p-3 hover:bg-blue-100/50 transition-all flex items-center gap-3', formData.access.subfamily.includes('Automatización') ? 'border-blue-500 bg-blue-50' : 'border-transparent bg-white/50']">
                                    <input type="checkbox" value="Automatización" v-model="formData.access.subfamily" class="sr-only"/>
                                    <Icon icon="mdi:home-automation" class="h-6 w-6 text-blue-600" />
                                    <div>
                                        <span class="block text-sm font-bold">Automatización</span>
                                        <span class="text-xs text-muted-foreground">Casas inteligentes</span>
                                    </div>
                                </label>
                             </div>
                        </div>

                        <!-- Entry Methods (Bio, Card, etc) -->
                        <div v-if="formData.access.subfamily.includes('Control de acceso') || formData.access.subfamily.includes('Portero visor')">
                            <label class="text-xs font-bold uppercase tracking-wide text-muted-foreground block mb-2">Métodos de Apertura Deseados</label>
                            <div class="flex flex-wrap gap-3">
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-blue-100/50 cursor-pointer">
                                    <input type="checkbox" value="Face" v-model="formData.access.entryMethods" class="rounded border-blue-500 text-blue-600 focus:ring-blue-500"/>
                                    <span class="text-sm flex items-center gap-1"><Icon icon="mdi:face-recognition" /> Rostro</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-blue-100/50 cursor-pointer">
                                    <input type="checkbox" value="Fingerprint" v-model="formData.access.entryMethods" class="rounded border-blue-500 text-blue-600 focus:ring-blue-500"/>
                                    <span class="text-sm flex items-center gap-1"><Icon icon="mdi:fingerprint" /> Huella</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-blue-100/50 cursor-pointer">
                                    <input type="checkbox" value="Card" v-model="formData.access.entryMethods" class="rounded border-blue-500 text-blue-600 focus:ring-blue-500"/>
                                    <span class="text-sm flex items-center gap-1"><Icon icon="mdi:card-account-details" /> Tarjeta/Tag</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-blue-100/50 cursor-pointer">
                                    <input type="checkbox" value="Code" v-model="formData.access.entryMethods" class="rounded border-blue-500 text-blue-600 focus:ring-blue-500"/>
                                    <span class="text-sm flex items-center gap-1"><Icon icon="mdi:dialpad" /> Clave</span>
                                </label>
                                <label class="flex items-center space-x-2 border rounded-md p-2 bg-white/50 hover:bg-blue-100/50 cursor-pointer">
                                    <input type="checkbox" value="App" v-model="formData.access.entryMethods" class="rounded border-blue-500 text-blue-600 focus:ring-blue-500"/>
                                    <span class="text-sm flex items-center gap-1"><Icon icon="mdi:cellphone" /> App Móvil</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full inline-flex h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-base font-semibold text-primary-foreground shadow-md transition-all hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50" :disabled="loading || formData.productTypes.length === 0">
                        <span v-if="loading" class="mr-2">
                            <Icon icon="mdi:loading" class="animate-spin h-5 w-5" />
                        </span>
                        {{ loading ? 'Procesando...' : 'Confirmar Selección' }}
                    </button>
                </form>
            </div>

            <!-- Result Section with Chat matches -->
            <div class="flex flex-col space-y-4 h-full">
                <!-- Initial Empty State -->
                <div v-if="!recommendation && !loading" class="flex flex-col justify-center items-center rounded-lg border bg-muted/30 p-8 text-center min-h-[300px] h-full">
                     <Icon icon="mdi:brain" class="h-16 w-16 text-muted-foreground/30 mb-4" />
                     <p class="text-muted-foreground">Los resultados aparecerán aquí.</p>
                </div>
                
                 <!-- Loading State (Optional but good UX) -->
                <div v-if="loading" class="flex flex-col justify-center items-center rounded-lg border bg-muted/30 p-8 text-center min-h-[300px] h-full animate-pulse">
                     <Icon icon="mdi:loading" class="h-10 w-10 text-primary animate-spin mb-4" />
                     <p class="text-muted-foreground">Analizando tu solicitud...</p>
                </div>

                <!-- Chat Interface -->
                <div v-if="recommendation" class="flex flex-col border rounded-lg bg-card shadow-sm overflow-hidden h-[600px] animate-in fade-in slide-in-from-bottom-4">
                    <!-- Chat Header -->
                    <div class="bg-primary/10 border-b p-3 flex items-center gap-2 shrink-0">
                         <div class="bg-primary rounded-full p-1.5 text-primary-foreground">
                            <Icon icon="mdi:robot" class="h-5 w-5" />
                         </div>
                         <div>
                             <h3 class="font-bold text-sm">Asistente IA Gustavo</h3>
                             <p class="text-xs text-muted-foreground">Experto en Seguridad</p>
                         </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-4" ref="chatContainer">
                        <div v-for="(msg, index) in chatMessages" :key="index" :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                             <div :class="['max-w-[85%] rounded-lg p-3 text-sm shadow-sm', msg.role === 'user' ? 'bg-primary text-primary-foreground rounded-tr-none' : 'bg-muted rounded-tl-none border']">
                                <p class="whitespace-pre-wrap leading-relaxed">{{ msg.content }}</p>
                             </div>
                        </div>
                        <div v-if="sendingMessage" class="flex justify-start">
                             <div class="bg-muted rounded-lg p-3 rounded-tl-none border">
                                <Icon icon="mdi:dots-horizontal" class="animate-pulse h-5 w-5 text-muted-foreground" />
                             </div>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="p-3 border-t bg-background shrink-0">
                         <form @submit.prevent="sendChatMessage" class="flex gap-2">
                            <input 
                                v-model="userMessage" 
                                type="text" 
                                placeholder="Escribí tu consulta sobre los productos..." 
                                class="flex-1 h-10 rounded-md border border-input px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                :disabled="sendingMessage"
                            />
                            <button type="submit" class="h-10 w-10 bg-primary text-primary-foreground rounded-md flex items-center justify-center hover:bg-primary/90 disabled:opacity-50 transition-colors" :disabled="!userMessage.trim() || sendingMessage">
                                <Icon icon="mdi:send" class="h-5 w-5" />
                            </button>
                         </form>
                    </div>
                </div>

                <!-- Recommended Products Grid (Below Chat) -->
                <div v-if="displayedRecommendedProducts.length > 0" class="grid gap-3">
                    <div class="flex items-center justify-between px-1">
                        <h4 class="font-semibold text-sm text-foreground/80 mt-2 flex items-center gap-2">
                            <Icon icon="mdi:cart-variant" />
                            Productos Sugeridos
                        </h4>
                        <p class="text-xs text-muted-foreground">Usá el botón + para agregar al carrito.</p>
                    </div>
                    <div 
                        v-for="prod in displayedRecommendedProducts" 
                        :key="prod.ID" 
                        class="group flex gap-3 p-3 rounded-lg border bg-card hover:border-primary/50 hover:shadow-sm transition-all items-center relative overflow-hidden"
                    >
                        <div class="h-12 w-12 bg-muted rounded-md flex items-center justify-center shrink-0 overflow-hidden group-hover:bg-primary/5 transition-colors">
                             <Icon icon="mdi:package-variant" class="h-6 w-6 text-muted-foreground/50 group-hover:text-primary transition-colors" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="font-medium text-sm truncate leading-tight w-full mb-1" :title="prod.Nombre">{{ prod.Nombre }}</h4>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span class="bg-muted px-1.5 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider">{{ prod.subfamilia || prod.familia }}</span>
                                <span class="font-mono text-primary font-bold">{{ formatPrice(prod["Precio (ARS)"]) }}</span>
                            </div>
                        </div>
                        <button @click="addToCart(prod)" class="relative h-9 w-9 rounded-full border border-input text-foreground/70 hover:bg-primary hover:text-primary-foreground hover:border-primary flex items-center justify-center transition-all shadow-sm active:scale-95" title="Agregar al presupuesto">
                            <Icon icon="mdi:plus" class="h-5 w-5" />
                            <span v-if="getProductQuantity(prod.ID) > 0" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] rounded-full bg-primary text-primary-foreground text-[10px] font-bold flex items-center justify-center shadow">
                                {{ getProductQuantity(prod.ID) }}
                            </span>
                        </button>
                    </div>
                     <button @click="router.push('/budget')" class="w-full btn-secondary text-xs h-9 mt-2">
                        <Icon icon="mdi:file-document-outline" class="mr-2 h-4 w-4"/>
                        Ver Presupuesto Completo
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
