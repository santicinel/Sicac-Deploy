import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import { config_app } from '@/config/app';
import { useAdminSettingsStore } from '@/store/adminSettingsStore';

export interface Product {
    id: string;
    name: string;
    description: string;
    price: number;
    category: 'camera' | 'alarm' | 'sensor';
    image?: string;
}

interface LabourEstimateItem {
    id: string;
    name: string;
    description: string;
    category: Product['category'];
    quantity: number;
}

interface LabourEstimateResponse {
    estimated_hours?: number;
    summary?: string;
    assumptions?: string[];
}

const fallbackHoursEstimate = (items: LabourEstimateItem[], laborRequest: string) => {
    const categoryBase: Record<Product['category'], number> = {
        camera: 1.8,
        alarm: 1.6,
        sensor: 0.8,
    };

    let total = 0.6;

    for (const item of items) {
        const quantity = Math.max(1, Number(item.quantity || 1));
        total += (categoryBase[item.category] ?? 1.2) * quantity;

        const fullText = `${item.name} ${item.description}`.toLowerCase();
        if (
            fullText.includes('cable') ||
            fullText.includes('exterior') ||
            fullText.includes('altura') ||
            fullText.includes('canalizacion')
        ) {
            total += 0.4 * quantity;
        }
    }

    const normalizedRequest = (laborRequest || '').toLowerCase();
    if (
        normalizedRequest.includes('exterior') ||
        normalizedRequest.includes('intemperie') ||
        normalizedRequest.includes('altura')
    ) {
        total *= 1.15;
    }
    if (
        normalizedRequest.includes('urgente') ||
        normalizedRequest.includes('complejo') ||
        normalizedRequest.includes('integracion')
    ) {
        total *= 1.2;
    }

    const clamped = Math.max(0.5, Math.min(total, 120));
    return Number(clamped.toFixed(1));
};

export const useCartStore = defineStore('cart', () => {
    const items = ref<Product[]>([]);
    const includeTechnician = ref(false);
    const laborDescription = ref('');
    const estimatedLaborHours = ref(0);
    const laborEstimateSummary = ref('');
    const laborAssumptions = ref<string[]>([]);
    const laborEstimationLoading = ref(false);
    const laborEstimationError = ref('');
    const laborEstimateStale = ref(false);

    const settingsStore = useAdminSettingsStore();
    void settingsStore.syncLaborRate();
    const laborRate = computed(() => Number(settingsStore.laborRate || 0));

    const groupedItems = computed<LabourEstimateItem[]>(() => {
        const grouped = new Map<string, LabourEstimateItem>();

        for (const item of items.value) {
            const existing = grouped.get(item.id);
            if (existing) {
                existing.quantity += 1;
                continue;
            }
            grouped.set(item.id, {
                id: item.id,
                name: item.name,
                description: item.description,
                category: item.category,
                quantity: 1,
            });
        }

        return Array.from(grouped.values());
    });

    const subtotalCost = computed(() =>
        items.value.reduce((sum, item) => sum + item.price, 0)
    );

    const technicianFee = computed(() => {
        if (!includeTechnician.value) return 0;
        return Number((estimatedLaborHours.value * laborRate.value).toFixed(2));
    });

    const totalCost = computed(() => {
        return subtotalCost.value + technicianFee.value;
    });

    const resetLaborEstimate = () => {
        estimatedLaborHours.value = 0;
        laborEstimateSummary.value = '';
        laborAssumptions.value = [];
        laborEstimationError.value = '';
        laborEstimateStale.value = false;
    };

    const addItem = (product: Product) => {
        items.value.push(product);
        if (includeTechnician.value) {
            laborEstimateStale.value = true;
        }
    };

    const removeItem = (productId: string) => {
        const index = items.value.findIndex(item => item.id === productId);
        if (index > -1) {
            items.value.splice(index, 1);
            if (includeTechnician.value) {
                laborEstimateStale.value = true;
            }
        }
        if (items.value.length === 0) {
            includeTechnician.value = false;
            resetLaborEstimate();
        }
    };

    const applyFallbackEstimate = () => {
        const fallbackHours = fallbackHoursEstimate(groupedItems.value, laborDescription.value);
        estimatedLaborHours.value = fallbackHours;
        laborEstimateSummary.value = 'Se uso una estimacion tecnica local por un problema temporal con IA.';
        laborAssumptions.value = [
            'Incluye instalacion, configuracion inicial y pruebas basicas.',
            'La estimacion final puede variar segun acceso, cableado y condiciones del sitio.',
        ];
        laborEstimationError.value =
            'No se pudo obtener respuesta del servicio IA. Se aplico una estimacion local.';
        laborEstimateStale.value = false;
    };

    const estimateLaborByAI = async () => {
        if (!includeTechnician.value) {
            resetLaborEstimate();
            return;
        }

        if (items.value.length === 0) {
            includeTechnician.value = false;
            resetLaborEstimate();
            return;
        }

        laborEstimationLoading.value = true;
        laborEstimationError.value = '';

        try {
            const response = await fetch(`${config_app.ai_url}/labour/estimate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    items: groupedItems.value,
                    labor_request: laborDescription.value || null,
                }),
            });

            if (!response.ok) {
                throw new Error(`Labor estimate API error: ${response.status}`);
            }

            const data = (await response.json()) as LabourEstimateResponse;
            const parsedHours = Number(data.estimated_hours ?? 0);
            if (!Number.isFinite(parsedHours) || parsedHours <= 0) {
                throw new Error('Invalid hours in labor estimate response');
            }

            estimatedLaborHours.value = Number(parsedHours.toFixed(1));
            laborEstimateSummary.value = String(data.summary || 'Estimacion calculada por IA.');
            laborAssumptions.value = Array.isArray(data.assumptions)
                ? data.assumptions.map((item) => String(item)).filter(Boolean)
                : [];
            laborEstimateStale.value = false;
        } catch (error) {
            console.error(error);
            applyFallbackEstimate();
        } finally {
            laborEstimationLoading.value = false;
        }
    };

    const clearCart = () => {
        items.value = [];
        includeTechnician.value = false;
        laborDescription.value = '';
        resetLaborEstimate();
    };

    watch(includeTechnician, (enabled) => {
        if (!enabled) {
            resetLaborEstimate();
            return;
        }
        if (items.value.length > 0) {
            laborEstimateStale.value = true;
        }
    });

    watch(laborDescription, () => {
        if (includeTechnician.value && items.value.length > 0) {
            laborEstimateStale.value = true;
        }
    });

    return {
        items,
        includeTechnician,
        laborDescription,
        estimatedLaborHours,
        laborEstimateSummary,
        laborAssumptions,
        laborEstimationLoading,
        laborEstimationError,
        laborEstimateStale,
        laborRate,
        subtotalCost,
        technicianFee,
        addItem,
        removeItem,
        estimateLaborByAI,
        totalCost,
        clearCart
    };
});
