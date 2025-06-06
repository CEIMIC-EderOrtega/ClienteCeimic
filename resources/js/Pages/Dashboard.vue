<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { defineProps, ref } from 'vue';
import { router } from '@inertiajs/vue3';

import MuestrasTable from '@/Components/MuestrasTable.vue';
import MuestrasFilters from '@/Components/MuestrasFilters.vue'; // Ya tienes esto

const props = defineProps({
    registros: {
        type: Array,
        default: () => []
    },
    filters: { // Estos son los filtros que vienen del backend tras una carga/filtrado
        type: Object,
        // Asegurar que el default inicial refleje la unidad 'Food'
        default: () => ({ unit: 'Food', status: '3' /* otros defaults si los tienes */ })
    },
    selectedUnit: { // Esta prop ahora siempre será 'Food' desde el controlador
        type: String,
        default: 'Food' // Default en el frontend
    },
    error: {
        type: String,
        default: null
    }
});

const loading = ref(false);

// Función para aplicar filtros haciendo una petición POST a Inertia
// Esta función es llamada por el evento @update-filters de MuestrasFilters
const applyFilters = (newFilters) => { // newFilters ya viene con unit: 'Food'
    console.log('Aplicando filtros (POST) desde Dashboard (disparado por botón):', newFilters);
    loading.value = true;

    router.post(route('dashboard'), newFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
        onError: (errors) => {
            console.error('Error de Inertia al aplicar filtros (POST):', errors);
            loading.value = false;
        }
    });
};
</script>

<template>

    <Head title="Dashboard Muestras Food" />
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
                        <div v-if="!loading && props.registros.length === 0 && !props.error"
                            class="p-6 text-center text-gray-500">
                            No se encontraron registros con los filtros aplicados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
