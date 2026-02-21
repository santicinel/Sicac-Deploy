import catalogData from '../../IA/kb/catalogo_sielse_normalizado.json';

export interface CatalogProduct {
    ID: string;
    Nombre: string;
    Marca: string | null;
    "Modelo/SKU": string;
    familia: string;
    subfamilia: string;
    "Precio (ARS)": number;
    URL: string;
    "Categoría (original)": string;
    Categoría_base: string;
    Tags: string[];
    Specs_JSON: Record<string, any>;
    Texto_RAG: string;
    "Características (raw)": string | null;
}

export interface ProductFilter {
    category?: string; // familia
    subcategory?: string; // subfamilia
    search?: string;
    features?: Record<string, any>;
    minPrice?: number;
    maxPrice?: number;
}

class DataService {
    private products: CatalogProduct[];

    constructor() {
        const data: any = catalogData as any;
        if (Array.isArray(data)) {
            this.products = data as CatalogProduct[];
        } else {
            this.products = (data.products as CatalogProduct[]) || (data.Productos as CatalogProduct[]) || [];
        }
    }

    // Simulate DB pagination
    async getProducts(page: number = 1, limit: number = 20, filters: ProductFilter = {}): Promise<{ items: CatalogProduct[], total: number }> {
        // Simulate network delay
        await new Promise(resolve => setTimeout(resolve, 300));

        let filtered = this.products;

        if (filters.search) {
            const q = filters.search.toLowerCase();
            filtered = filtered.filter(p =>
                p.Nombre.toLowerCase().includes(q) ||
                p["Modelo/SKU"]?.toLowerCase().includes(q) ||
                p.Texto_RAG?.toLowerCase().includes(q)
            );
        }

        if (filters.category && filters.category !== 'all') {
            filtered = filtered.filter(p => p.familia === filters.category);
        }

        if (filters.subcategory && filters.subcategory !== 'all') {
            // Special handling for divided "Vehiculos"
            // Special handling for divided "Vehiculos"
            if (filters.category && filters.category.toLowerCase().startsWith('veh')) {
                const sub = filters.subcategory.toLowerCase();
                const q = sub === 'alarmas' ? 'alarma' : (sub === 'transmisores' ? 'transmisor' : '');

                if (q) {
                    filtered = filtered.filter(p =>
                        p.Nombre.toLowerCase().includes(q) ||
                        p.subfamilia?.toLowerCase().includes(q) ||
                        (sub === 'transmisores' && p.Nombre.toLowerCase().includes('control')) // heuristic
                    );
                }
            } else {
                filtered = filtered.filter(p => p.subfamilia === filters.subcategory);
            }
        }

        if (filters.minPrice !== undefined) {
            filtered = filtered.filter(p => p["Precio (ARS)"] >= filters.minPrice!);
        }

        if (filters.maxPrice !== undefined) {
            filtered = filtered.filter(p => p["Precio (ARS)"] <= filters.maxPrice!);
        }

        if (filters.features) {
            filtered = filtered.filter(p => {
                for (const [key, value] of Object.entries(filters.features!)) {
                    if (p.Specs_JSON && p.Specs_JSON[key] !== undefined) {
                        if (Array.isArray(p.Specs_JSON[key])) {
                            // Assuming values are strings/numbers, check inclusion
                            if (!p.Specs_JSON[key].includes(value)) return false;
                        } else if (p.Specs_JSON[key] != value) {
                            return false;
                        }
                    }
                }
                return true;
            });
        }

        const total = filtered.length;
        const start = (page - 1) * limit;
        const end = start + limit;

        return {
            items: filtered.slice(start, end),
            total
        };
    }

    async getPriceStats(): Promise<{ min: number; max: number }> {
        if (this.products.length === 0) return { min: 0, max: 0 };
        const prices = this.products.map(p => p["Precio (ARS)"]);
        return {
            min: Math.min(...prices),
            max: Math.max(...prices)
        };
    }

    async getCategories(): Promise<Record<string, string[]>> {
        const categories: Record<string, Set<string>> = {};

        this.products.forEach(p => {
            if (!categories[p.familia]) {
                categories[p.familia] = new Set();
            }
            if (p.subfamilia) {
                categories[p.familia]!.add(p.subfamilia);
            }
        });

        // Convert Sets to Arrays
        const result: Record<string, string[]> = {};
        for (const [fam, subs] of Object.entries(categories)) {
            if (fam.toLowerCase().startsWith('veh')) {
                result[fam] = ['Alarmas', 'Transmisores'];
            } else {
                result[fam] = Array.from(subs).sort();
            }
        }

        return result;
    }

    async getRecommendations(preferences: any): Promise<CatalogProduct[]> {
        // Simple logic to find relevant products
        // This simulates a "RAG" or smart query
        let relevant = this.products;

        if (preferences.productTypes && preferences.productTypes.length > 0) {
            // Map 'camera' -> 'Acceso'/'subfamilia' logic etc.
            // This is a heuristic mapping
            relevant = relevant.filter(p => {
                if (preferences.productTypes.includes('camera')) {
                    if (p.subfamilia?.toLowerCase().includes('portero') || p.subfamilia?.toLowerCase().includes('camara') || p.Categoría_base === 'CCTV') return true;
                }
                if (preferences.productTypes.includes('alarm')) {
                    if (p.familia === 'Alarmas') return true;
                }
                if (preferences.productTypes.includes('sensor')) {
                    if (p.Nombre.toLowerCase().includes('sensor') || p.Nombre.toLowerCase().includes('detector')) return true;
                }
                return false;
            });
        }

        // Apply specs filters
        if (preferences.cameraResolution) {
            // Heuristic: filter if Specs_JSON.resolucion_mp exists and matches roughly
            // For now, just return relevance based on filters
        }

        // Return top 5 matches
        return relevant.slice(0, 5);
    }
}

export const dataService = new DataService();
