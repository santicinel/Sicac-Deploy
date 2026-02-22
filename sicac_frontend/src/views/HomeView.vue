<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useCartStore } from '@/store/cartStore';
import { Icon } from "@iconify/vue";
import { dataService, type CatalogProduct } from '@/services/DataService';
import ConfirmDialog from '@/components/ConfirmDialog.vue'; 

const cartStore = useCartStore();


// State
const products = ref<CatalogProduct[]>([]);
const loading = ref(false);
const page = ref(1);
const total = ref(0);
const pageSize = 12;

const families = ref<Record<string, string[]>>({}); // Family -> Subfamilies
const selectedFamily = ref<string>('all');
const selectedSubfamily = ref<string>('all');
const searchQuery = ref<string>('');
const priceRange = ref<{min: number, max: number}>({min: 0, max: 1000000});
const selectedPrice = ref<{min: number, max: number}>({min: 0, max: 1000000});

// Fetch Initial Data
onMounted(async () => {
    loading.value = true;
    families.value = await dataService.getCategories();
    const stats = await dataService.getPriceStats();
    priceRange.value = stats;
    selectedPrice.value = { ...stats };
    await loadProducts(true);
    loading.value = false;
});

// Load Products
const loadProducts = async (reset = false) => {
    if (reset) {
        page.value = 1;
        products.value = [];
    }
    
    loading.value = true;
    const result = await dataService.getProducts(page.value, pageSize, {
        category: selectedFamily.value,
        subcategory: selectedSubfamily.value,
        search: searchQuery.value,
        minPrice: selectedPrice.value.min,
        maxPrice: selectedPrice.value.max
    });
    
    if (reset) {
        products.value = result.items;
    } else {
        products.value = [...products.value, ...result.items];
    }
    total.value = result.total;
    loading.value = false;
};

// Watchers for filters
watch([selectedFamily, selectedSubfamily, searchQuery], () => {
    // If family changes, reset subfamily unless it's valid for new family (unlikely)
    if (selectedFamily.value === 'all') selectedSubfamily.value = 'all';
    // Debounce could be added here for search
    loadProducts(true);
});

// Watch price separately with debounce ideally, but here directly for now
watch(() => selectedPrice.value, () => {
    loadProducts(true);
}, { deep: true });

const loadMore = () => {
    page.value++;
    loadProducts(false);
};

const hasMore = computed(() => {
    return products.value.length < total.value;
});

const availableSubfamilies = computed<string[]>(() => {
    if (selectedFamily.value === 'all') return [];
    return families.value[selectedFamily.value] || [];
});

const getImagePath = (product: CatalogProduct) => {
    const normalizeKey = (value: string) =>
        value
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9]+/g, "");

    const familyFolderMap: Record<string, string> = {
        acceso: "acceso",
        alarmas: "alarmas",
        cctv: "CCTV",
        infra: "infraestructura",
        otros: "otros",
        vehiculos: "vehiculos"
    };

    const subfamilyImageMap: Record<string, Record<string, string>> = {
        acceso: {
            auto: "automatizacion.png",
            automatizacion: "automatizacion.png",
            control: "control de acceso.png",
            controldeacceso: "control de acceso.png",
            portero: "portero visor.png",
            porterovisor: "portero visor.png"
        },
        alarmas: {
            centrales: "alarma central.png",
            controles: "controles.png",
            incendio: "alarma incendios.png",
            kits: "kit.png",
            modulos: "modulos-comunicadores.png",
            sensores: "sensores.png",
            sirenas: "sirenas-antisabotaje.png",
            teclados: "teclados.png"
        },
        cctv: {
            accvideo: "accesorios de video.png",
            almacen: "almacenamiento.png",
            camhd: "camara analógica-hd.png",
            camip: "camara IP.png",
            grabacion: "grabadores DVR-NVR.png"
        },
        infra: {
            acccctv: "accesorios cctv.png",
            cablesred: "cables-conectores-red.png",
            energia: "energía.png"
        },
        otros: {
            general: "otros.png"
        }
    };

    const famKey = normalizeKey(product.familia || "");
    const folder = familyFolderMap[famKey] || famKey;
    const subKey = normalizeKey(product.subfamilia || "");

    if (famKey === "vehiculos") {
        const name = normalizeKey(product.Nombre || "");
        if (name.includes("control") || name.includes("transmisor") || name.includes("remoto")) {
            return `/img/${folder}/transmisores.png`;
        }
        return `/img/${folder}/alarma auto.png`;
    }

    if (famKey === "cctv") {
        const name = normalizeKey(product.Nombre || "");
        if (name.includes("monitor") || name.includes("pantalla")) {
            return `/img/${folder}/monitor.png`;
        }
    }

    const mapped = subfamilyImageMap[famKey]?.[subKey];
    if (mapped) return `/img/${folder}/${mapped}`;

    return `/img/otros/otros.png`;
};



const failedImages = ref<Set<string>>(new Set());
const onImageError = (id: string) => {
    failedImages.value.add(id);
};

// Mapping for Cart
const addToCart = (product: CatalogProduct) => {
    // Map Strategy:
    // Category: 'Alarmas' -> 'alarm', 'Acceso' -> 'camera' (defaulting logic), etc.
    // This is imperfect but satisfies the store type.
    let category: 'camera' | 'alarm' | 'sensor' = 'sensor'; // Default
    const fam = product.familia.toLowerCase();
    const sub = product.subfamilia?.toLowerCase() || '';

    if (fam.includes('alarm') || sub.includes('sirena') || sub.includes('central')) category = 'alarm';
    else if (fam.includes('acceso') || sub.includes('camera') || sub.includes('portero')) category = 'camera';
    else if (sub.includes('sensor') || sub.includes('detector')) category = 'sensor';

    cartStore.addItem({
        id: product.ID,
        name: product.Nombre,
        description: product.Texto_RAG || product["Modelo/SKU"],
        price: product["Precio (ARS)"],
        category: category
    });
};

// ... existing dialog code ...
const showDialog = ref(false);
const dialogConfig = ref({ title: '', description: '', confirmText: 'Confirmar' });
let pendingAction: (() => void) | null = null;

const addWithConfirm = (product: CatalogProduct) => {
    dialogConfig.value = {
        title: 'Agregar al carrito',
        description: `¿Estás seguro de que querés agregar "${product.Nombre}" al carrito?`,
        confirmText: 'Agregar'
    };
    pendingAction = () => addToCart(product);
    showDialog.value = true;
};

const handleConfirm = () => {
    if (pendingAction) pendingAction();
    showDialog.value = false;
    pendingAction = null;
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(price);
};

const getProductQuantity = (productId: string) => {
    return cartStore.items.filter(item => item.id === productId).length;
};
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Catálogo de Productos</h1>
        <p class="text-muted-foreground">Explorá nuestro catálogo completo con precios actualizados.</p>
      </div>
      
      <router-link to="/budget" class="group relative inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90">
        <Icon icon="mdi:cart-outline" class="mr-2 h-5 w-5" />
        Presupuesto ({{ cartStore.items.length }})
      </router-link>
    </div>

    <!-- Filters & Search -->
    <div class="space-y-4">
        <!-- Main Families -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 no-scrollbar">
            <button 
                @click="selectedFamily = 'all'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors whitespace-nowrap', selectedFamily === 'all' ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/80']"
            >
                Todos
            </button>
            <button 
                v-for="(_, fam) in families" 
                :key="fam"
                @click="selectedFamily = fam; selectedSubfamily = 'all'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors whitespace-nowrap border', selectedFamily === fam ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted']"
            >
                {{ fam }}
            </button>
        </div>

        <!-- Subfamilies & Search row -->
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <div class="flex flex-wrap gap-2 items-center flex-1">
                 <div v-if="selectedFamily !== 'all' && availableSubfamilies.length > 0" class="flex items-center gap-2 overflow-x-auto max-w-full">
                    <span class="text-xs font-semibold text-muted-foreground uppercase mr-1">Filtros:</span>
                    <button 
                        @click="selectedSubfamily = 'all'"
                         :class="['px-3 py-1 rounded-md text-xs font-medium transition-colors border', selectedSubfamily === 'all' ? 'bg-secondary text-secondary-foreground border-secondary' : 'bg-background hover:bg-muted']"
                    >
                        Todo {{ selectedFamily }}
                    </button>
                    <button 
                        v-for="sub in availableSubfamilies" 
                        :key="sub"
                        @click="selectedSubfamily = sub"
                        :class="['px-3 py-1 rounded-md text-xs font-medium transition-colors border whitespace-nowrap', selectedSubfamily === sub ? 'bg-secondary text-secondary-foreground border-secondary' : 'bg-background hover:bg-muted']"
                    >
                        {{ sub }}
                    </button>
                 </div>
            </div>

            <!-- Price Filter & Search -->
             <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
                <div class="flex items-center gap-2 border rounded-full px-3 py-1.5 bg-background shadow-sm hover:shadow transition-shadow">
                    <span class="text-xs font-semibold text-muted-foreground mr-1">Precio</span>
                    <div class="flex items-center relative">
                        <span class="absolute left-2 text-xs text-muted-foreground">$</span>
                        <input 
                            type="number" 
                            v-model.number="selectedPrice.min" 
                            class="w-20 pl-4 pr-1 py-1 text-xs bg-transparent focus:outline-none text-right font-mono"
                            :min="priceRange.min" 
                            :max="selectedPrice.max"
                            placeholder="Min"
                        />
                    </div>
                    <span class="text-xs text-muted-foreground">-</span>
                    <div class="flex items-center relative">
                        <span class="absolute left-2 text-xs text-muted-foreground">$</span>
                        <input 
                            type="number" 
                            v-model.number="selectedPrice.max" 
                            class="w-20 pl-4 pr-1 py-1 text-xs bg-transparent focus:outline-none text-right font-mono"
                            :min="selectedPrice.min" 
                            :max="priceRange.max"
                            placeholder="Max"
                        />
                    </div>
                </div>

                <div class="relative w-full md:w-60 shrink-0">
                    <Icon icon="mdi:magnify" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <input 
                        v-model.lazy="searchQuery"
                        type="text" 
                        placeholder="Buscar por nombre, SKU..."
                        class="w-full rounded-full border border-input bg-background pl-9 pr-3 py-1.5 text-sm shadow-sm transition-all focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="min-h-[300px]">
        <div v-if="loading && products.length === 0" class="flex items-center justify-center py-20">
             <Icon icon="mdi:loading" class="h-8 w-8 animate-spin text-primary" />
        </div>

        <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center py-16 text-center border-2 border-dashed rounded-lg bg-muted/5">
            <Icon icon="mdi:package-variant" class="h-12 w-12 text-muted-foreground/50 mb-3" />
            <h3 class="text-lg font-medium">No se encontraron productos</h3>
            <p class="text-muted-foreground">Intentá ajustar los filtros o la búsqueda.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 animate-in fade-in duration-500">
            <div 
                v-for="product in products" 
                :key="product.ID" 
                class="group relative flex flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm transition-all hover:shadow-lg hover:-translate-y-1"
            >
                <!-- Image / Icon Placeholder -->
                <div class="aspect-video w-full bg-gradient-to-br from-muted/50 to-muted flex items-center justify-center border-b relative overflow-hidden bg-white">
                    <img 
                        v-if="!failedImages.has(product.ID)"
                        :src="getImagePath(product)" 
                        @error="onImageError(product.ID)"
                        class="w-full h-full object-contain p-4 mix-blend-multiply transition-transform group-hover:scale-105"
                        alt="Product Image"
                    />
                    
                    <div v-else class="flex items-center justify-center w-full h-full">
                        <Icon 
                            v-if="product.familia === 'Acceso'" 
                            icon="mdi:shield-check" 
                            class="h-12 w-12 text-muted-foreground/40 group-hover:text-primary/60 transition-colors" 
                        />
                         <Icon 
                            v-else-if="product.familia === 'Alarmas'" 
                            icon="mdi:alarm-light" 
                            class="h-12 w-12 text-muted-foreground/40 group-hover:text-red-500/60 transition-colors" 
                        />
                        <Icon 
                            v-else
                            icon="mdi:package-variant-closed" 
                            class="h-12 w-12 text-muted-foreground/40" 
                        />
                    </div>
                    
                    <div class="absolute top-2 right-2 bg-background/80 backdrop-blur-sm px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider text-muted-foreground shadow-sm border">
                        {{ product.familia }}
                    </div>
                </div>
                
                <div class="flex flex-1 flex-col p-5 gap-3">
                    <div>
                        <div class="flex justify-between items-start gap-2">
                             <h3 class="font-semibold leading-tight text-base line-clamp-2" :title="product.Nombre">
                                {{ product.Nombre }}
                            </h3>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1 font-mono">{{ product["Modelo/SKU"] }}</p>
                    </div>
                    
                    <p class="text-sm text-muted-foreground line-clamp-3 leading-relaxed">
                        {{ product.Texto_RAG }}
                    </p>
                    
                    <div class="mt-auto pt-4 flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-bold text-primary">{{ formatPrice(product["Precio (ARS)"]) }}</span>
                        </div>
                        
                            <button 
                                @click.stop="addWithConfirm(product)"
                                class="w-full inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <Icon icon="mdi:cart-plus" class="mr-2 h-4 w-4" />
                                <span v-if="getProductQuantity(product.ID) > 0">
                                    Agregar (+{{ getProductQuantity(product.ID) }})
                                </span>
                                <span v-else>
                                    Agregar al Presupuesto
                                </span>
                            </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="products.length > 0 && hasMore" class="flex justify-center pt-8 pb-4">
            <button 
                @click="loadMore" 
                :disabled="loading"
                class="inline-flex items-center justify-center rounded-full border border-input bg-background px-8 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
            >
                <span v-if="loading" class="mr-2"><Icon icon="mdi:loading" class="animate-spin" /></span>
                Cargar más productos ({{ total - products.length }} restantes)
            </button>
        </div>
        <div v-else-if="products.length > 0" class="text-center py-8 text-sm text-muted-foreground">
            Has visto todos los productos.
        </div>
    </div>

    <!-- Confirm Dialog -->
    <ConfirmDialog 
      :is-open="showDialog"
      :title="dialogConfig.title"
      :description="dialogConfig.description"
      :confirmText="dialogConfig.confirmText"
      @confirm="handleConfirm"
      @cancel="showDialog = false"
    />
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

