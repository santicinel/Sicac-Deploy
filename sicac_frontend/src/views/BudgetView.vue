<script setup lang="ts">
import { computed, onMounted, ref, unref, watch } from 'vue';
import { useCartStore } from '@/store/cartStore';
import { storeToRefs } from 'pinia';
import { Icon } from "@iconify/vue";
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import budgetService, { type BudgetDocument } from '@/services/budgetService';
import { useAdminSettingsStore } from '@/store/adminSettingsStore';

import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const cartStore = useCartStore();
const settingsStore = useAdminSettingsStore();
const {
    items,
    includeTechnician,
    totalCost,
    subtotalCost,
    technicianFee,
    laborRate,
    laborDescription,
    estimatedLaborHours,
    laborEstimateSummary,
    laborAssumptions,
    laborEstimationLoading,
    laborEstimationError,
    laborEstimateStale,
} = storeToRefs(cartStore);
const router = useRouter();

const budgetHistory = ref<BudgetDocument[]>([]);
const budgetHistoryLoading = ref(false);
const budgetHistoryError = ref('');
const budgetHistoryCollapsed = ref(false);
const budgetHistorySearch = ref('');
type BudgetDatePreset = 'all' | 'today' | 'last_7_days' | 'last_30_days' | 'last_3_months' | 'last_6_months' | 'last_12_months';
const budgetHistoryDatePreset = ref<BudgetDatePreset>('all');

const itemsSignature = computed(() => {
    return items.value.map((item) => item.id).sort().join('|');
});

const laborHoursLabel = computed(() => {
    if (estimatedLaborHours.value <= 0) return 'sin estimar';
    return `${estimatedLaborHours.value.toFixed(1)} h`;
});

const formatPrice = (price: number | string | null | undefined) => {
    const numeric = typeof price === 'string' ? Number(price) : price;
    const safePrice = Number.isFinite(numeric as number) ? Number(numeric) : 0;
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(safePrice);
};

const formatDateTime = (value: string) => {
    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const getDateRangeFromPreset = (preset: BudgetDatePreset): { from: Date; to: Date } | null => {
    if (preset === 'all') return null;

    const now = new Date();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
    const endOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);

    if (preset === 'today') {
        return { from: startOfToday, to: endOfToday };
    }

    if (preset === 'last_7_days') {
        const from = new Date(startOfToday);
        from.setDate(from.getDate() - 6);
        return { from, to: endOfToday };
    }

    if (preset === 'last_30_days') {
        const from = new Date(startOfToday);
        from.setDate(from.getDate() - 29);
        return { from, to: endOfToday };
    }

    if (preset === 'last_3_months') {
        const from = new Date(startOfToday);
        from.setMonth(from.getMonth() - 3);
        return { from, to: endOfToday };
    }

    if (preset === 'last_6_months') {
        const from = new Date(startOfToday);
        from.setMonth(from.getMonth() - 6);
        return { from, to: endOfToday };
    }

    const from = new Date(startOfToday);
    from.setMonth(from.getMonth() - 12);
    return { from, to: endOfToday };
};

const filteredBudgetHistory = computed(() => {
    const query = budgetHistorySearch.value.trim().toLowerCase();
    const range = getDateRangeFromPreset(budgetHistoryDatePreset.value);

    return budgetHistory.value.filter((entry) => {
        if (query) {
            const values = [
                entry.file_name || '',
                String(entry.id),
                String(entry.items_count),
                String(entry.total_amount ?? ''),
            ];
            const matchesText = values.some((value) => value.toLowerCase().includes(query));
            if (!matchesText) return false;
        }

        if (!range) return true;
        const generated = new Date(entry.generated_at);
        if (Number.isNaN(generated.getTime())) return false;
        return generated >= range.from && generated <= range.to;
    });
});

const extractApiMessage = (error: unknown): string => {
    const maybeError = error as { response?: { data?: { message?: unknown } } };
    const message = maybeError?.response?.data?.message;
    return typeof message === 'string' ? message : '';
};

const loadBudgetHistory = async () => {
    budgetHistoryLoading.value = true;
    budgetHistoryError.value = '';
    try {
        budgetHistory.value = await budgetService.getMyBudgetDocuments();
    } catch (error) {
        console.error(error);
        budgetHistoryError.value = 'No se pudo cargar el historial de presupuestos.';
    } finally {
        budgetHistoryLoading.value = false;
    }
};

const downloadHistoryDocument = async (entry: BudgetDocument) => {
    try {
        await budgetService.downloadBudgetDocument(entry.id, entry.file_name || 'presupuesto-cea-insumos.pdf');
    } catch (error) {
        console.error(error);
        toast.error('No se pudo descargar el presupuesto seleccionado.');
    }
};

const estimateLabor = async () => {
    await cartStore.estimateLaborByAI();
};

watch([includeTechnician, itemsSignature], async ([enabled]) => {
    if (!enabled || items.value.length === 0) return;
    await estimateLabor();
});

const generatePDF = async () => {
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.width;

    doc.setDrawColor(0);
    doc.rect(10, 10, pageWidth - 20, 40);

    doc.rect(pageWidth / 2 - 8, 10, 16, 16);
    doc.setFontSize(22);
    doc.setFont('helvetica', 'bold');
    doc.text('X', pageWidth / 2, 21, { align: 'center' });
    doc.setFontSize(8);
    doc.text('DOCUMENTO NO VALIDO', pageWidth / 2, 30, { align: 'center' });
    doc.text('COMO FACTURA', pageWidth / 2, 33, { align: 'center' });

    doc.setFontSize(18);
    doc.text('CEA INSUMOS', 15, 22);
    doc.setFontSize(9);
    doc.setFont('helvetica', 'bold');
    doc.text('Razon Social: CEA Insumos S.A.', 15, 30);
    doc.setFont('helvetica', 'normal');
    doc.text('Domicilio: Buenos Aires 432, Firmat', 15, 35);
    doc.text('Condicion frente al IVA: Responsable Inscripto', 15, 40);

    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('PRESUPUESTO', pageWidth - 15, 20, { align: 'right' });
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    const invoiceDate = new Date().toLocaleDateString('es-AR');
    doc.text(`Fecha: ${invoiceDate}`, pageWidth - 15, 30, { align: 'right' });
    doc.text('CUIT: 20-25459992-2', pageWidth - 15, 35, { align: 'right' });
    doc.text('Inicio de Actividades: 07/03/2022', pageWidth - 15, 40, { align: 'right' });

    doc.rect(10, 55, pageWidth - 20, 20);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text('Senor(es):', 15, 62);
    doc.setFont('helvetica', 'normal');
    doc.text('Consumidor Final', 35, 62);
    doc.setFont('helvetica', 'bold');
    doc.text('Domicilio:', 15, 68);
    doc.setFont('helvetica', 'normal');
    doc.text('A confirmar', 35, 68);
    doc.setFont('helvetica', 'bold');
    doc.text('Condicion IVA:', pageWidth / 2 + 5, 62);
    doc.setFont('helvetica', 'normal');
    doc.text('Consumidor Final', pageWidth / 2 + 35, 62);

    const groupedItems: Record<string, { id: string; name: string; category: string; price: number; quantity: number }> = {};

    items.value.forEach(item => {
        const existing = groupedItems[item.id];
        if (existing) {
            existing.quantity += 1;
        } else {
            groupedItems[item.id] = { ...item, quantity: 1 };
        }
    });

    const tableData = Object.values(groupedItems).map(item => [
        item.quantity,
        item.name,
        formatPrice(item.price),
        formatPrice(item.price * item.quantity)
    ]);

    if (includeTechnician.value) {
        const fee = unref(technicianFee);
        const hours = unref(estimatedLaborHours);
        const rate = unref(laborRate);
        const labourLine =
            hours > 0
                ? `Mano de obra estimada (${hours.toFixed(1)} hs x ${formatPrice(rate)}/h)`
                : 'Mano de obra estimada (pendiente de estimacion)';
        tableData.push(['1', labourLine, formatPrice(fee), formatPrice(fee)]);
    }

    autoTable(doc, {
        head: [['Cant.', 'Descripcion', 'Precio Unit.', 'Subtotal']],
        body: tableData,
        startY: 85,
        theme: 'plain',
        styles: { fontSize: 9, cellPadding: 2, lineColor: [0, 0, 0], lineWidth: 0.1 },
        headStyles: { fillColor: [220, 220, 220], textColor: 0, fontStyle: 'bold', halign: 'center' },
        columnStyles: {
            0: { halign: 'center', cellWidth: 15 },
            1: { cellWidth: 'auto' },
            2: { halign: 'right', cellWidth: 30 },
            3: { halign: 'right', cellWidth: 30 }
        }
    });

    const finalY = (doc as any).lastAutoTable.finalY + 10;

    const subtotalLabelX = pageWidth - 60;
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text('Subtotal:', subtotalLabelX, finalY, { align: 'right' });
    doc.setFont('helvetica', 'normal');
    doc.text(`${formatPrice(subtotalCost.value)}`, pageWidth - 15, finalY, { align: 'right' });

    if (includeTechnician.value) {
        doc.setFont('helvetica', 'bold');
        doc.text('Mano de obra estimada:', subtotalLabelX, finalY + 7, { align: 'right' });
        doc.setFont('helvetica', 'normal');
        doc.text(`${formatPrice(technicianFee.value)}`, pageWidth - 15, finalY + 7, { align: 'right' });
    }

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('Total Presupuestado:', pageWidth - 15, finalY + 16, { align: 'right' });
    doc.setFontSize(14);
    doc.text(`${formatPrice(totalCost.value)}`, pageWidth - 15, finalY + 22, { align: 'right' });

    doc.setFontSize(8);
    doc.setFont('helvetica', 'italic');
    doc.text('Presupuesto valido por 15 dias habiles. Sujeto a disponibilidad de stock.', 10, finalY + 30);
    doc.text('Para formalizar la compra comunicarse con contacto@ceainsumos.com con este presupuesto.', 10, finalY + 35);
    doc.text('Los precios expresados incluyen IVA salvo indicacion contraria.', 10, finalY + 40);
    doc.text('Gracias por elegir a CEA Insumos.', 10, finalY + 45);

    const pdfDataUri = doc.output('datauristring');
    doc.save('presupuesto-cea-insumos.pdf');

    try {
        await budgetService.saveBudgetDocument({
            pdf_base64: pdfDataUri,
            file_name: 'presupuesto-cea-insumos.pdf',
            total_amount: totalCost.value,
            items_count: items.value.length,
            metadata: {
                include_technician: includeTechnician.value,
                estimated_labor_hours: estimatedLaborHours.value,
                grouped_items: Object.values(groupedItems).map((item) => ({
                    id: item.id,
                    name: item.name,
                    category: item.category,
                    price: item.price,
                    quantity: item.quantity,
                })),
            },
            generated_at: new Date().toISOString(),
        });
        toast.success('Presupuesto guardado en tu historial.');
        await loadBudgetHistory();
    } catch (error) {
        console.error(error);
        const backendMessage = extractApiMessage(error);
        toast.error(
            backendMessage
                ? `El PDF se descargo, pero no se pudo guardar en el historial: ${backendMessage}`
                : 'El PDF se descargo, pero no se pudo guardar en el historial.'
        );
    }
};

onMounted(async () => {
    await settingsStore.syncLaborRate(true);
    await loadBudgetHistory();
});
</script>

<template>
    <div class="p-6 space-y-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Tu Presupuesto</h1>
            <p class="text-muted-foreground">Revisa los productos seleccionados.</p>
        </div>

        <section class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold">Historial de presupuestos</h2>
                    <p class="text-sm text-muted-foreground">Cada PDF generado queda asociado a tu usuario con fecha.</p>
                </div>
                <button class="rounded-md border px-2 py-1 text-xs font-semibold" @click="budgetHistoryCollapsed = !budgetHistoryCollapsed">
                    <span class="inline-flex items-center gap-1">
                        {{ budgetHistoryCollapsed ? 'Expandir' : 'Comprimir' }}
                        <Icon :icon="budgetHistoryCollapsed ? 'mdi:chevron-down' : 'mdi:chevron-up'" class="h-4 w-4" />
                    </span>
                </button>
            </div>
            <div v-if="budgetHistoryCollapsed" class="px-6 py-6 text-sm text-muted-foreground">
                Historial comprimido.
            </div>
            <template v-else>
                <div class="grid gap-3 border-b px-6 py-4 md:grid-cols-[minmax(0,1fr)_220px]">
                    <div class="relative">
                        <Icon icon="mdi:magnify" class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="budgetHistorySearch"
                            type="text"
                            placeholder="Buscar por archivo, ID o monto..."
                            class="w-full rounded-md border border-input bg-background py-2 pl-9 pr-3 text-sm"
                        />
                    </div>
                    <select v-model="budgetHistoryDatePreset" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
                        <option value="all">Todas las fechas</option>
                        <option value="today">Hoy</option>
                        <option value="last_7_days">Ultimos 7 dias</option>
                        <option value="last_30_days">Ultimos 30 dias</option>
                        <option value="last_3_months">Ultimos 3 meses</option>
                        <option value="last_6_months">Ultimos 6 meses</option>
                        <option value="last_12_months">Ultimos 12 meses</option>
                    </select>
                </div>
            <div v-if="budgetHistoryLoading" class="px-6 py-6 text-sm text-muted-foreground">
                Cargando historial...
            </div>
            <div v-else-if="budgetHistoryError" class="px-6 py-6 text-sm text-destructive">
                {{ budgetHistoryError }}
            </div>
            <div v-else-if="budgetHistory.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
                Aun no generaste presupuestos.
            </div>
            <div v-else-if="filteredBudgetHistory.length === 0" class="px-6 py-6 text-sm text-muted-foreground">
                No hay resultados para los filtros seleccionados.
            </div>
            <div v-else class="divide-y">
                <div
                    v-for="entry in filteredBudgetHistory"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 text-sm"
                >
                    <div class="space-y-1">
                        <p class="font-medium">{{ entry.file_name }}</p>
                        <p class="text-xs text-muted-foreground">
                            Fecha: {{ formatDateTime(entry.generated_at) }} | Total: {{ formatPrice(entry.total_amount) }} | Items: {{ entry.items_count }}
                        </p>
                    </div>
                    <button
                        class="inline-flex items-center rounded-md border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary/20"
                        @click="downloadHistoryDocument(entry)"
                    >
                        Descargar
                    </button>
                </div>
            </div>
            </template>
        </section>

        <div v-if="items.length === 0" class="flex flex-col items-center justify-center py-16 text-center border rounded-lg border-dashed">
            <div class="rounded-full bg-muted/50 p-6 mb-4">
                <Icon icon="mdi:cart-outline" class="h-10 w-10 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-semibold">Tu presupuesto esta vacio</h3>
            <p class="text-sm text-muted-foreground mt-1 mb-6">Comenza a armar tu sistema de seguridad agregando productos.</p>
            <button @click="router.push('/home')" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                Ver Productos
            </button>
        </div>

        <div v-else class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Producto</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Categoria</th>
                                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Precio</th>
                                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle font-medium">{{ item.name }}</td>
                                <td class="p-4 align-middle text-muted-foreground capitalize">
                                    {{ item.category === 'camera' ? 'Camara' : item.category === 'alarm' ? 'Alarma' : 'Sensor' }}
                                </td>
                                <td class="p-4 align-middle text-right">{{ formatPrice(item.price) }}</td>
                                <td class="p-4 align-middle text-right">
                                    <button @click="cartStore.removeItem(item.id)" class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground h-8 w-8 text-destructive">
                                        <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border bg-card p-6 shadow-sm space-y-4">
                    <div class="flex items-start space-x-4">
                        <div class="flex items-center space-x-2 pt-1">
                            <input type="checkbox" id="technician" v-model="includeTechnician" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
                        </div>
                        <div>
                            <label for="technician" class="text-base font-medium leading-none cursor-pointer">
                                Incluir mano de obra estimada
                            </label>
                            <p class="text-sm text-muted-foreground mt-1">
                                La IA estima horas usando manuales tecnicos y luego se calcula:
                                <span class="font-semibold">horas x tarifa admin</span>.
                            </p>
                            <p class="text-sm text-muted-foreground mt-1">
                                Tarifa vigente: <span class="font-semibold">{{ formatPrice(laborRate) }}/hora</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="includeTechnician" class="rounded-md border bg-muted/20 p-4 space-y-3">
                        <label for="labor-description" class="text-sm font-medium">Detalle adicional para la IA (opcional)</label>
                        <textarea
                            id="labor-description"
                            v-model="laborDescription"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ej: vivienda de 2 pisos, cableado existente, 4 camaras exteriores, 2 sensores interiores."
                        />

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
                                :disabled="laborEstimationLoading"
                                @click="estimateLabor"
                            >
                                <Icon v-if="laborEstimationLoading" icon="mdi:loading" class="mr-2 h-4 w-4 animate-spin" />
                                {{ laborEstimationLoading ? 'Estimando...' : 'Calcular tiempo estimado' }}
                            </button>
                            <span v-if="laborEstimateStale && !laborEstimationLoading" class="text-xs text-amber-600">
                                Hay cambios pendientes. Reestima para actualizar el costo.
                            </span>
                        </div>

                        <p v-if="laborEstimationError" class="text-xs text-destructive">
                            {{ laborEstimationError }}
                        </p>

                        <div class="rounded-md border bg-background p-3 space-y-2">
                            <div class="grid gap-2 text-sm md:grid-cols-3">
                                <div>
                                    <p class="text-muted-foreground">Horas estimadas</p>
                                    <p class="font-semibold">{{ laborHoursLabel }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Tarifa por hora</p>
                                    <p class="font-semibold">{{ formatPrice(laborRate) }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Costo mano de obra</p>
                                    <p class="font-semibold">{{ formatPrice(technicianFee) }}</p>
                                </div>
                            </div>

                            <p v-if="laborEstimateSummary" class="text-xs text-muted-foreground">
                                {{ laborEstimateSummary }}
                            </p>

                            <ul v-if="laborAssumptions.length > 0" class="list-disc pl-5 text-xs text-muted-foreground space-y-1">
                                <li v-for="(assumption, idx) in laborAssumptions" :key="idx">{{ assumption }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="rounded-lg border bg-card text-card-foreground shadow-sm sticky top-6">
                    <div class="flex flex-col space-y-1.5 p-6">
                        <h3 class="font-semibold leading-none tracking-tight">Resumen</h3>
                    </div>
                    <div class="p-6 pt-0 space-y-4">
                        <div class="grid grid-cols-[1fr_auto] gap-4 text-sm">
                            <span class="text-muted-foreground">Subtotal ({{ items.length }} items)</span>
                            <span class="text-right tabular-nums whitespace-nowrap">{{ formatPrice(subtotalCost) }}</span>
                        </div>
                        <div class="grid grid-cols-[1fr_auto] gap-4 text-sm" v-if="includeTechnician">
                            <span class="text-muted-foreground">Mano de obra estimada ({{ laborHoursLabel }})</span>
                            <span class="text-right tabular-nums whitespace-nowrap">{{ formatPrice(technicianFee) }}</span>
                        </div>
                        <div class="border-t pt-4 grid grid-cols-[1fr_auto] gap-4 font-bold text-lg">
                            <span>Total Presupuesto</span>
                            <span class="text-right tabular-nums whitespace-nowrap">{{ formatPrice(totalCost) }}</span>
                        </div>
                    </div>
                    <div class="p-6 pt-0">
                        <button @click="generatePDF" class="w-full inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90">
                            <Icon icon="mdi:file-pdf-box" class="mr-2 h-5 w-5" />
                            Generar Presupuesto
                        </button>
                        <p class="mt-3 text-xs text-muted-foreground leading-relaxed">
                            Para realizar la compra final comunicate via mail con contacto@ceainsumos.com con el presupuesto generado dentro de los 15 dias habiles.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
