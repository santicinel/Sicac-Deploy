<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { toast } from "vue-sonner";
import StarRatingInput from "@/components/ui/StarRatingInput.vue";
import { config_app } from "@/config/app";
import supportRequestsService, {
  type ApiCategory,
  type ApiServiceRequest,
} from "@/services/supportRequestsService";

const categories = ref<ApiCategory[]>([]);
const loadingHistory = ref(false);
const historyItems = ref<ApiServiceRequest[]>([]);
const selectedRequest = ref<ApiServiceRequest | null>(null);
const submitting = ref(false);
const ratingBusy = ref(false);
const historyCollapsed = ref(false);
const historySearch = ref("");
type HistoryDatePreset = "all" | "today" | "last_7_days" | "last_30_days" | "last_3_months" | "last_6_months";
const historyDatePreset = ref<HistoryDatePreset>("all");
const aiBaseUrl = config_app.ai_url;
const showChat = ref(false);
const chatInput = ref("");
const isTyping = ref(false);
const chatMessages = ref<{ role: "assistant" | "user"; content: string }[]>([]);

const form = reactive({
  type: "technical" as "technical" | "claim",
  categoryId: "",
  subject: "",
  description: "",
  wantedDateStart: "",
  wantedDateEnd: "",
  timeShift: "",
});

const technicianRating = ref<number | null>(null);
const technicianRatingNote = ref("");

const statusLabel: Record<string, string> = {
  pending: "Sin asignación",
  assigned: "Asignado",
  completed: "Completada",
  cancelled: "Cancelada",
};

const statusClass: Record<string, string> = {
  pending: "bg-amber-100 text-amber-700",
  assigned: "bg-blue-100 text-blue-700",
  completed: "bg-emerald-100 text-emerald-700",
  cancelled: "bg-zinc-200 text-zinc-700",
};

const getDisplayStatus = (item: Pick<ApiServiceRequest, "status" | "technician_id">): string => {
  if (item.status === "completed" || item.status === "cancelled") {
    return item.status;
  }
  return item.technician_id ? "assigned" : "pending";
};

const getDateRangeFromPreset = (preset: HistoryDatePreset): { from: Date; to: Date } | null => {
  if (preset === "all") return null;

  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
  const endOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);

  if (preset === "today") {
    return { from: startOfToday, to: endOfToday };
  }

  if (preset === "last_7_days") {
    const from = new Date(startOfToday);
    from.setDate(from.getDate() - 6);
    return { from, to: endOfToday };
  }

  if (preset === "last_30_days") {
    const from = new Date(startOfToday);
    from.setDate(from.getDate() - 29);
    return { from, to: endOfToday };
  }

  if (preset === "last_3_months") {
    const from = new Date(startOfToday);
    from.setMonth(from.getMonth() - 3);
    return { from, to: endOfToday };
  }

  const from = new Date(startOfToday);
  from.setMonth(from.getMonth() - 6);
  return { from, to: endOfToday };
};

const filteredHistoryItems = computed(() => {
  const query = historySearch.value.trim().toLowerCase();
  const dateRange = getDateRangeFromPreset(historyDatePreset.value);

  return historyItems.value.filter((item) => {
    if (query) {
      const values = [
        item.subject,
        item.description,
        String(item.id),
        item.type === "claim" ? "reclamo" : "solicitud tecnica",
        statusLabel[getDisplayStatus(item)] || getDisplayStatus(item),
      ];
      const matchesText = values.some((value) => value.toLowerCase().includes(query));
      if (!matchesText) return false;
    }

    if (!dateRange) return true;
    const parsed = new Date(item.created_at);
    if (Number.isNaN(parsed.getTime())) return false;
    return parsed >= dateRange.from && parsed <= dateRange.to;
  });
});

const formatDate = (value?: string | null) => {
  if (!value) return "Sin fecha";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(date);
};

const formatCurrency = (value?: number | string | null) => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return "Sin monto";
  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric);
};

const shiftLabel = (value?: string | null) => {
  if (!value) return "Sin turno";
  if (value === "morning") return "Mañana";
  if (value === "afternoon") return "Tarde";
  return value;
};

const normalizeScore = (value: number | null): number | null => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 1) return null;
  return Math.min(5, Math.max(1, Math.trunc(numeric)));
};

const extractApiMessage = (error: unknown): string => {
  const maybeError = error as { response?: { data?: { message?: unknown } } };
  const message = maybeError?.response?.data?.message;
  return typeof message === "string" ? message : "";
};

const canRateSelected = computed(
  () =>
    selectedRequest.value?.status === "completed" &&
    Boolean(selectedRequest.value?.technician_id)
);

const hasSavedTechnicianRating = computed(
  () => normalizeScore(selectedRequest.value?.rating?.score ?? null) !== null
);

const selectedTechnicianName = computed(() => {
  const tech = selectedRequest.value?.technician;
  if (!tech) return "Sin tecnico asignado";
  const fullName = `${tech.first_name ?? ""} ${tech.last_name ?? ""}`.trim();
  return (tech.user?.name ?? fullName) || "Tecnico";
});

const openDetail = (item: ApiServiceRequest) => {
  selectedRequest.value = item;
  technicianRating.value = normalizeScore(item.rating?.score ?? null);
  technicianRatingNote.value = item.rating?.description ?? "";
};

const closeDetail = () => {
  selectedRequest.value = null;
  technicianRating.value = null;
  technicianRatingNote.value = "";
};

const loadInitialData = async () => {
  loadingHistory.value = true;
  try {
    const [categoryList, requests] = await Promise.all([
      supportRequestsService.getCategories(),
      supportRequestsService.getUserRequests(),
    ]);
    categories.value = categoryList;
    historyItems.value = requests;
  } catch (error) {
    console.error(error);
    toast.error("No se pudo cargar soporte y reclamos.");
  } finally {
    loadingHistory.value = false;
  }
};

const submitTicket = async () => {
  submitting.value = true;
  try {
    if (!form.wantedDateStart || !form.wantedDateEnd || !form.timeShift) {
      toast.error("Completa fechas y turno para enviar la solicitud.");
      return;
    }

    if (form.type === "technical") {
      await supportRequestsService.createTechnicalRequest({
        category_id: form.categoryId ? Number(form.categoryId) : null,
        subject: form.subject,
        description: form.description,
        wanted_date_start: form.wantedDateStart,
        wanted_date_end: form.wantedDateEnd,
        time_shift: form.timeShift,
      });
    } else {
      if (!form.categoryId) {
        toast.error("Selecciona una categoria para el reclamo.");
        return;
      }
      await supportRequestsService.createClaim({
        category_id: Number(form.categoryId),
        subject: form.subject,
        description: form.description,
        wanted_date_start: form.wantedDateStart,
        wanted_date_end: form.wantedDateEnd,
        time_shift: form.timeShift,
      });
    }
    toast.success("Solicitud enviada correctamente.");
    form.subject = "";
    form.description = "";
    form.wantedDateStart = "";
    form.wantedDateEnd = "";
    form.timeShift = "";
    historyItems.value = await supportRequestsService.getUserRequests();
  } catch (error) {
    console.error(error);
    toast.error("No se pudo enviar la solicitud.");
  } finally {
    submitting.value = false;
  }
};

const submitTechnicianRating = async () => {
  const score = normalizeScore(technicianRating.value);
  if (!selectedRequest.value?.technician_id) {
    toast.error("No hay tecnico asignado para puntuar.");
    return;
  }
  if (!score) {
    toast.error("Selecciona un puntaje de 1 a 5 para el tecnico.");
    return;
  }

  ratingBusy.value = true;
  try {
    const response = await supportRequestsService.submitTechnicianRating(selectedRequest.value.technician_id, {
      technician_request_id: selectedRequest.value.id,
      score,
      description: technicianRatingNote.value || undefined,
    });

    const currentRequestId = selectedRequest.value.id;
    const apiRating = (
      response?.data as {
        data?: {
          id?: number;
          user_id?: number;
          score?: number;
          description?: string | null;
          created_at?: string;
          updated_at?: string;
        };
      }
    )?.data;

    const savedRating = {
      id: apiRating?.id ?? 0,
      user_id: apiRating?.user_id,
      score,
      description: technicianRatingNote.value || null,
      created_at: apiRating?.created_at,
      updated_at: apiRating?.updated_at,
    };

    if (selectedRequest.value) {
      selectedRequest.value = {
        ...selectedRequest.value,
        rating: savedRating,
      };
    }

    historyItems.value = historyItems.value.map((item) =>
      item.id === currentRequestId
        ? {
            ...item,
            rating: savedRating,
          }
        : item
    );

    toast.success("Puntaje del tecnico guardado.");
  } catch (error) {
    console.error(error);
    const backendMessage = extractApiMessage(error);
    toast.error(backendMessage || "No se pudo guardar el puntaje del tecnico.");
  } finally {
    ratingBusy.value = false;
  }
};

const fetchAIResponse = async (messagesContext: { role: "assistant" | "user"; content: string }[]) => {
  isTyping.value = true;
  try {
    const response = await fetch(`${aiBaseUrl}/chat`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ messages: messagesContext }),
    });

    if (!response.ok) {
      throw new Error("API error");
    }

    const data = await response.json();
    const answer = typeof data?.response === "string" ? data.response : "No pude responder esa consulta.";
    chatMessages.value.push({ role: "assistant", content: answer });
  } catch (error) {
    console.error(error);
    chatMessages.value.push({
      role: "assistant",
      content: 'No pude conectarme con el servidor de IA. Verifica que este corriendo "python IA/qa.py".',
    });
  } finally {
    isTyping.value = false;
  }
};

const sendMessage = async () => {
  const text = chatInput.value.trim();
  if (!text || isTyping.value) return;

  chatMessages.value.push({ role: "user", content: text });
  chatInput.value = "";
  await fetchAIResponse(chatMessages.value.map((item) => ({ role: item.role, content: item.content })));
};

watch(showChat, async (open) => {
  if (!open || chatMessages.value.length > 0) return;
  await fetchAIResponse([{ role: "user", content: "Hola, quien eres?" }]);
});

onMounted(async () => {
  await loadInitialData();
});
</script>

<template>
  <div class="mx-auto w-full max-w-2xl space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Soporte y reclamos</h1>
      <p class="text-muted-foreground">Crea solicitudes o reclamos y revisa su estado actualizado.</p>
    </header>

    <section class="rounded-lg border bg-card p-6 shadow-sm">
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitTicket">
        <select v-model="form.type" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm">
          <option value="technical">Solicitud tecnica</option>
          <option value="claim">Iniciar reclamo</option>
        </select>
        <select v-model="form.categoryId" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm">
          <option value="">Categoria</option>
          <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="form.subject" required placeholder="Asunto" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm" />
        <textarea v-model="form.description" required rows="3" placeholder="Descripcion" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm md:col-span-2" />
        <div class="space-y-2 md:col-span-2">
          <p class="text-xs font-medium text-muted-foreground">Rango de fecha para visita</p>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <label for="wantedDateStart" class="text-xs font-medium text-muted-foreground">Fecha desde</label>
              <input id="wantedDateStart" v-model="form.wantedDateStart" type="date" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm" />
            </div>
            <div class="space-y-1">
              <label for="wantedDateEnd" class="text-xs font-medium text-muted-foreground">Fecha hasta</label>
              <input id="wantedDateEnd" v-model="form.wantedDateEnd" type="date" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm" />
            </div>
          </div>
        </div>
        <div class="space-y-1 md:col-span-2">
          <label for="timeShift" class="text-xs font-medium text-muted-foreground">Turno preferido para la visita</label>
          <select id="timeShift" v-model="form.timeShift" class="w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="">Turno</option>
            <option value="morning">Mañana</option>
            <option value="afternoon">Tarde</option>
          </select>
        </div>
        <button type="submit" class="w-full min-w-0 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground md:col-span-2" :disabled="submitting">
          {{ submitting ? "Enviando..." : "Enviar solicitud" }}
        </button>
      </form>
    </section>

    <section class="rounded-lg border bg-card shadow-sm">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <h2 class="text-lg font-semibold">Historial de solicitudes</h2>
        <button class="rounded-md border px-2 py-1 text-xs font-semibold" @click="historyCollapsed = !historyCollapsed">
          <span class="inline-flex items-center gap-1">
            {{ historyCollapsed ? "Expandir" : "Comprimir" }}
            <Icon :icon="historyCollapsed ? 'mdi:chevron-down' : 'mdi:chevron-up'" class="h-4 w-4" />
          </span>
        </button>
      </div>

      <div v-if="historyCollapsed" class="px-6 py-6 text-sm text-muted-foreground">
        Historial comprimido.
      </div>

      <template v-else>
        <div class="grid gap-3 border-b px-6 py-4 md:grid-cols-[minmax(0,1fr)_220px]">
          <div class="relative">
            <Icon icon="mdi:magnify" class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <input
              v-model="historySearch"
              type="text"
              placeholder="Buscar por asunto, ID o estado..."
              class="w-full rounded-md border border-input bg-background py-2 pl-9 pr-3 text-sm"
            />
          </div>
          <select v-model="historyDatePreset" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="all">Todas las fechas</option>
            <option value="today">Hoy</option>
            <option value="last_7_days">Ultimos 7 dias</option>
            <option value="last_30_days">Ultimos 30 dias</option>
            <option value="last_3_months">Ultimos 3 meses</option>
            <option value="last_6_months">Ultimos 6 meses</option>
          </select>
        </div>

        <div v-if="loadingHistory" class="px-6 py-6 text-sm text-muted-foreground">Cargando historial...</div>
        <div v-else-if="historyItems.length === 0" class="px-6 py-6 text-sm text-muted-foreground">No hay solicitudes aun.</div>
        <div v-else-if="filteredHistoryItems.length === 0" class="px-6 py-6 text-sm text-muted-foreground">No hay resultados para los filtros seleccionados.</div>
        <div v-else class="divide-y">
          <button
            v-for="item in filteredHistoryItems"
            :key="item.id"
            class="flex w-full items-center justify-between px-6 py-4 text-left text-sm hover:bg-muted/30"
            @click="openDetail(item)"
          >
            <div>
              <p class="font-medium">{{ item.subject }}</p>
              <p class="text-xs text-muted-foreground">
                #{{ item.id }} | {{ item.type === "claim" ? "Reclamo" : "Solicitud tecnica" }} | Generada: {{ formatDate(item.created_at) }}
              </p>
              <p v-if="item.scheduled_visit_date" class="text-xs text-muted-foreground">
                Visita programada: {{ formatDate(item.scheduled_visit_date) }}
              </p>
              <p v-if="item.charged_amount !== null && item.charged_amount !== undefined" class="text-xs text-muted-foreground">
                Monto pagado: {{ formatCurrency(item.charged_amount) }}
              </p>
            </div>
            <span :class="['rounded-full px-2 py-1 text-xs font-semibold', statusClass[getDisplayStatus(item)] || 'bg-muted text-muted-foreground']">
              {{ statusLabel[getDisplayStatus(item)] || getDisplayStatus(item) }}
            </span>
          </button>
        </div>
      </template>
    </section>

    <div v-if="showChat" class="fixed bottom-24 right-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-lg border bg-background shadow-xl md:right-6 md:w-96">
      <div class="flex items-center justify-between bg-primary p-4 text-primary-foreground">
        <span class="inline-flex items-center gap-2 text-sm font-semibold">
          <Icon icon="mdi:robot-happy" class="h-5 w-5" />
          Asistente virtual
        </span>
        <button class="rounded p-1 hover:bg-primary-foreground/20" @click="showChat = false">
          <Icon icon="mdi:close" class="h-5 w-5" />
        </button>
      </div>
      <div class="flex h-80 flex-col gap-3 overflow-y-auto bg-muted/30 p-4">
        <div
          v-for="(msg, idx) in chatMessages"
          :key="idx"
          :class="[
            'max-w-[85%] whitespace-pre-wrap rounded-lg p-3 text-sm leading-relaxed',
            msg.role === 'assistant'
              ? 'self-start rounded-tl-none bg-primary/10 text-foreground'
              : 'self-end rounded-tr-none bg-primary text-primary-foreground'
          ]"
        >
          {{ msg.content }}
        </div>
        <div v-if="isTyping" class="self-start rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
          Escribiendo...
        </div>
      </div>
      <form class="flex items-center gap-2 border-t bg-background p-3" @submit.prevent="sendMessage">
        <input
          v-model="chatInput"
          type="text"
          placeholder="Escribe tu consulta..."
          class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
          :disabled="isTyping"
        />
        <button
          type="submit"
          class="rounded-md bg-primary p-2 text-primary-foreground disabled:opacity-50"
          :disabled="isTyping || !chatInput.trim()"
        >
          <Icon icon="mdi:send" class="h-4 w-4" />
        </button>
      </form>
    </div>

    <div class="fixed bottom-4 right-4 z-50 md:bottom-6 md:right-6">
      <button :class="['flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:scale-105', !showChat ? 'animate-bounce' : '']" @click="showChat = !showChat">
        <Icon :icon="showChat ? 'mdi:close' : 'mdi:message-text-outline'" class="h-7 w-7" />
      </button>
      <div v-if="!showChat" class="pointer-events-none absolute -left-44 bottom-3 hidden rounded-md bg-popover px-3 py-2 text-xs font-medium text-popover-foreground shadow md:block">
        Consultame por soporte
      </div>
    </div>

    <div v-if="selectedRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-xl max-h-[90vh] overflow-hidden rounded-lg border bg-card shadow-lg">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h3 class="text-lg font-semibold">Detalle #{{ selectedRequest.id }}</h3>
          <button @click="closeDetail"><Icon icon="mdi:close" class="h-5 w-5" /></button>
        </div>
        <div class="max-h-[calc(90vh-72px)] overflow-y-auto overscroll-contain space-y-3 px-6 py-4 text-sm">
          <p><span class="font-semibold">Asunto:</span> {{ selectedRequest.subject }}</p>
          <p><span class="font-semibold">Descripcion:</span> {{ selectedRequest.description }}</p>
          <p><span class="font-semibold">Estado:</span> {{ statusLabel[getDisplayStatus(selectedRequest)] || getDisplayStatus(selectedRequest) }}</p>
          <p><span class="font-semibold">Tecnico:</span> {{ selectedTechnicianName }}</p>
          <p>
            <span class="font-semibold">Fechas solicitadas:</span>
            {{ formatDate(selectedRequest.wanted_date_start) }} a {{ formatDate(selectedRequest.wanted_date_end) }}
          </p>
          <p><span class="font-semibold">Turno solicitado:</span> {{ shiftLabel(selectedRequest.time_shift) }}</p>
          <p v-if="selectedRequest.scheduled_visit_date">
            <span class="font-semibold">Visita programada por tecnico:</span>
            {{ formatDate(selectedRequest.scheduled_visit_date) }}
          </p>
          <div v-if="selectedRequest.resolution_summary">
            <p><span class="font-semibold">Solucion informada por tecnico:</span></p>
            <p class="mt-1 whitespace-pre-wrap rounded-md border bg-muted/20 p-2">
              {{ selectedRequest.resolution_summary }}
            </p>
          </div>
          <div v-if="selectedRequest.status === 'cancelled' && selectedRequest.cancellation_reason">
            <p><span class="font-semibold">Motivo de cancelacion:</span></p>
            <p class="mt-1 whitespace-pre-wrap rounded-md border bg-muted/20 p-2">
              {{ selectedRequest.cancellation_reason }}
            </p>
          </div>
          <p v-if="selectedRequest.repaired_product">
            <span class="font-semibold">Producto reparado:</span>
            {{ selectedRequest.repaired_product.name || `Producto #${selectedRequest.repaired_product.id}` }}
          </p>
          <p v-if="selectedRequest.charged_amount !== null && selectedRequest.charged_amount !== undefined">
            <span class="font-semibold">Monto pagado:</span>
            {{ formatCurrency(selectedRequest.charged_amount) }}
          </p>
          <p v-if="selectedRequest.claim?.answer"><span class="font-semibold">Respuesta admin:</span> {{ selectedRequest.claim.answer }}</p>
          <div v-if="canRateSelected" class="space-y-3 rounded-md border bg-muted/20 p-3">
            <p class="text-xs font-semibold uppercase text-muted-foreground">Puntuar caso cerrado</p>
            <p v-if="hasSavedTechnicianRating" class="text-xs text-emerald-700">
              Ya guardaste este puntaje.
              <span v-if="selectedRequest.rating?.created_at"> Fecha: {{ formatDate(selectedRequest.rating?.created_at) }}.</span>
            </p>
            <div class="space-y-1">
              <p class="text-xs text-muted-foreground">Puntaje tecnico</p>
              <StarRatingInput v-model="technicianRating" :disabled="ratingBusy || hasSavedTechnicianRating" />
            </div>
            <textarea
              v-model="technicianRatingNote"
              rows="2"
              placeholder="Comentario tecnico (opcional)"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              :disabled="ratingBusy || hasSavedTechnicianRating"
            />
            <button
              class="rounded-md bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="ratingBusy || !technicianRating || hasSavedTechnicianRating"
              @click="submitTechnicianRating"
            >
              {{ hasSavedTechnicianRating ? "Puntaje ya guardado" : "Guardar puntaje tecnico" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
