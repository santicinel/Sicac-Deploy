import { defineStore } from "pinia";
import { computed, ref } from "vue";

export type Role = "admin" | "technician" | "user";

export interface AuthUser {
    id?: number;
    name: string;
    email: string;
    role: Role;
    dni?: string | null;
    phone?: string | null;
    address?: string | null;
    city?: string | null;
}

const STORAGE_KEY = "sicac_auth_user";

const loadUserFromStorage = (): AuthUser | null => {
    if (typeof window === "undefined") return null;
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        return JSON.parse(raw) as AuthUser;
    } catch {
        return null;
    }
};

const saveUserToStorage = (user: AuthUser | null) => {
    if (typeof window === "undefined") return;
    if (!user) {
        window.localStorage.removeItem(STORAGE_KEY);
        return;
    }
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
};

export const useAuthStore = defineStore("auth", () => {
    const user = ref<AuthUser | null>(loadUserFromStorage());

    const isAuthenticated = computed(() => Boolean(user.value));
    const role = computed<Role | null>(() => user.value?.role ?? null);

    const setUser = (nextUser: AuthUser | null) => {
        user.value = nextUser;
        saveUserToStorage(nextUser);
    };

    const logout = () => setUser(null);

    return {
        user,
        role,
        isAuthenticated,
        setUser,
        logout,
    };
});
