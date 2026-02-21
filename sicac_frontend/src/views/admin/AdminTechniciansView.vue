<script setup lang="ts">
import { reactive, ref } from "vue";
import { Icon } from "@iconify/vue";
import {
  useAdminTechniciansStore,
  type AvailabilitySlot,
  type Technician,
} from "@/store/adminTechniciansStore";

const store = useAdminTechniciansStore();

const slotOptions: Array<{ id: AvailabilitySlot; label: string }> = [
  { id: "morning", label: "Mañana (9-13)" },
  { id: "afternoon", label: "Tarde (14-18)" },
];

const emptyForm = (): Omit<Technician, "id"> => ({
  firstName: "",
  lastName: "",
  dni: "",
  email: "",
  password: "",
  phone: "",
  address: "",
  city: "",
  availabilitySlots: [],
  availabilityDate: "",
});

const form = reactive<Omit<Technician, "id">>(emptyForm());
const confirmPassword = ref("");
const isEditing = ref(false);
const editingId = ref<string | null>(null);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const getAvailabilityLabel = (slots: AvailabilitySlot[]) => {
  if (!slots.length) return "--";
  return slotOptions
    .filter((option) => slots.includes(option.id))
    .map((option) => option.label)
    .join(" / ");
};

const resetForm = () => {
  Object.assign(form, emptyForm());
  confirmPassword.value = "";
  isEditing.value = false;
  editingId.value = null;
  showPassword.value = false;
  showConfirmPassword.value = false;
};

const submitForm = () => {
  if (!form.email || !form.firstName || !form.lastName) return;
  if (!form.availabilitySlots.length) {
    window.alert("Selecciona al menos un turno de disponibilidad");
    return;
  }
  if (form.password !== confirmPassword.value) {
    window.alert("Las contrasenas no coinciden");
    return;
  }
  if (isEditing.value && editingId.value) {
    store.update(editingId.value, { ...form });
  } else {
    store.add({ ...form });
  }
  resetForm();
};

const editTechnician = (item: Technician) => {
  Object.assign(form, {
    firstName: item.firstName,
    lastName: item.lastName,
    dni: item.dni,
    email: item.email,
    password: item.password,
    phone: item.phone,
    address: item.address,
    city: item.city,
    availabilitySlots: [...item.availabilitySlots],
    availabilityDate: item.availabilityDate ?? "",
  });
  confirmPassword.value = item.password;
  isEditing.value = true;
  editingId.value = item.id;
  showPassword.value = false;
  showConfirmPassword.value = false;
};
</script>

<template>
  <div class="p-6 space-y-6">
    <header class="flex flex-col gap-2">
      <h1 class="text-3xl font-bold tracking-tight">Tecnicos</h1>
      <p class="text-muted-foreground">
        Administracion de tecnicos.
      </p>
    </header>

    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
      <form
        @submit.prevent="submitForm"
        class="rounded-lg border bg-card text-card-foreground shadow-sm p-5 space-y-4"
      >
        <h2 class="text-base font-semibold">
          {{ isEditing ? "Editar tecnico" : "Nuevo tecnico" }}
        </h2>
        <input
          v-model="form.firstName"
          type="text"
          placeholder="Nombre"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />
        <input
          v-model="form.lastName"
          type="text"
          placeholder="Apellido"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />
        <input
          v-model="form.dni"
          type="text"
          placeholder="DNI"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />
        <input
          v-model="form.email"
          type="email"
          placeholder="Email"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />
        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Contrasena"
            class="w-full rounded-md border border-input px-3 py-2 pr-10 text-sm"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-2 inline-flex items-center text-muted-foreground hover:text-foreground"
            :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
            @click="showPassword = !showPassword"
          >
            <Icon :icon="showPassword ? 'mdi:eye-off-outline' : 'mdi:eye-outline'" class="h-5 w-5" />
          </button>
        </div>
        <div class="relative">
          <input
            v-model="confirmPassword"
            :type="showConfirmPassword ? 'text' : 'password'"
            placeholder="Repetir contrasena"
            class="w-full rounded-md border border-input px-3 py-2 pr-10 text-sm"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-2 inline-flex items-center text-muted-foreground hover:text-foreground"
            :aria-label="showConfirmPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
            @click="showConfirmPassword = !showConfirmPassword"
          >
            <Icon :icon="showConfirmPassword ? 'mdi:eye-off-outline' : 'mdi:eye-outline'" class="h-5 w-5" />
          </button>
        </div>
        <input
          v-model="form.phone"
          type="text"
          placeholder="Telefono"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />
        <input
          v-model="form.address"
          type="text"
          placeholder="Direccion"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />
        <input
          v-model="form.city"
          type="text"
          placeholder="Ciudad"
          class="w-full rounded-md border border-input px-3 py-2 text-sm"
        />

        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Disponibilidad
          </p>
          <div class="space-y-2">
            <label
              v-for="slot in slotOptions"
              :key="slot.id"
              class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
            >
              <input
                v-model="form.availabilitySlots"
                type="checkbox"
                :value="slot.id"
                class="h-4 w-4 rounded border-input"
              />
              <span>{{ slot.label }}</span>
            </label>
          </div>
        </div>

        <div class="flex gap-2">
          <button
            type="submit"
            class="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
          >
            {{ isEditing ? "Guardar" : "Agregar" }}
          </button>
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm"
            @click="resetForm"
          >
            Limpiar
          </button>
        </div>
      </form>

      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="grid grid-cols-7 gap-4 border-b px-6 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          <span>Nombre</span>
          <span>DNI</span>
          <span>Email</span>
          <span>Telefono</span>
          <span>Ciudad</span>
          <span>Disponibilidad</span>
          <span>Acciones</span>
        </div>
        <div v-if="store.items.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
          Todavia no hay tecnicos cargados.
        </div>
        <div
          v-for="item in store.items"
          :key="item.id"
          class="grid grid-cols-7 gap-4 px-6 py-4 text-sm border-b last:border-b-0"
        >
          <span class="font-medium">{{ item.firstName }} {{ item.lastName }}</span>
          <span>{{ item.dni }}</span>
          <span>{{ item.email }}</span>
          <span>{{ item.phone }}</span>
          <span>{{ item.city }}</span>
          <span>{{ getAvailabilityLabel(item.availabilitySlots) }}</span>
          <div class="flex gap-2">
            <button
              class="text-xs font-medium text-primary hover:underline"
              @click="editTechnician(item)"
            >
              Editar
            </button>
            <button
              class="text-xs font-medium text-destructive hover:underline"
              @click="store.remove(item.id)"
            >
              Borrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
