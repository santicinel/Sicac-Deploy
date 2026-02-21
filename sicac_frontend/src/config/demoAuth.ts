import type { AuthUser, Role } from "@/store/authStore";

interface LoginPayloadLike {
    email: string;
    password: string;
}

interface DemoAccount extends AuthUser {
    password: string;
    label: string;
    redirectPath: string;
}

const DEMO_ACCOUNTS: DemoAccount[] = [
    {
        name: "Cliente Demo",
        email: "cliente@demo.com",
        password: "demo1234",
        role: "user",
        label: "Cliente",
        redirectPath: "/home",
    },
    {
        name: "Tecnico Demo",
        email: "tecnico@demo.com",
        password: "demo1234",
        role: "technician",
        label: "Tecnico",
        redirectPath: "/technician/claims",
    },
    {
        name: "Admin Demo",
        email: "admin@demo.com",
        password: "demo1234",
        role: "admin",
        label: "Admin",
        redirectPath: "/admin/claims",
    },
];

const normalizeEmail = (email: string) => email.trim().toLowerCase();

export const DEMO_CREDENTIALS = DEMO_ACCOUNTS.map((account) => ({
    label: account.label,
    email: account.email,
    password: account.password,
    role: account.role,
    redirectPath: account.redirectPath,
}));

export const resolveDemoUser = (
    payload: LoginPayloadLike,
    allowedRoles?: Role[]
): AuthUser | null => {
    const email = normalizeEmail(payload.email);
    const password = payload.password;

    const match = DEMO_ACCOUNTS.find((account) => {
        if (allowedRoles && !allowedRoles.includes(account.role)) {
            return false;
        }
        return normalizeEmail(account.email) === email && account.password === password;
    });

    if (!match) return null;

    return {
        name: match.name,
        email: match.email,
        role: match.role,
    };
};
