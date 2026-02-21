<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue";
import { Plus, Check } from "lucide-vue-next";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import productsService, {
  type CreateProductPayload,
  type ProductBrand,
  type ProductCategory,
  type ProductFamily,
  type ProductSubfamily,
} from "@/services/productsService";

import { useFetchState } from "@/composables/useFetchState";
import StatefulDialog from "@/components/ui/StatefulDialog.vue";

interface Props {
  options: {
    brands: ProductBrand[];
    categories: ProductCategory[];
    subfamilies: ProductSubfamily[];
    families: ProductFamily[];
  };
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: "product-created"): void;
}>();

const open = ref(false);

const createProductState = reactive(useFetchState(productsService.createProduct));
const errorMessage = ref<string | null>(null);

const emptyForm = () => ({
  external_id: "",
  name: "",
  brand_id: "",
  category_id: "",
  subfamily_id: "",
  family_id: "",
  model_sku: "",
  price_ars: "",
  description: "",
  technical_specs: [] as string[],

});

const form = reactive(emptyForm());

const filteredSubfamilies = computed(() => {
  if (!form.family_id) return [];
  const familyIdNum = Number(form.family_id);
  // Assuming subfamily has family_id based on user request example
  return props.options.subfamilies.filter((s: any) => s.family_id === familyIdNum);
});

watch(
  () => form.family_id,
  () => {
    // Reset subfamily if family changes
    // Only reset if the current subfamily doesn't belong to the new family? 
    // Simplest is to just reset it effectively forcing re-selection, which is safer.
    form.subfamily_id = "";
  }
);

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

const normalizeId = (value: string | number | null | undefined): number | null => {
  if (value === "" || value === "all" || value === null || value === undefined) return null;
  if (typeof value === "number") return value;
  const numeric = Number(value);
  return Number.isNaN(numeric) ? null : numeric;
};

const resetForm = () => {
  Object.assign(form, emptyForm());
  createProductState.reset();
  errorMessage.value = null;
};

const handleOpenChange = (value: boolean) => {
  open.value = value;
  if (!value) {
    // Reset form after a short delay if closed, or immediately if you prefer
    setTimeout(() => {
        if (createProductState.success) {
             resetForm();
        }
    }, 300);
  }
};

const submitForm = async () => {
  errorMessage.value = null;

  if (!form.external_id.trim() || !form.name.trim()) {
    errorMessage.value = "Completá el ID externo y el nombre.";
    return;
  }
  if (!form.brand_id || !form.category_id || !form.subfamily_id) {
    errorMessage.value = "Seleccioná marca, categoría y subfamilia.";
    return;
  }

  const payload: CreateProductPayload = {
    external_id: form.external_id.trim(),
    name: form.name.trim(),
    brand_id: normalizeId(form.brand_id) ?? Number(form.brand_id), // Fallback if number
    category_id: normalizeId(form.category_id) ?? Number(form.category_id),
    subfamily_id: normalizeId(form.subfamily_id) ?? Number(form.subfamily_id),
  };

  if (form.model_sku.trim()) payload.model_sku = form.model_sku.trim();
  
  if (form.price_ars !== "" && form.price_ars !== null && form.price_ars !== undefined) {
    const numeric = Number(form.price_ars);
    if (Number.isNaN(numeric)) {
      errorMessage.value = "El precio debe ser un número válido.";
      return;
    }
    payload.price_ars = numeric;
  }
  if (form.description.trim()) payload.description = form.description.trim();
  if (form.technical_specs.length > 0) payload.technical_specs = form.technical_specs;


  try {
    await createProductState.execute(payload);
    emit("product-created");
  } catch (error: any) {
    // Error is handled by StatefulDialog, but we can log or do extra logic if needed
    // For now, no extra logic needed as StatefulDialog shows the error view
  }
};
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogTrigger as-child>
      <Button variant="default">
          <Plus class="mr-2 h-4 w-4" /> Nuevo Producto
      </Button>
    </DialogTrigger>
    <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>Nuevo Producto</DialogTitle>
        <DialogDescription>
          Complete el formulario para agregar un nuevo producto al catálogo.
        </DialogDescription>
      </DialogHeader>

      <StatefulDialog :state="createProductState">
        <template #title>Guardando producto...</template>
        
        <template #success>
             <div class="flex flex-col h-full w-full">
                <!-- Centered Success Message -->
                <div class="flex-1 flex flex-col items-center justify-center space-y-6">
                    <div class="relative flex items-center justify-center">
                        <div class="bg-green-100 rounded-full p-4 animate-in zoom-in duration-300">
                             <Check class="h-16 w-16 text-green-600" />
                        </div>
                    </div>
                    <div class="text-center space-y-1">
                        <h3 class="text-xl font-semibold text-green-600">¡Producto creado exitosamente!</h3>
                         <p class="text-muted-foreground text-sm max-w-[300px]">
                            El producto ha sido agregado al catálogo correctamente.
                        </p>
                    </div>
                </div>
                
                 <!-- Bottom Right Buttons -->
                <div class="flex justify-end gap-3 mt-auto pt-6">
                    <Button variant="outline" @click="handleOpenChange(false)">Cerrar</Button>
                    <Button @click="resetForm">Crear otro producto</Button>
                </div>
            </div>
        </template>

        <form @submit.prevent="submitForm" class="space-y-3 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
               <div class="space-y-1">
                   <label class="text-sm font-medium">ID Externo *</label>
                   <input v-model="form.external_id" type="text" placeholder="ID externo" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
               </div>
               <div class="space-y-1">
                    <label class="text-sm font-medium">Nombre *</label>
                    <input v-model="form.name" type="text" placeholder="Nombre" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
               </div>
            </div>
   
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Marca *</label>
                    <select v-model="form.brand_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                      <option disabled value="">Seleccionar...</option>
                      <option v-for="brand in options.brands" :key="brand.id" :value="brand.id">
                        {{ resolveOptionLabel(brand) }}
                      </option>
                    </select>
                </div>
                 <div class="space-y-1">
                    <label class="text-sm font-medium">Modelo/SKU</label>
                    <input v-model="form.model_sku" type="text" placeholder="Modelo/SKU" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
                </div>
            </div>
   
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1">
                     <label class="text-sm font-medium">Categoría *</label>
                     <select v-model="form.category_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                      <option disabled value="">Seleccionar...</option>
                      <option v-for="category in options.categories" :key="category.id" :value="category.id">
                        {{ resolveOptionLabel(category) }}
                      </option>
                    </select>
                </div>
                 <div class="space-y-1">
                     <label class="text-sm font-medium">Familia *</label>
                     <select v-model="form.family_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                      <option value="">Seleccionar...</option>
                      <option v-for="family in options.families" :key="family.id" :value="family.id">
                        {{ resolveOptionLabel(family) }}
                      </option>
                    </select>
                </div>
                <div class="space-y-1">
                     <label class="text-sm font-medium">Subfamilia *</label>
                     <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div>
                                     <select 
                                        v-model="form.subfamily_id" 
                                        :disabled="!form.family_id"
                                        :class="{'opacity-50 cursor-not-allowed': !form.family_id}"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                     >
                                      <option disabled value="">Seleccionar...</option>
                                      <option v-for="subfamily in filteredSubfamilies" :key="subfamily.id" :value="subfamily.id">
                                        {{ resolveOptionLabel(subfamily) }}
                                      </option>
                                    </select>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent v-if="!form.family_id" side="bottom">
                                <p>Seleccione una familia primero</p>
                            </TooltipContent>
                        </Tooltip>
                     </TooltipProvider>
                </div>
            </div>
   
            <div class="space-y-2">
                <label class="text-sm font-medium">Precio ARS</label>
                <input v-model="form.price_ars" type="number" placeholder="0.00" class="w-full rounded-md border border-input px-3 py-2 text-sm" />
            </div>
   
             <div class="space-y-2">
                <label class="text-sm font-medium">Descripción</label>
                <textarea v-model="form.description" rows="3" class="w-full rounded-md border border-input px-3 py-2 text-sm"></textarea>
             </div>
   
            <div class="space-y-2">
              <label class="text-sm font-medium">Especificaciones Técnicas</label>
              <!-- TODO mejorar implementacion de especifcaciones técnicas -->
              <TagsInput v-model="form.technical_specs">
                <TagsInputItem v-for="item in form.technical_specs" :key="item" :value="item">
                  <TagsInputItemText />
                  <TagsInputItemDelete />
                </TagsInputItem>
                <TagsInputInput placeholder="Agregar especificación (Enter)" />
              </TagsInput>
            </div>
   
   
             <div v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</div>
   
            <DialogFooter>
                 <Button type="button" variant="outline" @click="handleOpenChange(false)">Cancelar</Button>
                <Button type="submit">Guardar Producto</Button>
            </DialogFooter>
        </form>
      </StatefulDialog>
    </DialogContent>
  </Dialog>
</template>
