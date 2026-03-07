<script setup lang="ts">
import { computed, ref, onMounted, watch, type Ref } from 'vue';
import { Icon } from "@iconify/vue";
import { dataService } from '@/services/DataService';
import { useAdminTechniciansStore } from '@/store/adminTechniciansStore';
import {
  DateFormatter,
  getLocalTimeZone,
  today,
} from '@internationalized/date'
import type { DateRange } from 'reka-ui'
import { RangeCalendar } from '@/components/ui/range-calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { config_app } from '@/config/app'

const df = new DateFormatter('es-AR', {
  dateStyle: 'medium',
})

const dateRange = ref({
  start: today(getLocalTimeZone()),
  end: today(getLocalTimeZone()).add({ days: 7 }),
}) as Ref<DateRange>

const form = ref({
    type: 'technical',
    family: '',
    subject: '',
    description: '',
    visitDate: '', // Kept for compatibility, but populated from dateRange
    visitTime: ''
});
const submitted = ref(false);
const showHistory = ref(false);
const families = ref<string[]>([]);
const techniciansStore = useAdminTechniciansStore();

type HistoryStatus = "open" | "in_progress" | "resolved";

interface HistoryItem {
    id: string;
    title: string;
    subject: string;
    status: HistoryStatus;
    description: string;
    response?: string;
    technicianId?: string;
    createdAt: string;
    completedAt?: string;
    completionNotes?: string;
    clientConfirmed?: boolean;
    technicianRating?: number;
    productRatings?: Array<{ name: string; rating?: number }>;
}

const historyItems = ref<HistoryItem[]>([
    {
        id: "8490",
        title: "Solicitud técnica",
        subject: "Cámara no conecta a WiFi",
        status: "resolved",
        description:
            "El equipo no conecta a la red. Se revisó el router y la alimentación.",
        response: "Se reinició el router y se reconfiguró el módulo WiFi.",
        technicianId: "tech-1",
        createdAt: "2026-01-10",
        completedAt: "2026-01-11",
        completionNotes: "Se restableci? la red y se verific? estabilidad.",
        clientConfirmed: false,
        technicianRating: undefined,
        productRatings: [
            { name: "Camara IP X1", rating: undefined },
            { name: "Router WiFi Z2", rating: undefined },
        ],
    },
    {
        id: "8485",
        title: "Consulta de presupuesto",
        subject: "Instalación de alarma vecinal",
        status: "in_progress",
        description: "Solicita cotización y visita técnica de relevamiento.",
        response: "",
        technicianId: "tech-2",
        createdAt: "2026-01-12",
        completedAt: "",
        completionNotes: "",
        clientConfirmed: false,
        technicianRating: undefined,
        productRatings: [
            { name: "Panel alarma V3", rating: undefined },
        ],
    },
]);

const selectedHistory = ref<HistoryItem | null>(null);
const showTechnicianDetails = ref(false);
const aiBaseUrl = config_app.ai_url;

const historyStatusLabels: Record<HistoryStatus, string> = {
    open: "Abierto",
    in_progress: "En progreso",
    resolved: "Resuelto",
};

const historyStatusClasses: Record<HistoryStatus, string> = {
    open: "bg-amber-100 text-amber-700",
    in_progress: "bg-blue-100 text-blue-700",
    resolved: "bg-emerald-100 text-emerald-700",
};

const selectedTechnician = computed(() => {
    const id = selectedHistory.value?.technicianId;
    if (!id) return null;
    return techniciansStore.items.find((item) => item.id === id) ?? null;
});

// Chat Logic
const showChat = ref(false);
const chatInput = ref('');
const isTyping = ref(false);
const chatMessages = ref<{ role: string, content: string }[]>([]);

const fetchAIResponse = async (messagesContext: { role: string, content: string }[]) => {
    isTyping.value = true;
    try {
        const response = await fetch(`${aiBaseUrl}/chat`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ messages: messagesContext })
        });

        if (!response.ok) {
            throw new Error('API Error');
        }

        const data = await response.json();
        chatMessages.value.push({ role: 'assistant', content: data.response });
    } catch (error) {
        console.error(error);
        chatMessages.value.push({ role: 'assistant', content: '⚠️ Lo siento, no puedo conectarme con el servidor de IA. Asegúrate de ejecutar "python IA/qa.py".' });
    } finally {
        isTyping.value = false;
    }
};

const sendMessage = async () => {
    if (!chatInput.value.trim()) return;

    // Add user message
    const text = chatInput.value;
    chatMessages.value.push({ role: 'user', content: text });
    chatInput.value = '';

    await fetchAIResponse(chatMessages.value.map(m => ({ role: m.role, content: m.content })));
};

watch(showChat, (val) => {
    if (val && chatMessages.value.length === 0) {
        chatMessages.value.push({
            role: 'assistant',
            content: 'Hola, soy Eduardo, asistente virtual de CEA. Te ayudo a generar reclamos o solicitudes. Que problema tenes?'
        });
    }
});

onMounted(async () => {
    const cats = await dataService.getCategories();
    families.value = Object.keys(cats);
});

const submitTicket = () => {
    // Mock submission
    setTimeout(() => {
        submitted.value = true;
    }, 1000);
};

const openHistoryDetail = (item: HistoryItem) => {
    selectedHistory.value = item;
    showTechnicianDetails.value = false;
};

const closeHistoryDetail = () => {
    selectedHistory.value = null;
    showTechnicianDetails.value = false;
};

const setTechnicianRating = (rating: number) => {
    if (!selectedHistory.value) return;
    selectedHistory.value.technicianRating = rating;
};

const setProductRating = (productIndex: number, rating: number) => {
    if (!selectedHistory.value?.productRatings) return;
    const product = selectedHistory.value?.productRatings?.[productIndex];
    if (product) {
        product.rating = rating;
    }
};

const confirmCompletion = () => {
    if (!selectedHistory.value) return;
    selectedHistory.value.clientConfirmed = true;
};
</script>

<template>
    <div class="max-w-3xl mx-auto p-6 space-y-8 pb-24">
        <div class="text-center md:text-left">
            <h1 class="text-3xl font-bold tracking-tight">Soporte y reclamos</h1>
            <p class="text-muted-foreground">¿Necesitás ayuda? Estamos para asistirte con problemas técnicos o reclamos.</p>
        </div>

        <!-- History Section -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm mb-6">
            <div class="p-6 cursor-pointer hover:bg-muted/20 transition-colors" @click="showHistory = !showHistory">
                <h3 class="text-lg font-semibold flex items-center justify-between">
                    Historial de Solicitudes
                    <Icon :icon="showHistory ? 'mdi:chevron-up' : 'mdi:chevron-down'" class="h-5 w-5" />
                </h3>
            </div>
            <div v-if="showHistory" class="px-6 pb-6 border-t pt-4">
                 <div class="space-y-4">
                    <div
                        v-for="item in historyItems"
                        :key="item.id"
                        class="flex items-center justify-between gap-4 p-3 bg-muted/30 rounded-md"
                    >
                        <div>
                            <p class="font-medium text-sm">{{ item.title }} #{{ item.id }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.subject }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="px-2 py-1 text-xs rounded-full font-medium"
                                :class="historyStatusClasses[item.status]"
                            >
                                {{ historyStatusLabels[item.status] }}
                            </span>
                            <button
                                class="rounded-md border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary hover:bg-primary/20"
                                @click="openHistoryDetail(item)"
                            >
                                Ver detalle
                            </button>
                        </div>
                    </div>
                 </div>
            </div>
        </div>

        <div v-if="submitted" class="rounded-lg border bg-emerald-50 p-8 text-center text-emerald-900 border-emerald-200">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                <Icon icon="mdi:check-bold" class="h-8 w-8 text-emerald-600" />
            </div>
            <h3 class="text-xl font-semibold mb-2">¡Ticket Recibido!</h3>
            <p>Tu solicitud (Ticket #8492) ha sido recibida. Un representante te contactará a la brevedad.</p>
            <button @click="submitted = false; form = {type:'technical', family: '', subject:'', description:'', visitDate: '', visitTime: ''}" class="mt-6 text-sm font-medium underline underline-offset-4 hover:text-emerald-700">
                Enviar otra solicitud
            </button>
        </div>

        <div v-else class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="p-6">
                <form @submit.prevent="submitTicket" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-sm font-medium leading-none">Tipo de Solicitud</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="cursor-pointer border rounded-md p-4 flex flex-col items-center gap-2 transition-all hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" v-model="form.type" value="technical" class="sr-only" />
                                <Icon icon="mdi:tools" class="h-6 w-6" />
                                <span class="font-medium">Solicitud Técnica</span>
                            </label>
                            <label class="cursor-pointer border rounded-md p-4 flex flex-col items-center gap-2 transition-all hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" v-model="form.type" value="claim" class="sr-only" />
                                <Icon icon="mdi:file-document-alert-outline" class="h-6 w-6" />
                                <span class="font-medium">Iniciar Reclamo</span>
                            </label>
                        </div>
                    </div>

                    <!-- Related System -->
                    <div class="space-y-2">
                        <label for="family" class="text-sm font-medium leading-none">Sistema relacionado</label>
                        <select id="family" v-model="form.family" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            <option value="" disabled selected>Seleccionar categoría (Opcional)</option>
                            <option v-for="fam in families" :key="fam" :value="fam">{{ fam }}</option>
                        </select>
                    </div>

                    <!-- Scheduling Section (Conditional) -->
                    <div v-if="form.type === 'technical'" class="space-y-2 pt-2 animate-in fade-in slide-in-from-top-1">
                        <label class="text-sm font-medium leading-none text-primary flex items-center gap-2">
                            <Icon icon="mdi:calendar-clock" />
                            Preferencia de Visita Técnica
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground">Fechas disponibles</label>
                                <Popover>
                                    <PopoverTrigger as-child>
                                        <Button
                                            variant="outline"
                                            :class="cn(
                                                'w-full justify-start text-left font-normal',
                                                !dateRange && 'text-muted-foreground',
                                            )"
                                        >
                                            <Icon icon="radix-icons:calendar" class="mr-2 h-4 w-4" />
                                            <template v-if="dateRange?.start">
                                                <template v-if="dateRange.end">
                                                    {{ df.format(dateRange.start.toDate(getLocalTimeZone())) }} - {{ df.format(dateRange.end.toDate(getLocalTimeZone())) }}
                                                </template>
                                                <template v-else>
                                                    {{ df.format(dateRange.start.toDate(getLocalTimeZone())) }}
                                                </template>
                                            </template>
                                            <template v-else>
                                                Seleccionar fechas
                                            </template>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-auto p-0">
                                        <RangeCalendar v-model="dateRange" initial-focus :min-value="today(getLocalTimeZone())" @update:model-value="(v) => form.visitDate = v ? v.toString() : ''" />
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <div class="space-y-1">
                                <label for="visitTime" class="text-xs text-muted-foreground">Rango horario</label>
                                <select id="visitTime" v-model="form.visitTime" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="" disabled selected>Seleccionar rango</option>
                                    <option value="morning">Mañana (09:00 - 13:00)</option>
                                    <option value="afternoon">Tarde (14:00 - 18:00)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="subject" class="text-sm font-medium leading-none">Asunto</label>
                        <input id="subject" v-model="form.subject" placeholder="Breve resumen del problema" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" required />
                    </div>

                    <div class="space-y-2">
                        <label for="description" class="text-sm font-medium leading-none">Descripción</label>
                        <textarea id="description" v-model="form.description" rows="5" placeholder="Por favor describí el problema en detalle..." class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" required></textarea>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground ring-offset-background transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50">
                            Enviar solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Chatbot UI -->
        <!-- Chat Window -->
        <div v-if="showChat" class="fixed bottom-24 right-6 w-80 bg-background border rounded-lg shadow-xl overflow-hidden z-50 animate-in fade-in slide-in-from-bottom-5 flex flex-col">
            <div class="bg-primary p-4 text-primary-foreground flex justify-between items-center shadow-sm">
                <span class="font-medium flex items-center gap-2">
                    <Icon icon="mdi:robot-happy" class="h-5 w-5" /> 
                    Asistente Virtual
                </span>
                <button @click="showChat = false" class="hover:bg-primary-foreground/20 rounded-full p-1 transition-colors">
                    <Icon icon="mdi:close" class="h-5 w-5" />
                </button>
            </div>
            <div class="h-80 p-4 bg-muted/30 overflow-y-auto flex flex-col gap-3">
                <div v-for="(msg, idx) in chatMessages" :key="idx" 
                    :class="['p-3 rounded-lg text-sm max-w-[85%]', 
                        msg.role === 'assistant' 
                        ? 'bg-primary/10 rounded-tl-none self-start text-foreground' 
                        : 'bg-primary text-primary-foreground rounded-tr-none self-end']">
                    {{ msg.content }}
                </div>
                <div v-if="isTyping" class="self-start bg-muted p-2 rounded-lg text-xs text-muted-foreground animate-pulse">
                    Escribiendo...
                </div>
            </div>
            <div class="p-3 border-t bg-background">
                <form @submit.prevent="sendMessage" class="flex gap-2">
                    <input v-model="chatInput" placeholder="Escribí tu consulta..." class="flex-1 text-sm bg-transparent border-none focus:outline-none px-2" :disabled="isTyping" />
                    <button type="submit" class="text-primary p-2 hover:bg-muted rounded-full transition-colors" :disabled="isTyping || !chatInput.trim()">
                        <Icon icon="mdi:send" class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>

        <!-- Chat Bubble -->
        <div class="fixed bottom-6 right-6 z-50 flex items-center gap-4">
            <div class="bg-popover text-popover-foreground px-4 py-2 rounded-lg shadow-md text-sm font-medium animate-bounce hidden md:block" v-if="!showChat">
                ¿Tenés alguna duda?
            </div>
            <button @click="showChat = !showChat" class="h-14 w-14 rounded-full bg-primary text-primary-foreground shadow-lg flex items-center justify-center hover:scale-105 transition-transform">
                <Icon :icon="showChat ? 'mdi:close' : 'mdi:message-text-outline'" class="h-7 w-7" />
            </button>
        </div>
        <div
            v-if="selectedHistory"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div class="w-full max-w-lg rounded-lg border bg-card text-card-foreground shadow-lg">
                <div class="flex items-start justify-between border-b px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold">Detalle de solicitud</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ selectedHistory.title }} #{{ selectedHistory.id }}
                        </p>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground" @click="closeHistoryDetail">
                        <Icon icon="mdi:close" class="h-5 w-5" />
                    </button>
                </div>
                <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Estado</span>
                        <span
                            class="px-2 py-1 text-xs rounded-full font-medium"
                            :class="historyStatusClasses[selectedHistory.status]"
                        >
                            {{ historyStatusLabels[selectedHistory.status] }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Asunto</p>
                        <p>{{ selectedHistory.subject }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Detalle</p>
                        <p>{{ selectedHistory.description }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Respuesta</p>
                        <p>{{ selectedHistory.response || "Sin respuesta todavía." }}</p>
                    </div>
                    <div v-if="selectedHistory.completedAt">
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Finalizado</p>
                        <p>{{ selectedHistory.completedAt }}</p>
                    </div>
                    <div v-if="selectedHistory.completionNotes">
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Resumen técnico</p>
                        <p>{{ selectedHistory.completionNotes }}</p>
                    </div>
                    <div v-if="selectedHistory.technicianId" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase text-muted-foreground">Técnico asignado</p>
                            <button
                                class="text-xs font-semibold text-primary hover:underline"
                                @click="showTechnicianDetails = !showTechnicianDetails"
                            >
                                {{ showTechnicianDetails ? "Ocultar técnico" : "Ver técnico" }}
                            </button>
                        </div>
                        <p>{{ selectedTechnician ? selectedTechnician.firstName + " " + selectedTechnician.lastName : "Técnico no disponible" }}</p>
                        <p class="text-xs text-muted-foreground">
                            Podés comunicarte con el técnico para coordinar la visita si es necesaria.
                        </p>
                        <div
                            v-if="showTechnicianDetails && selectedTechnician"
                            class="rounded-md border bg-muted/30 p-3 text-sm"
                        >
                            <p><span class="font-semibold">Email:</span> {{ selectedTechnician.email }}</p>
                            <p><span class="font-semibold">Teléfono:</span> {{ selectedTechnician.phone }}</p>
                            <p><span class="font-semibold">Ciudad:</span> {{ selectedTechnician.city }}</p>
                            <p><span class="font-semibold">Dirección:</span> {{ selectedTechnician.address }}</p>
                        </div>
                    </div>
                    <div
                        v-if="selectedHistory.status === 'resolved'"
                        class="space-y-4 rounded-md border bg-muted/30 p-4"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold uppercase text-muted-foreground">Confirmar finalización</p>
                                <p class="text-xs text-muted-foreground">
                                    Confirmá que el técnico finalizó la tarea.
                                </p>
                            </div>
                            <button
                                class="rounded-md bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:bg-muted"
                                :disabled="selectedHistory.clientConfirmed"
                                @click="confirmCompletion"
                            >
                                {{ selectedHistory.clientConfirmed ? "Confirmado" : "Confirmar" }}
                            </button>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-muted-foreground">Puntaje del técnico</p>
                            <div class="mt-2 flex items-center gap-1">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    class="p-1"
                                    :class="star <= (selectedHistory.technicianRating ?? 0) ? 'text-amber-500' : 'text-muted-foreground'"
                                    @click="setTechnicianRating(star)"
                                >
                                    <Icon icon="mdi:star" class="h-5 w-5" />
                                </button>
                                <span class="ml-2 text-xs text-muted-foreground">
                                    {{ selectedHistory.technicianRating ?? "Sin puntuar" }}
                                </span>
                            </div>
                        </div>
                        <div v-if="selectedHistory.productRatings?.length">
                            <p class="text-xs font-semibold uppercase text-muted-foreground">Puntaje de productos</p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="(product, idx) in selectedHistory.productRatings"
                                    :key="product.name"
                                    class="flex items-center justify-between gap-3 rounded-md border bg-background px-3 py-2 text-xs"
                                >
                                    <span class="font-medium text-foreground">{{ product.name }}</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            v-for="star in 5"
                                            :key="star"
                                            class="p-0.5"
                                            :class="star <= (product.rating ?? 0) ? 'text-amber-500' : 'text-muted-foreground'"
                                            @click="setProductRating(idx, star)"
                                        >
                                            <Icon icon="mdi:star" class="h-4 w-4" />
                                        </button>
                                        <span class="ml-2 text-[10px] text-muted-foreground">
                                            {{ product.rating ?? "Sin puntuar" }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end border-t px-6 py-3">
                    <button class="rounded-md border px-4 py-2 text-sm" @click="closeHistoryDetail">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
