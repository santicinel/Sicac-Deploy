import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import supportRequestsService, {
  type ApiServiceRequest,
} from "@/services/supportRequestsService";

interface UseTechnicianDailyItineraryOptions {
  autoRefresh?: boolean;
  refreshIntervalMs?: number;
}

export const TECHNICIAN_ITINERARY_UPDATED_EVENT = "sicac:technician-itinerary-updated";

export const notifyTechnicianItineraryUpdated = () => {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new Event(TECHNICIAN_ITINERARY_UPDATED_EVENT));
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
  let text = String(value ?? "");
  for (let i = 0; i < 2; i += 1) {
    const decoded = decodeLatin1ToUtf8(text);
    if (decoded === text) break;
    text = decoded;
  }
  return text;
};

const displayText = (value?: string | null, fallback = "N/D") => {
  const normalized = fixMojibake(value).trim();
  return normalized || fallback;
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

  const parsed = new Date(raw);
  if (Number.isNaN(parsed.getTime())) return "";
  return toLocalDateInput(parsed);
};

const normalizeTimeInput = (value?: string | null) => {
  if (!value) return "";
  const raw = fixMojibake(String(value)).trim();
  const hhmm = raw.match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
  if (hhmm?.[1] && hhmm[2]) {
    return `${hhmm[1]}:${hhmm[2]}`;
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

const isOpenTask = (item: ApiServiceRequest) =>
  item.status === "pending" || item.status === "assigned";

export const useTechnicianDailyItinerary = (
  options: UseTechnicianDailyItineraryOptions = {}
) => {
  const {
    autoRefresh = true,
    refreshIntervalMs = 120000,
  } = options;

  const loading = ref(false);
  const loadError = ref(false);
  const hasLoadedOnce = ref(false);
  const requests = ref<ApiServiceRequest[]>([]);
  const todayInput = computed(() => toLocalDateInput(new Date()));

  let refreshTimer: ReturnType<typeof setInterval> | null = null;
  const handleRealtimeItineraryUpdate = () => {
    void loadTodayTasks();
  };

  const todaysTasks = computed(() =>
    requests.value
      .filter(
        (item) =>
          isOpenTask(item) &&
          toDateInput(item.scheduled_visit_date) === todayInput.value
      )
      .slice()
      .sort((left, right) => {
        const leftTime = normalizeTimeInput(left.scheduled_visit_time) || "99:99";
        const rightTime = normalizeTimeInput(right.scheduled_visit_time) || "99:99";
        if (leftTime !== rightTime) return leftTime.localeCompare(rightTime);
        return left.id - right.id;
      })
  );

  const summary = computed(() => {
    const total = todaysTasks.value.length;
    const withTime = todaysTasks.value.filter((item) =>
      Boolean(normalizeTimeInput(item.scheduled_visit_time))
    ).length;
    const withoutTime = total - withTime;

    return { total, withTime, withoutTime };
  });

  const loadTodayTasks = async () => {
    loading.value = true;
    loadError.value = false;

    try {
      requests.value = await supportRequestsService.getMyTechnicianRequests();
    } catch (error) {
      console.error(error);
      loadError.value = true;
    } finally {
      loading.value = false;
      hasLoadedOnce.value = true;
    }
  };

  onMounted(() => {
    void loadTodayTasks();
    if (typeof window !== "undefined") {
      window.addEventListener(
        TECHNICIAN_ITINERARY_UPDATED_EVENT,
        handleRealtimeItineraryUpdate
      );
    }

    if (!autoRefresh) return;
    refreshTimer = setInterval(() => {
      void loadTodayTasks();
    }, refreshIntervalMs);
  });

  onBeforeUnmount(() => {
    if (typeof window !== "undefined") {
      window.removeEventListener(
        TECHNICIAN_ITINERARY_UPDATED_EVENT,
        handleRealtimeItineraryUpdate
      );
    }

    if (refreshTimer) {
      clearInterval(refreshTimer);
      refreshTimer = null;
    }
  });

  return {
    loading,
    loadError,
    hasLoadedOnce,
    requests,
    todaysTasks,
    summary,
    displayText,
    formatTime,
    loadTodayTasks,
  };
};
