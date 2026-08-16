import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { tooltip } from './directives/tooltip.js';

createInertiaApp({
    title: (title) => (title ? `${title} · Winchestack` : 'Winchestack'),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('tooltip', tooltip)
            .mount(el);
    },
    progress: {
        color: '#6366f1',
        showSpinner: false,
    },
});
