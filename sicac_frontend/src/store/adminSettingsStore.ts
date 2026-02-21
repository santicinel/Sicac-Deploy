import { defineStore } from "pinia";
import { ref } from "vue";
import { useAuthStore } from "@/store/authStore";
import adminSettingsService from "@/services/adminSettingsService";

const STORAGE_KEY = "sicac_admin_settings";
const DEFAULT_LABOR_RATE = 1500;

interface AdminSettings {
  laborRate: number;
}

const normalizeLaborRate = (value: unknown): number => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 0) return DEFAULT_LABOR_RATE;
  return Number(numeric.toFixed(2));
};

const loadSettings = (): AdminSettings => {
  if (typeof window === "undefined") return { laborRate: DEFAULT_LABOR_RATE };
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return { laborRate: DEFAULT_LABOR_RATE };
    const parsed = JSON.parse(raw) as Partial<AdminSettings>;
    return {
      laborRate: normalizeLaborRate(parsed.laborRate),
    };
  } catch {
    return { laborRate: DEFAULT_LABOR_RATE };
  }
};

const saveSettings = (settings: AdminSettings) => {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
};

export const useAdminSettingsStore = defineStore("adminSettings", () => {
  const laborRate = ref<number>(loadSettings().laborRate);
  const laborRateLoading = ref(false);
  const laborRateLoaded = ref(false);

  const applyLaborRate = (value: unknown): number => {
    const normalized = normalizeLaborRate(value);
    laborRate.value = normalized;
    saveSettings({ laborRate: normalized });
    return normalized;
  };

  const syncLaborRate = async (force = false): Promise<number> => {
    const authStore = useAuthStore();
    if (!authStore.isAuthenticated) {
      laborRateLoaded.value = true;
      return laborRate.value;
    }

    if (laborRateLoading.value) return laborRate.value;
    if (laborRateLoaded.value && !force) return laborRate.value;

    laborRateLoading.value = true;
    try {
      const serverRate = await adminSettingsService.getLaborRate();
      laborRateLoaded.value = true;
      return applyLaborRate(serverRate);
    } catch (error) {
      console.error(error);
      laborRateLoaded.value = true;
      return laborRate.value;
    } finally {
      laborRateLoading.value = false;
    }
  };

  const setLaborRate = async (value: number): Promise<number> => {
    const normalized = normalizeLaborRate(value);
    const previous = laborRate.value;
    applyLaborRate(normalized);

    try {
      const persistedRate = await adminSettingsService.updateLaborRate(normalized);
      laborRateLoaded.value = true;
      return applyLaborRate(persistedRate);
    } catch (error) {
      applyLaborRate(previous);
      throw error;
    }
  };

  if (typeof window !== "undefined") {
    window.addEventListener("storage", (event) => {
      if (event.key !== STORAGE_KEY) return;
      laborRate.value = loadSettings().laborRate;
    });
  }

  return {
    laborRate,
    laborRateLoading,
    laborRateLoaded,
    syncLaborRate,
    setLaborRate,
  };
});
