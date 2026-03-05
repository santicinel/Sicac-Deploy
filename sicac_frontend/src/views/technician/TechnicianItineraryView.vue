<script setup lang="ts">
import { Icon } from "@iconify/vue";
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue-sonner";
import type { ApiServiceRequest } from "@/services/supportRequestsService";
import { useTechnicianDailyItinerary } from "@/composables/useTechnicianDailyItinerary";

const route = useRoute();
const router = useRouter();
const selectedTask = ref<ApiServiceRequest | null>(null);

const statusLabel: Record<string, string> = {
  pending: "Pendiente",
  assigned: "Asignada",
  completed: "Completada",
  cancelled: "Cancelada",
};

const {
  loading,
  loadError,
  hasLoadedOnce,
  requests,
  todaysTasks,
  summary,
  displayText,
  formatTime,
  loadTodayTasks,
} = useTechnicianDailyItinerary();

const formatDate = (value?: string | null) => {
  if (!value) return "Sin fecha";

  const raw = String(value).trim();
  const isoDateMatch = raw.match(/(\d{4})-(\d{2})-(\d{2})/);
  if (isoDateMatch?.[1] && isoDateMatch[2] && isoDateMatch[3]) {
    const localDate = new Date(
      Number(isoDateMatch[1]),
      Number(isoDateMatch[2]) - 1,
      Number(isoDateMatch[3])
    );
    return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(localDate);
  }

  const parsed = new Date(raw);
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" }).format(parsed);
};

const shiftLabel = (value?: string | null) => {
  const normalized = displayText(value, "").toLowerCase();
  if (normalized === "morning" || normalized === "manana" || normalized === "maÃ±ana") return "Manana";
  if (normalized === "afternoon" || normalized === "tarde") return "Tarde";
  return displayText(value, "Sin turno");
};

const getClientLocation = (item: ApiServiceRequest) => {
  const address = displayText(item.requesting_user?.address, "Direccion no informada");
  const city = displayText(item.requesting_user?.city, "");
  return city ? `${address}, ${city}` : address;
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

const openTask = async (item: ApiServiceRequest) => {
  await router.replace({
    query: {
      ...route.query,
      visit: String(item.id),
    },
  });
};

const closeTask = () => {
  selectedTask.value = null;
  void clearVisitQueryParam();
};

const selectedTaskAddress = computed(() => {
  if (!selectedTask.value) return "";
  const address = displayText(selectedTask.value.requesting_user?.address, "").trim();
  const city = displayText(selectedTask.value.requesting_user?.city, "").trim();
  return [address, city, "Argentina"].filter(Boolean).join(", ");
});

const selectedMapEmbedUrl = computed(() =>
  selectedTaskAddress.value
    ? `https://www.google.com/maps?q=${encodeURIComponent(selectedTaskAddress.value)}&output=embed`
    : ""
);

const selectedMapLink = computed(() =>
  selectedTaskAddress.value
    ? `https://www.google.com/maps?q=${encodeURIComponent(selectedTaskAddress.value)}`
    : ""
);

const openTaskFromQuery = () => {
  const visitId = normalizeVisitQueryParam(route.query.visit);
  if (visitId === null) {
    selectedTask.value = null;
    return;
  }

  if (!hasLoadedOnce.value) {
    return;
  }

  const foundTask = requests.value.find((item) => item.id === visitId) ?? null;
  if (!foundTask) {
    if (loading.value) return;
    toast.info(`La visita #${visitId} no esta disponible para este tecnico.`);
    void clearVisitQueryParam();
    return;
  }

  selectedTask.value = foundTask;
};

watch(
  [() => route.query.visit, requests, loading],
  () => {
    openTaskFromQuery();
  },
  { immediate: true }
);
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div class="space-y-1">
        <h1 class="text-3xl font-bold tracking-tight">Itinerario del dia</h1>
        <p class="text-muted-foreground">Vista completa de tareas programadas para hoy.</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
          :disabled="loading"
          @click="loadTodayTasks"
        >
          <Icon icon="mdi:refresh" class="h-4 w-4" />
          Actualizar
        </button>
      </div>
    </header>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <article class="rounded-lg border bg-card p-4">
        <p class="text-2xl font-bold leading-none">{{ summary.total }}</p>
        <p class="mt-1 text-sm text-muted-foreground">Total</p>
      </article>
      <article class="rounded-lg border bg-card p-4">
        <p class="text-2xl font-bold leading-none">{{ summary.withTime }}</p>
        <p class="mt-1 text-sm text-muted-foreground">Con hora</p>
      </article>
      <article class="rounded-lg border bg-card p-4">
        <p class="text-2xl font-bold leading-none">{{ summary.withoutTime }}</p>
        <p class="mt-1 text-sm text-muted-foreground">Sin hora</p>
      </article>
    </section>

    <section class="rounded-lg border bg-card shadow-sm">
      <div v-if="loading" class="px-5 py-4 text-sm text-muted-foreground">
        Cargando itinerario...
      </div>
      <div v-else-if="loadError" class="px-5 py-4 text-sm text-destructive">
        No se pudo cargar el itinerario.
      </div>
      <div v-else-if="todaysTasks.length === 0" class="px-5 py-4 text-sm text-muted-foreground">
        No hay visitas programadas para hoy.
      </div>
      <div v-else class="divide-y">
        <button
          v-for="item in todaysTasks"
          :key="item.id"
          type="button"
          class="flex w-full flex-wrap items-start justify-between gap-3 px-5 py-4 text-left hover:bg-muted/30"
          @click="openTask(item)"
        >
          <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ displayText(item.subject, "Sin asunto") }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
              #{{ item.id }} | {{ getClientLocation(item) }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
              Cliente: {{ displayText(item.requesting_user?.name, "Cliente sin nombre") }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span class="rounded-md border bg-background px-2 py-1 text-xs font-semibold">
              {{ formatTime(item.scheduled_visit_time) }}
            </span>
            <Icon icon="mdi:arrow-top-right" class="h-4 w-4 text-muted-foreground" />
          </div>
        </button>
      </div>
    </section>
  </div>

  <div v-if="selectedTask" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-4xl rounded-lg border bg-card shadow-lg">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <h2 class="text-lg font-semibold">Solicitud #{{ selectedTask.id }}</h2>
        <button type="button" @click="closeTask">
          <Icon icon="mdi:close" class="h-5 w-5" />
        </button>
      </div>

      <div class="grid max-h-[75vh] gap-5 overflow-y-auto px-6 py-4 text-sm md:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-3">
          <p><span class="font-semibold">Asunto:</span> {{ displayText(selectedTask.subject, "Sin asunto") }}</p>
          <p><span class="font-semibold">Estado:</span> {{ statusLabel[selectedTask.status] || selectedTask.status }}</p>
          <p><span class="font-semibold">Cliente:</span> {{ displayText(selectedTask.requesting_user?.name, "N/D") }}</p>
          <p><span class="font-semibold">Telefono:</span> {{ displayText(selectedTask.requesting_user?.phone, "No informado") }}</p>
          <p><span class="font-semibold">Direccion:</span> {{ getClientLocation(selectedTask) }}</p>
          <p>
            <span class="font-semibold">Fechas solicitadas:</span>
            {{ formatDate(selectedTask.wanted_date_start) }} a {{ formatDate(selectedTask.wanted_date_end) }}
          </p>
          <p><span class="font-semibold">Turno solicitado:</span> {{ shiftLabel(selectedTask.time_shift) }}</p>
          <p><span class="font-semibold">Visita programada:</span> {{ formatDate(selectedTask.scheduled_visit_date) }}</p>
          <p><span class="font-semibold">Hora estimada de visita:</span> {{ formatTime(selectedTask.scheduled_visit_time) }}</p>

          <div>
            <p class="mb-1 font-semibold">Descripcion completa:</p>
            <p class="whitespace-pre-wrap rounded-md border bg-muted/20 p-3 leading-relaxed">
              {{ displayText(selectedTask.description, "Sin descripcion") }}
            </p>
          </div>
        </div>

        <div class="space-y-3">
          <p class="font-semibold">Ubicacion del cliente (Google Maps)</p>
          <div class="h-72 overflow-hidden rounded-md border bg-muted/20">
            <iframe
              v-if="selectedMapEmbedUrl"
              :src="selectedMapEmbedUrl"
              class="h-full w-full"
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
            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
          >
            <Icon icon="mdi:map-marker" class="h-4 w-4" />
            Abrir en Google Maps
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
