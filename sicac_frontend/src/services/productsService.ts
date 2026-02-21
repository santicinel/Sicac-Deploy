import type { AxiosResponse } from "axios";
import api, { csrf } from "@/lib/axios";
import type { PaginatedResponse } from "@/interfaces";

export interface ProductBrand {
    id: number | string;
    name?: string;
    nombre?: string;
    label?: string;
}

export interface ProductFamily {
    id: number | string;
    name?: string;
    nombre?: string;
    label?: string;
}

export interface ProductCategory {
    id: number | string;
    name?: string;
    nombre?: string;
    label?: string;
    family_id?: number | string | null;
    familyId?: number | string | null;
}

export interface ProductSubfamily {
    id: number | string;
    name?: string;
    nombre?: string;
    label?: string;
    family_id?: number | string | null;
    familyId?: number | string | null;
    category_id?: number | string | null;
    categoryId?: number | string | null;
    work_cost_points?: string | number | null;
    family?: ProductFamily | null;
}

export interface Product {
    id: number | string;
    external_id: string;
    name: string;
    brand_id: number | string;
    subfamily_id: number | string;
    category_id: number | string;
    family_id?: number | string | null;
    model_sku?: string | null;
    price_ars?: string | number | null;
    description?: string | null;
    technical_specs?: unknown[] | Record<string, unknown> | null;
    source_specs?: string | null;
    brand?: ProductBrand | null;
    category?: ProductCategory | null;
    subfamily?: ProductSubfamily | null;
    family?: ProductFamily | null;
}

export interface ProductListParams {
    page?: number;
    per_page?: number;
    search?: string;
    brand_id?: number | string;
    category_id?: number | string;
    subfamily_id?: number | string;
    family_id?: number | string;
}

export interface CreateProductPayload {
    external_id: string;
    name: string;
    brand_id: number | string;
    subfamily_id: number | string;
    category_id: number | string;
    model_sku?: string | null;
    price_ars?: number | string | null;
    description?: string | null;
    technical_specs?: unknown[] | Record<string, unknown>;
    source_specs?: string | null;
}

const resolveList = <TData>(payload: unknown): TData[] => {
    if (Array.isArray(payload)) return payload as TData[];
    if (payload && typeof payload === "object") {
        const candidate = (payload as { data?: unknown }).data;
        if (Array.isArray(candidate)) return candidate as TData[];
    }
    return [];
};

const resolveNestedList = <TData>(
    payload: unknown,
    key: "brands" | "categories" | "subfamilies" | "families"
): TData[] => {
    if (payload && typeof payload === "object") {
        const candidate = (payload as Record<string, unknown>)[key];
        if (Array.isArray(candidate)) return candidate as TData[];
    }
    return resolveList<TData>(payload);
};

const getProduct = async (
    params: ProductListParams = {}
): Promise<AxiosResponse<PaginatedResponse<Product[]>>> => {
    return api.get("/products", { params });
};

const getProductDetail = async (
    productId: number | string
): Promise<AxiosResponse<Product>> => {
    return api.get(`/products/${productId}`);
};

const createProduct = async (
    payload: CreateProductPayload
): Promise<AxiosResponse<Product>> => {
    console.log("productsService.createProduct called. Calling csrf()..."); // DEBUG
    await csrf();
    console.log("csrf() finished. Calling api.post..."); // DEBUG
    return api.post("/products", payload);
};

const deleteProduct = async (
    productId: number | string
): Promise<AxiosResponse<{ ok: boolean }>> => {
    await csrf();
    return api.delete(`/products/${productId}`);
};

const getBrands = async (): Promise<AxiosResponse<{ brands: ProductBrand[] }>> => {
    return api.get("/products/filters/brands");
};

const getCategories = async (): Promise<AxiosResponse<{ categories: ProductCategory[] }>> => {
    return api.get("/products/filters/categories");
};

const getSubfamilies = async (): Promise<AxiosResponse<{ subfamilies: ProductSubfamily[] }>> => {
    return api.get("/products/filters/subfamilies");
};

const getFamilies = async (): Promise<AxiosResponse<{ families: ProductFamily[] }>> => {
    return api.get("/products/filters/families");
};

const getFilters = async (): Promise<{
    brands: ProductBrand[];
    categories: ProductCategory[];
    subfamilies: ProductSubfamily[];
    families: ProductFamily[];
}> => {
    const [brands, categories, subfamilies, families] = await Promise.all([
        getBrands(),
        getCategories(),
        getSubfamilies(),
        getFamilies(),
    ]);

    return {
        brands: resolveNestedList<ProductBrand>(brands.data, "brands"),
        categories: resolveNestedList<ProductCategory>(categories.data, "categories"),
        subfamilies: resolveNestedList<ProductSubfamily>(subfamilies.data, "subfamilies"),
        families: resolveNestedList<ProductFamily>(families.data, "families"),
    };
};

const productsService = {
    getProduct,
    getProductDetail,
    createProduct,
    deleteProduct,
    getBrands,
    getCategories,
    getSubfamilies,
    getFamilies,
    getFilters,
};

export default productsService;
export {
    getProduct,
    getProductDetail,
    createProduct,
    deleteProduct,
    getBrands,
    getCategories,
    getSubfamilies,
    getFamilies,
    getFilters,
};
