<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Asumiendo que tu layout se llama así
import { Head } from '@inertiajs/vue3';
import { defineProps, ref } from 'vue';
import { router } from '@inertiajs/vue3'; // Importar router de Inertia

import MuestrasTable from '@/Components/MuestrasTable.vue'; // Asegúrate que la ruta sea correcta
import MuestrasFilters from '@/Components/MuestrasFilters.vue'; // Asegúrate que la ruta sea correcta

const props = defineProps({
    registros: {
        type: Array,
        default: () => []
    },
    // Recibe los filtros aplicados desde el backend (usados para la carga actual)
    filters: {
        type: Object,
        default: () => ({})
    },
    // Recibe la unidad seleccionada desde el backend
    selectedUnit: {
        type: String,
        default: 'Enviro' // O el default que definas en el controlador
    },
    // Recibe posibles errores del backend
    error: {
        type: String,
        default: null
    }
});

const loading = ref(false); // Estado de carga local para feedback visual

// Función para aplicar filtros haciendo una petición POST a Inertia
const applyFilters = (newFilters) => {
    console.log('Aplicando filtros (POST) desde Dashboard:', newFilters);
    loading.value = true; // Activar carga local

    // Ya no es tan necesario limpiar filtros nulos/vacíos para POST,
    // pero puedes mantenerlo si tu backend lo prefiere.
    // const cleanFilters = {};
    // for (const key in newFilters) {
    //     if (newFilters[key] !== null && newFilters[key] !== '') {
    //         cleanFilters[key] = newFilters[key];
    //     }
    // }

    // *** CAMBIO AQUÍ: Usar router.post ***
    // Enviamos los filtros directamente en el cuerpo de la petición.
    // El backend (Controlador) debe leerlos desde $request->input() o $request->all().
    router.post(route('dashboard'), newFilters, { // Ruta 'dashboard' ahora acepta POST
        preserveState: true,    // Mantiene estado local de Vue (como scroll, datos no afectados)
        preserveScroll: true,   // Mantiene posición de scroll
        replace: true,          // Reemplaza entrada en historial para no acumular filtros con botón atrás/adelante
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
        onError: (errors) => {
            console.error('Error de Inertia al aplicar filtros (POST):', errors);
            loading.value = false;
            // Considera mostrar un mensaje al usuario aquí si la petición POST falla.
            // Podrías usar una librería de notificaciones o un estado local.
            // Ejemplo: alert('Hubo un error al aplicar los filtros.');
        }
    });
};
</script>

<template>

    <Head title="Dashboard Muestras" />

    <AuthenticatedLayout>
        <div class="py-8 md:py-12">
            <div class="max-w-full mx-auto px-2 sm:px-4 lg:px-6">

                <div v-if="props.error"
                    class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-md text-sm shadow">
                    <strong>Error:</strong> {{ props.error }}
                </div>

                <div class="bg-white overflow-hidden shadow-lg sm:rounded-lg p-4">

                     <MuestrasFilters :initial-filters="props.filters" @update-filters="applyFilters" />

                    <div v-if="loading" class="text-center py-10 text-gray-500">
                        <svg class="animate-spin h-6 w-6 inline-block mr-2 text-blue-600"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z">
                            </path>
                        </svg>
                        Actualizando datos...
                    </div>

                    <div v-show="!loading">
                         <MuestrasTable :items="props.registros" :rows="20" />

                        <div v-if="!loading && props.registros.length === 0 && !props.error" class="p-6 text-center text-gray-500">
                            No se encontraron registros con los filtros aplicados.
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
