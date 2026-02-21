<script setup lang="ts">
import { computed, ref } from "vue";
import { Icon } from "@iconify/vue";
import { config_app } from "@/config/app";
import { useAdminTechniciansStore } from "@/store/adminTechniciansStore";

type ClaimStatus = "open" | "in_progress" | "closed";
type ClaimType = "technical" | "claim";

interface ClaimItem {
  id: string;
  type: ClaimType;
  status: ClaimStatus;
  subject: string;
  description: string;
  customer: string;
  createdAt: string;
  assignedTechnicianId?: string;
  assignmentDate?: string;
}

const claims = ref<ClaimItem[]>([
  {
    id: "REC-1001",
    type: "technical",
    status: "open",
    subject: "Cámara sin conexión",
    description:
      "El equipo dejó de conectar a la red wifi y la aplicación no muestra la imagen.",
    customer: "Juan Pérez",
    createdAt: "2026-01-10",
  },
  {
    id: "REC-1002",
    type: "claim",
    status: "in_progress",
    subject: "Garantía de alarma",
    description:
      "Necesita revisión del panel central, se reinicia cada 10 minutos.",
    customer: "María López",
    createdAt: "2026-01-12",
  },
  {
    id: "REC-1003",
    type: "technical",
    status: "closed",
    subject: "Cambio de sensores",
    description:
      "Solicita reemplazo de sensores en portón principal por falla intermitente.",
    customer: "Carlos Gómez",
    createdAt: "2026-01-15",
  },
]);

const searchQuery = ref("");
const statusFilter = ref<ClaimStatus | "all">("all");
const typeFilter = ref<ClaimType | "all">("all");
const selectedClaim = ref<ClaimItem | null>(null);
const summaryFrom = ref("");
const summaryTo = ref("");
const summaryStatus = ref<ClaimStatus | "all">("all");
const summaryType = ref<ClaimType | "all">("all");
const summaryResult = ref("");
const summaryLoading = ref(false);
const summaryError = ref("");
const summaryChatOpen = ref(false);
const summaryChatInput = ref("");
const summaryChatTyping = ref(false);
const summaryChatMessages = ref<{ role: string; content: string }[]>([]);
const modalMode = ref<"view" | "status" | "assign">("view");

const aiBaseUrl = config_app.ai_url;
const techniciansStore = useAdminTechniciansStore();

const getAvailabilityLabel = (slots: string[]) => {
  if (!slots?.length) return "Sin disponibilidad";
  const labels: string[] = [];
  if (slots.includes("morning")) labels.push("Manana (8-12)");
  if (slots.includes("afternoon")) labels.push("Tarde (14-18)");
  return labels.join(" / ");
};

const filteredClaims = computed(() => {
  return claims.value.filter((item) => {
    const matchesSearch =
      !searchQuery.value.trim() ||
      item.subject.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      item.customer.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      item.id.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesStatus =
      statusFilter.value === "all" || item.status === statusFilter.value;
    const matchesType =
      typeFilter.value === "all" || item.type === typeFilter.value;
    return matchesSearch && matchesStatus && matchesType;
  });
});

const summaryCandidates = computed(() => {
  return claims.value.filter((item) => {
    const matchesStatus =
      summaryStatus.value === "all" || item.status === summaryStatus.value;
    const matchesType =
      summaryType.value === "all" || item.type === summaryType.value;
    const matchesFrom =
      !summaryFrom.value || item.createdAt >= summaryFrom.value;
    const matchesTo = !summaryTo.value || item.createdAt <= summaryTo.value;
    return matchesStatus && matchesType && matchesFrom && matchesTo;
  });
});

const summaryFiltersPayload = computed(() => ({
  date_from: summaryFrom.value || null,
  date_to: summaryTo.value || null,
  status: summaryStatus.value === "all" ? null : summaryStatus.value,
  type: summaryType.value === "all" ? null : summaryType.value,
}));

const statusLabels: Record<ClaimStatus, string> = {
  open: "Abierto",
  in_progress: "En progreso",
  closed: "Cerrado",
};

const typeLabels: Record<ClaimType, string> = {
  technical: "Técnico",
  claim: "Reclamo",
};

const getTechnicianLabel = (id?: string) => {
  if (!id) return "Sin asignar";
  const tech = techniciansStore.items.find((item) => item.id === id);
  return tech ? `${tech.firstName} ${tech.lastName}` : "Sin asignar";
};

const availableTechnicians = computed(() => {
  if (!selectedClaim.value?.assignmentDate) return [];
  return techniciansStore.items.filter((item) => item.availabilitySlots?.length > 0);
});

const openClaim = (item: ClaimItem) => {
  selectedClaim.value = { ...item };
  modalMode.value = "view";
};

const closeClaim = () => {
  selectedClaim.value = null;
  modalMode.value = "view";
};

const updateClaimStatus = (status: ClaimStatus) => {
  if (!selectedClaim.value) return;
  selectedClaim.value.status = status;
  claims.value = claims.value.map((item) =>
    item.id === selectedClaim.value?.id ? { ...item, status } : item
  );
};

const openClaimForStatus = (item: ClaimItem) => {
  selectedClaim.value = { ...item };
  modalMode.value = "status";
};

const openClaimForAssign = (item: ClaimItem) => {
  selectedClaim.value = { ...item };
  modalMode.value = "assign";
};

const assignTechnician = () => {
  if (!selectedClaim.value) return;
  const date = selectedClaim.value.assignmentDate;
  const techId = selectedClaim.value.assignedTechnicianId;
  if (!date || !techId) return;
  if (!availableTechnicians.value.some((item) => item.id === techId)) return;

  claims.value = claims.value.map((item) =>
    item.id === selectedClaim.value?.id
      ? { ...item, assignedTechnicianId: techId, assignmentDate: date }
      : item
  );
  selectedClaim.value = {
    ...selectedClaim.value,
    assignedTechnicianId: techId,
    assignmentDate: date,
  };
};

const generateSummary = async () => {
  summaryLoading.value = true;
  summaryError.value = "";
  summaryResult.value = "";
  summaryChatMessages.value = [];
  summaryChatOpen.value = true;
  try {
    const response = await fetch(`${aiBaseUrl}/claims/summary`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        claims: summaryCandidates.value,
        filters: summaryFiltersPayload.value,
      }),
    });

    if (!response.ok) {
      throw new Error("API Error");
    }

    const data = await response.json();
    summaryResult.value =
      data.summary ?? "No se pudo generar el resumen con IA.";
  } catch (error) {
    console.error(error);
    summaryError.value =
      "No pude conectar con el servidor de IA. Verificá que esté corriendo.";
  } finally {
    summaryLoading.value = false;
  }
};

const sendSummaryMessage = async () => {
  if (!summaryChatInput.value.trim()) return;
  if (!summaryResult.value) return;

  const text = summaryChatInput.value;
  summaryChatMessages.value.push({ role: "user", content: text });
  summaryChatInput.value = "";
  summaryChatTyping.value = true;

  try {
    const response = await fetch(`${aiBaseUrl}/claims/chat`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        messages: summaryChatMessages.value,
        summary: summaryResult.value,
        claims: summaryCandidates.value,
        filters: summaryFiltersPayload.value,
      }),
    });

    if (!response.ok) {
      throw new Error("API Error");
    }

    const data = await response.json();
    summaryChatMessages.value.push({
      role: "assistant",
      content: data.response ?? "No pude responder en este momento.",
    });
  } catch (error) {
    console.error(error);
    summaryChatMessages.value.push({
      role: "assistant",
      content: "No pude conectar con el servidor de IA.",
    });
  } finally {
    summaryChatTyping.value = false;
  }
};
</script>

<template>
  <div class="p-6 space-y-6">
    <header class="flex flex-col gap-2">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight">Reclamos</h1>
        <a
          href="#claims-summary"
          class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
        >
          Resumir reclamos
        </a>
      </div>
      <p class="text-muted-foreground">
        Panel de seguimiento para solicitudes y reclamos.
      </p>
    </header>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="relative">
        <Icon
          icon="mdi:magnify"
          class="absolute left-3 top-3 h-4 w-4 text-muted-foreground"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por cliente, asunto o ID"
          class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        />
      </div>
      <select
        v-model="statusFilter"
        class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
      >
        <option value="all">Todos los estados</option>
        <option value="open">Abierto</option>
        <option value="in_progress">En progreso</option>
        <option value="closed">Cerrado</option>
      </select>
      <select
        v-model="typeFilter"
        class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
      >
        <option value="all">Todos los tipos</option>
        <option value="technical">Técnico</option>
        <option value="claim">Reclamo</option>
      </select>
    </div>

    <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="grid grid-cols-8 gap-4 border-b px-6 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        <span>ID</span>
        <span>Cliente</span>
        <span>Tipo</span>
        <span>Estado</span>
        <span>Fecha</span>
        <span>Acciones</span>
        <span>Cambiar estado</span>
        <span>Asignar</span>
      </div>
      <div v-if="filteredClaims.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
        No hay reclamos que coincidan con los filtros.
      </div>
      <div
        v-for="item in filteredClaims"
        :key="item.id"
        class="grid grid-cols-8 gap-4 px-6 py-4 text-sm border-b last:border-b-0"
      >
        <span class="font-medium">{{ item.id }}</span>
        <span>{{ item.customer }}</span>
        <span>{{ typeLabels[item.type] }}</span>
        <span
          :class="[
            'inline-flex w-fit rounded-full px-2 py-1 text-xs font-semibold',
            item.status === 'open'
              ? 'bg-amber-100 text-amber-700'
              : item.status === 'in_progress'
                ? 'bg-blue-100 text-blue-700'
                : 'bg-emerald-100 text-emerald-700',
          ]"
        >
          {{ statusLabels[item.status] }}
        </span>
        <span>{{ item.createdAt }}</span>
        <button
          class="inline-flex items-center justify-center rounded-md border border-primary/30 bg-primary/10 px-2 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20"
          @click="openClaim(item)"
        >
          Ver reclamo
        </button>
        <button
          class="inline-flex items-center justify-center rounded-md border border-primary/30 bg-primary/10 px-2 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20"
          @click="openClaimForStatus(item)"
        >
          Cambiar estado
        </button>
        <button
          class="inline-flex items-center justify-center rounded-md border border-primary/30 bg-primary/10 px-2 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:cursor-not-allowed disabled:border-muted disabled:bg-muted/40 disabled:text-muted-foreground disabled:hover:bg-muted/40"
          :disabled="item.status !== 'open'"
          @click="openClaimForAssign(item)"
        >
          Asignar técnico
        </button>
      </div>
    </div>

    <section id="claims-summary" class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="border-b px-6 py-4">
          <h2 class="text-lg font-semibold">Resumen de reclamos</h2>
          <p class="text-sm text-muted-foreground">
            Filtra por fechas, tipo y estado para generar un resumen con IA.
          </p>
        </div>
        <div class="grid gap-4 px-6 py-4">
          <label class="flex flex-col gap-2 text-sm">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Desde</span>
            <input
              v-model="summaryFrom"
              type="date"
              class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </label>
          <label class="flex flex-col gap-2 text-sm">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Hasta</span>
            <input
              v-model="summaryTo"
              type="date"
              class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </label>
          <label class="flex flex-col gap-2 text-sm">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Estado</span>
            <select
              v-model="summaryStatus"
              class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="all">Todos</option>
              <option value="open">Abierto</option>
              <option value="in_progress">En progreso</option>
              <option value="closed">Cerrado</option>
            </select>
          </label>
          <label class="flex flex-col gap-2 text-sm">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Tipo</span>
            <select
              v-model="summaryType"
              class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="all">Todos</option>
              <option value="technical">Técnico</option>
              <option value="claim">Reclamo</option>
            </select>
          </label>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4 text-sm">
          <p class="text-muted-foreground">
            {{ summaryCandidates.length }} reclamos coinciden con los filtros.
          </p>
          <button
            class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
            :disabled="summaryLoading"
            @click="generateSummary"
          >
            {{ summaryLoading ? "Generando..." : "Hacer resumen" }}
          </button>
        </div>
        <div class="border-t px-6 py-4">
          <p v-if="summaryError" class="text-sm text-destructive">{{ summaryError }}</p>
          <div v-else class="rounded-md border bg-muted/20 p-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Resumen general
            </p>
            <div v-if="summaryLoading" class="flex items-center gap-2 text-sm text-muted-foreground">
              <span class="h-2.5 w-2.5 animate-ping rounded-full bg-primary/70"></span>
              Generando resumen...
            </div>
            <pre v-else class="whitespace-pre-wrap text-base leading-relaxed text-foreground">
{{ summaryResult }}
            </pre>
          </div>
        </div>
        <div class="border-t px-6 py-4">
          <div class="flex items-center justify-between text-sm">
            <span class="font-semibold">Consultas sobre el resumen</span>
            <button
              class="text-xs font-medium text-primary hover:underline"
              :disabled="!summaryResult"
              @click="summaryChatOpen = !summaryChatOpen"
            >
              {{ summaryChatOpen ? "Cerrar" : "Abrir" }}
            </button>
          </div>
          <div v-if="summaryChatOpen" class="mt-4 space-y-3">
            <div class="max-h-64 space-y-3 overflow-y-auto rounded-md border bg-muted/30 p-3 text-sm">
              <div
                v-for="(msg, idx) in summaryChatMessages"
                :key="idx"
                :class="[
                  'rounded-lg px-3 py-2',
                  msg.role === 'assistant'
                    ? 'bg-background text-foreground'
                    : 'bg-primary text-primary-foreground',
                ]"
              >
                {{ msg.content }}
              </div>
              <div v-if="summaryChatTyping" class="text-xs text-muted-foreground">
                Escribiendo...
              </div>
            </div>
            <form class="flex gap-2" @submit.prevent="sendSummaryMessage">
              <input
                v-model="summaryChatInput"
                placeholder="Pregunta sobre el resumen..."
                class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                :disabled="summaryChatTyping || !summaryResult"
              />
              <button
                type="submit"
                class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground"
                :disabled="summaryChatTyping || !summaryChatInput.trim() || !summaryResult"
              >
                Enviar
              </button>
            </form>
          </div>
        </div>
      </section>

    <div
      v-if="selectedClaim"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="w-full max-w-lg rounded-lg border bg-card text-card-foreground shadow-lg">
        <div class="flex items-start justify-between border-b px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold">Detalle del reclamo</h2>
            <p class="text-sm text-muted-foreground">
              {{ selectedClaim.id }} - {{ selectedClaim.customer }}
            </p>
          </div>
          <button class="text-muted-foreground hover:text-foreground" @click="closeClaim">
            <Icon icon="mdi:close" class="h-5 w-5" />
          </button>
        </div>
        <div class="space-y-4 px-6 py-4 text-sm">
          <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Asunto</p>
            <p>{{ selectedClaim.subject }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Detalle</p>
            <p>{{ selectedClaim.description }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Técnico asignado</p>
            <p>{{ getTechnicianLabel(selectedClaim.assignedTechnicianId) }}</p>
            <p v-if="selectedClaim.assignmentDate" class="text-xs text-muted-foreground">
              Fecha: {{ selectedClaim.assignmentDate }}
            </p>
          </div>
          <div v-if="modalMode === 'assign' && selectedClaim.status === 'open'" class="space-y-3">
            <p class="text-xs font-semibold uppercase text-muted-foreground">
              Asignar técnico disponible
            </p>
            <div class="grid gap-2">
              <input
                v-model="selectedClaim.assignmentDate"
                type="date"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
              />
              <select
                v-model="selectedClaim.assignedTechnicianId"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                :disabled="!selectedClaim.assignmentDate"
              >
                <option value="">Seleccionar técnico disponible</option>
                <option
                  v-for="tech in availableTechnicians"
                  :key="tech.id"
                  :value="tech.id"
                >
                  {{ tech.firstName }} {{ tech.lastName }} ({{ getAvailabilityLabel(tech.availabilitySlots) }})
                </option>
              </select>
            </div>
            <p
              v-if="selectedClaim.assignmentDate && availableTechnicians.length === 0"
              class="text-xs text-muted-foreground"
            >
              No hay tecnicos cargados con disponibilidad de turnos.
            </p>
            <button
              class="rounded-md bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground"
              :disabled="!selectedClaim.assignmentDate || !selectedClaim.assignedTechnicianId"
              @click="assignTechnician"
            >
              Asignar técnico
            </button>
          </div>
          <div v-if="modalMode === 'status'" class="flex flex-col gap-2">
            <p class="text-xs font-semibold uppercase text-muted-foreground">Estado</p>
            <select
              :value="selectedClaim.status"
              class="rounded-md border border-input bg-background px-3 py-2 text-sm"
              @change="updateClaimStatus(($event.target as HTMLSelectElement).value as ClaimStatus)"
            >
              <option value="open">Abierto</option>
              <option value="in_progress">En progreso</option>
              <option value="closed">Cerrado</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end border-t px-6 py-3">
          <button
            class="rounded-md border px-4 py-2 text-sm"
            @click="closeClaim"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
