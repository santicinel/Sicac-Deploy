import { config_app } from "@/config/app";
import axios from "axios";
import { toast } from "vue-sonner";
import { useAuthStore } from "@/store/authStore";
import type { AxiosError, AxiosInstance, AxiosResponse } from "axios";

const baseUrl: string = config_app.api_url;
const apiBaseUrl = `${baseUrl}/api`;
const connectionErrorMessage = `No se pudo conectar con el servidor (${baseUrl}). Verifica que Laravel este activo.`;

const api: AxiosInstance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
    xsrfCookieName: "XSRF-TOKEN",
    xsrfHeaderName: "X-XSRF-TOKEN",
});

api.interceptors.response.use(
    (response: AxiosResponse): AxiosResponse => response,
    (error: AxiosError): Promise<never> => {
        const status = error.response?.status;
        const backendMessage = (error.response?.data as any)?.message as string | undefined;

        const statusMessageMap = {
            500: "Error interno en el servidor",
            502: "No se pudo conectar con el servidor",
            503: "Servicio no disponible",
            504: "Timeout en la conexion",
        } as const;

        if (!status && (error.code === "ERR_CONNECTION_REFUSED" || error.code === "ERR_NETWORK")) {
            error.message = connectionErrorMessage;
            const pathname = window.location.pathname;
            const isLoginPage =
                pathname === "/login" ||
                pathname === "/admin/login" ||
                pathname === "/technician/login";

            if (!isLoginPage) {
                toast.error(connectionErrorMessage);
            }
        } else if (status === 419) {
            const message = "Sesion expirada o token CSRF invalido. Recarga la pagina e intenta nuevamente.";
            error.message = message;
            toast.error(message);
        } else if (status === 401) {
            const message = "No autenticado";
            error.message = message;
            toast.error(message);

            const pathname = window.location.pathname;
            if (pathname.startsWith("/admin")) {
                window.location.href = "/admin/login";
            } else if (pathname.startsWith("/technician")) {
                window.location.href = "/technician/login";
            } else {
                window.location.href = "/login";
            }
        } else if (status === 404) {
            const message = "Recurso no encontrado. Sesion expirada.";
            error.message = message;
            toast.error(message);

            const authStore = useAuthStore();
            authStore.logout();

            const pathname = window.location.pathname;
            if (pathname.startsWith("/admin")) {
                window.location.href = "/admin/login";
            } else if (pathname.startsWith("/technician")) {
                window.location.href = "/technician/login";
            } else {
                window.location.href = "/login";
            }
        } else if (status && status in statusMessageMap) {
            const message = statusMessageMap[status as keyof typeof statusMessageMap];
            error.message = message;
            toast.error(message);
        } else if (status && status >= 500) {
            error.message = "Error interno del servidor";
        } else if (status && status >= 400 && status < 500) {
            const pathname = window.location.pathname;
            const isLoginPage =
                pathname === "/login" ||
                pathname === "/admin/login" ||
                pathname === "/technician/login";

            if (!isLoginPage) {
                const message = backendMessage || "Error en la solicitud";
                error.message = message;
                toast.error(message);
            } else if (backendMessage) {
                error.message = backendMessage;
            }
        }

        return Promise.reject(error);
    }
);

export const csrf = (): Promise<AxiosResponse> => api.get("/sanctum/csrf-cookie", { baseURL: baseUrl });

export default api;
