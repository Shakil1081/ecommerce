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

export interface NavCategoryChild {
    id: string;
    name: string;
    slug: string;
}

export interface NavCategoryItem {
    id: string;
    name: string;
    slug: string;
    description?: string | null;
    is_featured: boolean;
    products_count: number;
    children: NavCategoryChild[];
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    flash: FlashMessages;
    app: AppMeta;
    categories?: NavCategoryItem[];
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

