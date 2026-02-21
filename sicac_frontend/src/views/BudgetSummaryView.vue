<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useCartStore } from '@/store/cartStore';
import { storeToRefs } from 'pinia';
import { Icon } from "@iconify/vue";
import { useRouter } from 'vue-router';
import { useAdminSettingsStore } from '@/store/adminSettingsStore';

const cartStore = useCartStore();
const settingsStore = useAdminSettingsStore();
const { items, includeTechnician, totalCost, subtotalCost, technicianFee, laborRate, estimatedLaborHours } = storeToRefs(cartStore);
const router = useRouter();

const laborHoursLabel = computed(() => {
    if (estimatedLaborHours.value <= 0) return 'sin estimar';
    return `${estimatedLaborHours.value.toFixed(1)} h`;
});

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(price);
};

const printBudget = () => {
    window.print();
};

onMounted(async () => {
    await settingsStore.syncLaborRate(true);
});
</script>

<template>
    <div class="p-8 max-w-4xl mx-auto bg-white min-h-screen">
        <div class="flex justify-between items-start border-b pb-8 mb-8">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="bg-primary/10 p-2 rounded-lg">
                        <Icon icon="mdi:shield-check" class="h-8 w-8 text-primary" />
                    </div>
                    <h1 class="text-2xl font-bold text-primary">CEA Insumos</h1>
                </div>
                <p class="text-sm text-muted-foreground">Tu aliado en seguridad electronica</p>
                <p class="text-sm text-muted-foreground">Buenos Aires 432, Firmat</p>
                <p class="text-sm text-muted-foreground">contacto@ceainsumos.com</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-semibold mb-1">Presupuesto</h2>
                <p class="text-sm text-muted-foreground">Fecha: {{ new Date().toLocaleDateString('es-ES') }}</p>
                <p class="text-sm text-muted-foreground">Validez: 15 dias</p>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="font-semibold mb-4 text-lg">Detalle de Productos</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-primary/20">
                        <th class="text-left py-3 font-semibold">Producto</th>
                        <th class="text-left py-3 font-semibold">Categoria</th>
                        <th class="text-right py-3 font-semibold">Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b">
                        <td class="py-4">
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.description }}</p>
                        </td>
                        <td class="py-4 capitalize text-muted-foreground">
                            {{ item.category === 'camera' ? 'Camara' : item.category === 'alarm' ? 'Alarma' : 'Sensor' }}
                        </td>
                        <td class="py-4 text-right">{{ formatPrice(item.price) }}</td>
                    </tr>
                    <tr v-if="items.length === 0">
                        <td colspan="3" class="py-8 text-center text-muted-foreground">No hay productos seleccionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="includeTechnician" class="mb-8 bg-muted/30 p-4 rounded-lg border">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-semibold">Mano de obra estimada</h3>
                    <p class="text-sm text-muted-foreground">
                        Tiempo estimado: {{ laborHoursLabel }} | Tarifa: {{ formatPrice(laborRate) }}/hora
                    </p>
                </div>
                <span class="font-medium">{{ formatPrice(technicianFee) }}</span>
            </div>
        </div>

        <div class="flex flex-col items-end gap-2 border-t pt-4 mb-12">
            <div class="grid grid-cols-[1fr_auto] gap-4 w-full max-w-sm text-sm">
                <span class="text-muted-foreground">Subtotal:</span>
                <span class="text-right tabular-nums whitespace-nowrap">{{ formatPrice(subtotalCost) }}</span>
            </div>
            <div class="grid grid-cols-[1fr_auto] gap-4 w-full max-w-sm text-sm" v-if="includeTechnician">
                <span class="text-muted-foreground">Mano de obra estimada ({{ laborHoursLabel }}):</span>
                <span class="text-right tabular-nums whitespace-nowrap">{{ formatPrice(technicianFee) }}</span>
            </div>
            <div class="grid grid-cols-[1fr_auto] gap-4 w-full max-w-sm text-xl font-bold border-t border-dashed pt-2 mt-2">
                <span>Total:</span>
                <span class="text-right text-primary tabular-nums whitespace-nowrap">{{ formatPrice(totalCost) }}</span>
            </div>
        </div>

        <div class="flex justify-between print:hidden">
            <button @click="router.back()" class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground">
                <Icon icon="mdi:arrow-left" class="mr-2 h-4 w-4" />
                Volver
            </button>
            <div class="flex gap-4">
                <button @click="printBudget" class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground">
                    <Icon icon="mdi:printer" class="mr-2 h-4 w-4" />
                    Imprimir / PDF
                </button>
                <button class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90">
                    <Icon icon="mdi:email-outline" class="mr-2 h-4 w-4" />
                    Enviar por email
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .print\:hidden {
        display: none;
    }
}
</style>
