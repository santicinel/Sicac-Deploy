const LOOPBACK_HOSTS = new Set(["localhost", "127.0.0.1", "0.0.0.0", "::1", "[::1]"]);

const normalizeUrl = (url: string) => url.replace(/\/+$/, "");

const resolveDevHost = () => {
    if (typeof window === "undefined") {
        return "localhost";
    }

    const host = window.location.hostname;
    return host === "0.0.0.0" ? "localhost" : host;
};

const devHost = resolveDevHost();
const devProtocol = typeof window !== "undefined" ? window.location.protocol : "http:";

const resolveLoopbackAwareUrl = (envValue: string | undefined, fallbackPort: number) => {
    const fallback = `${devProtocol}//${devHost}:${fallbackPort}`;
    const raw = envValue?.trim() ? envValue : fallback;

    try {
        const parsed = new URL(raw);
        if (typeof window !== "undefined") {
            const currentHost = resolveDevHost();
            if (LOOPBACK_HOSTS.has(parsed.hostname) && LOOPBACK_HOSTS.has(currentHost)) {
                parsed.hostname = currentHost;
            }
        }
        return normalizeUrl(parsed.toString());
    } catch {
        return normalizeUrl(fallback);
    }
};

export const config_app = {
    url: resolveLoopbackAwareUrl(import.meta.env.VITE_APP_URL, 5173),
    api_url: resolveLoopbackAwareUrl(import.meta.env.VITE_API_URL, 8001),
    ai_url: resolveLoopbackAwareUrl(import.meta.env.VITE_AI_URL, 8002),
};
