export interface User {
    id: number | string;
    name: string;
    email: string;
    role?: string;
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

export interface AppMeta {
    name: string;
    locale: string;
    currency: string;
    currency_symbol: string;
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    flash: FlashMessages;
    app: AppMeta;
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

