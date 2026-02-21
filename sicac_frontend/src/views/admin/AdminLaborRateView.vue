<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useAdminSettingsStore } from "@/store/adminSettingsStore";

const settingsStore = useAdminSettingsStore();
const laborRateInput = ref(Number(settingsStore.laborRate));
const hoursExample = ref(1);
const saveFeedback = ref("");
const saveError = ref("");

const previewTotal = computed(() => {
  return Number(laborRateInput.value || 0) * Number(hoursExample.value || 0);
});

watch(
  () => settingsStore.laborRate,
  (value) => {
    laborRateInput.value = value;
  }
);

const saveRate = async () => {
  const normalized = Number(laborRateInput.value) || 0;
  saveFeedback.value = "";
  saveError.value = "";

  try {
    const persistedRate = await settingsStore.setLaborRate(normalized);
    laborRateInput.value = Number(persistedRate);
    saveFeedback.value = `Tarifa guardada: ARS ${persistedRate}/hora`;
  } catch {
    laborRateInput.value = Number(settingsStore.laborRate);
    saveError.value = "No se pudo guardar la tarifa. Revisa la conexion e intenta de nuevo.";
  }
};

onMounted(async () => {
  await settingsStore.syncLaborRate(true);
  laborRateInput.value = Number(settingsStore.laborRate);
});
</script>

<template>
  <div class="p-6 space-y-6">
    <header class="flex flex-col gap-2">
      <h1 class="text-3xl font-bold tracking-tight">Mano de obra</h1>
      <p class="text-muted-foreground">
        Configura la tarifa por hora que se aplicara a la estimacion de tiempo hecha por IA.
      </p>
    </header>

    <div class="max-w-xl rounded-lg border bg-card text-card-foreground shadow-sm p-6 space-y-4">
      <div>
        <label class="text-sm font-medium leading-none">Tarifa por hora (ARS)</label>
        <input
          v-model.number="laborRateInput"
          type="number"
          min="0"
          class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div class="rounded-md border border-dashed p-4 text-sm text-muted-foreground space-y-2">
        <p class="font-semibold text-foreground">Desglose de calculo</p>
        <p>
          Mano de obra = Tarifa por hora x Horas estimadas por IA.
        </p>
        <div class="grid gap-2 md:grid-cols-[1fr_140px]">
          <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Horas estimadas (ejemplo)
          </label>
          <input
            v-model.number="hoursExample"
            type="number"
            min="0"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          />
        </div>
        <div class="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2">
          <span>Total ejemplo</span>
          <span class="font-semibold">ARS {{ previewTotal }}</span>
        </div>
      </div>
      <button
        :disabled="settingsStore.laborRateLoading"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
        @click="saveRate"
      >
        {{ settingsStore.laborRateLoading ? "Guardando..." : "Guardar tarifa" }}
      </button>
      <p v-if="saveFeedback" class="text-sm text-emerald-600">{{ saveFeedback }}</p>
      <p v-if="saveError" class="text-sm text-destructive">{{ saveError }}</p>
    </div>
  </div>
</template>
