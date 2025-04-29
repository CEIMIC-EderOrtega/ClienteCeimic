<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import axios from 'axios';
import { route } from 'ziggy-js';
import {
    CogIcon,
    ArrowDownTrayIcon,
    LinkIcon,
    TableCellsIcon,
    InformationCircleIcon,
    ListBulletIcon,
    ArchiveBoxIcon,
    ArrowLeftIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

// --- PROPS ---
const props = defineProps({
    items: { type: Array, default: () => [] },
    rows: { type: Number, default: 10 },
});

// --- ESTADOS ---
const sampleResults = ref([]);
const isLoadingResults = ref(false);
const resultsError = ref(null);
const selectedItems = ref([]);
const showDetailPanel = ref(false);
const detailRecord = ref({});
const isGenerating = ref(false);
const showColumnToggle = ref(false);
const columnVisibility = ref({});
const pagination = ref(null);
const paginationHeight = ref(60);

// --- COLUMNAS (Tabla Principal) ---
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
    { field: "cdunidade", header: "cdunidade" },
    { field: "moroso", header: "Moroso" },
    { field: "mrl", header: "MRL" },
    { field: "mercados", header: "Mercados" },
    { field: "retailers", header: "Retailers" },
]);

// --- COLUMNAS (Tabla Resultados Detalle) ---
const resultsColumns = ref([
    { field: "NUMERO", header: "Número" },
    { field: "IDAMOSTRA", header: "Id. Amostra" },
    { field: "METODO", header: "Método" },
    { field: "PARAMETRO", header: "Parámetro" },
    { field: "RES", header: "Resultado" },
    { field: "UNID", header: "Unidad" },
]);

// --- INICIALIZACIÓN Y WATCHERS ---
onMounted(() => {
    initializeColumnVisibility();
    updatePaginationHeight();
    document.addEventListener('click', closeColumnToggleOnClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeColumnToggleOnClickOutside);
});

watch(() => definedColumns.value, () => initializeColumnVisibility(), { deep: true });
watch(pagination, updatePaginationHeight, { flush: "post" });

watch(detailRecord, (newDetailRecord, oldDetailRecord) => {
    if (showDetailPanel.value && newDetailRecord?.cdamostra && newDetailRecord.cdamostra !== oldDetailRecord?.cdamostra) {
        fetchSampleResults(newDetailRecord.cdamostra);
    } else if (!showDetailPanel.value) {
        sampleResults.value = [];
        resultsError.value = null;
    }
}, { deep: true });

// --- LÓGICA VISIBILIDAD COLUMNAS (Tabla Principal) ---
function initializeColumnVisibility() {
    const defaultHiddenColumns = new Set(['cdamostra', 'cdunidade', 'moroso', 'mrl', 'mercados', 'retailers']);
    const vis = {};
    definedColumns.value.forEach(c => {
        vis[c.field] = columnVisibility.value.hasOwnProperty(c.field)
            ? columnVisibility.value[c.field]
            : !defaultHiddenColumns.has(c.field);
    });
    columnVisibility.value = vis;
}

function toggleColumnVisibilityDropdown() {
    showColumnToggle.value = !showColumnToggle.value;
}

function closeColumnToggleOnClickOutside(event) {
    const dropdown = document.getElementById('column-toggle-dropdown');
    const button = document.getElementById('column-toggle-button');
    if (showColumnToggle.value && dropdown && button && !dropdown.contains(event.target) && !button.contains(event.target)) {
        showColumnToggle.value = false;
    }
}

// --- PAGINACIÓN (CLIENT-SIDE - Solo para vista tabla) ---
const currentPage = ref(1);
const itemsPerPage = ref(props.rows);
const filteredItems = computed(() => props.items);

const totalPages = computed(() => {
    const total = filteredItems.value.length;
    const perPage = Number(itemsPerPage.value);
    const effectivePerPage = (perPage <= 0 || (total > 0 && perPage >= total)) ? total : perPage;
    if (total === 0) return 1;
    return Math.ceil(total / effectivePerPage);
});

watch([filteredItems, itemsPerPage], () => {
    const currentTotalPages = totalPages.value;
    if (currentPage.value > currentTotalPages) {
        currentPage.value = currentTotalPages || 1;
    } else if (currentPage.value < 1 && currentTotalPages > 0) {
        currentPage.value = 1;
    } else if (currentTotalPages === 0) {
        currentPage.value = 1;
    }
}, { immediate: true });

const paginatedItems = computed(() => {
    const perPage = Number(itemsPerPage.value);
    const total = filteredItems.value.length;
    if (total === 0 || perPage <= 0 || perPage >= total) {
        return filteredItems.value;
    }
    const start = (currentPage.value - 1) * perPage;
    const end = start + perPage;
    return filteredItems.value.slice(start, end);
});

function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }
function prevPage() { if (currentPage.value > 1) currentPage.value--; }
function goToPage(n) {
    const p = Number(n);
    if (p >= 1 && p <= totalPages.value) currentPage.value = p;
}

function updatePaginationHeight() {
    if (pagination.value) {
        requestAnimationFrame(() => {
            const styles = getComputedStyle(pagination.value);
            const marginTop = parseInt(styles.marginTop, 10) || 0;
            const marginBottom = parseInt(styles.marginBottom, 10) || 0;
            paginationHeight.value = pagination.value.offsetHeight + marginTop + marginBottom;
        });
    } else {
        paginationHeight.value = 60;
    }
}

// --- SELECCIÓN DE FILAS ---
const isSelected = (item) => item?.cdamostra && selectedItems.value.some(si => si.cdamostra === item.cdamostra);

function toggleSelection(item) {
    if (!item?.cdamostra) return;
    const index = selectedItems.value.findIndex(si => si.cdamostra === item.cdamostra);
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(item);
    }
}

const allVisibleSelected = computed(() =>
    paginatedItems.value.length > 0 && paginatedItems.value.every(isSelected)
);

function toggleAllSelection() {
    const visibleItems = paginatedItems.value.filter(item => item?.cdamostra);
    const visibleIds = new Set(visibleItems.map(item => item.cdamostra));
    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter(item => !visibleIds.has(item.cdamostra));
    } else {
        visibleItems.forEach(item => {
            if (!isSelected(item)) {
                selectedItems.value.push(item);
            }
        });
    }
}

// --- MANEJO CLIC EN FILA Y CAMBIO DE VISTA ---
function handleRowClick(event, item) {
    if (!item?.cdamostra) return;
    let target = event.target;
    while (target && target !== event.currentTarget) {
        if (target.tagName === 'INPUT' && target.type === 'checkbox') {
            return;
        }
        target = target.parentElement;
    }
    console.log('Row clicked, item data:', JSON.stringify(item));
    detailRecord.value = item;
    console.log('Setting detailRecord:', JSON.stringify(detailRecord.value));
    showDetailPanel.value = true;
}

function hideDetailPanel() {
    showDetailPanel.value = false;
    detailRecord.value = {};
    sampleResults.value = [];
    resultsError.value = null;
}

// --- OBTENER RESULTADOS (API) ---
async function fetchSampleResults(cdamostra) {
    if (!cdamostra) {
        console.warn("fetchSampleResults llamado sin cdamostra");
        sampleResults.value = [];
        resultsError.value = "ID de muestra inválido.";
        return;
    }
    isLoadingResults.value = true;
    resultsError.value = null;
    sampleResults.value = [];
    try {
        console.log(`Workspaceing results for cdamostra: ${cdamostra}`);
        const response = await axios.post(route('muestras.getResults'), { cdamostra });
        const data = response.data;
        console.log("Results response:", data);
        if (data.success) {
            sampleResults.value = data.data || [];
            if (sampleResults.value.length === 0) {
                resultsError.value = data.message || 'No se encontraron resultados para esta muestra.';
                if (!data.message && sampleResults.value.length === 0) resultsError.value = null;
            }
        } else {
            resultsError.value = data.message || 'Error desconocido al cargar resultados.';
            sampleResults.value = [];
        }
    } catch (error) {
        console.error("Error fetching sample results:", error);
        const errorMsg = error.response?.data?.message || error.message || 'Error de conexión.';
        resultsError.value = `Error al cargar resultados: ${errorMsg}`;
        sampleResults.value = [];
    } finally {
        isLoadingResults.value = false;
    }
}

// --- EJECUTAR ACCIONES (Informe, Cadena) ---
async function ejecutarInforme() {
    if (!selectedItems.value.length) return;
    isGenerating.value = true;
    try {
        const idsToProcess = selectedItems.value.map(i => i.cdamostra);
        const resp = await axios.post(route('muestras.extraerLaudos'),
            { selected_ids: idsToProcess },
            { timeout: 180000 }
        );
        const data = resp.data;
        if (data.success && data.data?.length > 0) {
            let downloadCount = 0;
            for (const fileData of data.data) {
                if (fileData.Laudo && fileData.NombreLaudo) {
                    await downloadBase64File(fileData.Laudo, fileData.NombreLaudo, getMimeType(fileData.NombreLaudo));
                    downloadCount++;
                }
            }
            if (downloadCount === 0) alert(data.message || 'No se encontraron archivos válidos.');
            // selectedItems.value = [];
        } else {
            alert(data.message || 'No se encontraron laudos o hubo un error.');
        }
    } catch (e) {
        console.error("Error al generar informe:", e);
        alert('Error al generar informe: ' + (e.response?.data?.message || e.message));
    } finally {
        isGenerating.value = false;
    }
}

function ejecutarCadena() {
    if (!selectedItems.value.length) return;
    alert(`Generar cadena para ${selectedItems.value.length} items (A IMPLEMENTAR).`);
}

function getMimeType(filename = '') {
    const ext = filename.split('.').pop()?.toLowerCase() || '';
    switch (ext) {
        case 'pdf': return 'application/pdf';
        case 'xlsx': return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        case 'zip': return 'application/zip';
        default: return 'application/octet-stream';
    }
}

async function downloadBase64File(base64Data, filename, mimeType) {
    try {
        const blob = await (await fetch(`data:${mimeType};base64,${base64Data}`)).blob();
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        await new Promise(resolve => setTimeout(resolve, 100));
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error('Error al descargar:', err);
        alert(`Error al descargar "${filename}": ${err.message}`);
    }
}

// --- LIMPIAR ESTADO LOCAL ---
function clearLocalState() {
    selectedItems.value = [];
    currentPage.value = 1;
}

watch(() => props.items, () => {
    if (showDetailPanel.value) {
        hideDetailPanel();
    }
    clearLocalState();
}, { deep: false });

</script>

<style scoped>
/* Estilos tabla compacta */
.compact-datatable th,
.compact-datatable td {
    padding: 0.4rem 0.6rem;
    font-size: 0.8rem;
    white-space: nowrap;
    vertical-align: middle;
}

/* Transición botones acción */
.fade-actions-enter-active,
.fade-actions-leave-active {
    transition: opacity 0.3s ease-in-out;
}

.fade-actions-enter-from,
.fade-actions-leave-to {
    opacity: 0;
}

/* Fijar cabecera tabla */
.compact-datatable thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f9fafb;
    border-bottom-width: 2px;
}

/* Scrollbar */
.scrollable-area::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.scrollable-area::-webkit-scrollbar-thumb {
    background-color: #a0aec0;
    border-radius: 4px;
}

.scrollable-area::-webkit-scrollbar-thumb:hover {
    background-color: #718096;
}

.scrollable-area::-webkit-scrollbar-track {
    background-color: #edf2f7;
}

/* Fila seleccionada */
.bg-blue-50 {
    background-color: #EBF8FF;
}

.hover\:bg-blue-100\/50:hover {
    background-color: rgba(219, 234, 254, 0.5);
}

/* Estilos Vista Detalles (No Modal) */
.detail-view-container {
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    background-color: #ffffff;
}

.detail-view-header {
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

/* Estilos info cards en detalle */
.info-card {
    background-color: #f9fafb;
    /* bg-gray-50 */
    padding: 0.75rem;
    /* p-3 */
    border-radius: 0.375rem;
    /* rounded-md */
    border: 1px solid #f3f4f6;
    /* border-gray-100 */
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    /* shadow-sm */
}

/* --- ESTILOS PARA LA TRANSICIÓN DE VISTA (AÑADIR ESTO) --- */
.fade-view-enter-active,
.fade-view-leave-active {
    transition: opacity 0.3s ease-out;
    /* Puedes ajustar la duración (0.3s) y el timing (ease-out) */
}

.fade-view-enter-from,
.fade-view-leave-to {
    opacity: 0;
    /* Hace que el elemento sea invisible al inicio/final */
}

/* Opcional: Para evitar saltos de layout si la altura cambia mucho.
   Puede que no sea necesario o requiera ajustar el contenedor padre. */
/*
.fade-view-leave-active {
  position: absolute;
  width: 100%;
}
*/

/* --- OTROS ESTILOS (ASEGÚRATE QUE ESTÉN PRESENTES) --- */

/* Estilos tabla compacta */
.compact-datatable th,
.compact-datatable td {
    padding: 0.4rem 0.6rem;
    font-size: 0.8rem;
    white-space: nowrap;
    vertical-align: middle;
}

/* Transición botones acción */
.fade-actions-enter-active,
.fade-actions-leave-active {
    transition: opacity 0.3s ease-in-out;
}

.fade-actions-enter-from,
.fade-actions-leave-to {
    opacity: 0;
}

/* Fijar cabecera tabla */
.compact-datatable thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f9fafb;
    border-bottom-width: 2px;
}

/* Scrollbar */
.scrollable-area::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.scrollable-area::-webkit-scrollbar-thumb {
    background-color: #a0aec0;
    border-radius: 4px;
}

.scrollable-area::-webkit-scrollbar-thumb:hover {
    background-color: #718096;
}

.scrollable-area::-webkit-scrollbar-track {
    background-color: #edf2f7;
}

/* Fila seleccionada */
.bg-blue-50 {
    background-color: #EBF8FF;
}

.hover\:bg-blue-100\/50:hover {
    background-color: rgba(219, 234, 254, 0.5);
}

/* Estilos Vista Detalles (No Modal) */
.detail-view-container {
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    background-color: #ffffff;
}

.detail-view-header {
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.info-card {
    background-color: #f9fafb;
    padding: 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid #f3f4f6;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
</style>

<template>
    <div>


        <div v-if="isGenerating"
            class="fixed top-4 right-4 bg-yellow-100 text-yellow-800 p-3 rounded shadow-lg z-50 border flex items-center">
            <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z">
                </path>
            </svg>
            Generando informe…
        </div>


        <Transition name="fade-view" mode="out-in">


            <div v-if="!showDetailPanel" key="tableView" class="datatable-container">

                <div class="datatable-header-responsive flex flex-wrap items-center mb-4 gap-2">

                    <div class="flex flex-grow items-center gap-2 order-1 md:order-1 w-full md:w-auto">
                        <Transition name="fade-actions">
                            <div>
                                <div v-if="selectedItems.length > 0" class="flex flex-wrap gap-2">
                                    <button type="button" @click="ejecutarInforme" :disabled="isGenerating"
                                        class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-60">
                                        <ArrowDownTrayIcon class="w-4 h-4 mr-1 inline-block" /> Informe ({{
                                            selectedItems.length }})
                                    </button>
                                    <button type="button" @click="ejecutarCadena" :disabled="isGenerating"
                                        class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-60">
                                        <LinkIcon class="w-4 h-4 mr-1 inline-block" /> Cadena ({{ selectedItems.length
                                        }})
                                    </button>
                                </div>
                                <div v-else class="h-9"></div>
                            </div>
                        </Transition>
                    </div>

                    <div class="relative flex items-center gap-2 order-2 md:order-2 ml-auto w-auto flex-shrink-0">
                        <div class="relative inline-block text-left">
                            <button id="column-toggle-button" type="button" @click="toggleColumnVisibilityDropdown"
                                title="Mostrar/Ocultar Columnas"
                                class="p-2 rounded-md border border-gray-300 bg-gray-100 hover:bg-gray-200">
                                <CogIcon class="w-5 h-5 text-gray-700" />
                            </button>
                            <Transition name="fade-actions">
                                <div v-if="showColumnToggle" id="column-toggle-dropdown"
                                    class="absolute right-0 top-full mt-1 w-56 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50 p-3 border">
                                    <p class="text-sm font-semibold mb-2 border-b pb-1">Columnas</p>
                                    <div
                                        class="flex flex-col gap-1.5 max-h-60 overflow-y-auto text-sm pr-2 scrollable-area">
                                        <label v-for="col in definedColumns" :key="'toggle-' + col.field"
                                            class="inline-flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                            <input type="checkbox" v-model="columnVisibility[col.field]"
                                                class="form-checkbox h-4 w-4 text-blue-600 rounded" />
                                            <span class="ml-2 text-gray-700 select-none">{{ col.header }}</span>
                                        </label>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto relative border border-gray-200 rounded-md scrollable-area"
                    :style="{ maxHeight: `calc(85vh - 180px - ${paginationHeight}px)` }">
                    <table class="min-w-full border-collapse compact-datatable">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 text-center border-b-2 bg-gray-100 sticky top-0 z-10 w-10">
                                    <input type="checkbox" title="Seleccionar Todos (Página)"
                                        :checked="allVisibleSelected && paginatedItems.length > 0"
                                        :indeterminate="paginatedItems.length > 0 && !allVisibleSelected && selectedItems.some(isSelected)"
                                        @change="toggleAllSelection"
                                        class="form-checkbox h-4 w-4 text-blue-600 rounded" />
                                </th>
                                <template v-for="col in definedColumns" :key="col.field">
                                    <th v-if="columnVisibility[col.field]"
                                        class="p-2 text-start border-b-2 bg-gray-100 font-bold text-sm sticky top-0 z-10 whitespace-nowrap"
                                        :style="{ 'min-width': col.field === 'Solicitante' ? '12rem' : '8rem' }">
                                        <span class="truncate">{{ col.header }}</span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="filteredItems.length === 0">
                                <td :colspan="definedColumns.filter(c => columnVisibility[c.field]).length + 1"
                                    class="text-center p-6 text-gray-500 italic">
                                    No se encontraron registros.
                                </td>
                            </tr>
                            <tr v-for="item in paginatedItems" :key="item.cdamostra"
                                class="hover:bg-blue-100/50 cursor-pointer" :class="{ 'bg-blue-50': isSelected(item) }"
                                @click="handleRowClick($event, item)">
                                <td class="p-2 text-center">
                                    <input type="checkbox" :checked="isSelected(item)"
                                        @change.stop="toggleSelection(item)" @click.stop
                                        class="form-checkbox h-4 w-4 text-blue-600 rounded" />
                                </td>
                                <template v-for="col in definedColumns" :key="col.field + '-data'">
                                    <td v-if="columnVisibility[col.field]" class="p-2 text-start text-sm text-gray-700">
                                        {{ item[col.field] ?? "-" }}
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div ref="pagination"
                    class="flex flex-col sm:flex-row justify-between items-center mt-3 p-3 bg-gray-50 border rounded-md text-sm">
                    <span>
                        Mostrando {{ filteredItems.length > 0 ? Math.min(((currentPage - 1) * Number(itemsPerPage)) + 1,
                            filteredItems.length) : 0 }}
                        a {{ Math.min(currentPage * Number(itemsPerPage), filteredItems.length) }}
                        de {{ filteredItems.length }}
                    </span>
                    <div class="flex items-center gap-1.5">
                        <select v-model="itemsPerPage" @change="currentPage = 1"
                            class="border border-gray-300 rounded px-2 h-8 text-xs focus:border-indigo-500 bg-white shadow-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option :value="0">Todos ({{ props.items.length }})</option>
                        </select>
                        <button @click="goToPage(1)" :disabled="currentPage === 1"
                            class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">&laquo;</button>
                        <button @click="prevPage" :disabled="currentPage === 1"
                            class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">&lsaquo;</button>
                        <span>Pág {{ currentPage }}/{{ totalPages }}</span>
                        <button @click="nextPage" :disabled="currentPage === totalPages || filteredItems.length === 0"
                            class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">&rsaquo;</button>
                        <button @click="goToPage(totalPages)"
                            :disabled="currentPage === totalPages || filteredItems.length === 0"
                            class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">&raquo;</button>
                    </div>
                </div>
            </div>


            <div v-else key="detailView" class="detail-view-container">

                <div class="detail-view-header flex justify-between items-center">
                    <h2 class="text-xl font-semibold flex items-center gap-2 text-gray-800">
                        <InformationCircleIcon class="w-6 h-6 text-blue-600" /> Detalle Muestra: {{
                            detailRecord['Id.Amostra'] ||
                        detailRecord.cdamostra }}
                    </h2>
                    <button type="button"
                        class="text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1 p-2 rounded hover:bg-blue-50"
                        title="Volver a la tabla" @click="hideDetailPanel">
                        <ArrowLeftIcon class="w-4 h-4" /> Volver a Tabla
                    </button>
                </div>

                <div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div class="info-card">
                            <h3
                                class="text-base font-semibold mb-2 border-b pb-1.5 flex items-center gap-1.5 text-gray-700">
                                <ListBulletIcon class="w-4 h-4" /> Detalles Principales
                            </h3>
                            <ul class="space-y-1.5 text-xs">
                                <template
                                    v-for="col in definedColumns.filter(c => columnVisibility[c.field] || ['cdamostra', 'cdunidade'].includes(c.field))"
                                    :key="'detail-' + col.field">
                                    <li v-if="detailRecord[col.field] != null && detailRecord[col.field] !== ''"
                                        class="flex justify-between items-start py-0.5">
                                        <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">{{
                                            col.header }}:</strong>
                                        <span class="text-gray-800 text-right flex-1 ml-2 break-words">{{
                                            detailRecord[col.field] }}</span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div class="info-card">
                            <h3
                                class="text-base font-semibold mb-2 border-b pb-1.5 flex items-center gap-1.5 text-gray-700">
                                <ArchiveBoxIcon class="w-4 h-4" /> Otros Datos
                            </h3>
                            <dl class="text-xs space-y-1.5">
                                <div v-if="detailRecord.moroso != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Moroso:</dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">{{ detailRecord.moroso }}</dd>
                                </div>
                                <div v-if="detailRecord.mrl != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">MRL:</dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">{{ detailRecord.mrl }}</dd>
                                </div>
                                <div v-if="detailRecord.mercados != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Mercados:</dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">{{ detailRecord.mercados }}
                                    </dd>
                                </div>
                                <div v-if="detailRecord.retailers != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Retailers:</dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">{{ detailRecord.retailers }}
                                    </dd>
                                </div>
                                <div v-if="!detailRecord.moroso && !detailRecord.mrl && !detailRecord.mercados && !detailRecord.retailers"
                                    class="text-gray-500 italic text-center pt-2">N/A</div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <h3 class="text-base font-semibold mb-2 pb-1.5 flex items-center gap-1.5 text-gray-700">
                            <TableCellsIcon class="w-4 h-4" /> Resultados del Análisis
                        </h3>
                        <div v-if="isLoadingResults"
                            class="text-center p-5 text-blue-600 flex items-center justify-center gap-2 bg-blue-50 rounded border">
                            <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z">
                                </path>
                            </svg> Cargando resultados...
                        </div>
                        <div v-else-if="resultsError" class="p-4 text-red-700 bg-red-50 border border-red-200 rounded">
                            <p>{{ resultsError }}</p>
                        </div>
                        <div v-else-if="sampleResults.length > 0"
                            class="overflow-x-auto border rounded max-h-[45vh] scrollable-area">
                            <table class="min-w-full border-collapse compact-datatable">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th v-for="col in resultsColumns" :key="'res-h-' + col.field"
                                            class="p-2 text-start border-b font-bold text-xs sticky top-0 z-10 bg-gray-100">
                                            {{
                                                col.header }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y">
                                    <tr v-for="(result, index) in sampleResults"
                                        :key="'res-d-' + index + '-' + result.NUMERO" class="hover:bg-blue-50/50">
                                        <td v-for="col in resultsColumns" :key="'res-c-' + col.field"
                                            class="p-2 text-start text-xs text-gray-700">{{ result[col.field] ?? "-" }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="!resultsError && sampleResults.length === 0"
                            class="text-center p-4 text-gray-500 bg-gray-50 border rounded">No hay resultados de
                            análisis
                            disponibles.</div>
                    </div>
                </div>
            </div>

        </Transition>

    </div>
</template>
