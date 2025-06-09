<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import { Bar, Pie } from 'vue-chartjs';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    chartData: { type: Object, required: true },
    filtersOptions: { type: Object, required: true },
    currentFilters: { type: Object, required: true },
    isAdmin: { type: Boolean, required: true }
});

const localFilters = reactive({
    status: props.currentFilters.status || '4',
    desde: props.currentFilters.desde,
    hasta: props.currentFilters.hasta,
    search_solicitante: props.currentFilters.search_solicitante || '',
    search_tipo: props.currentFilters.search_tipo || '',
});

// NUEVO: Estado de carga
const isLoading = ref(false);
let debounceTimer = null;

const statusOptions = ref([
    { value: '2', label: 'Recibida' },
    { value: '10', label: 'En proceso' },
    { value: '3', label: 'Finalizada' },
    { value: '4', label: 'Publicada' },
]);

// La lógica de los gráficos no cambia
const barChartData = computed(() => ({ /* ... */ }));
const pieChartData = computed(() => ({ /* ... */ }));
const chartOptions = { responsive: true, maintainAspectRatio: false };
// ... (puedes copiar la lógica de los gráficos de la respuesta anterior si es necesario)
// Gráfico de Barras: Cantidad de muestras por Estado
const barChartDataComputed = computed(() => ({
    labels: Object.keys(props.chartData.bar),
    datasets: [{
        label: 'Cantidad de Muestras',
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        data: Object.values(props.chartData.bar),
        borderRadius: 5,
    }],
}));

// Gráfico Circular: Distribución por Tipo de Amostra
const pieChartDataComputed = computed(() => ({
    labels: Object.keys(props.chartData.pie),
    datasets: [{
        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
        data: Object.values(props.chartData.pie),
    }],
}));



function applyFilters() {
    // CORRECCIÓN CLAVE: Cambiamos .get por .post
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
    // applyFilters se llamará automáticamente gracias al watch
}

// --- NUEVO: WATCH PARA FILTRADO AUTOMÁTICO ---
watch(localFilters, () => {
    // Usamos un debounce para no enviar peticiones en cada tecla.
    // Espera 500ms después de la última modificación para aplicar el filtro.
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 500);
}, { deep: true }); // 'deep' es crucial para que observe cambios dentro del objeto


// --- NUEVO: MANEJO DEL INDICADOR DE CARGA ---
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow-sm rounded-lg mb-6 border">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                        <div>
                            <label for="status-filter" class="block text-sm font-medium text-gray-700">Estado</label>
                            <select id="status-filter" v-model="localFilters.status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs h-8 py-1.5">
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div v-if="isAdmin">
                            <label for="solicitante-filter" class="block text-sm font-medium text-gray-700">Cliente
                                (Solicitante)</label>
                            <select id="solicitante-filter" v-model="localFilters.search_solicitante"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs h-8 py-1.5">
                                <option value="">Todos</option>
                                <option v-for="solicitante in filtersOptions.solicitantes" :key="solicitante"
                                    :value="solicitante">{{ solicitante }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="tipo-filter" class="block text-sm font-medium text-gray-700">Tipo
                                Muestra</label>
                            <select id="tipo-filter" v-model="localFilters.search_tipo"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs h-8 py-1.5">
                                <option value="">Todos</option>
                                <option v-for="tipo in filtersOptions.tiposAmostra" :key="tipo" :value="tipo">{{ tipo }}
                                </option>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Rango de Fechas</label>
                            <div class="flex items-center mt-1 space-x-2">
                                <input type="date" v-model="localFilters.desde"
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-xs h-8 px-2">
                                <input type="date" v-model="localFilters.hasta"
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-xs h-8 px-2">
                            </div>
                        </div>
                        <div :class="{ 'lg:col-start-5': isAdmin, 'lg:col-start-4': !isAdmin }">
                            <button @click="resetFilters"
                                class="w-full h-8 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Limpiar</button>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div v-if="isLoading"
                        class="absolute inset-0 bg-white bg-opacity-75 z-10 flex items-center justify-center rounded-lg">
                        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z">
                            </path>
                        </svg>
                        <span class="ml-3 text-gray-700">Actualizando...</span>
                    </div>

                    <div v-if="Object.keys(chartData.bar).length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 border h-[50vh]">
                            <Bar :data="barChartDataComputed" :options="chartOptions" />
                        </div>
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 border h-[50vh]">
                            <Pie :data="pieChartDataComputed" :options="chartOptions" />
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-white rounded-lg shadow-sm border">
                        <p class="text-gray-500">No hay datos para mostrar con los filtros seleccionados.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
