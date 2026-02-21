<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { toast } from "vue-sonner";
import supportRequestsService, {
  type ApiTechnician,
  type TechnicianUpsertPayload,
} from "@/services/supportRequestsService";

const technicians = ref<ApiTechnician[]>([]);
const loading = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);

const form = reactive({
  first_name: "",
  last_name: "",
  dni: "",
  email: "",
  password: "",
  phone: "",
  address: "",
  city: "",
});

const resetForm = () => {
  form.first_name = "";
  form.last_name = "";
  form.dni = "";
  form.email = "";
  form.password = "";
  form.phone = "";
  form.address = "";
  form.city = "";
  editingId.value = null;
};

const loadTechnicians = async () => {
  loading.value = true;
  try {
    technicians.value = await supportRequestsService.getTechnicians();
  } catch (error) {
    console.error(error);
    toast.error("No se pudieron cargar los tecnicos.");
  } finally {
    loading.value = false;
  }
};

const submitForm = async () => {
  if (!form.first_name || !form.last_name || !form.email || (!editingId.value && !form.password) || !form.dni) {
    toast.error("Completa los campos obligatorios.");
    return;
  }

  const payload: TechnicianUpsertPayload = {
    first_name: form.first_name,
    last_name: form.last_name,
    email: form.email,
    dni: form.dni,
    phone: form.phone || undefined,
    address: form.address || undefined,
    city: form.city || undefined,
  };

  if (form.password) {
    payload.password = form.password;
  }

  saving.value = true;
  try {
    if (editingId.value) {
      await supportRequestsService.updateTechnician(editingId.value, payload);
      toast.success("Tecnico actualizado.");
    } else {
      await supportRequestsService.createTechnician(payload);
      toast.success("Tecnico creado.");
    }
    resetForm();
    await loadTechnicians();
  } catch (error) {
    console.error(error);
    toast.error("No se pudo guardar el tecnico.");
  } finally {
    saving.value = false;
  }
};

const editTechnician = (item: ApiTechnician) => {
  editingId.value = item.id;
  form.first_name = item.first_name || item.user?.name?.split(" ").slice(0, 1).join(" ") || "";
  form.last_name = item.last_name || item.user?.name?.split(" ").slice(1).join(" ") || "";
  form.dni = "";
  form.email = item.email || item.user?.email || "";
  form.password = "";
  form.phone = item.phone || item.user?.phone || "";
  form.address = item.address || item.user?.address || "";
  form.city = item.city || item.user?.city || "";
};

const removeTechnician = async (id: number) => {
  const confirmed = window.confirm("Eliminar tecnico?");
  if (!confirmed) return;
  try {
    await supportRequestsService.deleteTechnician(id);
    toast.success("Tecnico eliminado.");
    await loadTechnicians();
  } catch (error) {
    console.error(error);
    toast.error("No se pudo eliminar el tecnico.");
  }
};

onMounted(async () => {
  await loadTechnicians();
});
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Tecnicos</h1>
      <p class="text-muted-foreground">Alta, edicion y baja de tecnicos.</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
      <form class="space-y-3 rounded-lg border bg-card p-5 shadow-sm" @submit.prevent="submitForm">
        <input v-model="form.first_name" type="text" placeholder="Nombre" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.last_name" type="text" placeholder="Apellido" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.dni" type="text" placeholder="DNI" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.email" type="email" placeholder="Email" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.password" type="password" :placeholder="editingId ? 'Nueva contrasena (opcional)' : 'Contrasena'" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.phone" type="text" placeholder="Telefono" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.address" type="text" placeholder="Direccion" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <input v-model="form.city" type="text" placeholder="Ciudad" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
        <div class="flex gap-2">
          <button type="submit" class="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground" :disabled="saving">
            {{ saving ? "Guardando..." : editingId ? "Actualizar" : "Crear tecnico" }}
          </button>
          <button type="button" class="rounded-md border px-4 py-2 text-sm" @click="resetForm">Limpiar</button>
        </div>
      </form>

      <section class="rounded-lg border bg-card shadow-sm">
        <div v-if="loading" class="px-6 py-6 text-sm text-muted-foreground">Cargando tecnicos...</div>
        <div v-else-if="technicians.length === 0" class="px-6 py-6 text-sm text-muted-foreground">No hay tecnicos cargados.</div>
        <div v-else class="divide-y">
          <div class="hidden gap-2 border-b bg-muted/20 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground md:grid md:grid-cols-[1fr_1fr_1fr_120px]">
            <span>Nombre y apellido</span>
            <span>Mail</span>
            <span>Ciudad</span>
            <span>Modificar</span>
          </div>
          <div v-for="item in technicians" :key="item.id" class="grid gap-2 px-6 py-4 text-sm md:grid-cols-[1fr_1fr_1fr_120px]">
            <div>
              <p class="font-medium">{{ item.user?.name || `${item.first_name || ""} ${item.last_name || ""}` }}</p>
              <p class="text-xs text-muted-foreground">ID: {{ item.id }}</p>
            </div>
            <span>{{ item.email || item.user?.email || "-" }}</span>
            <span>{{ item.city || item.user?.city || "-" }}</span>
            <div class="flex gap-2">
              <button class="text-xs font-semibold text-primary hover:underline" @click="editTechnician(item)">Editar</button>
              <button class="text-xs font-semibold text-destructive hover:underline" @click="removeTechnician(item.id)">Borrar</button>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
