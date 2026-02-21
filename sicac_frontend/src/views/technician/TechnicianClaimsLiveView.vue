<script setup lang="ts">
import { DateFormatter, getLocalTimeZone, parseDate } from "@internationalized/date";
import { computed, onMounted, ref } from "vue";
import { Icon } from "@iconify/vue";
import { toast } from "vue-sonner";
import StarRatingInput from "@/components/ui/StarRatingInput.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RangeCalendar } from "@/components/ui/range-calendar";
import productsService, { type Product } from "@/services/productsService";
import supportRequestsService, {
  type ApiServiceRequest,
  type ServiceRequestStatus,
} from "@/services/supportRequestsService";

const loading = ref(false);
const myRequests = ref<ApiServiceRequest[]>([]);
const unassignedRequests = ref<ApiServiceRequest[]>([]);
const products = ref<Product[]>([]);
const section = ref<"assigned" | "unassigned" | "history">("assigned");
const panelSearch = ref("");
type DatePreset = "all" | "today" | "last_7_days" | "last_30_days" | "last_3_months" | "last_6_months";
const panelDatePreset = ref<DatePreset>("all");
const selected = ref<ApiServiceRequest | null>(null);
const saving = ref(false);
const scheduledVisitDate = ref("");
const visitCalendarOpen = ref(false);
const scheduledVisitRange = ref<any>({ start: undefined, end: undefined });
const resolutionSummary = ref("");
const cancellationReason = ref("");
const chargedAmount = ref("");
const repairedProductId = ref<number | null>(null);
const productSearch = ref("");
const clientScore = ref<number | null>(null);
const clientComment = ref("");
const dateFormatter = new DateFormatter("es-AR", { dateStyle: "long" });
const localTimeZone = getLocalTimeZone();

const statusLabel: Record<ServiceRequestStatus, string> = {
  pending: "Sin asignaci\u00f3n",
  assigned: "Asignado",
  completed: "Completada",
  cancelled: "Cancelada",
};

const decodeLatin1ToUtf8 = (input: string) => {
  try {
    const bytes = Uint8Array.from(input, (char) => char.charCodeAt(0));
    return new TextDecoder("utf-8", { fatal: true }).decode(bytes);
  } catch {
    return input;
  }
};

const fixMojibake = (value?: string | null) => {
  let textValue = String(value ?? "");
  for (let i = 0; i < 2; i += 1) {
    const decoded = decodeLatin1ToUtf8(textValue);
    if (decoded === textValue) break;
    textValue = decoded;
  }
  return textValue;
};

const displayText = (value?: string | null, fallback = "N/D") => {
  const normalized = fixMojibake(value).trim();
  return normalized || fallback;
};

const getDisplayStatus = (item: Pick<ApiServiceRequest, "status" | "technician_id">): ServiceRequestStatus => {
  if (item.status === "completed" || item.status === "cancelled") {
    return item.status;
  }
  return item.technician_id ? "assigned" : "pending";
};

const myActive = computed(() => myRequests.value.filter((item) => item.status === "assigned" || item.status === "pending"));
const myHistory = computed(() => myRequests.value.filter((item) => item.status === "completed" || item.status === "cancelled"));
const sectionItems = computed(() => {
  if (section.value === "assigned") return myActive.value;
  if (section.value === "unassigned") return unassignedRequests.value;
  return myHistory.value;
});

const getDateRangeFromPreset = (preset: DatePreset): { from: Date; to: Date } | null => {
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

const includesSearchText = (item: ApiServiceRequest, query: string) => {
  if (!query) return true;

  const values = [
    fixMojibake(item.subject),
    fixMojibake(item.description),
    fixMojibake(item.requesting_user?.name || ""),
    fixMojibake(item.requesting_user?.email || ""),
    fixMojibake(item.repaired_product?.name || ""),
    fixMojibake(item.repaired_product?.model_sku || ""),
    item.type === "claim" ? "reclamo" : "solicitud tecnica",
    String(item.id),
  ];

  return values.some((value) => value.toLowerCase().includes(query));
};

const withinDatePreset = (item: ApiServiceRequest, range: { from: Date; to: Date } | null) => {
  if (!range) return true;

  const referenceDate =
    section.value === "history"
      ? (item.completed_at || item.created_at)
      : item.created_at;

  const parsed = new Date(referenceDate);
  if (Number.isNaN(parsed.getTime())) return false;
  return parsed >= range.from && parsed <= range.to;
};

const filteredSectionItems = computed(() => {
  const query = panelSearch.value.trim().toLowerCase();
  const range = getDateRangeFromPreset(panelDatePreset.value);
  return sectionItems.value.filter((item) => includesSearchText(item, query) && withinDatePreset(item, range));
});

const filteredProducts = computed(() => {
  const q = productSearch.value.trim().toLowerCase();
  if (!q) return products.value.slice(0, 50);

  return products.value
    .filter((item) => {
      const name = fixMojibake(item.name || "").toLowerCase();
      const sku = fixMojibake(item.model_sku || item.external_id || "").toLowerCase();
      return name.includes(q) || sku.includes(q);
    })
    .slice(0, 50);
});

const formatDate = (value?: string | null) => {
  if (!value) return "Sin fecha";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(date);
};

const shiftLabel = (value?: string | null) => {
  if (!value) return "Sin turno";
  const normalized = fixMojibake(value).trim().toLowerCase();
  if (normalized === "morning" || normalized === "ma\u00f1ana" || normalized === "manana") return "Ma\u00f1ana";
  if (normalized === "afternoon" || normalized === "tarde") return "Tarde";
  return fixMojibake(value);
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

const pad = (value: number) => String(value).padStart(2, "0");

const toLocalDateInput = (date: Date) =>
  `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const buildDateInput = (year: number, month: number, day: number) => {
  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return "";
  if (month < 1 || month > 12 || day < 1 || day > 31) return "";
  const date = new Date(year, month - 1, day);
  if (date.getFullYear() !== year || date.getMonth() + 1 !== month || date.getDate() !== day) return "";
  return `${year}-${pad(month)}-${pad(day)}`;
};

const toDateInput = (value?: string | null) => {
  if (!value) return "";
  const raw = fixMojibake(String(value)).trim();
  const isoMatch = raw.match(/(\d{4})-(\d{2})-(\d{2})/);
  if (isoMatch?.[1] && isoMatch[2] && isoMatch[3]) {
    return buildDateInput(Number(isoMatch[1]), Number(isoMatch[2]), Number(isoMatch[3]));
  }
  const dayFirstMatch = raw.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
  if (dayFirstMatch?.[1] && dayFirstMatch[2] && dayFirstMatch[3]) {
    return buildDateInput(Number(dayFirstMatch[3]), Number(dayFirstMatch[2]), Number(dayFirstMatch[1]));
  }
  const date = new Date(raw);
  if (Number.isNaN(date.getTime())) return "";
  return toLocalDateInput(date);
};

const toDateValue = (value?: string | null) => {
  const normalized = toDateInput(value);
  if (!normalized) return undefined;
  try {
    return parseDate(normalized);
  } catch {
    return undefined;
  }
};

const syncScheduledVisitRange = (value?: string | null) => {
  const parsed = toDateValue(value);
  scheduledVisitRange.value = parsed
    ? { start: parsed, end: parsed }
    : { start: undefined, end: undefined };
};

const clearScheduledVisitDate = () => {
  scheduledVisitDate.value = "";
  syncScheduledVisitRange("");
};

const normalizeScore = (value: number | null): number | null => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 1) return null;
  return Math.min(5, Math.max(1, Math.trunc(numeric)));
};

const normalizeProductId = (value: unknown): number | null => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric <= 0) return null;
  return Math.trunc(numeric);
};


const normalizeChargedAmount = (value: unknown): number | null => {
  if (value === null || value === undefined) return null;
  const normalized = String(value).trim().replace(",", ".");
  if (!normalized) return null;
  const numeric = Number(normalized);
  if (!Number.isFinite(numeric) || numeric <= 0) return null;
  return Math.round(numeric * 100) / 100;
};

const normalizeExistingChargedAmount = (value: unknown): number | null => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric <= 0) return null;
  return Math.round(numeric * 100) / 100;
};

const extractApiMessage = (error: unknown): string => {
  const maybeError = error as {
    response?: {
      data?: {
        message?: unknown;
        errors?: Record<string, string[] | string>;
      };
    };
  };

  const backendMessage = maybeError?.response?.data?.message;
  if (typeof backendMessage === "string" && backendMessage.trim()) {
    return backendMessage;
  }

  const errors = maybeError?.response?.data?.errors;
  if (errors && typeof errors === "object") {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) return String(first[0]);
    if (typeof first === "string" && first.trim()) return first;
  }

  if (error instanceof Error && error.message.trim()) {
    return error.message;
  }

  return "";
};
const selectedClientAddress = computed(() => {
  const address = fixMojibake(selected.value?.requesting_user?.address || "").trim();
  const city = fixMojibake(selected.value?.requesting_user?.city || "").trim();
  return [address, city, "Argentina"].filter(Boolean).join(", ");
});

const selectedMapEmbedUrl = computed(() =>
  selectedClientAddress.value
    ? `https://www.google.com/maps?q=${encodeURIComponent(selectedClientAddress.value)}&output=embed`
    : ""
);

const selectedMapLink = computed(() =>
  selectedClientAddress.value
    ? `https://www.google.com/maps?q=${encodeURIComponent(selectedClientAddress.value)}`
    : ""
);

const resolveRequestedDateBounds = (
  item?: Pick<ApiServiceRequest, "wanted_date_start" | "wanted_date_end"> | null
) => {
  const parsedStart = toDateInput(item?.wanted_date_start);
  const parsedEnd = toDateInput(item?.wanted_date_end);

  if (parsedStart && parsedEnd) {
    return parsedStart <= parsedEnd
      ? { start: parsedStart, end: parsedEnd }
      : { start: parsedEnd, end: parsedStart };
  }

  const singleBound = parsedStart || parsedEnd || "";
  return { start: singleBound, end: singleBound };
};

const selectedWantedRange = computed(() => resolveRequestedDateBounds(selected.value));
const selectedWantedStart = computed(() => selectedWantedRange.value.start);
const selectedWantedEnd = computed(() => selectedWantedRange.value.end);
const selectedWantedStartValue = computed(() => toDateValue(selectedWantedStart.value));
const selectedWantedEndValue = computed(() => toDateValue(selectedWantedEnd.value));
const scheduledVisitDateLabel = computed(() => {
  const parsed = toDateValue(scheduledVisitDate.value);
  if (!parsed) return "Seleccionar fecha de visita";
  return dateFormatter.format(parsed.toDate(localTimeZone));
});

const isOutsideRequestedRange = (dateValue: string, start?: string, end?: string) => {
  if (start && dateValue < start) return true;
  if (end && dateValue > end) return true;
  return false;
};

const enforceScheduledVisitDateInRange = (showError = false) => {
  const current = scheduledVisitDate.value;
  const start = selectedWantedStart.value;
  const end = selectedWantedEnd.value;

  if (!current || (!start && !end)) return;
  if (!isOutsideRequestedRange(current, start, end)) return;

  clearScheduledVisitDate();
  if (showError) {
    toast.error("La fecha elegida debe estar dentro del rango solicitado por el cliente.");
  }
};

const onScheduledVisitRangeChange = (value: any) => {
  scheduledVisitRange.value = value;
  const pickedDate = value.end || value.start;
  if (!pickedDate) {
    clearScheduledVisitDate();
    return;
  }

  scheduledVisitDate.value = toDateInput(pickedDate.toString());
  enforceScheduledVisitDateInRange(true);
  syncScheduledVisitRange(scheduledVisitDate.value);
  if (scheduledVisitDate.value) {
    visitCalendarOpen.value = false;
  }
};

const loadData = async (options: { reloadProducts?: boolean } = {}) => {
  loading.value = true;
  try {
    const shouldReloadProducts = options.reloadProducts === true || products.value.length === 0;
    const requestsPromise = Promise.all([
      supportRequestsService.getMyTechnicianRequests(),
      supportRequestsService.getUnassignedRequests(),
    ]);
    const productsPromise = shouldReloadProducts
      ? productsService.getProduct({ page: 1, per_page: 1000 })
      : Promise.resolve(null);

    const [[mine, available], productsResponse] = await Promise.all([
      requestsPromise,
      productsPromise,
    ]);

    myRequests.value = mine;
    unassignedRequests.value = available;
    if (productsResponse) {
      products.value = Array.isArray(productsResponse.data?.data) ? productsResponse.data.data : [];
    }
  } catch (error) {
    console.error(error);
    toast.error("No se pudieron cargar las solicitudes tecnicas.");
  } finally {
    loading.value = false;
  }
};

const openDetail = (item: ApiServiceRequest) => {
  saving.value = false;
  selected.value = item;
  visitCalendarOpen.value = false;
  scheduledVisitDate.value = toDateInput(item.scheduled_visit_date);
  enforceScheduledVisitDateInRange(false);
  syncScheduledVisitRange(scheduledVisitDate.value);
  resolutionSummary.value = fixMojibake(item.resolution_summary || "").trim();
  cancellationReason.value = fixMojibake(item.cancellation_reason || "").trim();
  chargedAmount.value = item.charged_amount !== null && item.charged_amount !== undefined ? String(item.charged_amount) : "";
  repairedProductId.value = normalizeProductId(item.repaired_product_id ?? item.repaired_product?.id);
  productSearch.value = fixMojibake(item.repaired_product?.name || "");
  clientScore.value = null;
  clientComment.value = "";
};

const assignSelf = async () => {
  if (!selected.value) return;
  saving.value = true;
  try {
    await supportRequestsService.assignToMyself(selected.value.id);
    toast.success("Solicitud tomada correctamente.");
    selected.value = null;
    await loadData();
  } catch (error) {
    console.error(error);
    toast.error("No se pudo tomar la solicitud.");
  } finally {
    saving.value = false;
  }
};

const completeTask = async () => {
  if (!selected.value) return;
  if (saving.value) {
    toast.info("Hay una accion en curso. Espera un instante.");
    return;
  }

  const selectedItem = selected.value;
  const completionScheduledDate =
    scheduledVisitDate.value ||
    toDateInput(selectedItem.scheduled_visit_date) ||
    null;
  const completionSummary =
    resolutionSummary.value.trim() ||
    (selectedItem.resolution_summary || "").trim() ||
    null;
  const completionProductId =
    repairedProductId.value ??
    normalizeProductId(selectedItem.repaired_product_id ?? selectedItem.repaired_product?.id);
  const completionChargedAmount =
    normalizeChargedAmount(chargedAmount.value) ??
    normalizeExistingChargedAmount(selectedItem.charged_amount);

  if (!completionScheduledDate) {
    toast.error("Debes definir la fecha de visita antes de completar.");
    return;
  }

  const { start: wantedStart, end: wantedEnd } = resolveRequestedDateBounds(selectedItem);
  if (isOutsideRequestedRange(completionScheduledDate, wantedStart, wantedEnd)) {
    toast.error("La fecha elegida debe estar dentro del rango solicitado por el cliente.");
    return;
  }

  if (!completionSummary) {
    toast.error("Debes describir la solucion aplicada antes de completar.");
    return;
  }

  if (!completionProductId) {
    toast.error("Debes seleccionar el producto reparado antes de completar.");
    return;
  }

  if (!completionChargedAmount) {
    toast.error("Debes indicar cuanto cobraste por la tarea antes de completar.");
    return;
  }

  saving.value = true;
  try {
    const response = await supportRequestsService.updateRequestStatus(selectedItem.id, "completed", {
      scheduled_visit_date: completionScheduledDate,
      resolution_summary: completionSummary,
      cancellation_reason: null,
      charged_amount: completionChargedAmount,
      repaired_product_id: completionProductId,
    });
    selected.value = response.data.data;

    const normalizedClientScore = normalizeScore(clientScore.value);
    if (normalizedClientScore) {
      await supportRequestsService.submitClientRating(selected.value.id, {
        score: normalizedClientScore,
        description: clientComment.value || undefined,
      });
    }
    toast.success("Tarea completada.");
    selected.value = null;
    await loadData();
  } catch (error) {
    console.error(error);
    toast.error(extractApiMessage(error) || "No se pudo completar la tarea.");
  } finally {
    saving.value = false;
  }
};

const updateStatus = async (status: ServiceRequestStatus) => {
  if (status === "completed") {
    await completeTask();
    return;
  }

  if (saving.value || !selected.value) return;

  const selectedItem = selected.value;
  const cancellationReasonPayload =
    cancellationReason.value.trim() ||
    (selectedItem.cancellation_reason || "").trim() ||
    null;

  if (status === "cancelled" && !cancellationReasonPayload) {
    toast.error("Debes ingresar una justificacion para cancelar.");
    return;
  }

  saving.value = true;
  try {
    const response = await supportRequestsService.updateRequestStatus(selectedItem.id, status, {
      scheduled_visit_date: scheduledVisitDate.value || null,
      resolution_summary: resolutionSummary.value.trim() || null,
      cancellation_reason: status === "cancelled" ? cancellationReasonPayload : null,
      charged_amount: null,
      repaired_product_id: repairedProductId.value,
    });
    selected.value = response.data.data;
    toast.success("Estado actualizado.");
    selected.value = null;
    await loadData();
  } catch (error) {
    console.error(error);
    toast.error(extractApiMessage(error) || "No se pudo actualizar el estado.");
  } finally {
    saving.value = false;
  }
};

const saveVisitSchedule = async () => {
  if (!selected.value) return;

  if (!scheduledVisitDate.value) {
    toast.error("Selecciona una fecha de visita.");
    return;
  }

  const { start: wantedStart, end: wantedEnd } = resolveRequestedDateBounds(selected.value);
  if (isOutsideRequestedRange(scheduledVisitDate.value, wantedStart, wantedEnd)) {
    toast.error("La fecha debe estar dentro del rango solicitado por el cliente.");
    return;
  }

  saving.value = true;
  try {
    const response = await supportRequestsService.updateRequestStatus(selected.value.id, selected.value.status, {
      scheduled_visit_date: scheduledVisitDate.value,
      repaired_product_id: repairedProductId.value,
    });
    selected.value = response.data.data;
    scheduledVisitDate.value = toDateInput(response.data.data.scheduled_visit_date) || scheduledVisitDate.value;
    syncScheduledVisitRange(scheduledVisitDate.value);
    visitCalendarOpen.value = false;
    toast.success("Fecha de visita guardada.");
    await loadData();
  } catch (error) {
    console.error(error);
    toast.error(extractApiMessage(error) || "No se pudo guardar la fecha de visita.");
  } finally {
    saving.value = false;
  }
};

const completeFromPendingList = (item: ApiServiceRequest) => {
  openDetail(item);
  toast.info("Completa fecha, producto reparado y descripcion de solucion para cerrar la tarea.");
};

onMounted(async () => {
  await loadData({ reloadProducts: true });
});
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Panel tecnico</h1>
      <p class="text-muted-foreground">Toma solicitudes, actualiza su estado, selecciona el producto reparado y puntua al cliente al cerrar.</p>
    </header>

    <div class="flex gap-2 rounded-lg border bg-card p-2">
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="section === 'assigned' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="section = 'assigned'">Asignadas</button>
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="section === 'unassigned' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="section = 'unassigned'">Sin asignar</button>
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="section === 'history' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="section = 'history'">Historial</button>
    </div>

    <div class="grid gap-3 rounded-lg border bg-card p-3 md:grid-cols-[minmax(0,1fr)_220px]">
      <div class="relative">
        <Icon icon="mdi:magnify" class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
        <input
          v-model="panelSearch"
          type="text"
          placeholder="Buscar por asunto, cliente, ID o producto..."
          class="w-full rounded-md border border-input bg-background py-2 pl-9 pr-3 text-sm"
        />
      </div>
      <select v-model="panelDatePreset" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
        <option value="all">Todas las fechas</option>
        <option value="today">Hoy</option>
        <option value="last_7_days">Ultimos 7 dias</option>
        <option value="last_30_days">Ultimos 30 dias</option>
        <option value="last_3_months">Ultimos 3 meses</option>
        <option value="last_6_months">Ultimos 6 meses</option>
      </select>
    </div>

    <section class="rounded-lg border bg-card shadow-sm">
      <div v-if="loading" class="px-6 py-6 text-sm text-muted-foreground">Cargando...</div>
      <div
        v-else-if="section === 'assigned' && myActive.length === 0"
        class="px-6 py-6 text-sm text-muted-foreground"
      >
        No tienes solicitudes activas.
      </div>
      <div
        v-else-if="section === 'unassigned' && unassignedRequests.length === 0"
        class="px-6 py-6 text-sm text-muted-foreground"
      >
        No hay solicitudes disponibles.
      </div>
      <div
        v-else-if="section === 'history' && myHistory.length === 0"
        class="px-6 py-6 text-sm text-muted-foreground"
      >
        No hay historial cerrado.
      </div>
      <div v-else-if="filteredSectionItems.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
        No hay resultados para los filtros seleccionados.
      </div>
      <div v-else class="divide-y">
        <div
          v-for="item in filteredSectionItems"
          :key="item.id"
          class="flex flex-wrap items-start justify-between gap-4 px-6 py-4 text-sm"
        >
          <button class="flex-1 text-left" @click="openDetail(item)">
            <p class="font-medium">{{ fixMojibake(item.subject) }}</p>
            <p class="text-xs text-muted-foreground">
              #{{ item.id }} | {{ item.type === "claim" ? "Reclamo" : "Solicitud tecnica" }} | {{ formatDate(item.created_at) }}
            </p>
            <p class="mt-2 text-xs text-muted-foreground">{{ fixMojibake(item.description) }}</p>
            <p v-if="item.repaired_product" class="mt-1 text-xs text-muted-foreground">
              Producto reparado: {{ fixMojibake(item.repaired_product.name) || `#${item.repaired_product.id}` }}
            </p>
          </button>
          <div class="flex flex-col items-end gap-2">
            <span class="text-xs font-semibold">{{ statusLabel[getDisplayStatus(item)] }}</span>
            <button
              v-if="section === 'assigned' && (item.status === 'pending' || item.status === 'assigned')"
              class="rounded-md bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground"
              @click="completeFromPendingList(item)"
            >
              Completar tarea
            </button>
            <button
              class="rounded-md border px-3 py-1 text-xs font-semibold"
              @click="openDetail(item)"
            >
              Ver detalle
            </button>
          </div>
        </div>
      </div>
    </section>

    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-4xl rounded-lg border bg-card shadow-lg">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h2 class="text-lg font-semibold">Solicitud #{{ selected.id }}</h2>
          <button @click="selected = null"><Icon icon="mdi:close" class="h-5 w-5" /></button>
        </div>
        <div class="grid max-h-[75vh] gap-5 overflow-y-auto px-6 py-4 text-sm md:grid-cols-[1.1fr_0.9fr]">
          <div class="relative z-20 space-y-3 pointer-events-auto">
            <p><span class="font-semibold">Asunto:</span> {{ fixMojibake(selected.subject) }}</p>
            <p><span class="font-semibold">Estado:</span> {{ statusLabel[getDisplayStatus(selected)] }}</p>
            <p><span class="font-semibold">Cliente:</span> {{ displayText(selected.requesting_user?.name, "N/D") }}</p>
            <p><span class="font-semibold">Telefono:</span> {{ displayText(selected.requesting_user?.phone, "No informado") }}</p>
            <p>
              <span class="font-semibold">Direccion:</span>
              {{ displayText(selected.requesting_user?.address, "No informada") }}
              <template v-if="selected.requesting_user?.city">, {{ fixMojibake(selected.requesting_user?.city) }}</template>
            </p>
            <p>
              <span class="font-semibold">Fechas solicitadas:</span>
              {{ formatDate(selected.wanted_date_start) }} a {{ formatDate(selected.wanted_date_end) }}
            </p>
            <p><span class="font-semibold">Turno solicitado:</span> {{ shiftLabel(selected.time_shift) }}</p>
            <p v-if="selected.scheduled_visit_date">
              <span class="font-semibold">Visita programada:</span> {{ formatDate(selected.scheduled_visit_date) }}
            </p>
            <p v-if="selected.completed_at">
              <span class="font-semibold">Fecha de cierre:</span> {{ formatDate(selected.completed_at) }}
            </p>
            <p v-if="selected.charged_amount !== null && selected.charged_amount !== undefined">
              <span class="font-semibold">Monto cobrado:</span> {{ formatCurrency(selected.charged_amount) }}
            </p>
            <div>
              <p class="mb-1 font-semibold">Descripcion completa:</p>
              <p class="whitespace-pre-wrap rounded-md border bg-muted/20 p-3 leading-relaxed">
                {{ fixMojibake(selected.description) }}
              </p>
            </div>
            <div v-if="selected.resolution_summary">
              <p class="mb-1 font-semibold">Solucion aplicada:</p>
              <p class="whitespace-pre-wrap rounded-md border bg-muted/20 p-3 leading-relaxed">
                {{ fixMojibake(selected.resolution_summary) }}
              </p>
            </div>
            <div v-if="selected.cancellation_reason">
              <p class="mb-1 font-semibold">Motivo de cancelacion:</p>
              <p class="whitespace-pre-wrap rounded-md border bg-muted/20 p-3 leading-relaxed">
                {{ fixMojibake(selected.cancellation_reason) }}
              </p>
            </div>
            <p v-if="selected.repaired_product">
              <span class="font-semibold">Producto reparado:</span>
              {{ fixMojibake(selected.repaired_product.name) || `Producto #${selected.repaired_product.id}` }}
            </p>

            <div v-if="selected.technician_id === null && section === 'unassigned'" class="space-y-2">
              <button type="button" class="w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground" :disabled="saving" @click.stop.prevent="assignSelf">
                {{ saving ? "Asignando..." : "Asignarme solicitud" }}
              </button>
            </div>

            <div v-else-if="selected.status !== 'completed' && selected.status !== 'cancelled'" class="space-y-2">
              <label class="text-xs font-semibold uppercase text-muted-foreground">Fecha de visita elegida por tecnico</label>
              <Popover v-model:open="visitCalendarOpen">
                <PopoverTrigger as-child>
                  <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-left text-sm transition hover:border-primary/50"
                    :disabled="saving || (!selectedWantedStart && !selectedWantedEnd)"
                  >
                    <span class="inline-flex items-center gap-2 truncate">
                      <Icon icon="mdi:calendar-month-outline" class="h-4 w-4 text-primary" />
                      <span class="truncate">{{ scheduledVisitDateLabel }}</span>
                    </span>
                    <Icon icon="mdi:chevron-down" class="h-4 w-4 text-muted-foreground" />
                  </button>
                </PopoverTrigger>
                <PopoverContent align="start" class="w-auto border border-primary/35 p-0">
                  <div class="border-b border-primary/20 bg-primary/10 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-primary">Solo fechas habilitadas</p>
                    <p class="text-xs text-muted-foreground">
                      Rango del cliente: {{ formatDate(selected.wanted_date_start) }} a {{ formatDate(selected.wanted_date_end) }}
                    </p>
                  </div>
                  <RangeCalendar
                    class="visit-calendar-strong"
                    :model-value="scheduledVisitRange"
                    :min-value="selectedWantedStartValue"
                    :max-value="selectedWantedEndValue"
                    initial-focus
                    @update:model-value="onScheduledVisitRangeChange"
                  />
                </PopoverContent>
              </Popover>
              <div class="flex items-center justify-between">
                <p class="text-[11px] text-muted-foreground">Habilitadas: color intenso. Bloqueadas: atenuadas y tachadas.</p>
                <button type="button" class="rounded-md border px-2 py-1 text-xs" :disabled="saving || !scheduledVisitDate" @click.stop.prevent="clearScheduledVisitDate">
                  Limpiar
                </button>
              </div>
              <button type="button" class="w-full rounded-md border px-4 py-2 text-sm" :disabled="saving" @click.stop.prevent="saveVisitSchedule">
                Guardar fecha de visita
              </button>
              <textarea
                v-model="resolutionSummary"
                rows="3"
                placeholder="Descripcion de la solucion aplicada (obligatoria para completar)"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              />
              <div class="space-y-2">
                <label class="text-xs font-semibold uppercase text-muted-foreground">Producto reparado (obligatorio al completar)</label>
                <input
                  v-model="productSearch"
                  type="text"
                  placeholder="Buscar por nombre o SKU..."
                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <select
                  v-model="repairedProductId"
                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option :value="null">Seleccionar producto reparado</option>
                  <option v-for="item in filteredProducts" :key="item.id" :value="Number(item.id)">
                    {{ fixMojibake(item.name) }} {{ item.model_sku ? `(${fixMojibake(item.model_sku)})` : '' }}
                  </option>
                </select>
              </div>
              <textarea v-model="clientComment" rows="2" placeholder="Comentario sobre cliente (opcional al cerrar)" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
              <div class="space-y-1">
                <p class="text-xs text-muted-foreground">Puntaje cliente</p>
                <StarRatingInput v-model="clientScore" :disabled="saving" />
      </div>
              <input
                v-model="chargedAmount"
                type="number"
                min="0"
                step="0.01"
                placeholder="Monto cobrado (obligatorio al completar)"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              />
              <button
                type="button"
                class="relative z-20 w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                @pointerdown.stop.prevent="completeTask"
                @click.stop.prevent="completeTask"
              >
                {{ saving ? "Guardando..." : "Completar tarea" }}
              </button>
              <textarea
                v-model="cancellationReason"
                rows="2"
                placeholder="Justificacion de cancelacion (obligatoria para cancelar)"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              />
              <button type="button" class="w-full rounded-md border px-4 py-2 text-sm" :disabled="saving" @click.stop.prevent="updateStatus('cancelled')">
                Marcar como cancelada
              </button>
            </div>
          </div>

          <div class="relative z-0 space-y-3 pointer-events-none">
            <p class="font-semibold">Ubicacion del cliente (Google Maps)</p>
            <div class="h-72 overflow-hidden rounded-md border bg-muted/20">
              <iframe
                v-if="selectedMapEmbedUrl"
                :src="selectedMapEmbedUrl"
                class="h-full w-full pointer-events-none"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
              <div v-else class="flex h-full items-center justify-center px-4 text-center text-xs text-muted-foreground">
                El cliente no tiene direccion cargada para mostrar en el mapa.
              </div>
            </div>
            <a
              v-if="selectedMapLink"
              :href="selectedMapLink"
              target="_blank"
              rel="noreferrer"
              class="pointer-events-auto inline-flex items-center gap-2 text-xs font-semibold text-primary hover:underline"
            >
              <Icon icon="mdi:map-marker-radius-outline" class="h-4 w-4" />
              Abrir en Google Maps
            </a>
          </div>
        </div>
              </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"]) {
  border: 1px solid transparent;
  transition: all 120ms ease;
}

:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"]:not([data-disabled]):not([data-unavailable]):not([data-outside-view])) {
  font-weight: 700;
  color: hsl(var(--foreground));
  background-color: hsl(var(--primary) / 0.14);
  border-color: hsl(var(--primary) / 0.38);
}

:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"][data-disabled]),
:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"][data-outside-view]) {
  opacity: 0.18 !important;
  filter: grayscale(1);
  text-decoration: line-through;
}

:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"][data-selected]),
:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"][data-selection-start]),
:deep(.visit-calendar-strong [data-slot="range-calendar-trigger"][data-selection-end]) {
  opacity: 1 !important;
  border-color: hsl(var(--primary));
  box-shadow: 0 0 0 1px hsl(var(--primary));
}
</style>
