import './bootstrap';
import '../scss/app.scss';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME || 'Enterprise E-Commerce';

createInertiaApp({
    title: (title) => title ? `${title} | ${appName}` : appName,
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob<DefineComponent>('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#10b981',
        showSpinner: true,
    },
});
