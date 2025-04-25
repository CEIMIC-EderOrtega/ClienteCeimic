<template>
    <div class="relative">
        <!-- Banner de carga mientras se generan los informes -->
        <div v-if="isGenerating" class="fixed top-4 right-4 bg-yellow-100 text-yellow-800 p-3 rounded shadow-lg z-50">
            Generando informe… esto puede tardar varios minutos.
        </div>

        <div class="card p-3 bg-white shadow rounded-lg app-main-container" v-if="!showDetailPanel">
            <div class="datatable-header-responsive flex flex-wrap items-center mb-4 gap-2">
                <div class="flex flex-grow items-center gap-2 order-1 md:order-1 w-full md:w-auto">
                    <div class="flex gap-2 responsive-button-group-always-visible">
                        <button type="button" @click="clearFilters"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 transition duration-150 ease-in-out">
                            <FunnelIcon class="w-4 h-4 mr-1 inline-block" /> Limpiar Filtros
                        </button>
                    </div>

                    <Transition name="fade">
                        <div v-if="selectedItems.length > 0"
                            class="flex flex-wrap gap-2 responsive-button-group-actions">
                            <!-- Botón Informe: deshabilitado mientras isGenerating = true -->
                            <button type="button" @click="ejecutarInforme" title="Descargar Informe"
                                :disabled="isGenerating"
                                class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-150 ease-in-out disabled:opacity-50">
                                <ArrowDownTrayIcon class="w-4 h-4 mr-1 inline-block" />
                                Informe ({{ selectedItems.length }})
                            </button>
                            <!-- Botón Cadena también deshabilitado -->
                            <button type="button" @click="ejecutarCadena" title="Descargar Cadena"
                                :disabled="isGenerating"
                                class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-150 ease-in-out disabled:opacity-50">
                                <LinkIcon class="w-4 h-4 mr-1 inline-block" />
                                Cadena ({{ selectedItems.length }})
                            </button>
                        </div>
                    </Transition>
                </div>

                <div class="flex items-center gap-2 order-2 md:order-2 ml-auto w-full md:w-auto flex-shrink-0">
                    <div class="relative flex-grow">
                        <MagnifyingGlassIcon
                            class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm" />
                        <input type="text" v-model="globalFilterValue" placeholder="Buscar en todas las columnas..."
                            class="w-full pl-8 pr-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm" />
                    </div>

                    <button type="button" @click="toggleColumnVisibilityDropdown"
                        class="p-2 rounded-md border border-gray-300 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 transition duration-150 ease-in-out flex-shrink-0">
                        <CogIcon class="w-5 h-5 text-gray-700" />
                    </button>
                </div>
            </div>

            <div v-if="showColumnToggle"
                class="absolute right-3 mt-2 w-48 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 p-3">
                <p class="text-sm font-semibold mb-2 border-b pb-1">
                    Mostrar/Ocultar Columnas
                </p>
                <div class="flex flex-col gap-2 max-h-60 overflow-y-auto text-sm">
                    <label v-for="col in definedColumns" :key="'toggle-' + col.field" class="inline-flex items-center">
                        <input type="checkbox" v-model="columnVisibility[col.field]"
                            class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out rounded" />
                        <TableCellsIcon class="w-4 h-4 mr-1 text-gray-500 inline-block" />
                        <span class="ml-2 text-gray-700">{{ col.header }}</span>
                    </label>
                </div>
            </div>

            <div class="overflow-x-auto" :style="{ maxHeight: `calc(90vh - 220px - ${paginationHeight}px)` }">
                <table class="min-w-full border-collapse compact-datatable">
                    <thead>
                        <tr>
                            <th class="p-2 text-center border-b border-gray-300 bg-gray-100 font-bold text-sm">
                                <input type="checkbox" :checked="allVisibleSelected && paginatedItems.length > 0"
                                    :indeterminate.prop="paginatedItems.length > 0 && !allVisibleSelected && selectedItems.length > 0"
                                    @change="toggleAllSelection"
                                    class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out" />
                            </th>
                            <template v-for="col in definedColumns" :key="col.field">
                                <th v-if="columnVisibility[col.field]"
                                    class="p-2 text-start border-b border-gray-300 bg-gray-100 font-bold text-sm"
                                    style="min-width: 8rem">
                                    <div class="flex flex-col">
                                        <span class="truncate">{{ col.header }}</span>
                                        <input type="text" v-model="columnFilters[col.field]"
                                            :placeholder="`Filtrar ${col.header}`"
                                            class="mt-1 px-2 py-0.5 w-full text-xs border border-gray-300 rounded focus:outline-none focus:border-indigo-500" />
                                    </div>
                                </th>
                            </template>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in paginatedItems" :key="item.cdamostra"
                            class="border-b border-gray-200 hover:bg-blue-100 cursor-pointer transition duration-150 ease-in-out"
                            :class="{ 'bg-gray-100': isSelected(item) }" @click="handleRowClick($event, item)">
                            <td class="p-2 text-center border-b border-gray-200">
                                <input type="checkbox" :checked="isSelected(item)" @change="toggleSelection(item)"
                                    class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out" />
                            </td>
                            <template v-for="col in definedColumns" :key="col.field + '-data'">
                                <td v-if="columnVisibility[col.field]"
                                    class="p-2 text-start border-b border-gray-200 text-sm">
                                    {{ item[col.field] ?? "-" }}
                                </td>
                            </template>
                        </tr>

                        <tr v-if="filteredItems.length === 0">
                            <td :colspan="definedColumns.length + 1" class="text-center p-4 text-gray-500">
                                No se encontraron registros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div ref="pagination"
                class="flex flex-col sm:flex-row justify-between items-center mt-4 p-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-700">
                <span class="mb-2 sm:mb-0 text-center sm:text-left">
                    Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} a
                    {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }}
                    de {{ filteredItems.length }} registros ({{ items.length }} total sin filtro)
                </span>
                <div class="flex flex-wrap items-center justify-center gap-1">
                    <span class="mr-1">Registros:</span>
                    <select v-model.number="itemsPerPage" @change="currentPage = 1"
                        class="text-sm border border-gray-300 rounded px-1 py-0.5 focus:outline-none focus:border-indigo-500">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option :value="items.length">Todos</option>
                    </select>
                    <button @click="goToPage(1)" :disabled="currentPage === 1"
                        class="px-3 py-1 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out">
                        Inicio
                    </button>
                    <button @click="prevPage" :disabled="currentPage === 1"
                        class="px-3 py-1 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out">
                        Anterior
                    </button>
                    <span class="px-3 py-1">Página {{ currentPage }} de {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages"
                        class="px-3 py-1 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out">
                        Siguiente
                    </button>
                    <button @click="goToPage(totalPages)" :disabled="currentPage === totalPages"
                        class="px-3 py-1 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out">
                        Última
                    </button>
                </div>
            </div>
        </div>

        <Transition name="slide-from-top-left">
            <div class="card p-3 bg-white shadow rounded-lg app-main-container detail-view-container"
                v-if="showDetailPanel">
                <!-- Detalle completo como tenías -->
                <div class="detail-header flex justify-between items-center mb-4 border-b border-gray-200 pb-3">
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <InformationCircleIcon class="w-6 h-6 inline-block" /> Información de la Muestra
                    </h2>
                    <button type="button" class="text-blue-600 hover:text-blue-800 text-sm font-semibold"
                        @click="hideDetailPanel">
                        <ArrowLeftIcon class="w-4 h-4 mr-1 inline-block" /> Volver
                    </button>
                </div>
                <div class="detail-content grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="info-card bg-gray-50 p-4 rounded-md border border-gray-200">
                        <h3 class="text-lg font-semibold mb-3 border-b border-gray-200 pb-2 flex items-center gap-2">
                            <ListBulletIcon class="w-5 h-5 inline-block" /> Detalles de la Muestra
                        </h3>
                        <ul class="info-list space-y-2 text-sm">
                            <template v-for="col in definedColumns" :key="'detail-' + col.field">
                                <li v-if="columnVisibility[col.field]" class="flex justify-between">
                                    <strong class="text-gray-700">{{ col.header }}:</strong>
                                    <span class="text-gray-600 text-right flex-1 ml-4">{{ detailRecord[col.field] ?? "-"
                                        }}</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <div class="info-card bg-gray-50 p-4 rounded-md border border-gray-200">
                        <h3 class="text-lg font-semibold mb-3 border-b border-gray-200 pb-2 flex items-center gap-2">
                            <ArchiveBoxIcon class="w-5 h-5 inline-block" /> Otros Datos Relevantes
                        </h3>
                        <p class="text-sm text-gray-600">
                            Aquí puedes agregar contenido adicional específico de esta muestra...
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import axios from 'axios';
import {
    CogIcon,
    FunnelIcon,
    ArrowDownTrayIcon,
    LinkIcon,
    MagnifyingGlassIcon,
    TableCellsIcon,
    InformationCircleIcon,
    ListBulletIcon,
    ArchiveBoxIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    items: { type: Array, default: () => [] },
    rows: { type: Number, default: 10 },
});

const selectedItems = ref([]);
const showDetailPanel = ref(false);
const detailRecord = ref({});
const isGenerating = ref(false);

const definedColumns = ref([
    { field: "Grupo", header: "Grupo" },
    { field: "Processo", header: "Processo" },
    { field: "Numero", header: "Numero" },
    { field: "Id. Amostra", header: "Id. Amostra" },
    { field: "Tipo Amostra", header: "Tipo Amostra" },
    { field: "Solicitante", header: "Solicitante" },
    { field: "Coleta", header: "Coleta" },
    { field: "Recepcao", header: "Recepcao" },
    { field: "Previsao", header: "Previsao" },
    { field: "Situacao", header: "Situacao" },
    { field: "Data_Situacao", header: "Data_Situacao" },
    { field: "cdamostra", header: "cdamostra" },
]);

const showColumnToggle = ref(false);
const columnVisibility = ref({});

onMounted(() => initializeColumnVisibility());
watch(() => props.items, () => initializeColumnVisibility(), { immediate: true });
function initializeColumnVisibility() {
    const vis = {};
    definedColumns.value.forEach(c => {
        vis[c.field] = columnVisibility.value[c.field] ?? true;
    });
    columnVisibility.value = vis;
}

function toggleColumnVisibilityDropdown() {
    showColumnToggle.value = !showColumnToggle.value;
}

const globalFilterValue = ref("");
const columnFilters = ref({});
onMounted(() => initializeColumnFilters());
watch(() => props.items, () => initializeColumnFilters(), { immediate: true });
function initializeColumnFilters() {
    const f = {};
    definedColumns.value.forEach(c => {
        f[c.field] = columnFilters.value[c.field] ?? "";
    });
    columnFilters.value = f;
}

const filteredItems = computed(() => {
    let items = props.items;
    const gf = globalFilterValue.value.toLowerCase().trim();
    if (gf) {
        items = items.filter(item =>
            definedColumns.value.some(col =>
                String(item[col.field] ?? "").toLowerCase().includes(gf)
            )
        );
    }
    definedColumns.value.forEach(col => {
        const cf = columnFilters.value[col.field].toLowerCase().trim();
        if (cf) {
            items = items.filter(item =>
                String(item[col.field] ?? "").toLowerCase().includes(cf)
            );
        }
    });
    return items;
});

const currentPage = ref(1);
const itemsPerPage = ref(props.rows);
const totalPages = computed(() =>
    filteredItems.value.length
        ? Math.ceil(filteredItems.value.length / itemsPerPage.value)
        : 1
);
const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredItems.value.slice(start, start + itemsPerPage.value);
});

watch(totalPages, n => {
    if (currentPage.value > n) currentPage.value = n;
}, { immediate: true });
watch([globalFilterValue, columnFilters], () => {
    currentPage.value = 1;
}, { deep: true });

const isSelected = item =>
    selectedItems.value.some(si => si.cdamostra === item.cdamostra);
function toggleSelection(item) {
    const idx = selectedItems.value.findIndex(si => si.cdamostra === item.cdamostra);
    if (idx > -1) selectedItems.value.splice(idx, 1);
    else selectedItems.value.push(item);
}
const allVisibleSelected = computed(() =>
    paginatedItems.value.length > 0 && paginatedItems.value.every(isSelected)
);
function toggleAllSelection() {
    if (allVisibleSelected.value) {
        paginatedItems.value.forEach(item => {
            const idx = selectedItems.value.findIndex(si => si.cdamostra === item.cdamostra);
            if (idx > -1) selectedItems.value.splice(idx, 1);
        });
    } else {
        paginatedItems.value.forEach(item => {
            if (!isSelected(item)) selectedItems.value.push(item);
        });
    }
}

function handleRowClick(e, item) {
    let t = e.target;
    while (t && t !== e.currentTarget) {
        if (t.tagName === "INPUT" && t.type === "checkbox") return;
        t = t.parentElement;
    }
    detailRecord.value = item;
    showDetailPanel.value = true;
}
function hideDetailPanel() {
    showDetailPanel.value = false;
    detailRecord.value = {};
}

async function ejecutarInforme() {
    if (!selectedItems.value.length) {
        alert("No hay elementos seleccionados para generar el informe.");
        return;
    }
    isGenerating.value = true;
    try {
        const resp = await axios.post(
            window.location.origin + '/muestras/extraer-laudos',
            { selected_ids: selectedItems.value.map(i => i.cdamostra) },
            { timeout: 180000 }
        );
        const data = resp.data;
        if (data.success && data.data.length) {
            for (const f of data.data) {
                const mime = getMimeType(f.NombreLaudo);
                await downloadBase64File(f.Laudo, f.NombreLaudo, mime);
            }
            alert(`Descarga iniciada: ${data.data.length} archivo(s).`);
        } else {
            alert(data.message || 'No se encontraron laudos.');
        }
    } catch (e) {
        console.error(e);
        alert('Error al generar informe: ' + (e.response?.data?.message || e.message));
    } finally {
        isGenerating.value = false;
    }
}

function ejecutarCadena() {
    if (!selectedItems.value.length) return;
    alert(`Cadena para ${selectedItems.value.length} filas seleccionadas.`);
}

function getMimeType(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    switch (ext) {
        case 'pdf': return 'application/pdf';
        case 'xlsx': return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        case 'zip': return 'application/zip';
        default: return 'application/octet-stream';
    }
}

async function downloadBase64File(base64Data, filename, mimeType) {
    try {
        const res = await fetch(`data:${mimeType};base64,${base64Data}`);
        const blob = await res.blob();
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error('Error al descargar:', err);
        alert(`Error al descargar "${filename}": ${err.message}`);
    }
}

function nextPage() {
    if (currentPage.value < totalPages.value) currentPage.value++;
}
function prevPage() {
    if (currentPage.value > 1) currentPage.value--;
}
function goToPage(n) {
    const p = Number(n);
    if (p >= 1 && p <= totalPages.value) currentPage.value = p;
}

const pagination = ref(null);
const paginationHeight = ref(0);
watch(pagination, el => {
    if (el) {
        requestAnimationFrame(() => {
            const s = getComputedStyle(el);
            const mb = parseInt(s.marginBottom, 10) || 0;
            paginationHeight.value = el.offsetHeight + mb;
        });
    } else {
        paginationHeight.value = 0;
    }
}, { flush: "post" });

function clearFilters() {
    globalFilterValue.value = "";
    const cf = {};
    definedColumns.value.forEach(c => cf[c.field] = "");
    columnFilters.value = cf;
    selectedItems.value = [];
    currentPage.value = 1;
}
</script>

<style scoped>
.slide-from-top-left-enter-active,
.slide-from-top-left-leave-active {
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.slide-from-top-left-enter-from,
.slide-from-top-left-leave-to {
    opacity: 0;
    transform: translate(-20px, -20px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.card {
    background-color: #fff;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 0.75rem !important;
}

@media (max-width: 960px) {
    .datatable-header-responsive {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<style>
.compact-datatable {
    border-collapse: collapse;
    width: 100%;
}

.compact-datatable th,
.compact-datatable td {
    padding: 0.5rem;
    font-size: 0.8125rem;
    border-color: #dee2e6;
    color: #495057;
}

.compact-datatable th {
    background-color: #f2f2f2;
    font-weight: bold;
}

.compact-datatable td {
    border-bottom: 1px solid #dee2e6;
}

.compact-datatable tbody tr:hover {
    background-color: #acd4f2;
    color: #00345d;
}
</style>
