<script setup lang="ts">
import { DateFormatter, getLocalTimeZone, parseDate } from "@internationalized/date";
import { computed, onMounted, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { toast } from "vue-sonner";
import { useRoute, useRouter } from "vue-router";
import StarRatingInput from "@/components/ui/StarRatingInput.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RangeCalendar } from "@/components/ui/range-calendar";
import { notifyTechnicianItineraryUpdated } from "@/composables/useTechnicianDailyItinerary";
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
type VisitScheduleFilter = "all" | "scheduled" | "unscheduled";
const panelDatePreset = ref<DatePreset>("all");
const assignedVisitFilter = ref<VisitScheduleFilter>("all");
const selected = ref<ApiServiceRequest | null>(null);
const saving = ref(false);
const scheduledVisitDate = ref("");
const scheduledVisitTime = ref("");
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
const route = useRoute();
const router = useRouter();

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
    fixMojibake(item.requesting_user?.address || ""),
    fixMojibake(item.requesting_user?.city || ""),
    fixMojibake(item.repaired_product?.name || ""),
    fixMojibake(item.repaired_product?.model_sku || ""),
    item.type === "claim" ? "reclamo" : "solicitud tecnica",
    String(item.id),
    normalizeTimeInput(item.scheduled_visit_time),
    toDateInput(item.scheduled_visit_date),
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
  const filtered = sectionItems.value.filter((item) => {
    if (!includesSearchText(item, query) || !withinDatePreset(item, range)) {
      return false;
    }

    if (section.value !== "assigned" || assignedVisitFilter.value === "all") {
      return true;
    }

    const hasVisitDate = Boolean(toDateInput(item.scheduled_visit_date));
    return assignedVisitFilter.value === "scheduled" ? hasVisitDate : !hasVisitDate;
  });

  if (section.value !== "assigned") {
    return filtered;
  }

  return filtered.slice().sort((left, right) => compareAssignedItems(left, right));
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

  const normalized = toDateInput(value);
  if (normalized) {
    const parts = normalized.split("-");
    const year = Number(parts[0] ?? "");
    const month = Number(parts[1] ?? "");
    const day = Number(parts[2] ?? "");
    if (Number.isFinite(year) && Number.isFinite(month) && Number.isFinite(day)) {
      const localDate = new Date(year, month - 1, day);
      return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(localDate);
    }
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(parsed);
};

const shiftLabel = (value?: string | null) => {
  if (!value) return "Sin turno";
  const normalized = fixMojibake(value).trim().toLowerCase();
  if (normalized === "morning" || normalized === "ma\u00f1ana" || normalized === "manana") return "Ma\u00f1ana";
  if (normalized === "afternoon" || normalized === "tarde") return "Tarde";
  return fixMojibake(value);
};

const normalizeShift = (value?: string | null): "morning" | "afternoon" | null => {
  const normalized = fixMojibake(value).trim().toLowerCase();
  if (normalized === "morning" || normalized === "ma\u00f1ana" || normalized === "manana") return "morning";
  if (normalized === "afternoon" || normalized === "tarde") return "afternoon";
  return null;
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

const normalizeTimeInput = (value?: string | null) => {
  if (!value) return "";
  const raw = fixMojibake(String(value)).trim();
  const match = raw.match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
  if (match?.[1] && match[2]) {
    return `${match[1]}:${match[2]}`;
  }

  const parsed = new Date(`1970-01-01T${raw}`);
  if (Number.isNaN(parsed.getTime())) return "";
  return `${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
};

const formatTime = (value?: string | null) => {
  const normalized = normalizeTimeInput(value);
  if (!normalized) return "Sin horario";
  return `${normalized} hs`;
};

const isTimeAllowedForShift = (timeValue?: string | null, shiftValue?: string | null) => {
  const normalizedTime = normalizeTimeInput(timeValue);
  if (!normalizedTime) return true;

  const shift = normalizeShift(shiftValue);
  if (shift === "morning") return normalizedTime < "12:00";
  if (shift === "afternoon") return normalizedTime >= "12:00";
  return true;
};

const getShiftTimeRules = (shiftValue?: string | null) => {
  const shift = normalizeShift(shiftValue);
  if (shift === "morning") {
    return {
      min: "00:00",
      max: "11:59",
      hint: "Turno manana: solo horarios AM.",
      errorMessage: "El turno solicitado es manana. Solo puedes indicar horarios AM.",
    };
  }

  if (shift === "afternoon") {
    return {
      min: "12:00",
      max: "23:59",
      hint: "Turno tarde: solo horarios PM.",
      errorMessage: "El turno solicitado es tarde. Solo puedes indicar horarios PM.",
    };
  }

  return {
    min: undefined,
    max: undefined,
    hint: "",
    errorMessage: "La hora estimada no es valida para el turno solicitado.",
  };
};

const getClientAddressLabel = (item: ApiServiceRequest) => {
  const address = displayText(item.requesting_user?.address, "Direccion no informada");
  const city = fixMojibake(item.requesting_user?.city || "").trim();
  return city ? `${address}, ${city}` : address;
};

const hasScheduledVisit = (item: ApiServiceRequest) => Boolean(toDateInput(item.scheduled_visit_date));

const formatVisitSchedule = (item: ApiServiceRequest) => {
  if (!hasScheduledVisit(item)) return "Sin fecha de visita";
  const dateLabel = formatDate(item.scheduled_visit_date);
  const timeLabel = normalizeTimeInput(item.scheduled_visit_time);
  return timeLabel ? `${dateLabel} - ${formatTime(timeLabel)}` : dateLabel;
};

const formatRequestedRange = (item: ApiServiceRequest) =>
  `Rango solicitado: ${formatDate(item.wanted_date_start)} a ${formatDate(item.wanted_date_end)}`;

const getAssignedSortValue = (item: ApiServiceRequest) => {
  const visitDate = toDateInput(item.scheduled_visit_date);
  if (!visitDate) return Number.POSITIVE_INFINITY;

  const visitTime = normalizeTimeInput(item.scheduled_visit_time) || "23:59";
  const timestamp = new Date(`${visitDate}T${visitTime}:00`).getTime();
  return Number.isNaN(timestamp) ? Number.POSITIVE_INFINITY : timestamp;
};

const compareAssignedItems = (left: ApiServiceRequest, right: ApiServiceRequest) => {
  const leftVisit = getAssignedSortValue(left);
  const rightVisit = getAssignedSortValue(right);

  if (leftVisit !== rightVisit) {
    return leftVisit - rightVisit;
  }

  const leftWanted = new Date(left.wanted_date_start).getTime();
  const rightWanted = new Date(right.wanted_date_start).getTime();
  if (leftWanted !== rightWanted) {
    return leftWanted - rightWanted;
  }

  return right.id - left.id;
};

const getAssignedCardStatus = (item: ApiServiceRequest) => {
  if (section.value === "assigned" && getDisplayStatus(item) === "assigned") {
    return hasScheduledVisit(item)
      ? { label: "Con fecha de visita", className: "text-emerald-700" }
      : { label: "Sin fecha de visita", className: "text-amber-700" };
  }

  return {
    label: statusLabel[getDisplayStatus(item)],
    className: "text-foreground",
  };
};

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

const clearScheduledVisitSchedule = () => {
  if (isVisitDateLocked.value) {
    scheduledVisitTime.value = "";
    return;
  }
  scheduledVisitDate.value = "";
  scheduledVisitTime.value = "";
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
const selectedShiftTimeRules = computed(() => getShiftTimeRules(selected.value?.time_shift));
const scheduledVisitDateLabel = computed(() => {
  const parsed = toDateValue(scheduledVisitDate.value);
  if (!parsed) return "Seleccionar fecha de visita";
  return dateFormatter.format(parsed.toDate(localTimeZone));
});
const persistedScheduledVisitDate = computed(() => toDateInput(selected.value?.scheduled_visit_date));
const isVisitDateLocked = computed(() => Boolean(persistedScheduledVisitDate.value));
const canEditVisitTime = computed(() => isVisitDateLocked.value);
const saveVisitScheduleButtonLabel = computed(() =>
  isVisitDateLocked.value ? "Guardar hora de visita" : "Guardar fecha de visita"
);

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

  clearScheduledVisitSchedule();
  if (showError) {
    toast.error("La fecha elegida debe estar dentro del rango solicitado por el cliente.");
  }
};

const onScheduledVisitRangeChange = (value: any) => {
  if (isVisitDateLocked.value) {
    syncScheduledVisitRange(scheduledVisitDate.value);
    return;
  }

  scheduledVisitRange.value = value;
  const pickedDate = value.end || value.start;
  if (!pickedDate) {
    clearScheduledVisitSchedule();
    return;
  }

  scheduledVisitDate.value = toDateInput(pickedDate.toString());
  enforceScheduledVisitDateInRange(true);
  syncScheduledVisitRange(scheduledVisitDate.value);
  if (scheduledVisitDate.value) {
    visitCalendarOpen.value = false;
  }
};

const validateScheduledVisitTimeForSelectedShift = (showError = false) => {
  if (isTimeAllowedForShift(scheduledVisitTime.value, selected.value?.time_shift)) {
    return true;
  }

  if (showError) {
    toast.error(selectedShiftTimeRules.value.errorMessage);
  }

  return false;
};

const onScheduledVisitTimeChange = () => {
  if (!canEditVisitTime.value) {
    scheduledVisitTime.value = "";
    toast.info("Guarda primero la fecha de visita para habilitar la hora.");
    return;
  }

  if (!scheduledVisitTime.value) return;
  if (validateScheduledVisitTimeForSelectedShift(false)) return;

  scheduledVisitTime.value = "";
  toast.error(selectedShiftTimeRules.value.errorMessage);
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
  scheduledVisitTime.value = normalizeTimeInput(item.scheduled_visit_time);
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

const normalizeVisitQueryParam = (rawValue: unknown): number | null => {
  const value = Array.isArray(rawValue) ? rawValue[0] : rawValue;
  const numeric = Number(value);
  if (!Number.isInteger(numeric) || numeric <= 0) return null;
  return numeric;
};

const clearVisitQueryParam = async () => {
  if (!Object.prototype.hasOwnProperty.call(route.query, "visit")) return;
  const nextQuery = { ...route.query };
  delete nextQuery.visit;
  await router.replace({ query: nextQuery });
};

const openVisitFromQueryParam = () => {
  const visitId = normalizeVisitQueryParam(route.query.visit);
  if (visitId === null) return;

  const item = myRequests.value.find((entry) => entry.id === visitId);
  if (!item) {
    toast.info(`La visita #${visitId} no esta disponible para este tecnico.`);
    void clearVisitQueryParam();
    return;
  }

  panelSearch.value = "";
  panelDatePreset.value = "all";
  assignedVisitFilter.value = "all";

  const status = getDisplayStatus(item);
  section.value = status === "completed" || status === "cancelled" ? "history" : "assigned";
  openDetail(item);
};

const closeDetail = () => {
  selected.value = null;
  visitCalendarOpen.value = false;
  void clearVisitQueryParam();
};

const assignSelf = async () => {
  if (!selected.value) return;
  saving.value = true;
  try {
    await supportRequestsService.assignToMyself(selected.value.id);
    toast.success("Solicitud tomada correctamente.");
    notifyTechnicianItineraryUpdated();
    closeDetail();
    void loadData();
  } catch (error) {
    console.error(error);
    const apiMessage = extractApiMessage(error).toLowerCase();
    const alreadyAssigned =
      apiMessage.includes("ya tiene technician asignado") ||
      apiMessage.includes("solicitud ya tiene technician asignado");

    if (alreadyAssigned) {
      toast.info("La solicitud ya estaba asignada. Se actualizo la vista.");
      closeDetail();
      void loadData();
      return;
    }

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
  const completionScheduledTime =
    scheduledVisitTime.value ||
    normalizeTimeInput(selectedItem.scheduled_visit_time) ||
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

  if (!completionScheduledTime) {
    toast.error("Debes definir la hora estimada de visita antes de completar.");
    return;
  }

  if (!isTimeAllowedForShift(completionScheduledTime, selectedItem.time_shift)) {
    toast.error(getShiftTimeRules(selectedItem.time_shift).errorMessage);
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
      scheduled_visit_time: completionScheduledTime,
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
    notifyTechnicianItineraryUpdated();
    closeDetail();
    void loadData();
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

  if (scheduledVisitTime.value && !isTimeAllowedForShift(scheduledVisitTime.value, selectedItem.time_shift)) {
    toast.error(getShiftTimeRules(selectedItem.time_shift).errorMessage);
    return;
  }

  saving.value = true;
  try {
    const response = await supportRequestsService.updateRequestStatus(selectedItem.id, status, {
      scheduled_visit_date: scheduledVisitDate.value || null,
      scheduled_visit_time: scheduledVisitTime.value || null,
      resolution_summary: resolutionSummary.value.trim() || null,
      cancellation_reason: status === "cancelled" ? cancellationReasonPayload : null,
      charged_amount: null,
      repaired_product_id: repairedProductId.value,
    });
    selected.value = response.data.data;
    toast.success("Estado actualizado.");
    notifyTechnicianItineraryUpdated();
    closeDetail();
    void loadData();
  } catch (error) {
    console.error(error);
    toast.error(extractApiMessage(error) || "No se pudo actualizar el estado.");
  } finally {
    saving.value = false;
  }
};

const saveVisitSchedule = async () => {
  if (!selected.value) return;

  const persistedDate = persistedScheduledVisitDate.value;
  const hasPersistedDate = Boolean(persistedDate);

  if (!hasPersistedDate) {
    if (!scheduledVisitDate.value) {
      toast.error("Primero debes guardar la fecha de visita.");
      return;
    }

    if (scheduledVisitTime.value) {
      toast.error("Primero guarda la fecha. La hora se habilita despues.");
      scheduledVisitTime.value = "";
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
        scheduled_visit_time: null,
        repaired_product_id: repairedProductId.value,
      });
      selected.value = response.data.data;
      scheduledVisitDate.value = toDateInput(response.data.data.scheduled_visit_date);
      scheduledVisitTime.value = normalizeTimeInput(response.data.data.scheduled_visit_time);
      syncScheduledVisitRange(scheduledVisitDate.value);
      visitCalendarOpen.value = false;
      toast.success("Fecha de visita guardada. Ahora puedes cargar la hora.");
      notifyTechnicianItineraryUpdated();
      void loadData();
    } catch (error) {
      console.error(error);
      toast.error(extractApiMessage(error) || "No se pudo guardar la fecha de visita.");
    } finally {
      saving.value = false;
    }

    return;
  }

  if (scheduledVisitDate.value && scheduledVisitDate.value !== persistedDate) {
    scheduledVisitDate.value = persistedDate;
    syncScheduledVisitRange(scheduledVisitDate.value);
    toast.error("La fecha ya fue guardada y no se puede modificar.");
    return;
  }

  if (!scheduledVisitTime.value) {
    toast.error("Debes indicar la hora estimada de visita.");
    return;
  }

  if (!validateScheduledVisitTimeForSelectedShift(true)) {
    return;
  }

  saving.value = true;
  try {
    const response = await supportRequestsService.updateRequestStatus(selected.value.id, selected.value.status, {
      scheduled_visit_date: persistedDate,
      scheduled_visit_time: scheduledVisitTime.value,
      repaired_product_id: repairedProductId.value,
    });
    selected.value = response.data.data;
    scheduledVisitDate.value = toDateInput(response.data.data.scheduled_visit_date);
    scheduledVisitTime.value = normalizeTimeInput(response.data.data.scheduled_visit_time);
    syncScheduledVisitRange(scheduledVisitDate.value);
    visitCalendarOpen.value = false;
    toast.success("Hora estimada de visita guardada.");
    notifyTechnicianItineraryUpdated();
    void loadData();
  } catch (error) {
    console.error(error);
    toast.error(extractApiMessage(error) || "No se pudo guardar la hora de visita.");
  } finally {
    saving.value = false;
  }
};

const completeFromPendingList = (item: ApiServiceRequest) => {
  openDetail(item);
  toast.info("Completa fecha, hora, producto reparado y descripcion de solucion para cerrar la tarea.");
};

onMounted(async () => {
  await loadData({ reloadProducts: true });
  openVisitFromQueryParam();
});

watch(
  () => route.query.visit,
  () => {
    if (loading.value) return;
    openVisitFromQueryParam();
  }
);
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Panel técnico</h1>
      <p class="text-muted-foreground">Toma solicitudes, actualiza su estado, selecciona el producto reparado y puntua al cliente al cerrar.</p>
    </header>

    <div class="flex gap-2 rounded-lg border bg-card p-2">
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="section === 'assigned' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="section = 'assigned'">Asignadas</button>
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="section === 'unassigned' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="section = 'unassigned'">Sin asignar</button>
      <button class="rounded-md px-3 py-2 text-xs font-semibold" :class="section === 'history' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="section = 'history'">Historial</button>
    </div>

    <div
      class="grid gap-3 rounded-lg border bg-card p-3"
      :class="section === 'assigned' ? 'md:grid-cols-[minmax(0,1fr)_220px_220px]' : 'md:grid-cols-[minmax(0,1fr)_220px]'"
    >
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
      <select
        v-if="section === 'assigned'"
        v-model="assignedVisitFilter"
        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
      >
        <option value="all">Todas las asignadas</option>
        <option value="scheduled">Con fecha de visita</option>
        <option value="unscheduled">Sin fecha de visita</option>
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
              #{{ item.id }} | {{ getClientAddressLabel(item) }}
              <template v-if="section === 'assigned'"> | {{ formatVisitSchedule(item) }}</template>
              <template v-else-if="section === 'unassigned'"> | {{ item.type === "claim" ? "Reclamo" : "Solicitud tecnica" }} | {{ formatRequestedRange(item) }}</template>
              <template v-else> | {{ item.type === "claim" ? "Reclamo" : "Solicitud tecnica" }} | {{ formatDate(item.created_at) }}</template>
            </p>
            <p class="mt-2 text-xs text-muted-foreground">{{ fixMojibake(item.description) }}</p>
            <p v-if="item.repaired_product" class="mt-1 text-xs text-muted-foreground">
              Producto reparado: {{ fixMojibake(item.repaired_product.name) || `#${item.repaired_product.id}` }}
            </p>
          </button>
          <div class="flex flex-col items-end gap-2">
            <span :class="['text-xs font-semibold', getAssignedCardStatus(item).className]">{{ getAssignedCardStatus(item).label }}</span>
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
          <button @click="closeDetail"><Icon icon="mdi:close" class="h-5 w-5" /></button>
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
            <p v-if="selected.scheduled_visit_time">
              <span class="font-semibold">Hora estimada de visita:</span> {{ formatTime(selected.scheduled_visit_time) }}
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
                    :disabled="saving || isVisitDateLocked || (!selectedWantedStart && !selectedWantedEnd)"
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
              <div class="space-y-1">
                <label class="text-xs font-semibold uppercase text-muted-foreground">Hora estimada de visita</label>
                <input
                  v-model="scheduledVisitTime"
                  type="time"
                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  :min="selectedShiftTimeRules.min"
                  :max="selectedShiftTimeRules.max"
                  step="60"
                  :disabled="saving || !canEditVisitTime"
                  @change="onScheduledVisitTimeChange"
                />
                <p v-if="!canEditVisitTime" class="text-[11px] text-muted-foreground">
                  Guarda primero la fecha de visita para habilitar la hora.
                </p>
                <p v-if="selectedShiftTimeRules.hint" class="text-[11px] text-muted-foreground">
                  {{ selectedShiftTimeRules.hint }}
                </p>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[11px] text-muted-foreground">Habilitadas: color intenso. Bloqueadas: atenuadas y tachadas.</p>
                <button
                  type="button"
                  class="rounded-md border px-2 py-1 text-xs"
                  :disabled="saving || (isVisitDateLocked ? !scheduledVisitTime : !scheduledVisitDate)"
                  @click.stop.prevent="clearScheduledVisitSchedule"
                >
                  {{ isVisitDateLocked ? "Limpiar hora" : "Limpiar fecha" }}
                </button>
              </div>
              <button type="button" class="w-full rounded-md border px-4 py-2 text-sm" :disabled="saving" @click.stop.prevent="saveVisitSchedule">
                {{ saveVisitScheduleButtonLabel }}
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
