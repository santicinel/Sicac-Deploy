<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { toast } from "vue-sonner";
import supportRequestsService, {
  type ApiServiceRequest,
  type RatingSummaryPeriod,
  type RatingSummaryType,
  type ServiceRequestStatus,
} from "@/services/supportRequestsService";

interface TechnicianSummary {
  technician_id: number;
  first_name?: string;
  last_name?: string;
  name?: string;
  average: number;
  total: number;
  assigned_cases?: number;
  closed_cases?: number;
  generated_revenue?: number;
  last_review_at?: string;
  last_comment?: string;
  last_client_notes?: string;
}

interface ClientSummary {
  client_user_id: number;
  first_name?: string;
  last_name?: string;
  name?: string;
  email?: string;
  average: number;
  total: number;
  assigned_cases?: number;
  closed_cases?: number;
  last_score?: number;
  last_comment?: string;
}

type HistoryDatePreset = "all" | "today" | "last_7_days" | "last_30_days" | "last_3_months" | "last_6_months";

interface HistoryContext {
  mode: RatingSummaryType;
  id: number;
  title: string;
  subtitle: string;
}

const ratingType = ref<RatingSummaryType>("technicians");
const ratingPeriod = ref<RatingSummaryPeriod>("all");
const loading = ref(false);
const search = ref("");
const technicians = ref<TechnicianSummary[]>([]);
const clients = ref<ClientSummary[]>([]);
const historyOpen = ref(false);
const historyLoading = ref(false);
const historySearch = ref("");
const historyDatePreset = ref<HistoryDatePreset>("all");
const historyContext = ref<HistoryContext | null>(null);
const historyItems = ref<ApiServiceRequest[]>([]);
const selectedHistoryRequest = ref<ApiServiceRequest | null>(null);

const statusLabel: Record<ServiceRequestStatus, string> = {
  pending: "Sin asignacion",
  assigned: "Asignado",
  completed: "Completada",
  cancelled: "Cancelada",
};

const statusClass: Record<ServiceRequestStatus, string> = {
  pending: "bg-amber-100 text-amber-800 border-amber-200",
  assigned: "bg-blue-100 text-blue-800 border-blue-200",
  completed: "bg-emerald-100 text-emerald-800 border-emerald-200",
  cancelled: "bg-zinc-200 text-zinc-800 border-zinc-300",
};

const fullName = (item: { first_name?: string; last_name?: string; name?: string }, fallback: string) => {
  const first = (item.first_name || "").trim();
  const last = (item.last_name || "").trim();
  const combined = `${first} ${last}`.trim();
  return combined || item.name || fallback;
};

const getDisplayStatus = (item: Pick<ApiServiceRequest, "status" | "technician_id">): ServiceRequestStatus => {
  if (item.status === "completed" || item.status === "cancelled") {
    return item.status;
  }
  return item.technician_id ? "assigned" : "pending";
};

const formatDate = (value?: string | null) => {
  if (!value) return "Sin fecha";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(date);
};

const formatDateTime = (value?: string | null) => {
  if (!value) return "Sin fecha";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
};

const normalizeTimeInput = (value?: string | null) => {
  if (!value) return "";
  const raw = String(value).trim();
  const match = raw.match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
  if (match?.[1] && match[2]) {
    return `${match[1]}:${match[2]}`;
  }

  const parsed = new Date(`1970-01-01T${raw}`);
  if (Number.isNaN(parsed.getTime())) return "";
  return `${String(parsed.getHours()).padStart(2, "0")}:${String(parsed.getMinutes()).padStart(2, "0")}`;
};

const formatTime = (value?: string | null) => {
  const normalized = normalizeTimeInput(value);
  if (!normalized) return "Sin horario";
  return `${normalized} hs`;
};

const shiftLabel = (value?: string | null) => {
  const normalized = String(value || "").trim().toLowerCase();
  if (!normalized) return "Sin turno";
  if (normalized === "morning" || normalized === "mañana" || normalized === "manana") return "Mañana";
  if (normalized === "afternoon" || normalized === "tarde") return "Tarde";
  return String(value);
};

const displayText = (value?: string | null, fallback = "N/D") => {
  const normalized = String(value || "").trim();
  return normalized || fallback;
};

const getTechnicianNameFromRequest = (item?: ApiServiceRequest | null) => {
  const tech = item?.technician;
  if (!tech) return "Sin tecnico asignado";
  const full = `${tech.first_name ?? ""} ${tech.last_name ?? ""}`.trim();
  return tech.user?.name || full || "Tecnico";
};

const getClientAddressLabel = (item?: ApiServiceRequest | null) => {
  const address = displayText(item?.requesting_user?.address, "Direccion no informada");
  const city = displayText(item?.requesting_user?.city, "").trim();
  return city ? `${address}, ${city}` : address;
};

const formatCurrency = (value?: number | string | null) => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return "$ 0,00";
  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric);
};

const formatHistoryCardMeta = (item: ApiServiceRequest) => {
  if (historyContext.value?.mode === "clients") {
    return `#${item.id} | ${item.type === "claim" ? "Reclamo" : "Solicitud tecnica"} | Generada: ${formatDate(item.created_at)}`;
  }

  return `#${item.id} | ${item.type === "claim" ? "Reclamo" : "Solicitud tecnica"} | ${formatDate(item.completed_at || item.created_at)}`;
};

const resetHistoryModal = () => {
  historySearch.value = "";
  historyDatePreset.value = "all";
  selectedHistoryRequest.value = null;
  historyItems.value = [];
  historyContext.value = null;
  historyOpen.value = false;
};

const openTechnicianHistory = async (item: TechnicianSummary) => {
  historyOpen.value = true;
  historyLoading.value = true;
  selectedHistoryRequest.value = null;
  historySearch.value = "";
  historyDatePreset.value = "all";
  historyContext.value = {
    mode: "technicians",
    id: item.technician_id,
    title: fullName(item, `Tecnico #${item.technician_id}`),
    subtitle: "Historial de tareas del tecnico",
  };

  try {
    historyItems.value = await supportRequestsService.getAdminRequests({
      technician_id: item.technician_id,
    });
  } catch (error) {
    console.error(error);
    toast.error("No se pudo cargar el historial del tecnico.");
    resetHistoryModal();
  } finally {
    historyLoading.value = false;
  }
};

const openClientHistory = async (item: ClientSummary) => {
  historyOpen.value = true;
  historyLoading.value = true;
  selectedHistoryRequest.value = null;
  historySearch.value = "";
  historyDatePreset.value = "all";
  historyContext.value = {
    mode: "clients",
    id: item.client_user_id,
    title: fullName(item, `Cliente #${item.client_user_id}`),
    subtitle: item.email || "Historial de reclamos y solicitudes del cliente",
  };

  try {
    historyItems.value = await supportRequestsService.getAdminRequests({
      requesting_user_id: item.client_user_id,
    });
  } catch (error) {
    console.error(error);
    toast.error("No se pudo cargar el historial del cliente.");
    resetHistoryModal();
  } finally {
    historyLoading.value = false;
  }
};

const filteredTechnicians = computed(() =>
  technicians.value.filter((item) => {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    const technicianName = fullName(item, `Tecnico #${item.technician_id}`).toLowerCase();
    return (
      technicianName.includes(q) ||
      String(item.technician_id).includes(q)
    );
  })
);

const filteredClients = computed(() =>
  clients.value.filter((item) => {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    const clientName = fullName(item, `Cliente #${item.client_user_id}`).toLowerCase();
    return (
      clientName.includes(q) ||
      (item.email || "").toLowerCase().includes(q) ||
      String(item.client_user_id).includes(q)
    );
  })
);

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
  const text = historySearch.value.trim().toLowerCase();
  const dateRange = getDateRangeFromPreset(historyDatePreset.value);

  return historyItems.value.filter((item) => {
    if (text) {
      const values = [
        item.subject,
        item.description,
        String(item.id),
        item.type === "claim" ? "reclamo" : "solicitud tecnica",
        statusLabel[getDisplayStatus(item)] || getDisplayStatus(item),
        item.requesting_user?.name || "",
        item.requesting_user?.email || "",
        item.requesting_user?.address || "",
        getTechnicianNameFromRequest(item),
      ];

      const matchesText = values.some((value) => value.toLowerCase().includes(text));
      if (!matchesText) return false;
    }

    if (!dateRange) return true;

    const referenceDate =
      historyContext.value?.mode === "technicians"
        ? (item.completed_at || item.created_at)
        : item.created_at;

    const parsed = new Date(referenceDate);
    if (Number.isNaN(parsed.getTime())) return false;
    return parsed >= dateRange.from && parsed <= dateRange.to;
  });
});

const loadRatings = async () => {
  loading.value = true;
  try {
    const data = await supportRequestsService.getRatingsSummary(ratingType.value, ratingPeriod.value);
    if (ratingType.value === "technicians") {
      technicians.value = data as TechnicianSummary[];
    } else {
      clients.value = data as ClientSummary[];
    }
  } catch (error) {
    console.error(error);
    toast.error("No se pudieron cargar los puntajes.");
  } finally {
    loading.value = false;
  }
};

watch([ratingType, ratingPeriod], async () => {
  await loadRatings();
});

onMounted(async () => {
  await loadRatings();
});
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Puntajes</h1>
      <p class="text-muted-foreground">Resumen de puntajes de tecnicos y clientes en casos cerrados.</p>
    </header>

    <div class="flex flex-wrap items-center gap-3 rounded-lg border bg-card p-4 shadow-sm">
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="ratingType === 'technicians' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="ratingType = 'technicians'">Tecnicos</button>
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="ratingType === 'clients' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="ratingType = 'clients'">Clientes</button>
      <select v-model="ratingPeriod" class="w-full min-w-[220px] rounded-md border border-input bg-background px-3 py-2 text-sm md:w-auto">
        <option value="all">Todo el historial</option>
        <option value="last_day">Ultimo dia</option>
        <option value="last_week">Ultima semana</option>
        <option value="last_month">Ultimo mes</option>
        <option value="last_3_months">Ultimos 3 meses</option>
        <option value="last_6_months">Ultimos 6 meses</option>
        <option value="last_12_months">Ultimos 12 meses</option>
      </select>
      <div class="relative min-w-[220px] flex-1">
        <Icon icon="mdi:magnify" class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
        <input v-model="search" type="text" placeholder="Buscar..." class="w-full rounded-md border border-input bg-background py-2 pl-9 pr-3 text-sm" />
      </div>
    </div>

    <section class="rounded-lg border bg-card shadow-sm">
      <div class="space-y-1 border-b px-6 py-4">
        <h2 class="text-lg font-semibold">
          {{ ratingType === "technicians" ? "Resumen de puntajes de tecnicos" : "Resumen de puntajes de clientes" }}
        </h2>
        <p class="text-xs text-muted-foreground">
          Promedio: media de puntajes (1 a 5). Asignados: casos con tecnico asignado. Cerrados: casos completados.
        </p>
      </div>

      <div v-if="loading" class="px-6 py-6 text-sm text-muted-foreground">Cargando puntajes...</div>

      <div v-else-if="ratingType === 'technicians'" class="divide-y">
        <div v-if="filteredTechnicians.length === 0" class="px-6 py-6 text-sm text-muted-foreground">Sin puntajes de tecnicos.</div>
        <div v-else class="hidden gap-2 border-b bg-muted/20 px-6 py-3 text-xs font-semibold uppercase text-muted-foreground md:grid md:grid-cols-[1.2fr_130px_130px_130px_130px_160px_1.4fr_120px]">
          <span>Nombre y apellido</span>
          <span>Promedio</span>
          <span>Puntajes</span>
          <span>Asignados</span>
          <span>Cerrados</span>
          <span>Generado</span>
          <span>Ultimo comentario</span>
          <span>Historial</span>
        </div>
        <div
          v-for="item in filteredTechnicians"
          :key="item.technician_id"
          class="grid gap-2 px-6 py-4 text-sm md:grid-cols-[1.2fr_130px_130px_130px_130px_160px_1.4fr_120px] md:items-start"
        >
          <span class="font-medium">{{ fullName(item, `Tecnico #${item.technician_id}`) }}</span>
          <span class="inline-flex items-center gap-1">
            <Icon icon="mdi:star" class="h-4 w-4 text-amber-500" />
            {{ item.average.toFixed(2) }}
          </span>
          <span>{{ item.total }}</span>
          <span>{{ item.assigned_cases ?? 0 }}</span>
          <span>{{ item.closed_cases ?? 0 }}</span>
          <span>{{ formatCurrency(item.generated_revenue ?? 0) }}</span>
          <span class="text-muted-foreground">{{ item.last_comment || item.last_client_notes || "-" }}</span>
          <button class="rounded-md border px-3 py-2 text-xs font-semibold" @click="openTechnicianHistory(item)">
            Ver historial
          </button>
        </div>
      </div>

      <div v-else class="divide-y">
        <div v-if="filteredClients.length === 0" class="px-6 py-6 text-sm text-muted-foreground">Sin puntajes de clientes.</div>
        <div v-else class="hidden gap-2 border-b bg-muted/20 px-6 py-3 text-xs font-semibold uppercase text-muted-foreground md:grid md:grid-cols-[1.2fr_1fr_130px_130px_130px_130px_1.4fr_120px]">
          <span>Nombre y apellido</span>
          <span>Email</span>
          <span>Promedio</span>
          <span>Puntajes</span>
          <span>Asignados</span>
          <span>Cerrados</span>
          <span>Ultimo comentario</span>
          <span>Historial</span>
        </div>
        <div
          v-for="item in filteredClients"
          :key="item.client_user_id"
          class="grid gap-2 px-6 py-4 text-sm md:grid-cols-[1.2fr_1fr_130px_130px_130px_130px_1.4fr_120px] md:items-start"
        >
          <span class="font-medium">{{ fullName(item, `Cliente #${item.client_user_id}`) }}</span>
          <span>{{ item.email || "-" }}</span>
          <span class="inline-flex items-center gap-1">
            <Icon icon="mdi:star" class="h-4 w-4 text-amber-500" />
            {{ item.average.toFixed(2) }}
          </span>
          <span>{{ item.total }}</span>
          <span>{{ item.assigned_cases ?? 0 }}</span>
          <span>{{ item.closed_cases ?? 0 }}</span>
          <span class="text-muted-foreground">{{ item.last_comment || "-" }}</span>
          <button class="rounded-md border px-3 py-2 text-xs font-semibold" @click="openClientHistory(item)">
            Ver historial
          </button>
        </div>
      </div>
    </section>

    <div v-if="historyOpen && historyContext" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-5xl max-h-[90vh] overflow-hidden rounded-lg border bg-card shadow-lg">
        <div class="flex items-start justify-between gap-4 border-b px-6 py-4">
          <div class="space-y-1">
            <h3 class="text-lg font-semibold">{{ historyContext.title }}</h3>
            <p class="text-sm text-muted-foreground">{{ historyContext.subtitle }}</p>
          </div>
          <button @click="resetHistoryModal"><Icon icon="mdi:close" class="h-5 w-5" /></button>
        </div>

        <div class="grid gap-3 border-b px-6 py-4 md:grid-cols-[minmax(0,1fr)_220px]">
          <div class="relative">
            <Icon icon="mdi:magnify" class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <input
              v-model="historySearch"
              type="text"
              :placeholder="historyContext.mode === 'technicians' ? 'Buscar por asunto, cliente o ID...' : 'Buscar por asunto, tecnico o ID...'"
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

        <div class="max-h-[calc(90vh-160px)] overflow-y-auto">
          <div v-if="historyLoading" class="px-6 py-6 text-sm text-muted-foreground">Cargando historial...</div>
          <div v-else-if="filteredHistoryItems.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
            No hay elementos para mostrar en este historial.
          </div>
          <div v-else class="divide-y">
            <div
              v-for="item in filteredHistoryItems"
              :key="item.id"
              class="flex flex-wrap items-start justify-between gap-4 px-6 py-4 text-sm"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium">{{ item.subject }}</p>
                <p class="text-xs text-muted-foreground">{{ formatHistoryCardMeta(item) }}</p>

                <template v-if="historyContext.mode === 'technicians'">
                  <p class="mt-2 text-xs text-muted-foreground">{{ item.description }}</p>
                  <p class="mt-1 text-xs text-muted-foreground">Cliente: {{ item.requesting_user?.name || "N/D" }}</p>
                  <p v-if="item.repaired_product" class="mt-1 text-xs text-muted-foreground">
                    Producto reparado: {{ item.repaired_product.name || `#${item.repaired_product.id}` }}
                  </p>
                </template>

                <template v-else>
                  <p class="mt-2 text-xs text-muted-foreground">Tecnico: {{ getTechnicianNameFromRequest(item) }}</p>
                  <p v-if="item.scheduled_visit_date" class="mt-1 text-xs text-muted-foreground">
                    Visita programada: {{ formatDate(item.scheduled_visit_date) }}
                    <template v-if="item.scheduled_visit_time"> | {{ formatTime(item.scheduled_visit_time) }}</template>
                  </p>
                  <p v-if="item.charged_amount !== null && item.charged_amount !== undefined" class="mt-1 text-xs text-muted-foreground">
                    Monto pagado: {{ formatCurrency(item.charged_amount) }}
                  </p>
                </template>
              </div>

              <div class="flex flex-col items-end gap-2">
                <span :class="['inline-flex rounded-full border px-2 py-1 text-xs font-semibold', statusClass[getDisplayStatus(item)]]">
                  {{ statusLabel[getDisplayStatus(item)] }}
                </span>
                <button class="rounded-md border px-3 py-1 text-xs font-semibold" @click="selectedHistoryRequest = item">
                  Ver detalle
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedHistoryRequest && historyContext" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-lg border bg-card shadow-lg">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h3 class="text-lg font-semibold">Detalle #{{ selectedHistoryRequest.id }}</h3>
          <button @click="selectedHistoryRequest = null"><Icon icon="mdi:close" class="h-5 w-5" /></button>
        </div>

        <div class="max-h-[calc(90vh-72px)] overflow-y-auto space-y-3 px-6 py-4 text-sm">
          <template v-if="historyContext.mode === 'technicians'">
            <p><span class="font-semibold">Asunto:</span> {{ selectedHistoryRequest.subject }}</p>
            <p><span class="font-semibold">Descripcion:</span> {{ selectedHistoryRequest.description }}</p>
            <p><span class="font-semibold">Estado:</span> {{ statusLabel[getDisplayStatus(selectedHistoryRequest)] }}</p>
            <p><span class="font-semibold">Cliente:</span> {{ selectedHistoryRequest.requesting_user?.name || "N/D" }}</p>
            <p><span class="font-semibold">Telefono:</span> {{ displayText(selectedHistoryRequest.requesting_user?.phone, "No informado") }}</p>
            <p><span class="font-semibold">Direccion:</span> {{ getClientAddressLabel(selectedHistoryRequest) }}</p>
            <p>
              <span class="font-semibold">Fechas solicitadas:</span>
              {{ formatDate(selectedHistoryRequest.wanted_date_start) }} a {{ formatDate(selectedHistoryRequest.wanted_date_end) }}
            </p>
            <p><span class="font-semibold">Turno solicitado:</span> {{ shiftLabel(selectedHistoryRequest.time_shift) }}</p>
            <p v-if="selectedHistoryRequest.scheduled_visit_date">
              <span class="font-semibold">Visita programada:</span> {{ formatDate(selectedHistoryRequest.scheduled_visit_date) }}
            </p>
            <p v-if="selectedHistoryRequest.scheduled_visit_time">
              <span class="font-semibold">Hora estimada de visita:</span> {{ formatTime(selectedHistoryRequest.scheduled_visit_time) }}
            </p>
            <p v-if="selectedHistoryRequest.completed_at">
              <span class="font-semibold">Fecha de cierre:</span> {{ formatDateTime(selectedHistoryRequest.completed_at) }}
            </p>
            <div v-if="selectedHistoryRequest.resolution_summary">
              <p><span class="font-semibold">Solucion aplicada:</span></p>
              <p class="mt-1 whitespace-pre-wrap rounded-md border bg-muted/20 p-3">{{ selectedHistoryRequest.resolution_summary }}</p>
            </div>
            <div v-if="selectedHistoryRequest.cancellation_reason">
              <p><span class="font-semibold">Motivo de cancelacion:</span></p>
              <p class="mt-1 whitespace-pre-wrap rounded-md border bg-muted/20 p-3">{{ selectedHistoryRequest.cancellation_reason }}</p>
            </div>
            <p v-if="selectedHistoryRequest.repaired_product">
              <span class="font-semibold">Producto reparado:</span>
              {{ selectedHistoryRequest.repaired_product.name || `Producto #${selectedHistoryRequest.repaired_product.id}` }}
            </p>
            <p v-if="selectedHistoryRequest.charged_amount !== null && selectedHistoryRequest.charged_amount !== undefined">
              <span class="font-semibold">Monto cobrado:</span> {{ formatCurrency(selectedHistoryRequest.charged_amount) }}
            </p>
          </template>

          <template v-else>
            <p><span class="font-semibold">Asunto:</span> {{ selectedHistoryRequest.subject }}</p>
            <p><span class="font-semibold">Descripcion:</span> {{ selectedHistoryRequest.description }}</p>
            <p><span class="font-semibold">Estado:</span> {{ statusLabel[getDisplayStatus(selectedHistoryRequest)] }}</p>
            <p><span class="font-semibold">Tecnico:</span> {{ getTechnicianNameFromRequest(selectedHistoryRequest) }}</p>
            <p>
              <span class="font-semibold">Fechas solicitadas:</span>
              {{ formatDate(selectedHistoryRequest.wanted_date_start) }} a {{ formatDate(selectedHistoryRequest.wanted_date_end) }}
            </p>
            <p><span class="font-semibold">Turno solicitado:</span> {{ shiftLabel(selectedHistoryRequest.time_shift) }}</p>
            <p v-if="selectedHistoryRequest.scheduled_visit_date">
              <span class="font-semibold">Visita programada por tecnico:</span> {{ formatDate(selectedHistoryRequest.scheduled_visit_date) }}
            </p>
            <p v-if="selectedHistoryRequest.scheduled_visit_time">
              <span class="font-semibold">Hora estimada de visita:</span> {{ formatTime(selectedHistoryRequest.scheduled_visit_time) }}
            </p>
            <div v-if="selectedHistoryRequest.resolution_summary">
              <p><span class="font-semibold">Solucion informada por tecnico:</span></p>
              <p class="mt-1 whitespace-pre-wrap rounded-md border bg-muted/20 p-3">{{ selectedHistoryRequest.resolution_summary }}</p>
            </div>
            <div v-if="selectedHistoryRequest.cancellation_reason">
              <p><span class="font-semibold">Motivo de cancelacion:</span></p>
              <p class="mt-1 whitespace-pre-wrap rounded-md border bg-muted/20 p-3">{{ selectedHistoryRequest.cancellation_reason }}</p>
            </div>
            <p v-if="selectedHistoryRequest.repaired_product">
              <span class="font-semibold">Producto reparado:</span>
              {{ selectedHistoryRequest.repaired_product.name || `Producto #${selectedHistoryRequest.repaired_product.id}` }}
            </p>
            <p v-if="selectedHistoryRequest.charged_amount !== null && selectedHistoryRequest.charged_amount !== undefined">
              <span class="font-semibold">Monto pagado:</span> {{ formatCurrency(selectedHistoryRequest.charged_amount) }}
            </p>
            <p v-if="selectedHistoryRequest.claim?.answer">
              <span class="font-semibold">Respuesta admin:</span> {{ selectedHistoryRequest.claim.answer }}
            </p>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
