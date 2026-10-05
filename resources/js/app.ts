import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';

const appName = import.meta.env.VITE_APP_NAME || 'Tenantly';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Página não encontrada: ${name}`);
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        const queryClient = new QueryClient({ defaultOptions: { queries: { staleTime: 30_000, retry: 1 } } });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(VueQueryPlugin, { queryClient })
            .mount(el);
    },
    progress: { color: 'var(--brand-primary)' },
});
