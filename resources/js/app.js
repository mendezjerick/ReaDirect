import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => (title ? `${title} | ReaDirect` : 'ReaDirect'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = (err, instance, info) => {
            console.error(err, info);
            document.body.innerHTML = `<div style="padding:20px;background:#fee2e2;color:#991b1b;z-index:9999;position:fixed;inset:0;overflow:auto;">
                <h2 style="font-size:24px;font-weight:bold;margin-bottom:16px;">Global Vue Error</h2>
                <pre style="background:white;padding:16px;border-radius:8px;">${err.stack || err}</pre>
                <p style="margin-top:16px;font-weight:bold;">Info: ${info}</p>
            </div>`;
        };
        app.use(plugin).mount(el);
    },
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

window.ReaDirect = { router };
