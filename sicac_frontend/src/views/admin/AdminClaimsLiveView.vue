<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Icon } from "@iconify/vue";
import { toast } from "vue-sonner";
import supportRequestsService, {
  type ApiServiceRequest,
  type ApiTechnician,
  type ServiceRequestStatus,
} from "@/services/supportRequestsService";

const loading = ref(false);
const requests = ref<ApiServiceRequest[]>([]);
const technicians = ref<ApiTechnician[]>([]);
const search = ref("");
const statusFilter = ref<ServiceRequestStatus | "all">("all");
const typeFilter = ref<"technical_service" | "claim" | "all">("all");
const selected = ref<ApiServiceRequest | null>(null);
const modalStatus = ref<ServiceRequestStatus>("pending");
const modalTechnicianId = ref<number | null>(null);
const modalCancellationReason = ref("");
const modalResolutionSummary = ref("");
const modalChargedAmount = ref("");
const saving = ref(false);

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

const getDisplayStatus = (item: Pick<ApiServiceRequest, "status" | "technician_id">): ServiceRequestStatus => {
  if (item.status === "completed" || item.status === "cancelled") {
    return item.status;
  }
  return item.technician_id ? "assigned" : "pending";
};

const isFinalStatus = (status: ServiceRequestStatus) => status === "completed" || status === "cancelled";

const isSelectedReadOnly = computed(() => {
  if (!selected.value) return false;
  return isFinalStatus(selected.value.status);
});

type DateOrder = "desc" | "asc";
type DatePreset = "all" | "last_7_days" | "last_30_days" | "last_3_months" | "last_6_months" | "this_month" | "last_month";

const dateOrderFilter = ref<DateOrder>("desc");
const datePresetFilter = ref<DatePreset>("all");

const formatDateTime = (value?: string | null) => {
  if (!value) return "Sin fecha";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("es-AR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
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

const normalizeChargedAmount = (value: unknown): number | null => {
  if (value === null || value === undefined) return null;
  const normalized = String(value).trim().replace(",", ".");
  if (!normalized) return null;
  const numeric = Number(normalized);
  if (!Number.isFinite(numeric) || numeric <= 0) return null;
  return Math.round(numeric * 100) / 100;
};

const getDateRangeFromPreset = (preset: DatePreset): { from: Date; to: Date } | null => {
  if (preset === "all") return null;

  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
  const endOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);

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

  if (preset === "last_6_months") {
    const from = new Date(startOfToday);
    from.setMonth(from.getMonth() - 6);
    return { from, to: endOfToday };
  }

  if (preset === "this_month") {
    const from = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0);
    return { from, to: endOfToday };
  }

  const from = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0, 0, 0);
  const to = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59, 999);
  return { from, to };
};

const matchesDateFilter = (item: ApiServiceRequest) => {
  const range = getDateRangeFromPreset(datePresetFilter.value);
  if (!range) return true;

  const targetDate = new Date(item.created_at);
  if (Number.isNaN(targetDate.getTime())) return false;
  return targetDate >= range.from && targetDate <= range.to;
};

const filteredRequests = computed(() => {
  const filtered = requests.value.filter((item) => {
    const text = search.value.trim().toLowerCase();
    const matchesSearch =
      !text ||
      item.subject.toLowerCase().includes(text) ||
      item.description.toLowerCase().includes(text) ||
      String(item.id).includes(text) ||
      (item.requesting_user?.name || "").toLowerCase().includes(text);
    const matchesStatus = statusFilter.value === "all" || getDisplayStatus(item) === statusFilter.value;
    const matchesType = typeFilter.value === "all" || item.type === typeFilter.value;
    const matchesDate = matchesDateFilter(item);
    return matchesSearch && matchesStatus && matchesType && matchesDate;
  });

  return filtered.sort((a, b) => {
    const aTime = new Date(a.created_at).getTime();
    const bTime = new Date(b.created_at).getTime();
    const aValue = Number.isNaN(aTime) ? 0 : aTime;
    const bValue = Number.isNaN(bTime) ? 0 : bTime;
    return dateOrderFilter.value === "desc" ? bValue - aValue : aValue - bValue;
  });
});

const getTechnicianName = (id: number | null | undefined) => {
  if (!id) return "Sin asignar";
  const tech = technicians.value.find((item) => item.id === id);
  if (!tech) return "Sin asignar";
  const fullName = `${tech.first_name ?? ""} ${tech.last_name ?? ""}`.trim();
  return (tech.user?.name ?? fullName) || `Tecnico #${id}`;
};

const loadData = async () => {
  loading.value = true;
  try {
    const [requestList, technicianList] = await Promise.all([
      supportRequestsService.getAdminRequests(),
      supportRequestsService.getTechnicians(),
    ]);
    requests.value = requestList;
    technicians.value = technicianList;
  } catch (error) {
    console.error(error);
    toast.error("No se pudieron cargar los reclamos.");
  } finally {
    loading.value = false;
  }
};

const openModal = (item: ApiServiceRequest) => {
  selected.value = item;
  modalStatus.value = item.status;
  modalTechnicianId.value = item.technician_id;
  modalCancellationReason.value = (item.cancellation_reason || "").trim();
  modalResolutionSummary.value = (item.resolution_summary || "").trim();
  modalChargedAmount.value = item.charged_amount !== null && item.charged_amount !== undefined ? String(item.charged_amount) : "";
};

const saveChanges = async () => {
  if (!selected.value) return;
  if (isSelectedReadOnly.value) {
    toast.error("La solicitud ya esta cerrada y no se puede editar.");
    return;
  }

  const cancellationReason = modalCancellationReason.value.trim();
  const normalizedChargedAmount = normalizeChargedAmount(modalChargedAmount.value);
  const nextStatus =
    modalTechnicianId.value && modalStatus.value === "pending"
      ? "assigned"
      : modalStatus.value;

  if (nextStatus === "cancelled" && !cancellationReason) {
    toast.error("Debes ingresar una justificacion para cancelar.");
    return;
  }

  if (nextStatus === "completed") {
    if (!modalResolutionSummary.value.trim()) {
      toast.error("Debes detallar que se hizo para completar la tarea.");
      return;
    }

    if (!normalizedChargedAmount) {
      toast.error("Debes indicar cuanto se cobro por la tarea.");
      return;
    }
  }

  saving.value = true;
  try {
    await supportRequestsService.updateRequest(selected.value.id, {
      status: nextStatus,
      technician_id: modalTechnicianId.value,
      cancellation_reason: nextStatus === "cancelled" ? cancellationReason : null,
      resolution_summary: nextStatus === "completed" ? modalResolutionSummary.value.trim() : null,
      charged_amount: nextStatus === "completed" ? normalizedChargedAmount : null,
    });
    toast.success("Solicitud actualizada.");
    selected.value = null;
    await loadData();
  } catch (error) {
    console.error(error);
    toast.error("No se pudo actualizar la solicitud.");
  } finally {
    saving.value = false;
  }
};

onMounted(async () => {
  await loadData();
});
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Reclamos y solicitudes</h1>
      <p class="text-muted-foreground">Vista administrativa para asignar tecnicos y actualizar estados.</p>
    </header>

    <div class="grid gap-3 md:grid-cols-5">
      <input v-model="search" type="text" placeholder="Buscar por cliente, asunto o ID" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
      <select v-model="statusFilter" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
        <option value="all">Todos los estados</option>
        <option value="pending">Sin asignacion</option>
        <option value="assigned">Asignado</option>
        <option value="completed">Completada</option>
        <option value="cancelled">Cancelada</option>
      </select>
      <select v-model="typeFilter" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
        <option value="all">Todos los tipos</option>
        <option value="technical_service">Solicitud tecnica</option>
        <option value="claim">Reclamo</option>
      </select>
      <select v-model="dateOrderFilter" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
        <option value="desc">Mostrar fecha mayor</option>
        <option value="asc">Mostrar fecha menor</option>
      </select>
      <select v-model="datePresetFilter" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
        <option value="all">Todas las fechas</option>
        <option value="last_7_days">Ultima semana</option>
        <option value="last_30_days">Ultimo mes</option>
        <option value="last_3_months">Ultimos 3 meses</option>
        <option value="last_6_months">Ultimos 6 meses</option>
        <option value="this_month">Este mes</option>
        <option value="last_month">Mes pasado</option>
      </select>
    </div>

    <section class="rounded-lg border bg-card shadow-sm">
      <div v-if="loading" class="px-6 py-6 text-sm text-muted-foreground">Cargando...</div>
      <div v-else-if="filteredRequests.length === 0" class="px-6 py-6 text-sm text-muted-foreground">No hay registros para los filtros aplicados.</div>
      <div v-else class="divide-y">
        <div class="hidden gap-3 border-b bg-muted/20 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground md:grid md:grid-cols-[80px_1fr_130px_130px_180px_180px]">
          <span>ID</span>
          <span>Descripcion</span>
          <span>Tipo</span>
          <span>Estado</span>
          <span>Tecnico</span>
          <span>Gestion</span>
        </div>
        <div
          v-for="item in filteredRequests"
          :key="item.id"
          class="grid grid-cols-1 gap-3 px-6 py-4 text-sm md:grid-cols-[80px_1fr_130px_130px_180px_180px] md:items-center"
        >
          <span class="font-semibold">#{{ item.id }}</span>
          <div>
            <p class="font-medium">{{ item.subject }}</p>
            <p class="text-xs text-muted-foreground">{{ item.requesting_user?.name || "Cliente" }}</p>
            <p class="text-xs text-muted-foreground">Generado: {{ formatDateTime(item.created_at) }}</p>
            <p class="text-xs text-muted-foreground">Cierre: {{ item.completed_at ? formatDateTime(item.completed_at) : "Sin cierre" }}</p>
          </div>
          <span>{{ item.type === "claim" ? "Reclamo" : "Solicitud tecnica" }}</span>
          <span
            :class="[
              'inline-flex w-fit rounded-full border px-2.5 py-1 text-xs font-semibold',
              statusClass[getDisplayStatus(item)],
            ]"
          >
            {{ statusLabel[getDisplayStatus(item)] }}
          </span>
          <span>{{ getTechnicianName(item.technician_id) }}</span>
          <button
            class="w-full rounded-md bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground shadow-sm hover:opacity-95 md:w-auto"
            @click="openModal(item)"
          >
            Gestionar solicitud
          </button>
        </div>
      </div>
    </section>

    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-xl rounded-lg border bg-card shadow-lg">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h2 class="text-lg font-semibold">Gestionar solicitud #{{ selected.id }}</h2>
          <button @click="selected = null"><Icon icon="mdi:close" class="h-5 w-5" /></button>
        </div>
        <div class="space-y-3 px-6 py-4 text-sm">
          <p><span class="font-semibold">Asunto:</span> {{ selected.subject }}</p>
          <p><span class="font-semibold">Descripcion:</span> {{ selected.description }}</p>
          <p><span class="font-semibold">Cliente:</span> {{ selected.requesting_user?.name || "N/D" }}</p>
          <p><span class="font-semibold">Fecha y hora de generacion:</span> {{ formatDateTime(selected.created_at) }}</p>
          <p><span class="font-semibold">Fecha y hora de cierre:</span> {{ selected.completed_at ? formatDateTime(selected.completed_at) : "Sin cierre" }}</p>
          <p><span class="font-semibold">Estado actual:</span> {{ statusLabel[getDisplayStatus(selected)] }}</p>
          <p v-if="selected.resolution_summary"><span class="font-semibold">Detalle de resolucion actual:</span> {{ selected.resolution_summary }}</p>
          <p v-if="selected.charged_amount !== null && selected.charged_amount !== undefined"><span class="font-semibold">Monto cobrado actual:</span> {{ formatCurrency(selected.charged_amount) }}</p>
          <p v-if="selected.cancellation_reason"><span class="font-semibold">Motivo de cancelacion actual:</span> {{ selected.cancellation_reason }}</p>
          <p v-if="isSelectedReadOnly" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            Esta solicitud esta cerrada (completada o cancelada) y solo puede visualizarse.
          </p>
          <select v-model="modalStatus" :disabled="isSelectedReadOnly || saving" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="pending">Sin asignacion</option>
            <option value="assigned">Asignado</option>
            <option value="completed">Completada</option>
            <option value="cancelled">Cancelada</option>
          </select>
          <textarea
            v-if="modalStatus === 'completed'"
            v-model="modalResolutionSummary"
            rows="3"
            placeholder="Explica que se hizo para resolver la tarea (obligatorio)"
            :disabled="isSelectedReadOnly || saving"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          />
          <input
            v-if="modalStatus === 'completed'"
            v-model="modalChargedAmount"
            type="number"
            min="0"
            step="0.01"
            placeholder="Monto cobrado (obligatorio)"
            :disabled="isSelectedReadOnly || saving"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          />
          <textarea
            v-if="modalStatus === 'cancelled'"
            v-model="modalCancellationReason"
            rows="3"
            placeholder="Justificacion de cancelacion (obligatoria)"
            :disabled="isSelectedReadOnly || saving"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          />
          <select v-model.number="modalTechnicianId" :disabled="isSelectedReadOnly || saving" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option :value="null">Sin tecnico asignado</option>
            <option v-for="tech in technicians" :key="tech.id" :value="tech.id">
              {{ tech.user?.name || `${tech.first_name || ""} ${tech.last_name || ""}` }}
            </option>
          </select>
          <button class="w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || isSelectedReadOnly" @click="saveChanges">
            {{ saving ? "Guardando..." : isSelectedReadOnly ? "Solicitud cerrada" : "Guardar cambios" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
