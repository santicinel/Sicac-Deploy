<script setup lang="ts">
import { Icon } from "@iconify/vue";
import { ref, watch } from "vue";
import { useTechnicianDailyItinerary } from "@/composables/useTechnicianDailyItinerary";

const COLLAPSED_STORAGE_KEY = "sicac_sidebar_itinerary_collapsed";

const readCollapsedPreference = () => {
  if (typeof window === "undefined") return false;
  return window.localStorage.getItem(COLLAPSED_STORAGE_KEY) === "1";
};

const isCollapsed = ref(readCollapsedPreference());

watch(isCollapsed, (value) => {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(COLLAPSED_STORAGE_KEY, value ? "1" : "0");
});

const toggleCollapsed = () => {
  isCollapsed.value = !isCollapsed.value;
};

const {
  loading,
  loadError,
  todaysTasks,
  summary,
  displayText,
  formatTime,
} = useTechnicianDailyItinerary();
</script>

<template>
  <section class="px-2 pb-2 group-data-[collapsible=icon]:hidden">
    <div class="rounded-lg border border-sidebar-border/80 bg-sidebar-accent/25 p-3">
      <div class="flex items-start justify-between gap-2">
        <div>
          <router-link
            to="/technician/itinerary"
            class="text-[11px] font-semibold uppercase tracking-wide text-sidebar-foreground/80 hover:underline"
          >
            Itinerario del dia
          </router-link>
          <p class="text-xs text-sidebar-foreground/70">
            Resumen de tareas de hoy
          </p>
        </div>

        <div class="flex items-center gap-1">
          <router-link
            to="/technician/itinerary"
            class="rounded p-1 text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground"
            title="Ver en pantalla completa"
          >
            <Icon icon="mdi:open-in-new" class="h-4 w-4" />
          </router-link>
          <button
            type="button"
            class="rounded p-1 text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground"
            :title="isCollapsed ? 'Expandir tareas' : 'Comprimir tareas'"
            @click="toggleCollapsed"
          >
            <Icon :icon="isCollapsed ? 'mdi:chevron-down' : 'mdi:chevron-up'" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div class="mt-3 grid grid-cols-3 gap-2 text-[11px]">
        <div class="rounded-md border border-sidebar-border/70 bg-sidebar px-2 py-1">
          <p class="font-semibold leading-none">{{ summary.total }}</p>
          <p class="mt-1 text-sidebar-foreground/70">Total</p>
        </div>
        <div class="rounded-md border border-sidebar-border/70 bg-sidebar px-2 py-1">
          <p class="font-semibold leading-none">{{ summary.withTime }}</p>
          <p class="mt-1 text-sidebar-foreground/70">Con hora</p>
        </div>
        <div class="rounded-md border border-sidebar-border/70 bg-sidebar px-2 py-1">
          <p class="font-semibold leading-none">{{ summary.withoutTime }}</p>
          <p class="mt-1 text-sidebar-foreground/70">Sin hora</p>
        </div>
      </div>

      <p v-if="isCollapsed" class="mt-3 text-xs text-sidebar-foreground/70">
        Lista comprimida. Usa la flecha para ver tareas.
      </p>

      <div v-else class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
        <p v-if="loading" class="text-xs text-sidebar-foreground/70">
          Cargando itinerario...
        </p>
        <p v-else-if="loadError" class="text-xs text-destructive">
          No se pudo cargar el itinerario.
        </p>
        <p v-else-if="todaysTasks.length === 0" class="text-xs text-sidebar-foreground/70">
          No tenes visitas programadas para hoy.
        </p>

        <router-link
          v-for="item in todaysTasks"
          :key="item.id"
          :to="{ path: '/technician/itinerary', query: { visit: String(item.id) } }"
          class="block rounded-md border border-sidebar-border/70 bg-sidebar px-2 py-2 hover:bg-sidebar-accent"
        >
          <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold">{{ formatTime(item.scheduled_visit_time) }}</p>
            <p class="text-[10px] text-sidebar-foreground/60">#{{ item.id }}</p>
          </div>
          <p class="mt-1 truncate text-xs font-medium">
            {{ displayText(item.subject, "Sin asunto") }}
          </p>
          <p class="mt-1 truncate text-[11px] text-sidebar-foreground/70">
            {{ displayText(item.requesting_user?.name, "Cliente sin nombre") }}
          </p>
        </router-link>
      </div>
    </div>
  </section>
</template>
