import { createSSRApp, h, type DefineComponent } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME || 'Enterprise E-Commerce';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => title ? `${title} | ${appName}` : appName,
        resolve: (name) => resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        ),
        setup({ App, props, plugin }) {
            const pinia = createPinia();
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(pinia);
        },
    })
);

