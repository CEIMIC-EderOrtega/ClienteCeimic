<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { defineProps, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

import MuestrasTable from '@/Components/MuestrasTable.vue';
import MuestrasFilters from '@/Components/MuestrasFilters.vue';

const props = defineProps({
    registros: {
        type: Array,
        default: () => []
    },
    filters: {
        type: Object,
        default: () => ({ unit: 'Food', status: '3' })
    },
    selectedUnit: {
        type: String,
        default: 'Food'
    },
    error: {
        type: String,
        default: null
    },
    mrlReportEnabled: { // <-- AÑADIR ESTA NUEVA PROP
        type: Boolean,
        default: true
    }
});

const currentRegistros = ref(props.registros);
const currentFilters = ref(props.filters);
const currentError = ref(props.error);
const loading = ref(props.registros.length === 0 && !props.error);

const applyFilters = (newFilters) => {
    currentFilters.value = { ...currentFilters.value, ...newFilters };
    console.log('Aplicando filtros (POST) desde Dashboard:', currentFilters.value);
    loading.value = true;
    currentError.value = null;

    router.post(route('dashboard'), currentFilters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            currentRegistros.value = page.props.registros;
            currentFilters.value = page.props.filters;
            currentError.value = page.props.error;
            // No necesitas actualizar mrlReportEnabled aquí porque es un flag global
            // que se mantiene constante a menos que se recargue la página.
        },
        onFinish: () => { loading.value = false; },
        onError: (errors) => {
            console.error('Error de Inertia al aplicar filtros (POST):', errors);
            currentError.value = 'Error al cargar los datos. Por favor, inténtelo de nuevo.';
            loading.value = false;
        }
    });
};

onMounted(() => {
    if (props.registros.length === 0 && !props.error) {
        console.log('Dashboard montado, iniciando carga de datos inicial...');
        applyFilters(props.filters);
    }
});
</script>

<template>
    <Head title="Dashboard Muestras Food" />
    <AuthenticatedLayout>
        <div class="py-8 md:py-12">
            <div class="max-w-full mx-auto px-2 sm:px-4 lg:px-6">
                <div v-if="currentError"
                    class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-md text-sm shadow">
                    <strong>Error:</strong> {{ currentError }}
                </div>

                <div class="bg-white overflow-hidden shadow-lg sm:rounded-lg p-4">
                    <MuestrasFilters :initial-filters="currentFilters" @update-filters="applyFilters" />

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
                        <MuestrasTable :items="currentRegistros" :rows="20" :mrl-report-enabled="props.mrlReportEnabled" /> <div v-if="!loading && currentRegistros.length === 0 && !currentError"
                            class="p-6 text-center text-gray-500">
                            No se encontraron registros con los filtros aplicados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
