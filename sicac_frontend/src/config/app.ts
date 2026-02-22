const LOOPBACK_HOSTS = new Set(["localhost", "127.0.0.1", "0.0.0.0", "::1", "[::1]"]);

const normalizeUrl = (url: string) => url.replace(/\/+$/, "");

const hasWindow = typeof window !== "undefined";
const currentProtocol = hasWindow ? window.location.protocol : "http:";
const currentHost = hasWindow
    ? window.location.hostname === "0.0.0.0"
        ? "localhost"
        : window.location.hostname
    : "localhost";
const currentOrigin = hasWindow ? normalizeUrl(window.location.origin) : "http://localhost";
const devBase = `${currentProtocol}//${currentHost}`;

const resolveUrl = (envValue: string | undefined, fallbackUrl: string) => {
    const raw = envValue?.trim() ? envValue.trim() : fallbackUrl;

    if (raw.startsWith("/")) {
        return normalizeUrl(`${currentOrigin}${raw}`);
    }

    try {
        const parsed = new URL(raw);
        if (LOOPBACK_HOSTS.has(parsed.hostname) && LOOPBACK_HOSTS.has(currentHost)) {
            parsed.hostname = currentHost;
        }
        return normalizeUrl(parsed.toString());
    } catch {
        return normalizeUrl(fallbackUrl);
    }
};

const defaultAppUrl = import.meta.env.PROD ? currentOrigin : `${devBase}:5173`;
const defaultApiUrl = import.meta.env.PROD ? currentOrigin : `${devBase}:8001`;
const defaultAiUrl = import.meta.env.PROD ? `${currentOrigin}/ai` : `${devBase}:8002`;

export const config_app = {
    url: resolveUrl(import.meta.env.VITE_APP_URL, defaultAppUrl),
    api_url: resolveUrl(import.meta.env.VITE_API_URL, defaultApiUrl),
    ai_url: resolveUrl(import.meta.env.VITE_AI_URL, defaultAiUrl),
};
