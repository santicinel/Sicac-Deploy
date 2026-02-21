<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { toast } from "vue-sonner";
import supportRequestsService, {
  type RatingSummaryPeriod,
  type RatingSummaryType,
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

const ratingType = ref<RatingSummaryType>("technicians");
const ratingPeriod = ref<RatingSummaryPeriod>("all");
const loading = ref(false);
const search = ref("");
const technicians = ref<TechnicianSummary[]>([]);
const clients = ref<ClientSummary[]>([]);

const fullName = (item: { first_name?: string; last_name?: string; name?: string }, fallback: string) => {
  const first = (item.first_name || "").trim();
  const last = (item.last_name || "").trim();
  const combined = `${first} ${last}`.trim();
  return combined || item.name || fallback;
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
        <div v-else class="hidden gap-2 border-b bg-muted/20 px-6 py-3 text-xs font-semibold uppercase text-muted-foreground md:grid md:grid-cols-[1.2fr_130px_130px_130px_130px_160px_1.4fr]">
          <span>Nombre y apellido</span>
          <span>Promedio</span>
          <span>Puntajes</span>
          <span>Asignados</span>
          <span>Cerrados</span>
          <span>Generado</span>
          <span>Ultimo comentario</span>
        </div>
        <div
          v-for="item in filteredTechnicians"
          :key="item.technician_id"
          class="grid gap-2 px-6 py-4 text-sm md:grid-cols-[1.2fr_130px_130px_130px_130px_160px_1.4fr] md:items-start"
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
        </div>
      </div>

      <div v-else class="divide-y">
        <div v-if="filteredClients.length === 0" class="px-6 py-6 text-sm text-muted-foreground">Sin puntajes de clientes.</div>
        <div v-else class="hidden gap-2 border-b bg-muted/20 px-6 py-3 text-xs font-semibold uppercase text-muted-foreground md:grid md:grid-cols-[1.2fr_1fr_130px_130px_130px_130px_1.4fr]">
          <span>Nombre y apellido</span>
          <span>Email</span>
          <span>Promedio</span>
          <span>Puntajes</span>
          <span>Asignados</span>
          <span>Cerrados</span>
          <span>Ultimo comentario</span>
        </div>
        <div
          v-for="item in filteredClients"
          :key="item.client_user_id"
          class="grid gap-2 px-6 py-4 text-sm md:grid-cols-[1.2fr_1fr_130px_130px_130px_130px_1.4fr] md:items-start"
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
        </div>
      </div>
    </section>
  </div>
</template>
