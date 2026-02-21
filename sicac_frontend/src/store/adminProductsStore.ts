import { defineStore } from "pinia";
import { ref } from "vue";
import { dataService, type CatalogProduct } from "@/services/DataService";

export interface AdminProduct {
  id: string;
  name: string;
  category: string;
  price: number;
  description: string;
  active: boolean;
}

const STORAGE_KEY = "sicac_admin_products";

const loadProducts = (): AdminProduct[] => {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    return JSON.parse(raw) as AdminProduct[];
  } catch {
    return [];
  }
};

const saveProducts = (items: AdminProduct[]) => {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
};

const mapCatalogProduct = (product: CatalogProduct): AdminProduct => ({
  id: product.ID,
  name: product.Nombre,
  category: product.familia,
  price: product["Precio (ARS)"],
  description: product.Texto_RAG || product["Modelo/SKU"],
  active: true,
});

export const useAdminProductsStore = defineStore("adminProducts", () => {
  const items = ref<AdminProduct[]>(loadProducts());
  const loaded = ref(false);

  const initialize = async () => {
    if (loaded.value) return;
    if (items.value.length) {
      loaded.value = true;
      return;
    }
    const result = await dataService.getProducts(1, 40, {});
    items.value = result.items.map(mapCatalogProduct);
    saveProducts(items.value);
    loaded.value = true;
  };

  const add = (payload: Omit<AdminProduct, "id">) => {
    const next = {
      ...payload,
      id: `prod-${Date.now()}`,
    };
    items.value = [next, ...items.value];
    saveProducts(items.value);
  };

  const update = (id: string, payload: Omit<AdminProduct, "id">) => {
    items.value = items.value.map((item) =>
      item.id === id ? { ...payload, id } : item
    );
    saveProducts(items.value);
  };

  const remove = (id: string) => {
    items.value = items.value.filter((item) => item.id !== id);
    saveProducts(items.value);
  };

  return {
    items,
    initialize,
    add,
    update,
    remove,
  };
});
