import { defineStore } from "pinia";
import { ref } from "vue";

export type AvailabilitySlot = "morning" | "afternoon";

export interface Technician {
  id: string;
  firstName: string;
  lastName: string;
  dni: string;
  email: string;
  password: string;
  phone: string;
  address: string;
  city: string;
  availabilitySlots: AvailabilitySlot[];
  availabilityDate?: string;
}

const STORAGE_KEY = "sicac_admin_technicians";

const normalizeAvailabilitySlots = (value: unknown): AvailabilitySlot[] => {
  if (!Array.isArray(value)) return [];

  const unique = new Set<AvailabilitySlot>();
  for (const entry of value) {
    if (entry === "morning" || entry === "afternoon") {
      unique.add(entry);
    }
  }
  return Array.from(unique);
};

const DEFAULT_TECHNICIANS: Technician[] = [
  {
    id: "tech-1",
    firstName: "Lucas",
    lastName: "Fernandez",
    dni: "30222333",
    email: "lucas.fernandez@sicac.com",
    password: "tecnico123",
    phone: "+54 9 3465 000001",
    address: "Mitre 123",
    city: "Firmat",
    availabilitySlots: ["morning", "afternoon"],
    availabilityDate: "2026-01-10",
  },
  {
    id: "tech-2",
    firstName: "Tecnico",
    lastName: "Generico",
    dni: "30111222",
    email: "generico@sicac.com",
    password: "tecnico123",
    phone: "+54 9 3465 000002",
    address: "San Martin 456",
    city: "Firmat",
    availabilitySlots: ["afternoon"],
    availabilityDate: "2026-01-12",
  },
];

const normalizeTechnicians = (items: Array<Partial<Technician>>): Technician[] =>
  items.map((item, index) => {
    const slotsFromStorage = normalizeAvailabilitySlots(item.availabilitySlots);
    const fallbackSlots: AvailabilitySlot[] =
      index % 2 === 0 ? ["morning", "afternoon"] : ["morning"];

    return {
      id: item.id ?? `tech-${Date.now()}-${index}`,
      firstName: item.firstName ?? "",
      lastName: item.lastName ?? "",
      dni: item.dni ?? "",
      email: item.email ?? "",
      password: item.password ?? "",
      phone: item.phone ?? "",
      address: item.address ?? "",
      city: item.city ?? "",
      availabilitySlots: slotsFromStorage.length ? slotsFromStorage : fallbackSlots,
      availabilityDate: item.availabilityDate ?? "",
    };
  });

const mergeDefaults = (items: Array<Partial<Technician>>) => {
  const byId = new Map(items.map((item) => [item.id, item]));
  for (const tech of DEFAULT_TECHNICIANS) {
    if (!byId.has(tech.id)) {
      byId.set(tech.id, tech);
    }
  }
  return Array.from(byId.values());
};

const loadTechnicians = (): Technician[] => {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as Array<Partial<Technician>>;
    return normalizeTechnicians(mergeDefaults(parsed));
  } catch {
    return [];
  }
};

const saveTechnicians = (items: Technician[]) => {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
};

export const useAdminTechniciansStore = defineStore("adminTechnicians", () => {
  const items = ref<Technician[]>(
    loadTechnicians().length ? loadTechnicians() : DEFAULT_TECHNICIANS
  );

  saveTechnicians(items.value);

  const add = (payload: Omit<Technician, "id">) => {
    const next = {
      ...payload,
      id: `tech-${Date.now()}`,
    };
    items.value = [next, ...items.value];
    saveTechnicians(items.value);
  };

  const update = (id: string, payload: Omit<Technician, "id">) => {
    items.value = items.value.map((item) =>
      item.id === id ? { ...payload, id } : item
    );
    saveTechnicians(items.value);
  };

  const remove = (id: string) => {
    items.value = items.value.filter((item) => item.id !== id);
    saveTechnicians(items.value);
  };

  return {
    items,
    add,
    update,
    remove,
  };
});
