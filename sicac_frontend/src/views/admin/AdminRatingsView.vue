<script setup lang="ts">
import { computed, ref } from "vue";
import { Icon } from "@iconify/vue";

type RatingType = "technicians" | "products";

interface TechnicianRating {
  id: string;
  name: string;
  average: number;
  total: number;
  lastReview: string;
  lastClientRating: number;
  lastClientNotes: string;
}

interface ProductRating {
  sku: string;
  name: string;
  average: number;
  total: number;
  category: string;
}

const ratingType = ref<RatingType>("technicians");
const searchQuery = ref("");
const minRating = ref<number | "all">("all");
const categoryFilter = ref("all");

const technicianRatings = ref<TechnicianRating[]>([
  {
    id: "tech-1",
    name: "Lucía Martínez",
    average: 4.6,
    total: 18,
    lastReview: "2026-01-12",
    lastClientRating: 5,
    lastClientNotes: "Cliente colaboró con acceso y suministro eléctrico.",
  },
  {
    id: "tech-2",
    name: "Iván Ruiz",
    average: 3.9,
    total: 11,
    lastReview: "2026-01-09",
    lastClientRating: 4,
    lastClientNotes: "Cliente presente, pidi? ajuste de horario.",
  },
  {
    id: "tech-3",
    name: "Sofía Díaz",
    average: 4.2,
    total: 7,
    lastReview: "2026-01-08",
    lastClientRating: 5,
    lastClientNotes: "Cliente conforme con la explicación final.",
  },
]);

const productRatings = ref<ProductRating[]>([
  {
    sku: "CAM-104",
    name: "Cámara IP X1",
    average: 4.4,
    total: 26,
    category: "CCTV",
  },
  {
    sku: "ALM-220",
    name: "Panel alarma V3",
    average: 3.7,
    total: 12,
    category: "Alarmas",
  },
  {
    sku: "ROU-700",
    name: "Router WiFi Z2",
    average: 4.1,
    total: 9,
    category: "Redes",
  },
]);

const filteredTechnicians = computed(() => {
  return technicianRatings.value.filter((item) => {
    const matchesSearch =
      !searchQuery.value.trim() ||
      item.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      item.id.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesRating =
      minRating.value === "all" || item.average >= Number(minRating.value);
    return matchesSearch && matchesRating;
  });
});

const filteredProducts = computed(() => {
  return productRatings.value.filter((item) => {
    const matchesSearch =
      !searchQuery.value.trim() ||
      item.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      item.sku.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesRating =
      minRating.value === "all" || item.average >= Number(minRating.value);
    const matchesCategory =
      categoryFilter.value === "all" || item.category === categoryFilter.value;
    return matchesSearch && matchesRating && matchesCategory;
  });
});

const productCategories = computed(() => {
  const categories = new Set(productRatings.value.map((item) => item.category));
  return Array.from(categories);
});
</script>

<template>
  <div class="space-y-6 p-6">
    <header class="space-y-2">
      <h1 class="text-3xl font-bold tracking-tight">Puntajes</h1>
      <p class="text-muted-foreground">
        Seguimiento de calificaciones de técnicos y productos.
      </p>
    </header>

    <div class="flex flex-wrap items-center gap-3 rounded-lg border bg-card p-4 shadow-sm">
      <div class="flex items-center gap-2">
        <button
          class="rounded-md px-3 py-2 text-xs font-semibold transition"
          :class="ratingType === 'technicians' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
          @click="ratingType = 'technicians'"
        >
          Técnicos
        </button>
        <button
          class="rounded-md px-3 py-2 text-xs font-semibold transition"
          :class="ratingType === 'products' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
          @click="ratingType = 'products'"
        >
          Productos
        </button>
      </div>
      <div class="relative flex-1 min-w-[200px]">
        <Icon icon="mdi:magnify" class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nombre o ID"
          class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        />
      </div>
      <select
        v-model="minRating"
        class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
      >
        <option value="all">Todos los puntajes</option>
        <option :value="4">4 o más</option>
        <option :value="3">3 o más</option>
        <option :value="2">2 o más</option>
      </select>
      <select
        v-if="ratingType === 'products'"
        v-model="categoryFilter"
        class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
      >
        <option value="all">Todas las categorías</option>
        <option v-for="category in productCategories" :key="category" :value="category">
          {{ category }}
        </option>
      </select>
    </div>

    <section v-if="ratingType === 'technicians'" class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="grid grid-cols-6 gap-4 border-b px-6 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        <span>Técnico</span>
        <span>Puntaje promedio</span>
        <span>Reseñas</span>
        <span>Última</span>
        <span>Puntaje cliente</span>
        <span>Comentario cliente</span>
      </div>
      <div v-if="filteredTechnicians.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
        No hay técnicos con esos filtros.
      </div>
      <div
        v-for="item in filteredTechnicians"
        :key="item.id"
        class="grid grid-cols-6 gap-4 px-6 py-4 text-sm border-b last:border-b-0"
      >
        <div>
          <p class="font-medium">{{ item.name }}</p>
          <p class="text-xs text-muted-foreground">{{ item.id }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="font-semibold">{{ item.average.toFixed(1) }}</span>
          <div class="flex items-center text-amber-500">
            <Icon v-for="star in 5" :key="star" icon="mdi:star" class="h-4 w-4" :class="star <= Math.round(item.average) ? '' : 'opacity-30'" />
          </div>
        </div>
        <span>{{ item.total }}</span>
        <span>{{ item.lastReview }}</span>
        <span>{{ item.lastClientRating }} / 5</span>
        <span class="text-xs text-muted-foreground">{{ item.lastClientNotes }}</span>
      </div>
    </section>

    <section v-else class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="grid grid-cols-5 gap-4 border-b px-6 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        <span>Producto</span>
        <span>Categoría</span>
        <span>Puntaje promedio</span>
        <span>Reseñas</span>
        <span>SKU</span>
      </div>
      <div v-if="filteredProducts.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
        No hay productos con esos filtros.
      </div>
      <div
        v-for="item in filteredProducts"
        :key="item.sku"
        class="grid grid-cols-5 gap-4 px-6 py-4 text-sm border-b last:border-b-0"
      >
        <div>
          <p class="font-medium">{{ item.name }}</p>
        </div>
        <span>{{ item.category }}</span>
        <div class="flex items-center gap-2">
          <span class="font-semibold">{{ item.average.toFixed(1) }}</span>
          <div class="flex items-center text-amber-500">
            <Icon v-for="star in 5" :key="star" icon="mdi:star" class="h-4 w-4" :class="star <= Math.round(item.average) ? '' : 'opacity-30'" />
          </div>
        </div>
        <span>{{ item.total }}</span>
        <span class="text-xs text-muted-foreground">{{ item.sku }}</span>
      </div>
    </section>
  </div>
</template>
