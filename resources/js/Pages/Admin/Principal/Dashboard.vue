<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import { Bar, Pie } from 'vue-chartjs';
import { FunnelIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import SearchableMultiSelect from '@/Components/SearchableMultiSelect.vue';

// --- REGISTRO DE CHART.JS ---
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

// --- PROPS ---
const props = defineProps({
    masterData: { type: Array, required: true },
    currentFilters: { type: Object, required: true },
    isAdmin: { type: Boolean, required: true },
    error: { type: String, default: null }
});

// --- ESTADO LOCAL ---
const isLoading = ref(false);

const dateFilters = reactive({
    desde: props.currentFilters.desde,
    hasta: props.currentFilters.hasta,
});

const memoryFilters = reactive({
    status: [],
    especie: [],
    selectedCompanies: [],
});

// --- LÓGICA DE FILTRADO EN MEMORIA ---

const statusOptions = computed(() => {
    const statuses = new Set(props.masterData.map(item => item.Situacao));
    return Array.from(statuses).sort();
});

const especieOptions = computed(() => {
    const especies = new Set(props.masterData.map(item => item.MATRIZ));
    return Array.from(especies).sort();
});

const companyOptions = computed(() => {
    const companies = new Set(props.masterData.map(item => item.Solicitante));
    return Array.from(companies).sort();
});

const filteredData = computed(() => {
    return props.masterData.filter(item => {
        const statusMatch = memoryFilters.status.length === 0 || memoryFilters.status.includes(item.Situacao);
        const especieMatch = memoryFilters.especie.length === 0 || memoryFilters.especie.includes(item.MATRIZ);
        const companyMatch = memoryFilters.selectedCompanies.length === 0 || memoryFilters.selectedCompanies.includes(item.Solicitante);
        return statusMatch && especieMatch && companyMatch;
    });
});


// --- PROPIEDADES PARA GRÁFICOS ---
const chartOptions = { responsive: true, maintainAspectRatio: false };

const barChartDataComputed = computed(() => {
    const emptyChartData = {
        labels: [],
        datasets: [{ label: 'Cantidad de Muestras', backgroundColor: 'rgba(59, 130, 246, 0.8)', data: [], borderRadius: 4, }],
    };
    if (!filteredData.value || filteredData.value.length === 0) return emptyChartData;
    const dataByStatus = {};
    filteredData.value.forEach(item => {
        const status = item.Situacao || 'Sin Estado';
        if (!dataByStatus[status]) dataByStatus[status] = 0;
        dataByStatus[status]++;
    });
    return {
        labels: Object.keys(dataByStatus),
        datasets: [{
            label: 'Cantidad de Muestras',
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            data: Object.values(dataByStatus),
            borderRadius: 4,
        }],
    };
});

const pieChartDataComputed = computed(() => {
    const emptyChartData = {
        labels: [],
        datasets: [{ backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'], data: [] }],
    };
    if (!filteredData.value || filteredData.value.length === 0) return emptyChartData;
    const dataByEspecie = {};
    filteredData.value.forEach(item => {
        const especie = item.MATRIZ || 'Sin Especie';
        if (!dataByEspecie[especie]) dataByEspecie[especie] = 0;
        dataByEspecie[especie]++;
    });
    return {
        labels: Object.keys(dataByEspecie),
        datasets: [{
            backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED'],
            data: Object.values(dataByEspecie),
        }],
    };
});


// --- MÉTODOS ---
function applyDateFilter() {
    router.get(route('principal.dashboard'), dateFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetMemoryFilters() {
    memoryFilters.status = [];
    memoryFilters.especie = [];
    memoryFilters.selectedCompanies = [];
}

function setPresetDateRange(days) {
    const today = new Date();
    const fromDate = new Date();
    fromDate.setDate(today.getDate() - days);
    const formatDate = (date) => date.toISOString().split('T')[0];
    dateFilters.hasta = formatDate(today);
    dateFilters.desde = formatDate(fromDate);
    applyDateFilter();
}

// ==========================================================
// === ESTA ES LA PARTE CORREGIDA QUE EVITA EL ERROR =======
// ==========================================================
onMounted(() => {
    router.on('start', () => {
        isLoading.value = true;
    });

    // Esta versión simple se asegura de que 'isLoading' siempre se ponga en false
    // al finalizar una visita, sin importar si fue exitosa o cancelada.
    router.on('finish', () => {
        isLoading.value = false;
    });
});
</script>

<template>
    <Head title="Dashboard Principal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard de Análisis</h2>
        </template>

        <div class="py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white p-5 shadow-sm rounded-lg mb-6 border">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-x-6 items-end">
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Rango de Fechas</label>
                            <div class="flex items-center mt-1 space-x-2">
                                <input type="date" v-model="dateFilters.desde" class="block w-full border-gray-300 rounded-md shadow-sm text-sm h-10 px-3">
                                <input type="date" v-model="dateFilters.hasta" class="block w-full border-gray-300 rounded-md shadow-sm text-sm h-10 px-3">
                            </div>
                        </div>
                        <div>
                            <button @click="applyDateFilter" class="w-full h-10 inline-flex items-center justify-center px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <CalendarDaysIcon class="h-5 w-5 mr-2" />
                                Aplicar Fechas
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div v-if="isLoading" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-20 flex items-center justify-center rounded-lg">
                        <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z"></path></svg>
                        <span class="ml-3 text-lg text-gray-700">Cargando Datos...</span>
                    </div>

                    <div v-if="masterData.length > 0">
                        <div class="bg-gray-50 p-5 rounded-lg mb-8 border border-dashed">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 items-end">
                                <div>
                                    <label for="status-mem-filter" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Situación</label>
                                    <SearchableMultiSelect id="status-mem-filter" v-model="memoryFilters.status" :options="statusOptions" placeholder="Todas las situaciones..." class="mt-1" />
                                </div>
                                <div>
                                    <label for="especie-mem-filter" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Especie (Matriz)</label>
                                     <SearchableMultiSelect id="especie-mem-filter" v-model="memoryFilters.especie" :options="especieOptions" placeholder="Todas las especies..." class="mt-1" />
                                </div>
                                <div v-if="props.isAdmin">
                                    <label for="company-mem-filter" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Empresa</label>
                                    <SearchableMultiSelect id="company-mem-filter" v-model="memoryFilters.selectedCompanies" :options="companyOptions" placeholder="Todas las empresas..." class="mt-1" />
                                </div>
                                <div>
                                    <button @click="resetMemoryFilters" class="w-full h-10 inline-flex items-center justify-center px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        <FunnelIcon class="h-4 w-4 mr-2 text-gray-500" />
                                        Limpiar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
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
                                    <h4 class="text-base font-semibold text-gray-800">Distribución por Especie (Matriz)</h4>
                                </div>
                                <div class="p-6">
                                    <div class="min-h-[400px]">
                                        <Pie :data="pieChartDataComputed" :options="chartOptions" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-16 bg-white rounded-lg shadow-sm border">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6v-3m3 3v-1m-6-1V7a2 2 0 012-2h2a2 2 0 012 2v3m-6 7h6" /></svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">Sin Datos</h3>
                        <p class="mt-1 text-sm text-gray-500">No se encontraron datos para los criterios de búsqueda.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>