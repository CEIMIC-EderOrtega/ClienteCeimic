// resources/js/app.js

import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'vue-toastification/dist/index.css'; // Estilos CSS para los toasts
// --- INICIO: Importaciones para Vue Toastification ---
import Toast, { POSITION } from 'vue-toastification';
import 'vue-toastification/dist/index.css'; // Estilos CSS para los toasts
// --- FIN: Importaciones para Vue Toastification ---
import '../css/app.css';
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // --- INICIO: Configuración y uso de Vue Toastification ---
        const toastOptions = {
            position: POSITION.TOP_RIGHT, // Posición de las notificaciones (puedes cambiarla)
            timeout: 4000,              // Duración por defecto en milisegundos (4 segundos)
            closeOnClick: true,         // Cerrar el toast al hacer clic en él
            pauseOnFocusLoss: true,     // Pausar el timeout cuando la ventana pierde el foco
            pauseOnHover: true,         // Pausar el timeout cuando el cursor está sobre el toast
            draggable: true,            // Permitir arrastrar los toasts
            draggablePercent: 0.6,      // Sensibilidad del arrastre
            showCloseButtonOnHover: false, // Mostrar botón de cierre solo al pasar el cursor (o siempre con `true`)
            hideProgressBar: false,     // Mostrar u ocultar la barra de progreso del timeout
            closeButton: "button",      // Tipo de botón de cierre: "button", "fontawesome", false
            icon: true,                 // Mostrar iconos por defecto (éxito, error, info, warning)
            rtl: false,                 // Soporte para Right-To-Left
            // Aquí puedes encontrar más opciones en la documentación oficial de vue-toastification:
            // https://vue-toastification.maronato.dev/
        };
        vueApp.use(Toast, toastOptions);
        // --- FIN: Configuración y uso de Vue Toastification ---

        vueApp.mount(el);
        // Si tu setup original devolvía vueApp (a veces se hace para testing o frameworks específicos), mantenlo.
        // return vueApp;
    },
    progress: {
        color: '#4B5563',
    },
});
