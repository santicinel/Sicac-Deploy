<script setup lang="ts">
import { h, onMounted, reactive, ref, watch } from "vue";
import type { ColumnDef } from "@tanstack/vue-table";
import DataList from "@/components/ui/data-list/DataList.vue";
import ProductCard from "@/components/admin/ProductCard.vue";
import ProductDialog from "@/components/admin/ProductDialog.vue";
import productsService, {
  type Product,
  type ProductBrand,
  type ProductCategory,
  type ProductFamily,
  type ProductListParams,
  type ProductSubfamily,
} from "@/services/productsService";
import type { PaginationData } from "@/interfaces";

type SelectFilterValue = "all" | string | number;

const products = ref<Product[]>([]);
const pagination = ref<PaginationData | null>(null);
const loading = ref(false);
const filtersLoading = ref(false);
const deletingProductId = ref<number | string | null>(null);

const page = ref(1);
const perPage = ref(10);

const filters = reactive({
  search: "",
  brand_id: "all" as SelectFilterValue,
  category_id: "all" as SelectFilterValue,
  subfamily_id: "all" as SelectFilterValue,
  family_id: "all" as SelectFilterValue,
});

const options = reactive({
  brands: [] as ProductBrand[],
  categories: [] as ProductCategory[],
  subfamilies: [] as ProductSubfamily[],
  families: [] as ProductFamily[],
});

const resolveOptionLabel = (option?: {
  id?: number | string;
  name?: string;
  nombre?: string;
  label?: string;
} | null) => {
  if (!option) return "Sin nombre";
  return (
    option.name ??
    option.nombre ??
    option.label ??
    (option.id !== undefined ? String(option.id) : "Sin nombre")
  );
};

const normalizeId = (value: SelectFilterValue | "" | null) => {
  if (value === "" || value === "all" || value === null) return null;
  if (typeof value === "number") return value;
  const numeric = Number(value);
  return Number.isNaN(numeric) ? value : numeric;
};



const formatPrice = (value?: number | string | null) => {
  if (value === null || value === undefined) return "-";
  const numeric = typeof value === "string" ? Number(value) : value;
  if (Number.isNaN(numeric)) return String(value);
  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  }).format(numeric);
};

const removeProduct = async (item: Product) => {
  const id = item.id;
  if (id === undefined || id === null) return;

  const confirmed = window.confirm(
    `¿Eliminar el producto "${item.name}"? Esta accion no se puede deshacer.`
  );
  if (!confirmed) return;

  deletingProductId.value = id;
  try {
    await productsService.deleteProduct(id);
    await fetchProducts();
    if (!products.value.length && page.value > 1) {
      page.value -= 1;
    }
  } finally {
    deletingProductId.value = null;
  }
};

const columns: ColumnDef<Product>[] = [
  {
    accessorKey: "external_id",
    header: "ID externo",
    cell: ({ row }) => row.original.external_id ?? "-",
  },
  {
    accessorKey: "name",
    header: "Nombre",
    cell: ({ row }) => row.original.name ?? "-",
  },
  {
    id: "brand",
    header: "Marca",
    cell: ({ row }) =>
      resolveOptionLabel(row.original.brand ?? { id: row.original.brand_id }),
  },
  {
    id: "category",
    header: "Categoría",
    cell: ({ row }) =>
      resolveOptionLabel(row.original.category ?? { id: row.original.category_id }),
  },
  {
    id: "subfamily",
    header: "Subfamilia",
    cell: ({ row }) =>
      resolveOptionLabel(row.original.subfamily ?? { id: row.original.subfamily_id }),
  },
  {
    id: "price",
    header: "Precio",
    cell: ({ row }) => formatPrice(row.original.price_ars),
  },
  {
    id: "actions",
    header: "Acciones",
    cell: ({ row }) => {
      const item = row.original;
      const isDeleting = deletingProductId.value === item.id;
      return h(
        "button",
        {
          class:
            "rounded-md border px-3 py-1 text-xs font-semibold text-destructive hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-60",
          disabled: isDeleting,
          onClick: () => {
            void removeProduct(item);
          },
        },
        isDeleting ? "Eliminando..." : "Eliminar"
      );
    },
  },
];

const getProductKey = (item: Product, index: number) =>
  item.id ?? item.external_id ?? index;

const buildParams = (): ProductListParams => {
  const params: ProductListParams = {
    page: page.value,
    per_page: perPage.value,
  };

  if (filters.search.trim()) params.search = filters.search.trim();

  const brandId = normalizeId(filters.brand_id);
  if (brandId !== null) params.brand_id = brandId;

  const categoryId = normalizeId(filters.category_id);
  if (categoryId !== null) params.category_id = categoryId;

  const subfamilyId = normalizeId(filters.subfamily_id);
  if (subfamilyId !== null) params.subfamily_id = subfamilyId;

  const familyId = normalizeId(filters.family_id);
  if (familyId !== null) params.family_id = familyId;

  return params;
};

const fetchProducts = async () => {
  loading.value = true;
  try {
    const response = await productsService.getProduct(buildParams());
    const payload = response.data;
    products.value = Array.isArray(payload.data) ? payload.data : [];
    pagination.value = payload.meta && payload.links ? {
      meta: payload.meta,
      links: payload.links,
    } : null;
  } finally {
    loading.value = false;
  }
};

const loadFilters = async () => {
  filtersLoading.value = true;
  try {
    const { brands, categories, subfamilies, families } = await productsService.getFilters();
    options.brands = brands;
    options.categories = categories;
    options.subfamilies = subfamilies;
    options.families = families;
  } finally {
    filtersLoading.value = false;
  }
};

watch(
  () => [
    filters.search,
    filters.brand_id,
    filters.category_id,
    filters.subfamily_id,
    filters.family_id,
    perPage.value,
  ],
  () => {
    if (page.value !== 1) {
      page.value = 1;
      return;
    }
    void fetchProducts();
  }
);

watch(page, () => {
  void fetchProducts();
});

onMounted(async () => {
  await Promise.all([loadFilters(), fetchProducts()]);
});
</script>

<template>
  <div class="p-6 space-y-6">
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold tracking-tight">Productos</h1>
      </div>
      <div>
        <ProductDialog :options="options" @product-created="fetchProducts" />
      </div>
    </header>

    <div class="space-y-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex w-full flex-col gap-3 md:flex-row">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Buscar por nombre o SKU"
            class="w-full md:max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm"
          />
          <select
            v-model="filters.brand_id"
            class="w-full md:max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm"
            :disabled="filtersLoading"
          >
            <option value="all">Todas las marcas</option>
            <option v-for="brand in options.brands" :key="brand.id" :value="brand.id">
              {{ resolveOptionLabel(brand) }}
            </option>
          </select>
          <select
            v-model="filters.family_id"
            class="w-full md:max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm"
            :disabled="filtersLoading"
          >
            <option value="all">Todas las familias</option>
            <option v-for="family in options.families" :key="family.id" :value="family.id">
              {{ resolveOptionLabel(family) }}
            </option>
          </select>
        </div>
        <div class="flex w-full flex-col gap-3 md:flex-row md:justify-end">
          <select
            v-model="filters.category_id"
            class="w-full md:max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm"
            :disabled="filtersLoading"
          >
            <option value="all">Todas las categorías</option>
            <option v-for="category in options.categories" :key="category.id" :value="category.id">
              {{ resolveOptionLabel(category) }}
            </option>
          </select>
          <select
            v-model="filters.subfamily_id"
            class="w-full md:max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm"
            :disabled="filtersLoading"
          >
            <option value="all">Todas las subfamilias</option>
            <option v-for="subfamily in options.subfamilies" :key="subfamily.id" :value="subfamily.id">
              {{ resolveOptionLabel(subfamily) }}
            </option>
          </select>
          <select
            v-model.number="perPage"
            class="w-full md:max-w-[140px] rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            <option :value="10">10 / pág</option>
            <option :value="20">20 / pág</option>
            <option :value="50">50 / pág</option>
          </select>
        </div>
      </div>

      <div
        v-if="loading"
        class="rounded-lg border bg-card text-card-foreground shadow-sm p-6 text-sm text-muted-foreground"
      >
        Cargando productos...
      </div>

      <DataList
        v-else
        :columns="columns"
        :data="products"
        :card-component="ProductCard"
        :card-props="{ onDelete: removeProduct, deletingId: deletingProductId }"
        :pagination="pagination"
        :get-item-key="getProductKey"
        @page-change="page = $event"
      />
    </div>
  </div>
</template>
