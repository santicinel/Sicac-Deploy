<script setup lang="ts">
import { computed, ref } from "vue";
import { useAuthStore } from "@/store/authStore";
import {
  useAdminTechniciansStore,
  type AvailabilitySlot,
} from "@/store/adminTechniciansStore";
import { Icon } from "@iconify/vue";

type ClaimStatus = "open" | "in_progress" | "closed";
type ClaimType = "technical" | "claim" | "labor";

interface ClaimCustomer {
  name: string;
  email: string;
  phone: string;
}

interface ClaimAddress {
  street: string;
  city: string;
  province: string;
}

interface ClaimItem {
  id: string;
  type: ClaimType;
  status: ClaimStatus;
  subject: string;
  description: string;
  createdAt: string;
  assignedTechnicianEmail?: string | null;
  assignmentDate?: string;
  assignmentShift?: AvailabilitySlot;
  requestedDateFrom?: string;
  requestedDateTo?: string;
  requestedShift?: AvailabilitySlot;
  customer: ClaimCustomer;
  address: ClaimAddress;
  completionNotes?: string;
  completionTasks?: string;
  completedAt?: string;
  clientRating?: number;
  clientNotes?: string;
}

const statusLabels: Record<ClaimStatus, string> = {
  open: "Abierto",
  in_progress: "En progreso",
  closed: "Cerrado",
};

const typeLabels: Record<ClaimType, string> = {
  technical: "Tecnico",
  claim: "Reclamo",
  labor: "Mano de obra",
};

const shiftLabels: Record<AvailabilitySlot, string> = {
  morning: "Manana (09:00 - 13:00)",
  afternoon: "Tarde (14:00 - 18:00)",
};
const allShiftSlots: AvailabilitySlot[] = ["morning", "afternoon"];

const authStore = useAuthStore();
const techniciansStore = useAdminTechniciansStore();
const selectedClaim = ref<ClaimItem | null>(null);
const modalMode = ref<"view" | "assign">("view");
const sectionFilter = ref<"assigned" | "unassigned" | "history">("assigned");

const claims = ref<ClaimItem[]>([
  {
    id: "REC-2001",
    type: "technical",
    status: "in_progress",
    subject: "Camaras sin red",
    description:
      "El DVR dejo de tomar IP en el switch. Cliente reporta perdida total de video.",
    createdAt: "2026-01-12",
    assignedTechnicianEmail: "tecnico@sicac.com",
    assignmentDate: "2026-01-18",
    assignmentShift: "morning",
    customer: {
      name: "Maria Lopez",
      email: "maria.lopez@mail.com",
      phone: "+54 3465 111-222",
    },
    address: {
      street: "San Martin 123",
      city: "Firmat",
      province: "Santa Fe",
    },
  },
  {
    id: "REC-2002",
    type: "claim",
    status: "open",
    subject: "Alarma con falsos disparos",
    description:
      "Se activan sensores de movimiento sin presencia. Solicita revision del panel.",
    createdAt: "2026-01-15",
    assignedTechnicianEmail: null,
    requestedDateFrom: "2026-02-17",
    requestedDateTo: "2026-02-27",
    requestedShift: "morning",
    customer: {
      name: "Juan Perez",
      email: "juan.perez@mail.com",
      phone: "+54 3465 333-444",
    },
    address: {
      street: "Buenos Aires 890",
      city: "Firmat",
      province: "Santa Fe",
    },
  },
  {
    id: "REC-2003",
    type: "labor",
    status: "open",
    subject: "Solicitud de mano de obra",
    description:
      "Instalacion adicional de dos sensores exteriores con cableado.",
    createdAt: "2026-01-16",
    assignedTechnicianEmail: null,
    requestedDateFrom: "2026-02-20",
    requestedDateTo: "2026-02-20",
    requestedShift: "afternoon",
    customer: {
      name: "Sofia Romero",
      email: "sofia.romero@mail.com",
      phone: "+54 3465 555-666",
    },
    address: {
      street: "Belgrano 455",
      city: "Firmat",
      province: "Santa Fe",
    },
  },
  {
    id: "REC-2004",
    type: "technical",
    status: "closed",
    subject: "Reemplazo de bateria",
    description: "Se cambio la bateria de respaldo del panel central.",
    createdAt: "2026-01-08",
    assignedTechnicianEmail: "tecnico@sicac.com",
    assignmentDate: "2026-01-10",
    assignmentShift: "afternoon",
    completedAt: "2026-01-11",
    completionNotes: "Se reemplazo la bateria y se verifico la autonomia.",
    completionTasks:
      "Reemplazo de bateria\nPrueba de backup\nLimpieza de bornes",
    clientRating: 5,
    clientNotes: "Cliente colaboro con acceso y suministro electrico.",
    customer: {
      name: "Carlos Gomez",
      email: "carlos.gomez@mail.com",
      phone: "+54 3465 777-888",
    },
    address: {
      street: "Mitre 200",
      city: "Firmat",
      province: "Santa Fe",
    },
  },
]);

const currentTechnicianEmail = computed(() => authStore.user?.email ?? "");

const currentTechnician = computed(() => {
  const email = currentTechnicianEmail.value.trim().toLowerCase();
  if (!email) return null;
  return (
    techniciansStore.items.find(
      (item) => item.email.trim().toLowerCase() === email
    ) ?? null
  );
});

const technicianAvailabilitySlots = computed<AvailabilitySlot[]>(() => {
  const slots = currentTechnician.value?.availabilitySlots ?? [];
  return slots.length ? slots : ["morning", "afternoon"];
});

const technicianAvailabilityLabel = computed(() =>
  technicianAvailabilitySlots.value.map((slot) => shiftLabels[slot]).join(" / ")
);

const myActiveClaims = computed(() =>
  claims.value.filter(
    (item) =>
      item.assignedTechnicianEmail === currentTechnicianEmail.value &&
      item.status !== "closed"
  )
);

const myHistoryClaims = computed(() =>
  claims.value.filter(
    (item) =>
      item.assignedTechnicianEmail === currentTechnicianEmail.value &&
      item.status === "closed"
  )
);

const unassignedClaims = computed(() =>
  claims.value.filter(
    (item) => !item.assignedTechnicianEmail && item.status === "open"
  )
);

const showAssigned = computed(() => sectionFilter.value === "assigned");
const showUnassigned = computed(() => sectionFilter.value === "unassigned");
const showHistory = computed(() => sectionFilter.value === "history");

const formatDate = (value?: string | null) => {
  if (!value) return "";
  const [year, month, day] = value.split("-");
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
};

const formatRequestedDateRange = (claim?: ClaimItem | null) => {
  if (!claim) return "A coordinar";

  const from = claim.requestedDateFrom ?? claim.requestedDateTo;
  const to = claim.requestedDateTo ?? claim.requestedDateFrom;

  if (from && to) {
    if (from === to) return formatDate(from);
    return `${formatDate(from)} - ${formatDate(to)}`;
  }

  return from ? formatDate(from) : "A coordinar";
};

const getRequestedWindowLabel = (claim?: ClaimItem | null) => {
  const dateRange = formatRequestedDateRange(claim);
  const shiftLabel = claim?.requestedShift
    ? shiftLabels[claim.requestedShift]
    : "Turno a coordinar";

  return `${dateRange} | ${shiftLabel}`;
};

const getAssignedDateTimeLabel = (claim?: ClaimItem | null) => {
  if (!claim?.assignmentDate) return "Sin fecha";
  const dateLabel = formatDate(claim.assignmentDate);
  const shiftLabel = claim.assignmentShift
    ? shiftLabels[claim.assignmentShift]
    : "Sin turno";
  return `${dateLabel} | ${shiftLabel}`;
};

const mapQuery = computed(() => {
  if (!selectedClaim.value) return "";
  const { street, city, province } = selectedClaim.value.address;
  return [street, city, province].filter(Boolean).join(", ");
});

const mapUrl = computed(() =>
  mapQuery.value
    ? `https://www.google.com/maps?q=${encodeURIComponent(
        mapQuery.value
      )}&output=embed`
    : ""
);

const assignmentRangeMin = computed(
  () => selectedClaim.value?.requestedDateFrom ?? selectedClaim.value?.requestedDateTo ?? ""
);

const assignmentRangeMax = computed(
  () => selectedClaim.value?.requestedDateTo ?? selectedClaim.value?.requestedDateFrom ?? ""
);

const assignmentShiftLocked = computed(() => Boolean(selectedClaim.value?.requestedShift));

const isSlotAvailable = (slot: AvailabilitySlot) =>
  technicianAvailabilitySlots.value.includes(slot);

const isDateInsideRequestedWindow = (claim: ClaimItem, assignmentDate: string) => {
  if (!assignmentDate) return false;

  const from = claim.requestedDateFrom ?? claim.requestedDateTo;
  const to = claim.requestedDateTo ?? claim.requestedDateFrom;

  if (from && assignmentDate < from) return false;
  if (to && assignmentDate > to) return false;

  return true;
};

const isShiftCompatible = (claim: ClaimItem) => {
  if (!claim.assignmentShift) return false;
  if (!technicianAvailabilitySlots.value.includes(claim.assignmentShift)) return false;
  if (claim.requestedShift && claim.assignmentShift !== claim.requestedShift) return false;
  return true;
};

const canSelfAssign = computed(() => {
  if (!selectedClaim.value?.assignmentDate) return false;

  return (
    isDateInsideRequestedWindow(
      selectedClaim.value,
      selectedClaim.value.assignmentDate
    ) && isShiftCompatible(selectedClaim.value)
  );
});

const assignmentHint = computed(() => {
  if (!selectedClaim.value) return "";
  const from = assignmentRangeMin.value;
  const to = assignmentRangeMax.value;

  if (!from && !to) {
    return "El cliente no definio un rango. Coordinar fecha con el cliente.";
  }

  if (from && to && from !== to) {
    return `Rango solicitado: ${formatDate(from)} al ${formatDate(to)}.`;
  }

  const singleDate = from || to;
  return `Fecha solicitada: ${formatDate(singleDate)}.`;
});

const openClaim = (item: ClaimItem, mode: "view" | "assign" = "view") => {
  const copy: ClaimItem = { ...item };

  if (mode === "assign") {
    if (!copy.assignmentDate) {
      copy.assignmentDate = copy.requestedDateFrom ?? copy.requestedDateTo ?? "";
    }

    if (!copy.assignmentShift) {
      copy.assignmentShift =
        copy.requestedShift ?? technicianAvailabilitySlots.value[0] ?? "morning";
    }
  }

  selectedClaim.value = copy;
  modalMode.value = mode;
};

const closeClaim = () => {
  selectedClaim.value = null;
  modalMode.value = "view";
};

const assignSelf = () => {
  if (!selectedClaim.value || !currentTechnicianEmail.value) return;
  if (!canSelfAssign.value) return;

  const updated = {
    ...selectedClaim.value,
    assignedTechnicianEmail: currentTechnicianEmail.value,
    status: "in_progress" as ClaimStatus,
  };

  claims.value = claims.value.map((item) =>
    item.id === updated.id ? { ...updated } : item
  );

  selectedClaim.value = { ...updated };
  closeClaim();
};

const finalizeClaim = () => {
  if (!selectedClaim.value) return;
  if (!selectedClaim.value.completionNotes?.trim()) return;

  const updated = {
    ...selectedClaim.value,
    status: "closed" as ClaimStatus,
    completedAt: new Date().toISOString().slice(0, 10),
  };

  claims.value = claims.value.map((item) =>
    item.id === updated.id ? { ...updated } : item
  );

  selectedClaim.value = { ...updated };
  closeClaim();
};
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight">Panel técnico</h1>
        <div class="flex items-center gap-2 rounded-md border bg-card px-3 py-2 text-xs text-muted-foreground">
          <Icon icon="mdi:calendar-check" class="h-4 w-4" />
          <span>
            Disponible: {{ technicianAvailabilityLabel }}
          </span>
        </div>
      </div>
      <p class="text-muted-foreground">
        Historial, reclamos asignados y solicitudes sin tecnico.
      </p>
    </header>

    <div class="flex flex-wrap items-center gap-2 rounded-lg border bg-card p-2 text-sm shadow-sm">
      <button
        class="rounded-md px-3 py-2 text-xs font-semibold transition"
        :class="showAssigned ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
        @click="sectionFilter = 'assigned'"
      >
        Asignado
      </button>
      <button
        class="rounded-md px-3 py-2 text-xs font-semibold transition"
        :class="showUnassigned ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
        @click="sectionFilter = 'unassigned'"
      >
        Sin asignar
      </button>
      <button
        class="rounded-md px-3 py-2 text-xs font-semibold transition"
        :class="showHistory ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
        @click="sectionFilter = 'history'"
      >
        Historial
      </button>
    </div>

    <section v-if="showAssigned" class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="border-b px-6 py-4">
        <h2 class="text-lg font-semibold">Asignados actualmente</h2>
        <p class="text-sm text-muted-foreground">
          Reclamos que estan en curso y tenes asignados.
        </p>
      </div>
      <div v-if="myActiveClaims.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
        No hay reclamos activos asignados.
      </div>
      <div v-else class="grid gap-4 px-6 py-6 md:grid-cols-2">
        <div
          v-for="item in myActiveClaims"
          :key="item.id"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">{{ item.id }}</p>
              <h3 class="text-base font-semibold">{{ item.subject }}</h3>
              <p class="text-sm text-muted-foreground">{{ item.customer.name }}</p>
            </div>
            <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">
              {{ statusLabels[item.status] }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="rounded-full bg-muted px-2 py-1">
              {{ typeLabels[item.type] }}
            </span>
            <span>Asignado: {{ getAssignedDateTimeLabel(item) }}</span>
          </div>
          <button
            class="mt-4 inline-flex items-center rounded-md border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20"
            @click="openClaim(item)"
          >
            Ver detalle
          </button>
        </div>
      </div>
    </section>

    <section v-if="showUnassigned" class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="border-b px-6 py-4">
        <h2 class="text-lg font-semibold">Sin asignar</h2>
        <p class="text-sm text-muted-foreground">
          Reclamos o solicitudes de mano de obra disponibles para tomar.
        </p>
      </div>
      <div v-if="unassignedClaims.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
        No hay reclamos disponibles en este momento.
      </div>
      <div v-else class="grid gap-4 px-6 py-6 md:grid-cols-2">
        <div
          v-for="item in unassignedClaims"
          :key="item.id"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">{{ item.id }}</p>
              <h3 class="text-base font-semibold">{{ item.subject }}</h3>
              <p class="text-sm text-muted-foreground">{{ item.customer.name }}</p>
            </div>
            <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">
              {{ statusLabels[item.status] }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="rounded-full bg-muted px-2 py-1">
              {{ typeLabels[item.type] }}
            </span>
            <span>Preferencia: {{ getRequestedWindowLabel(item) }}</span>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <button
              class="inline-flex items-center rounded-md border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20"
              @click="openClaim(item, 'assign')"
            >
              Ver detalle
            </button>
            <button
              class="inline-flex items-center rounded-md bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90"
              @click="openClaim(item, 'assign')"
            >
              Asignarme
            </button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="showHistory" class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="border-b px-6 py-4">
        <h2 class="text-lg font-semibold">Historial</h2>
        <p class="text-sm text-muted-foreground">
          Reclamos cerrados en los que participaste.
        </p>
      </div>
      <div v-if="myHistoryClaims.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
        Todavia no tenes reclamos cerrados.
      </div>
      <div v-else class="grid gap-4 px-6 py-6 md:grid-cols-2">
        <div
          v-for="item in myHistoryClaims"
          :key="item.id"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">{{ item.id }}</p>
              <h3 class="text-base font-semibold">{{ item.subject }}</h3>
              <p class="text-sm text-muted-foreground">{{ item.customer.name }}</p>
            </div>
            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">
              {{ statusLabels[item.status] }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="rounded-full bg-muted px-2 py-1">
              {{ typeLabels[item.type] }}
            </span>
            <span>Fecha: {{ getAssignedDateTimeLabel(item) }}</span>
          </div>
          <button
            class="mt-4 inline-flex items-center rounded-md border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20"
            @click="openClaim(item)"
          >
            Ver detalle
          </button>
        </div>
      </div>
    </section>

    <div
      v-if="selectedClaim"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="w-full max-w-3xl rounded-lg border bg-card text-card-foreground shadow-lg">
        <div class="flex items-start justify-between border-b px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold">Detalle del reclamo</h2>
            <p class="text-sm text-muted-foreground">
              {{ selectedClaim.id }} - {{ selectedClaim.subject }}
            </p>
          </div>
          <button class="text-muted-foreground hover:text-foreground" @click="closeClaim">
            <Icon icon="mdi:close" class="h-5 w-5" />
          </button>
        </div>
        <div class="grid max-h-[70vh] gap-6 overflow-y-auto px-6 py-4 md:grid-cols-[1.2fr_1fr]">
          <div class="space-y-4 text-sm">
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">Descripcion</p>
              <p>{{ selectedClaim.description }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">Datos del cliente</p>
              <p class="font-medium">{{ selectedClaim.customer.name }}</p>
              <p>{{ selectedClaim.customer.email }}</p>
              <p>{{ selectedClaim.customer.phone }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">Estado</p>
              <p>{{ statusLabels[selectedClaim.status] }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">Tipo</p>
              <p>{{ typeLabels[selectedClaim.type] }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">Preferencia solicitada</p>
              <p>{{ getRequestedWindowLabel(selectedClaim) }}</p>
            </div>
            <div v-if="selectedClaim.assignmentDate">
              <p class="text-xs font-semibold uppercase text-muted-foreground">Asignacion</p>
              <p>{{ getAssignedDateTimeLabel(selectedClaim) }}</p>
            </div>
            <div v-if="selectedClaim.completedAt">
              <p class="text-xs font-semibold uppercase text-muted-foreground">Finalizado</p>
              <p>{{ selectedClaim.completedAt }}</p>
            </div>
            <div v-if="selectedClaim.completionNotes">
              <p class="text-xs font-semibold uppercase text-muted-foreground">Resumen tecnico</p>
              <p>{{ selectedClaim.completionNotes }}</p>
            </div>
            <div v-if="selectedClaim.completionTasks">
              <p class="text-xs font-semibold uppercase text-muted-foreground">Tareas realizadas</p>
              <pre class="whitespace-pre-wrap text-sm text-foreground">{{ selectedClaim.completionTasks }}</pre>
            </div>
            <div v-if="selectedClaim.clientRating">
              <p class="text-xs font-semibold uppercase text-muted-foreground">Puntaje del cliente</p>
              <p>{{ selectedClaim.clientRating }} / 5</p>
            </div>
            <div v-if="selectedClaim.clientNotes">
              <p class="text-xs font-semibold uppercase text-muted-foreground">Descripcion del cliente</p>
              <p>{{ selectedClaim.clientNotes }}</p>
            </div>
          </div>
          <div class="space-y-4 text-sm">
            <div>
              <p class="text-xs font-semibold uppercase text-muted-foreground">Ubicacion</p>
              <p>
                {{ selectedClaim.address.street }},
                {{ selectedClaim.address.city }},
                {{ selectedClaim.address.province }}
              </p>
            </div>
            <div class="h-56 overflow-hidden rounded-md border">
              <iframe
                v-if="mapUrl"
                :src="mapUrl"
                class="h-full w-full"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
              <div v-else class="flex h-full items-center justify-center text-xs text-muted-foreground">
                No hay ubicacion disponible.
              </div>
            </div>
            <a
              v-if="mapUrl"
              class="inline-flex items-center gap-2 text-xs font-medium text-primary hover:underline"
              :href="mapUrl.replace('&output=embed', '')"
              target="_blank"
              rel="noreferrer"
            >
              <Icon icon="mdi:map-marker-radius-outline" class="h-4 w-4" />
              Ver en Google Maps
            </a>
            <div v-if="modalMode === 'assign'" class="space-y-3">
              <p class="text-xs font-semibold uppercase text-muted-foreground">
                Asignarme dentro de la preferencia del cliente
              </p>
              <input
                v-model="selectedClaim.assignmentDate"
                type="date"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                :min="assignmentRangeMin || undefined"
                :max="assignmentRangeMax || undefined"
              />
              <p class="text-xs text-muted-foreground">
                {{ assignmentHint }}
              </p>
              <select
                v-model="selectedClaim.assignmentShift"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                :disabled="assignmentShiftLocked"
              >
                <option
                  v-for="slot in allShiftSlots"
                  :key="slot"
                  :value="slot"
                  :disabled="!isSlotAvailable(slot)"
                >
                  {{ shiftLabels[slot] }}
                </option>
              </select>
              <p class="text-xs text-muted-foreground">
                Tu disponibilidad: {{ technicianAvailabilityLabel }}
              </p>
              <p v-if="selectedClaim.requestedShift" class="text-xs text-muted-foreground">
                Turno solicitado por cliente: {{ shiftLabels[selectedClaim.requestedShift] }}
              </p>
              <p
                v-if="selectedClaim.assignmentDate && selectedClaim.assignmentShift && !canSelfAssign"
                class="text-xs text-destructive"
              >
                La fecha o el turno no coinciden con la solicitud del cliente o con tu disponibilidad.
              </p>
              <button
                class="w-full rounded-md bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:bg-muted"
                :disabled="!canSelfAssign"
                @click="assignSelf"
              >
                Confirmar asignacion
              </button>
            </div>
            <div
              v-if="selectedClaim.assignedTechnicianEmail === currentTechnicianEmail && selectedClaim.status !== 'closed'"
              class="space-y-3"
            >
              <p class="text-xs font-semibold uppercase text-muted-foreground">
                Finalizar tarea
              </p>
              <textarea
                v-model="selectedClaim.completionNotes"
                rows="3"
                placeholder="Resumen tecnico de la tarea..."
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              ></textarea>
              <select
                v-model="selectedClaim.clientRating"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                <option :value="undefined">Puntaje del cliente (opcional)</option>
                <option :value="5">5 - Excelente</option>
                <option :value="4">4 - Muy bien</option>
                <option :value="3">3 - Correcto</option>
                <option :value="2">2 - Regular</option>
                <option :value="1">1 - Mala experiencia</option>
              </select>
              <textarea
                v-model="selectedClaim.clientNotes"
                rows="3"
                placeholder="Descripcion breve del cliente (opcional)"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              ></textarea>
              <textarea
                v-model="selectedClaim.completionTasks"
                rows="4"
                placeholder="Detalle de tareas realizadas (una por linea)"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              ></textarea>
              <button
                class="w-full rounded-md bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:bg-muted"
                :disabled="!selectedClaim.completionNotes?.trim()"
                @click="finalizeClaim"
              >
                Marcar como finalizado
              </button>
            </div>
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
