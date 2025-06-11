<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import { Bar, Pie } from 'vue-chartjs';
import { ArrowUturnLeftIcon } from '@heroicons/vue/24/outline'; // Ícono para el botón Limpiar

// --- REGISTRO DE COMPONENTES DE CHART.JS ---
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

// --- PROPS RECIBIDAS DEL CONTROLADOR ---
const props = defineProps({
    chartData: { type: Object, required: true },
    filtersOptions: { type: Object, required: true },
    currentFilters: { type: Object, required: true },
    isAdmin: { type: Boolean, required: true }
});

// --- ESTADO LOCAL DEL COMPONENTE ---
const localFilters = reactive({
    status: props.currentFilters.status || '4',
    desde: props.currentFilters.desde,
    hasta: props.currentFilters.hasta,
    search_solicitante: props.currentFilters.search_solicitante || '',
    search_tipo: props.currentFilters.search_tipo || '',
});

const isLoading = ref(false);
let debounceTimer = null;

const statusOptions = ref([
    { value: '2', label: 'Recibida' },
    { value: '10', label: 'En proceso' },
    { value: '3', label: 'Finalizada' },
    { value: '4', label: 'Publicada' },
]);

// --- PROPIEDADES COMPUTADAS PARA LOS GRÁFICOS ---
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
    },
};

const barChartDataComputed = computed(() => ({
    labels: Object.keys(props.chartData.bar),
    datasets: [{
        label: 'Cantidad de Muestras',
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1,
        data: Object.values(props.chartData.bar),
        borderRadius: 5,
    }],
}));

const pieChartDataComputed = computed(() => ({
    labels: Object.keys(props.chartData.pie),
    datasets: [{
        backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
        data: Object.values(props.chartData.pie),
    }],
}));

// --- MÉTODOS ---
function applyFilters() {
    router.post('/principal-dashboard', localFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    localFilters.status = '4';
    localFilters.desde = new Date(new Date().setMonth(new Date().getMonth() - 1)).toISOString().slice(0, 10);
    localFilters.hasta = new Date().toISOString().slice(0, 10);
    localFilters.search_solicitante = '';
    localFilters.search_tipo = '';
}

// --- HOOKS DE CICLO DE VIDA ---
watch(localFilters, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 500);
}, { deep: true });


onMounted(() => {
    router.on('start', () => { isLoading.value = true });
    router.on('finish', () => { isLoading.value = false });
});

</script>

<template>
    <Head title="Dashboard Principal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard Principal</h2>
        </template>

        <div class="py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8">

                <div class="bg-white p-6 shadow-sm rounded-lg mb-8 border">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filtros de Búsqueda</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-x-6 gap-y-5 items-end">
                        
                        <div>
                            <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select id="status-filter" v-model="localFilters.status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm h-9">
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <div v-if="isAdmin">
                            <label for="solicitante-filter" class="block text-sm font-medium text-gray-700 mb-1">Cliente (Solicitante)</label>
                            <select id="solicitante-filter" v-model="localFilters.search_solicitante" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm h-9">
                                <option value="">Todos</option>
                                <option v-for="solicitante in filtersOptions.solicitantes" :key="solicitante" :value="solicitante">{{ solicitante }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="tipo-filter" class="block text-sm font-medium text-gray-700 mb-1">Tipo Muestra</label>
                            <select id="tipo-filter" v-model="localFilters.search_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm h-9">
                                <option value="">Todos</option>
                                <option v-for="tipo in filtersOptions.tiposAmostra" :key="tipo" :value="tipo">{{ tipo }}</option>
                            </select>
                        </div>
                        
                        <div class="lg:col-span-2" :class="{ 'lg:col-start-4': !isAdmin }">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rango de Fechas</label>
                            <div class="flex items-center mt-1 space-x-2">
                                <input type="date" v-model="localFilters.desde" class="block w-full border-gray-300 rounded-md shadow-sm text-sm h-9 px-3">
                                <input type="date" v-model="localFilters.hasta" class="block w-full border-gray-300 rounded-md shadow-sm text-sm h-9 px-3">
                            </div>
                        </div>
                        
                        <div>
                            <button @click="resetFilters" class="w-full h-9 inline-flex items-center justify-center px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <ArrowUturnLeftIcon class="h-4 w-4 mr-2 text-gray-500" />
                                Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div v-if="isLoading" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg transition-opacity duration-300">
                        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z"></path>
                        </svg>
                        <span class="ml-3 text-lg text-gray-700">Actualizando...</span>
                    </div>

                    <div v-if="Object.keys(chartData.bar).length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg border">
                            <div class="p-5 border-b border-gray-200">
                                <h4 class="text-base font-semibold text-gray-800">Muestras por Estado</h4>
                            </div>
                            <div class="p-6">
                                <div class="min-h-[400px]">
                                    <Bar :data="barChartDataComputed" :options="chartOptions" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg border">
                            <div class="p-5 border-b border-gray-200">
                                <h4 class="text-base font-semibold text-gray-800">Distribución por Tipo de Muestra</h4>
                            </div>
                            <div class="p-6">
                                <div class="min-h-[400px]">
                                    <Pie :data="pieChartDataComputed" :options="chartOptions" />
                                </div>
                            </div>
                        </div>

                    </div>

                    <div v-else class="text-center py-16 bg-white rounded-lg shadow-sm border">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6v-3m3 3v-1m-6-1V7a2 2 0 012-2h2a2 2 0 012 2v3m-6 7h6" />
                        </svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">Sin Datos</h3>
                        <p class="mt-1 text-sm text-gray-500">No se encontraron datos para los filtros seleccionados.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>