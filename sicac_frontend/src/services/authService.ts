import type { AxiosResponse } from "axios";
import api, { csrf } from "@/lib/axios";

import { toast } from "vue-sonner";
import { resolveDemoUser } from "@/config/demoAuth";

export interface LoginPayload {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    dni: string;
    address: string;
    city: string;
    phone: string;
}

export interface AuthResponse {
    user?: {
        id?: number;
        name: string;
        email: string;
        role: "admin" | "technician" | "user";
        dni?: string | null;
        phone?: string | null;
        address?: string | null;
        city?: string | null;
    };
}

const normalizeEmail = (email: string): string =>
    email.trim().replace(/\s+/g, "").replace(/,+/g, ".").toLowerCase();

const buildDemoResponse = (user: NonNullable<AuthResponse["user"]>) =>
    ({
        data: { user },
        status: 200,
        statusText: "OK",
        headers: {},
        config: {},
    } as AxiosResponse<AuthResponse>);

const login = async (
    payload: LoginPayload
): Promise<AxiosResponse<AuthResponse> | any> => {
    const normalizedPayload = {
        ...payload,
        email: normalizeEmail(payload.email),
    };

    const demoUser = resolveDemoUser(normalizedPayload);
    if (demoUser) {
        return buildDemoResponse(demoUser);
    }

    await csrf();
    return api.post("/login", normalizedPayload);
};

const logout = async (): Promise<AxiosResponse<AuthResponse>> => {
    return api.post("/logout");
};

const getCurrentUser = async (): Promise<AuthResponse["user"] | null> => {
    const response = await api.get<AuthResponse>("/user");
    return response.data.user ?? null;
};

const updateCurrentUser = async (payload: {
    name: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    city?: string | null;
}): Promise<AuthResponse["user"] | null> => {
    await csrf();
    const response = await api.patch<AuthResponse>("/user", payload);
    return response.data.user ?? null;
};

const loginAdmin = async (
    payload: LoginPayload
): Promise<AxiosResponse<AuthResponse> | any> => {
    const normalizedPayload = {
        ...payload,
        email: normalizeEmail(payload.email),
    };

    const demoUser = resolveDemoUser(normalizedPayload, ["admin"]);
    if (demoUser) {
        return buildDemoResponse(demoUser);
    }

    await csrf();
    return api.post("/login/admin", normalizedPayload);
};

const loginTechnician = async (
    payload: LoginPayload
): Promise<AxiosResponse<AuthResponse> | any> => {
    const normalizedPayload = {
        ...payload,
        email: normalizeEmail(payload.email),
    };

    const demoUser = resolveDemoUser(normalizedPayload, ["technician"]);
    if (demoUser) {
        return buildDemoResponse(demoUser);
    }

    await csrf();
    return api.post("/login/technician", normalizedPayload);
};

const register = async (
    payload: RegisterPayload
): Promise<AxiosResponse<AuthResponse> | any> => {
    const normalizedPayload = {
        ...payload,
        email: normalizeEmail(payload.email),
    };

    await csrf();
    try {
        const response = await api.post("/register", normalizedPayload);
        toast.success(response.data.message || "Registrado exitosamente");
        return response;
    } catch (error: any) {
        if (error.response) {
            const status = error.response.status;
            const message = error.response.data.message || "Error en el registro";

            if (status >= 400 && status < 500) {
                toast.error(message);
            } else if (status === 500) {
                toast.error("Internal server error");
            }
        }
        throw error;
    }
};

const authService = {
    login,
    loginAdmin,
    loginTechnician,
    getCurrentUser,
    updateCurrentUser,
    logout,
    register,
};

export default authService;
export { login, loginAdmin, loginTechnician, getCurrentUser, updateCurrentUser, logout, register };
