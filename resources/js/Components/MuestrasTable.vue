<template>
    <div class="relative">
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
                            <button type="button" @click="ejecutarInforme" title="Descargar Informe"
                                class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-150 ease-in-out">

                                <ArrowDownTrayIcon class="w-4 h-4 mr-1 inline-block" /> Informe ({{ selectedItems.length
                                }})

                            </button>
                            <button type="button" @click="ejecutarCadena" title="Descargar Cadena"
                                class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-150 ease-in-out">

                                <LinkIcon class="w-4 h-4 mr-1 inline-block" /> Cadena ({{ selectedItems.length }})

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

            <div class="overflow-x-auto" :style="{
                maxHeight: `calc(90vh - 220px - ${paginationHeight}px)`,
            }">
                <table class="min-w-full border-collapse compact-datatable">
                    <thead>
                        <tr>
                            <th class="p-2 text-center border-b border-gray-300 bg-gray-100 font-bold text-sm">
                                <input type="checkbox" :checked="allVisibleSelected &&
                                    paginatedItems.length > 0
                                    " :indeterminate.prop="paginatedItems.length > 0 &&
                                        !allVisibleSelected &&
                                        selectedItems.length > 0
                                        " @change="toggleAllSelection"
                                    class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out" />
                            </th>

                            <template v-for="col in definedColumns" :key="col.field">
                                <th v-if="columnVisibility[col.field]"
                                    class="p-2 text-start border-b border-gray-300 bg-gray-100 font-bold text-sm"
                                    style="min-width: 8rem">
                                    <div class="flex flex-col">
                                        <span class="truncate">{{
                                            col.header
                                            }}</span>
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
                    {{
                        Math.min(
                            currentPage * itemsPerPage,
                            filteredItems.length
                        )
                    }}
                    de {{ filteredItems.length }} registros ({{
                        items.length
                    }}
                    total sin filtro)
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
                                    <strong class="text-gray-700">{{ col.header
                                        }}:</strong>
                                    <span class="text-gray-600 text-right flex-1 ml-4">{{
                                        detailRecord[col.field] ?? "-"
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
                            Aquí puedes agregar contenido adicional específico
                            de esta muestra, como resultados de análisis, notas,
                            imágenes, etc.
                            <br /><br />
                            Este es solo un placeholder. Implementa la lógica
                            para cargar datos adicionales aquí si es necesario.
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";

// ---> IMPORTAR HEROICONS AQUI <---
import {
    CogIcon, // para la ruedita
    FunnelIcon, // para limpiar filtro (Heroicons no tiene filter-slash)
    ArrowDownTrayIcon, // para descargar
    LinkIcon, // para cadena/link
    MagnifyingGlassIcon, // para buscar
    TableCellsIcon, // para el icono de tabla en el selector de columnas
    InformationCircleIcon, // para el icono de info en el detalle
    ListBulletIcon, // para el icono de lista en detalles
    ArchiveBoxIcon, // para el icono de caja en otros datos
    ArrowLeftIcon, // para la flecha de volver
} from '@heroicons/vue/24/outline'; // Asegúrate que la ruta sea correcta según tu instalación

const props = defineProps({
    items: { type: Array, default: () => [] }, // Tus datos completos
    rows: { type: Number, default: 10 }, // Número inicial de filas por página
});

// --- Estado de la Tabla ---
const selectedItems = ref([]); // Array de elementos seleccionados
const showDetailPanel = ref(false);
const detailRecord = ref({});

// --- Definición de Columnas ---
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
    { field: "cdamostra", header: "cdamostra" }, // campo unico para key
]);

// --- Estado y Lógica de Control de Columnas Visibles ---
const showColumnToggle = ref(false); // Controla la visibilidad del dropdown de columnas
const columnVisibility = ref({}); // Objeto para guardar el estado de visibilidad de cada columna

onMounted(() => {
    initializeColumnVisibility();
});

watch(
    () => props.items,
    () => {
        initializeColumnVisibility();
    },
    { immediate: true }
); // Inicializar también si los items cambian después del montaje

function initializeColumnVisibility() {
    const initialVisibility = {};
    definedColumns.value.forEach((col) => {
        initialVisibility[col.field] =
            columnVisibility.value[col.field] !== undefined
                ? columnVisibility.value[col.field]
                : true;
    });
    columnVisibility.value = initialVisibility;
}

// Función para alternar la visibilidad del dropdown de columnas
function toggleColumnVisibilityDropdown() {
    showColumnToggle.value = !showColumnToggle.value;
}

const globalFilterValue = ref("");
const columnFilters = ref({});
onMounted(() => {
    initializeColumnFilters();
});
watch(
    () => props.items,
    () => {
        initializeColumnFilters();
    },
    { immediate: true }
);

function initializeColumnFilters() {
    const initialFilters = {};
    definedColumns.value.forEach((col) => {
        initialFilters[col.field] =
            columnFilters.value[col.field] !== undefined
                ? columnFilters.value[col.field]
                : "";
    });
    columnFilters.value = initialFilters;
}

const filteredItems = computed(() => {
    let items = props.items;

    // 1. Aplicar filtro Global
    const globalFilter = globalFilterValue.value
        ? globalFilterValue.value.toLowerCase().trim()
        : "";
    if (globalFilter) {
        items = items.filter((item) =>
            definedColumns.value.some((col) => {
                const cellValue = String(item[col.field] ?? "").toLowerCase();
                return cellValue.includes(globalFilter);
            })
        );
    }

    // 2. Aplicar filtros por Columna
    definedColumns.value.forEach((col) => {
        const colFilter = columnFilters.value[col.field]
            ? columnFilters.value[col.field].toLowerCase().trim()
            : "";
        if (colFilter) {
            items = items.filter((item) => {
                const cellValue = String(item[col.field] ?? "").toLowerCase();
                return cellValue.includes(colFilter); // Filtro simple "contains"
            });
        }
    });

    return items;
});

const currentPage = ref(1);
const itemsPerPage = ref(props.rows);

// Computed para calcular el número total de páginas
const totalPages = computed(() => {
    if (!filteredItems.value || filteredItems.value.length === 0) {
        return 1; // Si no hay items filtrados, hay 1 página (vacía)
    }
    return Math.ceil(filteredItems.value.length / itemsPerPage.value);
});

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;

    return filteredItems.value.slice(start, end);
});

watch(
    totalPages,
    (newVal, oldVal) => {
        if (currentPage.value > newVal || (newVal === 0 && oldVal > 0)) {
            currentPage.value = newVal > 0 ? newVal : 1;
        }
    },
    { immediate: true }
);

watch(
    [globalFilterValue, columnFilters],
    () => {
        currentPage.value = 1;
    },
    { deep: true }
);

// --- Lógica de Selección ---
const isSelected = (item) => {
    return selectedItems.value.some(
        (selectedItem) => selectedItem.cdamostra === item.cdamostra
    );
};

const toggleSelection = (item) => {
    const index = selectedItems.value.findIndex(
        (selectedItem) => selectedItem.cdamostra === item.cdamostra
    );
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(item);
    }
    // console.log('Selección actualizada:', selectedItems.value.map(item => item.cdamostra)); // Log para depuración
};

const allVisibleSelected = computed(() => {
    if (!paginatedItems.value || paginatedItems.value.length === 0) {
        return false;
    }
    return paginatedItems.value.every((item) => isSelected(item));
});

const toggleAllSelection = () => {
    const visibleItems = paginatedItems.value;

    if (allVisibleSelected.value) {
        visibleItems.forEach((item) => {
            const index = selectedItems.value.findIndex(
                (selectedItem) => selectedItem.cdamostra === item.cdamostra
            );
            if (index > -1) {
                selectedItems.value.splice(index, 1);
            }
        });
    } else {
        visibleItems.forEach((item) => {
            if (!isSelected(item)) {
                selectedItems.value.push(item);
            }
        });
    }
};

function handleRowClick(event, item) {
    // console.log('handleRowClick ejecutada', event, item); // Log para depuración
    let target = event.target;
    while (target && target !== event.currentTarget) {
        if (target.tagName === "INPUT" && target.type === "checkbox") {
            console.log("Clickeado en checkbox, no se abre el detalle.");
            return; // Salir si el clic fue en el checkbox
        }
        target = target.parentElement;
    }

    // Si llegamos aquí, el clic no fue en un checkbox, abrimos el detalle
    detailRecord.value = item;
    showDetailPanel.value = true;
    console.log(
        "Fila clickeada (fuera de checkbox), showDetailPanel = true. Item:",
        item
    ); // Log para depuración
}

function hideDetailPanel() {
    showDetailPanel.value = false; // Oculta el detalle
    detailRecord.value = {}; // Limpia el registro de detalle
    console.log("hideDetailPanel ejecutada, showDetailPanel = false."); // Log para depuración
}

// --- Lógica de Botones de Acción ---
function ejecutarInforme() {
    if (selectedItems.value.length === 0) return;
    console.log("Informe para:", selectedItems.value);
    alert(
        "Informe para " + selectedItems.value.length + " filas seleccionadas."
    );
}
function ejecutarCadena() {
    if (selectedItems.value.length === 0) return;
    console.log("Cadena para:", selectedItems.value);
    alert(
        "Cadena para " + selectedItems.value.length + " filas seleccionadas."
    );
}

// --- Lógica de Paginación (Métodos) ---
function nextPage() {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
}

function prevPage() {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
}

function goToPage(page) {
    const pageNumber = Number(page);
    if (pageNumber >= 1 && pageNumber <= totalPages.value) {
        currentPage.value = pageNumber;
    }
}

const pagination = ref(null);
const paginationHeight = ref(0);

watch(
    pagination,
    (el) => {
        if (el) {
            requestAnimationFrame(() => {
                const style = getComputedStyle(el);
                const marginBottom = parseInt(style.marginBottom, 10) || 0;
                paginationHeight.value = el.offsetHeight + marginBottom;
                // console.log('Altura del paginador medida:', paginationHeight.value);
            });
        } else {
            paginationHeight.value = 0;
        }
    },
    { flush: "post" }
);

// --- Lógica para Limpiar Filtros ---
function clearFilters() {
    console.log("clearFilters ejecutada"); // Log para depuración

    // Limpiar filtro global
    globalFilterValue.value = "";
    console.log(
        "globalFilterValue después de limpiar:",
        globalFilterValue.value
    ); // Log

    const clearedColumnFilters = {};
    definedColumns.value.forEach((col) => {
        clearedColumnFilters[col.field] = "";
    });
    columnFilters.value = clearedColumnFilters;
    console.log("columnFilters después de limpiar:", {
        ...columnFilters.value,
    }); // Log

    // Limpiar selección
    selectedItems.value = [];
    console.log("selectedItems después de limpiar:", selectedItems.value); // Log

    // Asegurar que la página vuelve a la primera
    currentPage.value = 1;
    console.log("currentPage después de limpiar:", currentPage.value); // Log

    console.log(
        "Filtros y selección limpiados. Verificando si filteredItems y paginatedItems se recalculan..."
    ); // Log
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

.slide-from-top-left-enter-to,
.slide-from-top-left-leave-from {
    opacity: 1;
    transform: translate(0, 0);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.app-main-container {
    /* height/overflow si es necesario */
}

.card {
    background-color: #fff;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 0.75rem !important;
}

/* Header responsive (se mantiene y se ajusta el layout con flex/order) */
.datatable-header-responsive {
    /* Tailwind flex flex-wrap items-center mb-4 gap-2 */
}

.responsive-button-group-always-visible {
    /* Tailwind flex gap-2 */
}

.responsive-button-group-actions {
    /* Tailwind flex flex-wrap gap-2 */
}

.global-filter-responsive {
    /* Tailwind relative flex-grow (en responsive) */
}

@media (max-width: 960px) {
    .datatable-header-responsive {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .responsive-button-group-always-visible,
    .responsive-button-group-actions {
        width: 100%;
        justify-content: center;
    }

    .datatable-header-responsive>div:not(:last-child) {
        margin-bottom: 0.5rem;
    }

    .datatable-header-responsive>div.ml-auto {
        margin-left: 0;
        width: 100%;
        justify-content: space-between;
    }

    .datatable-header-responsive>div.ml-auto .relative {
        flex-grow: 1;
    }
}

/* Panel de Detalle (se mantiene) */
.detail-view-container {
    /* estilos */
}

.detail-header {
    border-bottom: 1px solid var(--surface-border, #dee2e6);
}

.detail-content {
    /* grid y gap definidos con Tailwind */
}

.info-card {
    background-color: var(--surface-ground, #f8f9fa);
    border: 1px solid var(--surface-border, #dee2e6);
    border-radius: 0.375rem;
    padding: 1rem;
}

.info-card h3 {
    border-bottom: 1px solid var(--surface-border, #dee2e6);
    padding-bottom: 0.5rem;
    margin-bottom: 0.75rem;
    color: var(--text-color, #495057);
    font-size: 1.1rem;
    font-weight: 600;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    padding: 0.3rem 0;
    border-bottom: 1px dashed var(--surface-d, #ced4da);
    align-items: flex-start;
}

.info-list li:last-child {
    border-bottom: none;
}

.info-list li strong {
    color: var(--text-color-secondary, #6c757d);
    flex-shrink: 0;
    margin-right: 1rem;
}

.info-list li span {
    word-break: break-word;
    color: var(--text-color, #495057);
}
</style>

<style>
/* Tus estilos globales para la tabla nativa con clases .compact-datatable (se mantienen) */
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
    text-align: start;
    vertical-align: middle;
}

.compact-datatable th {
    background-color: #f2f2f2;
    font-weight: bold;
    color: #000000;
    border-bottom: 1px solid #dee2e6;
}

.compact-datatable td {
    border-bottom: 1px solid #dee2e6;
}

.compact-datatable tbody tr:last-child td {
    border-bottom: none;
}

.compact-datatable th:first-child,
.compact-datatable td:first-child {
    text-align: center;
    vertical-align: middle;
    width: 3rem;
}

.compact-datatable tbody tr {
    cursor: pointer;
    transition: background-color 0.2s;
}

.compact-datatable tbody tr:hover {
    background-color: #acd4f2;
    color: #00345d;
}

.compact-datatable tbody tr.bg-gray-100 {
    border-left: 3px solid #007bff;
}



.compact-datatable th input[type="text"] {
    font-size: 0.6rem;
    padding: 0.1rem 0.3rem;
    margin-top: 0.2rem;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    width: calc(100% - 0.6rem);
}

.compact-datatable td input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
    margin: 0;
}

</style>