<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import { route } from "ziggy-js";
import * as XLSX from "xlsx"; // <-- IMPORTAR XLSX
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
    ShieldExclamationIcon,
} from "@heroicons/vue/24/outline";
//--para alert
// En la sección de imports de <script setup>
import { useToast, POSITION } from "vue-toastification"; // Añade POSITION aquí
const toast = useToast();
//------------------------------------------------------------------------------------------------------------------------
import MultiSelectDropdown from "@/Components/MultiSelectDropdown.vue"; // Asegúrate que la ruta sea correcta

// --- MODAL PARA GENERAR INFORME MRL ---
const showMrlModal = ref(false);
const isMrlLoading = ref(false); // para la carga de opciones del modal
const mrlOptions = ref({ markets: [], retailers: [] });
const mrlSelections = ref({
    markets: [],
    retailers: [],
    language: "0", // 0 para español, 1 para inglés
});

// NUEVA FUNCIÓN: Determina si la situación de una muestra permite la acción
// Normaliza a minúsculas para una comparación robusta.
const isSituacaoPermitida = (item) => {
    const situacao = item.Situacao ? String(item.Situacao).toLowerCase() : '';
    const estadosProhibidos = ['recebida', 'finalizada', 'em processo'];
    return !estadosProhibidos.includes(situacao);
};

// RENOMBRADA Y MODIFICADA: Ahora verifica si TODAS las muestras seleccionadas tienen una situación permitida
const areAllSelectedPermitidos = computed(() => {
    if (selectedItems.value.length === 0) {
        return false;
    }
    return selectedItems.value.every(item => isSituacaoPermitida(item));
});

// Determina si el botón MRL debe mostrarse.
// REEMPLAZA TU 'showMrlButton' ACTUAL CON ESTA NUEVA VERSIÓN:
const showMrlButton = computed(() => {
    // 1. Pre-condición: Debe haber EXACTAMENTE UN ítem seleccionado.
    if (selectedItems.value.length !== 1) {
        return false;
    }

    const unicoItemSeleccionado = selectedItems.value[0];

    // 2. Pre-condición: Ese ítem debe tener mrl == 1.
    if (unicoItemSeleccionado.mrl != 1) { // Usamos != en vez de !== para flexibilidad con tipos
        return false;
    }

    // 3. Pre-condición: Ese ítem debe tener una situación permitida.
    return isSituacaoPermitida(unicoItemSeleccionado);
});


// Propiedad computada para la visibilidad del botón "Informe"
const showInformeButton = computed(() => {
    // Debe haber al menos un ítem seleccionado.
    if (selectedItems.value.length === 0) {
        return false;
    }
    // Y TODOS los ítems seleccionados deben tener una situación permitida.
    return areAllSelectedPermitidos.value;
});


// NUEVA: Propiedad computada para el DIV que contiene los botones de acción.
// Esto evitará que se muestre un div vacío si no se cumplen las condiciones para ninguno de los botones.
const showActionButtonsDiv = computed(() => {
    // El div se muestra si hay algún botón de acción visible.
    return showInformeButton.value || showMrlButton.value;
});


async function openMrlModal() {
    // Esta función se llama solo si `showMrlButton` es true,
    // lo que ya garantiza que hay 1 ítem seleccionado, mrl==1 y situación permitida.
    // El warning que tenías para >1 ya no es necesario aquí,
    // ya que `showMrlButton` lo impide.
    showMrlModal.value = true;
}
// *** NUEVA FUNCIÓN PARA CARGAR OPCIONES MRL UNA VEZ ***
async function fetchMrlOptionsOnce() {
    isMrlLoading.value = true; // Indicar que estamos cargando opciones
    try {
        const response = await axios.get(route("mrl.options"));
        if (response.data.success) {
            mrlOptions.value = response.data.data;
        } else {
            // Podrías mostrar un error más persistente si la carga inicial falla
            console.error(
                "Error al cargar opciones MRL inicialmente:",
                response.data.message || "Error desconocido."
            );
            alert(
                response.data.message ||
                "Error al cargar opciones MRL iniciales."
            );
        }
    } catch (error) {
        console.error(
            "Error crítico al cargar opciones MRL inicialmente:",
            error
        );
        alert(
            "No se pudieron cargar las opciones para el informe MRL. Intente recargar la página."
        );
    } finally {
        isMrlLoading.value = false; // Termina la carga
    }
}

async function handleGenerateMrlReport() {
    // Filtra solo el cdamostra del ítem seleccionado que tiene mrl=1 y situación permitida.
    // Por la lógica de showMrlButton, aquí debería haber exactamente 1 ítem válido.
    const mrlSampleIds = selectedItems.value
        .filter((item) => item.mrl == 1 && isSituacaoPermitida(item))
        .map((item) => item.cdamostra);

    if (mrlSampleIds.length === 0) {
        toast.error("No se encontró una muestra MRL válida seleccionada para generar el informe.", {
            timeout: 3000,
            position: POSITION.TOP_CENTER,
        });
        return;
    }

    isGenerating.value = true; // Reutilizamos el loader global
    showMrlModal.value = false;

    try {
        const response = await axios.post(
            route("mrl.generateReport"),
            {
                market_ids: mrlSelections.value.markets,
                retail_ids: mrlSelections.value.retailers,
                language: mrlSelections.value.language,
                sample_ids: mrlSampleIds,
            },
            {
                responseType: "blob", // ¡Importante! Le decimos a Axios que esperamos un archivo
                timeout: 180000,
            }
        );

        // Comprobar si la respuesta es un error en formato JSON en lugar de un blob
        if (response.headers["content-type"] === "application/json") {
            const errorData = JSON.parse(await response.data.text());
            throw new Error(
                errorData.message || "El servidor devolvió un error inesperado."
            );
        }

        // Crear nombre de archivo desde el header
        const header = response.headers["content-disposition"];
        const parts = header.split(";");
        let filename = "mrl-report.zip"; // fallback
        parts.forEach((part) => {
            if (part.trim().startsWith("filename=")) {
                filename = part.split("=")[1].trim().replace(/"/g, "");
            }
        });

        // Lógica de descarga
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error("Error al generar informe MRL:", error);
        toast.error(
            "Error al generar informe MRL: " +
            (error.message || "Consulte la consola."),
            { timeout: 5000, position: POSITION.TOP_CENTER }
        );
    } finally {
        isGenerating.value = false;
        // Resetear selecciones del modal para la próxima vez
        mrlSelections.value = { markets: [], retailers: [], language: "0" };
    }
}
//------------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------
// --- PROPS ---
const props = defineProps({
    items: { type: Array, default: () => [] },
    rows: { type: Number, default: 10 },
});

// Definición de estilos para el Excel con enfoque UX/UI mejorado
const excelStyles = {
    titleSection: {
        // Para "Información de la Muestra" y "Resultados del Análisis"
        font: { name: "Calibri", sz: 14, bold: true, color: { rgb: "FFFFFF" } }, // Letra blanca
        fill: { fgColor: { rgb: "2F5496" } }, // Azul oscuro corporativo
        alignment: { horizontal: "left", vertical: "center", wrapText: true },
        border: {
            bottom: { style: "medium", color: { rgb: "1F3864" } }, // Borde inferior más oscuro
        },
    },
    sampleInfoLabel: {
        // Para etiquetas como "Id. Muestra:"
        font: { name: "Calibri", sz: 11, bold: true, color: { rgb: "404040" } }, // Gris oscuro para la etiqueta
        alignment: { horizontal: "right", vertical: "center" },
        fill: { fgColor: { rgb: "F2F2F2" } }, // Fondo gris muy claro para agrupar
    },
    sampleInfoValue: {
        // Para los valores de la información de la muestra
        font: { name: "Calibri", sz: 11, color: { rgb: "000000" } }, // Negro para el valor
        alignment: { horizontal: "left", vertical: "center" },
        fill: { fgColor: { rgb: "F2F2F2" } }, // Mismo fondo que la etiqueta
    },
    resultsTableHeader: {
        // Para las cabeceras de la tabla de resultados
        font: { name: "Calibri", sz: 12, bold: true, color: { rgb: "FFFFFF" } }, // Letra blanca
        fill: { fgColor: { rgb: "4472C4" } }, // Azul medio corporativo
        alignment: { horizontal: "center", vertical: "center", wrapText: true },
        border: {
            top: { style: "thin", color: { rgb: "B0B0B0" } },
            bottom: { style: "thin", color: { rgb: "B0B0B0" } },
            left: { style: "thin", color: { rgb: "B0B0B0" } },
            right: { style: "thin", color: { rgb: "B0B0B0" } },
        },
    },
    resultsTableCell: {
        // Para las celdas de datos (filas impares)
        font: { name: "Calibri", sz: 10, color: { rgb: "333333" } }, // Gris oscuro para el texto
        alignment: { horizontal: "left", vertical: "center" }, // Alinear a la izquierda por defecto
        border: {
            top: { style: "thin", color: { rgb: "D9D9D9" } },
            bottom: { style: "thin", color: { rgb: "D9D9D9" } },
            left: { style: "thin", color: { rgb: "D9D9D9" } },
            right: { style: "thin", color: { rgb: "D9D9D9" } },
        },
    },
    resultsTableCellAlt: {
        // Estilo alternativo para filas (efecto cebra - filas pares)
        font: { name: "Calibri", sz: 10, color: { rgb: "333333" } },
        fill: { fgColor: { rgb: "E6F0F9" } }, // Azul muy pálido para el fondo alterno
        alignment: { horizontal: "left", vertical: "center" },
        border: {
            top: { style: "thin", color: { rgb: "D9D9D9" } },
            bottom: { style: "thin", color: { rgb: "D9D9D9" } },
            left: { style: "thin", color: { rgb: "D9D9D9" } },
            right: { style: "thin", color: { rgb: "D9D9D9" } },
        },
    },
};
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

// --- FILTROS POR COLUMNA ---
const columnFilters = ref({});

// --- COLUMNAS (Tabla Principal) ---
const definedColumns = ref([
    { field: "Grupo", header: "Grupo" },
    { field: "Processo", header: "Processo" },
    { field: "Numero", header: "Numero" },
    { field: "Id. Amostra", header: "Id. Amostra" },
    { field: "Tipo Amostra", header: "Tipo Amostra" },
    { field: "cdamostra", header: "cdamostra" },
    { field: "Solicitante", header: "Solicitante" },
    { field: "Coleta", header: "Coleta" },
    { field: "Recepcao", header: "Recepcao" },
    { field: "Previsao", header: "Previsao" },
    { field: "Situacao", header: "Situacao" },
    { field: "Data_Situacao", header: "Data_Situacao" },
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
    document.addEventListener("click", closeColumnToggleOnClickOutside);
    fetchMrlOptionsOnce(); // *** LLAMAMOS AQUÍ PARA CARGAR LAS OPCIONES AL MONTAR EL COMPONENTE ***
});

onBeforeUnmount(() => {
    document.removeEventListener("click", closeColumnToggleOnClickOutside);
});

watch(
    () => definedColumns.value,
    () => initializeColumnVisibility(),
    { deep: true }
);
watch(pagination, updatePaginationHeight, { flush: "post" });

watch(
    detailRecord,
    (newDetailRecord, oldDetailRecord) => {
        if (
            showDetailPanel.value &&
            newDetailRecord?.cdamostra &&
            newDetailRecord.cdamostra !== oldDetailRecord?.cdamostra
        ) {
            fetchSampleResults(newDetailRecord.cdamostra);
        } else if (!showDetailPanel.value) {
            sampleResults.value = [];
            resultsError.value = null;
        }
    },
    { deep: true }
);

// --- NUEVO MÉTODO PARA EXPORTAR A EXCEL ---
async function exportToExcel() {
    if (
        !detailRecord.value ||
        typeof detailRecord.value !== "object" ||
        Object.keys(detailRecord.value).length === 0
    ) {
        alert("No hay datos de detalle de muestra para exportar.");
        return;
    }

    try {
        const muestraInfoData = [];
        muestraInfoData.push(["Información de la Muestra"]);

        const camposDetalleExportar = [
            { field: "Id. Amostra", label: "Id. Muestra" },
            { field: "cdamostra", label: "Código Interno (cdamostra)" },
            { field: "Grupo", label: "Grupo" },
            { field: "Processo", label: "Proceso" },
            { field: "Numero", label: "Número" },
            { field: "Tipo Amostra", label: "Tipo Muestra" },
            { field: "Solicitante", label: "Solicitante" },
            { field: "Coleta", label: "Fecha Colecta" },
            { field: "Recepcao", label: "Fecha Recepción" },
            { field: "Previsao", label: "Fecha Previsión" },
            { field: "Situacao", label: "Situación" },
            { field: "Data_Situacao", label: "Fecha Situación" },
            { field: "moroso", label: "Moroso" },
            { field: "mrl", label: "MRL" },
            { field: "mercados", label: "Mercados" },
            { field: "retailers", label: "Retailers" },
        ];

        camposDetalleExportar.forEach((item) => {
            const value = detailRecord.value[item.field];
            if (value !== null && value !== undefined && value !== "") {
                muestraInfoData.push([item.label + ":", value]);
            }
        });
        muestraInfoData.push([]); // Fila vacía como espaciador

        const resultadosTitleRow = ["Resultados del Análisis"];
        const cabecerasResultados = resultsColumns.value.map(
            (col) => col.header
        );
        const filasResultados = sampleResults.value.map((fila) =>
            resultsColumns.value.map((col) => fila[col.field] ?? "-")
        );

        const datosExcel = [
            ...muestraInfoData,
            resultadosTitleRow,
            cabecerasResultados,
            ...filasResultados,
        ];

        const ws = XLSX.utils.aoa_to_sheet(datosExcel);
        const sheetMerges = [];
        let excelRowCursor = 0;

        // --- Aplicar Estilos ---
        const numColsInfo = 2;
        const numColsResultados =
            cabecerasResultados.length > 0 ? cabecerasResultados.length : 1;
        const maxColsForTitle = Math.max(numColsInfo, numColsResultados);

        // 1. Título "Información de la Muestra"
        if (ws[XLSX.utils.encode_cell({ r: excelRowCursor, c: 0 })]) {
            ws[XLSX.utils.encode_cell({ r: excelRowCursor, c: 0 })].s =
                excelStyles.titleSection;
        }
        if (maxColsForTitle > 1) {
            sheetMerges.push({
                s: { r: excelRowCursor, c: 0 },
                e: { r: excelRowCursor, c: maxColsForTitle - 1 },
            });
        }
        excelRowCursor++;

        // 2. Pares Etiqueta-Valor de Información de Muestra
        while (
            excelRowCursor < datosExcel.length &&
            datosExcel[excelRowCursor].length > 0 &&
            datosExcel[excelRowCursor][0] !== resultadosTitleRow[0]
        ) {
            if (datosExcel[excelRowCursor].length >= 2) {
                const labelAddr = XLSX.utils.encode_cell({
                    r: excelRowCursor,
                    c: 0,
                });
                const valueAddr = XLSX.utils.encode_cell({
                    r: excelRowCursor,
                    c: 1,
                });
                if (ws[labelAddr])
                    ws[labelAddr].s = excelStyles.sampleInfoLabel;
                if (ws[valueAddr])
                    ws[valueAddr].s = excelStyles.sampleInfoValue;
            }
            excelRowCursor++;
        }
        // Saltar fila espaciadora si existe
        if (
            excelRowCursor < datosExcel.length &&
            datosExcel[excelRowCursor].length === 0
        ) {
            excelRowCursor++;
        }

        // 3. Título "Resultados del Análisis"
        if (
            excelRowCursor < datosExcel.length &&
            datosExcel[excelRowCursor][0] === resultadosTitleRow[0]
        ) {
            if (ws[XLSX.utils.encode_cell({ r: excelRowCursor, c: 0 })]) {
                ws[XLSX.utils.encode_cell({ r: excelRowCursor, c: 0 })].s =
                    excelStyles.titleSection; // Reutiliza el estilo de título
            }
            if (numColsResultados > 1) {
                sheetMerges.push({
                    s: { r: excelRowCursor, c: 0 },
                    e: { r: excelRowCursor, c: numColsResultados - 1 },
                });
            }
            excelRowCursor++;
        }

        // 4. Cabeceras de la Tabla de Resultados
        if (
            excelRowCursor < datosExcel.length &&
            datosExcel[excelRowCursor].length > 0
        ) {
            for (let c = 0; c < datosExcel[excelRowCursor].length; c++) {
                const cellAddr = XLSX.utils.encode_cell({
                    r: excelRowCursor,
                    c: c,
                });
                if (ws[cellAddr])
                    ws[cellAddr].s = excelStyles.resultsTableHeader;
            }
            excelRowCursor++;
        }

        // 5. Celdas de Datos de la Tabla de Resultados (con efecto cebra)
        let isEvenRow = false;
        for (let r = excelRowCursor; r < datosExcel.length; r++) {
            if (datosExcel[r]) {
                const currentStyle = isEvenRow
                    ? excelStyles.resultsTableCellAlt
                    : excelStyles.resultsTableCell;
                for (let c = 0; c < datosExcel[r].length; c++) {
                    const cellAddr = XLSX.utils.encode_cell({ r: r, c: c });
                    if (ws[cellAddr]) {
                        ws[cellAddr].s = currentStyle;
                        if (typeof datosExcel[r][c] === "number") {
                            ws[cellAddr].t = "n";
                        } else {
                            ws[cellAddr].t = "s";
                        }
                        if (
                            ws[cellAddr].v === null ||
                            ws[cellAddr].v === undefined
                        )
                            ws[cellAddr].v = "-";
                    }
                }
            }
            isEvenRow = !isEvenRow;
        }

        ws["!merges"] = sheetMerges;

        // Ajustar anchos de columna dinámicamente
        const anchosColumnas = [];
        if (datosExcel.length > 0) {
            let maxNumColsInSheet = 0;
            datosExcel.forEach((fila) => {
                if (fila.length > maxNumColsInSheet)
                    maxNumColsInSheet = fila.length;
            });

            for (let i = 0; i < maxNumColsInSheet; i++) {
                let maxAncho = 0;
                datosExcel.forEach((fila) => {
                    const celda = fila[i];
                    const longitudCelda = celda ? String(celda).length : 0;
                    if (longitudCelda > maxAncho) {
                        maxAncho = longitudCelda;
                    }
                });
                if (i < 2 && maxAncho < 25)
                    maxAncho = 25; // Para etiquetas y valores de info muestra
                else if (i >= 2 && maxAncho < 15) maxAncho = 15; // Para columnas de resultados
                anchosColumnas.push({
                    wch: Math.max(12, Math.min(maxAncho + 2, 60)),
                });
            }
        }
        ws["!cols"] = anchosColumnas;

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Detalle Muestra");
        const nombreArchivo = `Resultados_Muestra_${detailRecord.value["Id. Amostra"] ||
            detailRecord.value.cdamostra ||
            "export"
            }.xlsx`;
        XLSX.writeFile(wb, nombreArchivo);
    } catch (error) {
        console.error("Error al exportar a Excel con estilos:", error);
        alert(
            "Se produjo un error al generar el archivo Excel estilizado. Revise la consola."
        );
    }
}

// --- LÓGICA VISIBILIDAD COLUMNAS (Tabla Principal) ---
function initializeColumnVisibility() {
    const defaultHiddenColumns = new Set([
        "cdamostra",
        "cdunidade",
        "moroso",
        "mrl",
        "mercados",
        "retailers",
    ]);
    const vis = {};
    definedColumns.value.forEach((c) => {
        vis[c.field] = columnVisibility.value.hasOwnProperty(c.field)
            ? columnVisibility.value[c.field]
            : !defaultHiddenColumns.has(c.field);
        if (columnFilters.value[c.field] === undefined) {
            columnFilters.value[c.field] = "";
        }
    });
    columnVisibility.value = vis;
}

function toggleColumnVisibilityDropdown() {
    showColumnToggle.value = !showColumnToggle.value;
}

function closeColumnToggleOnClickOutside(event) {
    const dropdown = document.getElementById("column-toggle-dropdown");
    const button = document.getElementById("column-toggle-button");
    if (
        showColumnToggle.value &&
        dropdown &&
        button &&
        !dropdown.contains(event.target) &&
        !button.contains(event.target)
    ) {
        showColumnToggle.value = false;
    }
}

// --- PAGINACIÓN (CLIENT-SIDE - Solo para vista tabla) ---
const currentPage = ref(1);
const itemsPerPage = ref(props.rows);
const filteredItems = computed(() => {
    return props.items.filter((item) => {
        return definedColumns.value.every((c) => {
            const filtro = columnFilters.value[c.field]?.toLowerCase();
            if (!filtro) return true;
            const valor = item[c.field]
                ? String(item[c.field]).toLowerCase()
                : "";
            return valor.includes(filtro);
        });
    });
});

const totalPages = computed(() => {
    const total = filteredItems.value.length;
    const perPage = Number(itemsPerPage.value);
    const effectivePerPage =
        perPage <= 0 || (total > 0 && perPage >= total) ? total : perPage;
    if (total === 0) return 1;
    return Math.ceil(total / effectivePerPage);
});

watch(
    [filteredItems, itemsPerPage],
    () => {
        const currentTotalPages = totalPages.value;
        if (currentPage.value > currentTotalPages) {
            currentPage.value = currentTotalPages || 1;
        } else if (currentPage.value < 1 && currentTotalPages > 0) {
            currentPage.value = 1;
        } else if (currentTotalPages === 0) {
            currentPage.value = 1;
        }
    },
    { immediate: true }
);

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

function updatePaginationHeight() {
    if (pagination.value) {
        requestAnimationFrame(() => {
            const styles = getComputedStyle(pagination.value);
            const marginTop = parseInt(styles.marginTop, 10) || 0;
            const marginBottom = parseInt(styles.marginBottom, 10) || 0;
            paginationHeight.value =
                pagination.value.offsetHeight + marginTop + marginBottom;
        });
    } else {
        paginationHeight.value = 60;
    }
}

// --- SELECCIÓN DE FILAS ---
const isSelected = (item) =>
    item?.cdamostra &&
    selectedItems.value.some((si) => si.cdamostra === item.cdamostra);

function toggleSelection(item) {
    if (!item?.cdamostra) return;
    const index = selectedItems.value.findIndex(
        (si) => si.cdamostra === item.cdamostra
    );
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(item);
    }
}

const allVisibleSelected = computed(
    () =>
        paginatedItems.value.length > 0 &&
        paginatedItems.value.every(isSelected)
);

function toggleAllSelection() {
    const visibleItems = paginatedItems.value.filter((item) => item?.cdamostra);
    const visibleIds = new Set(visibleItems.map((item) => item.cdamostra));
    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter(
            (item) => !visibleIds.has(item.cdamostra)
        );
    } else {
        visibleItems.forEach((item) => {
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
        if (target.tagName === "INPUT" && target.type === "checkbox") {
            return;
        }
        target = target.parentElement;
    }
    detailRecord.value = item;
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
        const response = await axios.post(route("muestras.getResults"), {
            cdamostra,
        });
        const data = response.data;
        if (data.success) {
            sampleResults.value = data.data || [];
            if (sampleResults.value.length === 0) {
                resultsError.value =
                    data.message ||
                    "No se encontraron resultados para esta muestra.";
                if (!data.message && sampleResults.value.length === 0)
                    resultsError.value = null;
            }
        } else {
            resultsError.value =
                data.message || "Error desconocido al cargar resultados.";
            sampleResults.value = [];
        }
    } catch (error) {
        console.error("Error fetching sample results:", error);
        const errorMsg =
            error.response?.data?.message ||
            error.message ||
            "Error de conexión.";
        resultsError.value = `Error al cargar resultados: ${errorMsg}`;
        sampleResults.value = [];
    } finally {
        isLoadingResults.value = false;
    }
}

// --- EJECUTAR ACCIONES (Informe, Cadena) ---
async function ejecutarInforme() {
    // Aquí la validación se hace con `showInformeButton`, que ya considera la Situacao.
    if (!selectedItems.value.length || !showInformeButton.value) {
        toast.warning("Para generar informes, todas las muestras seleccionadas deben tener una situación que permita la acción.", {
            timeout: 5000,
            position: POSITION.TOP_CENTER,
        });
        return;
    }
    isGenerating.value = true;
    try {
        // Filtrar aquí para asegurar que solo se envíen IDs de muestras con situación permitida,
        // aunque `showInformeButton` ya debería garantizarlo.
        const idsToProcess = selectedItems.value
            .filter(item => isSituacaoPermitida(item))
            .map((i) => i.cdamostra);

        const resp = await axios.post(
            route("muestras.extraerLaudos"),
            { selected_ids: idsToProcess },
            { timeout: 180000 }
        );
        const data = resp.data;
        if (data.success && data.data?.length > 0) {
            let downloadCount = 0;
            for (const fileData of data.data) {
                if (fileData.Laudo && fileData.NombreLaudo) {
                    await downloadBase64File(
                        fileData.Laudo,
                        fileData.NombreLaudo,
                        getMimeType(fileData.NombreLaudo)
                    );
                    downloadCount++;
                }
            }
            if (downloadCount === 0)
                toast.info(data.message || "No se encontraron archivos válidos para descargar.", {
                    timeout: 3000,
                    position: POSITION.TOP_CENTER,
                });
            else
                toast.success(`Se generaron y descargaron ${downloadCount} informes.`, {
                    timeout: 3000,
                    position: POSITION.TOP_CENTER,
                });

        } else {
            toast.error(data.message || "No se encontraron laudos o hubo un error al procesar.", {
                timeout: 5000,
                position: POSITION.TOP_CENTER,
            });
        }
    } catch (e) {
        console.error("Error al generar informe:", e);
        toast.error(
            "Error al generar informe: " +
            (e.response?.data?.message || e.message),
            { timeout: 5000, position: POSITION.TOP_CENTER }
        );
    } finally {
        isGenerating.value = false;
    }
}

function ejecutarCadena() {
    // La lógica de Cadena no se ha modificado, pero podrías aplicar isSituacaoPermitida si fuera necesario
    if (!selectedItems.value.length) return;
    toast.info(
        `Generar cadena para ${selectedItems.value.length} items (A IMPLEMENTAR).`,
        { timeout: 3000, position: POSITION.TOP_CENTER }
    );
}

function getMimeType(filename = "") {
    const ext = filename.split(".").pop()?.toLowerCase() || "";
    switch (ext) {
        case "pdf":
            return "application/pdf";
        case "xlsx":
            return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
        case "zip":
            return "application/zip";
        default:
            return "application/octet-stream";
    }
}

async function downloadBase64File(base64Data, filename, mimeType) {
    try {
        const blob = await (
            await fetch(`data:${mimeType};base64,${base64Data}`)
        ).blob();
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        await new Promise((resolve) => setTimeout(resolve, 100));
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error("Error al descargar:", err);
        alert(`Error al descargar "${filename}": ${err.message}`);
    }
}

// --- LIMPIAR ESTADO LOCAL ---
function clearLocalState() {
    selectedItems.value = [];
    currentPage.value = 1;
}

watch(
    () => props.items,
    () => {
        if (showDetailPanel.value) {
            hideDetailPanel();
        }
        clearLocalState();
    },
    { deep: false }
);

function getRowClass(item) {
    // 1. Prioridad máxima: si mrl es 1 y la situación NO es permitida, la fila es una alerta roja.
    // Esto asegura que una muestra MRL con estado "Em Processo" sea roja.
    if (item.mrl == 1 && !isSituacaoPermitida(item)) {
        return "bg-red-100 hover:bg-red-200 text-red-800";
    }
    // 2. Si es una muestra MRL y la situación SÍ es permitida, también es roja (pero con el check habilitado si corresponde)
    // Esto es para mantener el visual de "MRL es importante"
    if (item.mrl == 1 && isSituacaoPermitida(item)) {
        return "bg-red-50 hover:bg-red-100 text-red-700"; // Rojo más sutil pero destacable para MRL válidos
    }


    // 3. Si no es una alerta MRL, revisa si está seleccionada.
    if (isSelected(item)) {
        return "bg-blue-50 hover:bg-blue-100/50";
    }

    // 4. Si no es alerta y no está seleccionada, es una fila normal.
    return "hover:bg-blue-100/50";
}
</script>

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
                                <div v-if="showActionButtonsDiv" class="flex flex-wrap gap-2">
                                    <button v-if="showInformeButton" type="button" @click="ejecutarInforme"
                                        :disabled="isGenerating"
                                        class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-60">
                                        <ArrowDownTrayIcon class="w-4 h-4 mr-1 inline-block" />
                                        Informe ({{ selectedItems.length }})
                                    </button>
                                    <button v-if="showMrlButton" type="button" @click="openMrlModal"
                                        :disabled="isGenerating"
                                        class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-60 flex items-center">
                                        <ShieldExclamationIcon class="w-4 h-4 mr-1 inline-block" />
                                        MRL
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
                                    <p class="text-sm font-semibold mb-2 border-b pb-1">
                                        Columnas
                                    </p>
                                    <div
                                        class="flex flex-col gap-1.5 max-h-60 overflow-y-auto text-sm pr-2 scrollable-area">
                                        <label v-for="col in definedColumns" :key="'toggle-' + col.field"
                                            class="inline-flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                            <input type="checkbox" v-model="columnVisibility[col.field]
                                                " class="form-checkbox h-4 w-4 text-blue-600 rounded" />
                                            <span class="ml-2 text-gray-700 select-none">{{ col.header }}</span>
                                        </label>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto relative border border-gray-200 rounded-md scrollable-area" :style="{
                    maxHeight: `calc(85vh - 180px - ${paginationHeight}px)`,
                }">
                    <table class="min-w-full border-collapse compact-datatable">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 text-center border-b-2 bg-gray-100 sticky top-0 z-10 w-10">
                                    <input type="checkbox" title="Seleccionar Todos (Página)" :checked="allVisibleSelected &&
                                        paginatedItems.length > 0
                                        " :indeterminate="paginatedItems.length > 0 &&
                                            !allVisibleSelected &&
                                            selectedItems.some(isSelected)
                                            " @change="toggleAllSelection"
                                        class="form-checkbox h-4 w-4 text-blue-600 rounded" />
                                </th>
                                <template v-for="col in definedColumns" :key="col.field">
                                    <th v-if="columnVisibility[col.field]"
                                        class="p-2 text-start border-b-2 bg-gray-100 font-bold text-sm sticky top-0 z-10 whitespace-nowrap"
                                        :style="{
                                            'min-width':
                                                col.field === 'Solicitante'
                                                    ? '12rem'
                                                    : '8rem',
                                        }">
                                        <span class="truncate">{{
                                            col.header
                                        }}</span>
                                    </th>
                                </template>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="p-1"></th>
                                <template v-for="col in definedColumns" :key="'filtro-' + col.field">
                                    <th v-if="columnVisibility[col.field]" class="p-1">
                                        <input type="text" v-model="columnFilters[col.field]" placeholder="Filtrar"
                                            class="w-full border rounded px-1 py-0.5 text-xs" />
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="filteredItems.length === 0">
                                <td :colspan="definedColumns.filter(
                                    (c) => columnVisibility[c.field]
                                ).length + 1
                                    " class="text-center p-6 text-gray-500 italic">
                                    No se encontraron registros.
                                </td>
                            </tr>
                            <tr v-for="item in paginatedItems" :key="item.cdamostra"
                                class="cursor-pointer transition-colors duration-150" :class="getRowClass(item)"
                                @click="handleRowClick($event, item)">
                                <td class="p-2 text-center">
                                    <input type="checkbox" :checked="isSelected(item)"
                                        @change.stop="toggleSelection(item)" @click.stop
                                        class="form-checkbox h-4 w-4 text-blue-600 rounded"
                                        :disabled="!isSituacaoPermitida(item) && !isSelected(item)" />
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
                        Mostrando
                        {{
                            filteredItems.length > 0
                                ? Math.min(
                                    (currentPage - 1) * Number(itemsPerPage) +
                                    1,
                                    filteredItems.length
                                )
                                : 0
                        }}
                        a
                        {{
                            Math.min(
                                currentPage * Number(itemsPerPage),
                                filteredItems.length
                            )
                        }}
                        de {{ filteredItems.length }}
                    </span>
                    <div class="flex items-center gap-1.5">
                        <select v-model="itemsPerPage" @change="currentPage = 1"
                            class="border border-gray-300 rounded px-2 h-8 text-xs focus:border-indigo-500 bg-white shadow-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option :value="0">
                                Todos ({{ props.items.length }})
                            </option>
                        </select>
                        <button @click="goToPage(1)" :disabled="currentPage === 1"
                            class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">
                            &laquo;
                        </button>
                        <button @click="prevPage" :disabled="currentPage === 1"
                            class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">
                            &lsaquo;
                        </button>
                        <span>Pág {{ currentPage }}/{{ totalPages }}</span>
                        <button @click="nextPage" :disabled="currentPage === totalPages ||
                            filteredItems.length === 0
                            " class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">
                            &rsaquo;
                        </button>
                        <button @click="goToPage(totalPages)" :disabled="currentPage === totalPages ||
                            filteredItems.length === 0
                            " class="px-2 h-8 rounded border hover:bg-gray-200 disabled:opacity-50 shadow-sm">
                            &raquo;
                        </button>
                    </div>
                </div>
            </div>

            <div v-else key="detailView" class="detail-view-container">
                <div class="detail-view-header flex justify-between items-center">
                    <h2 class="text-xl font-semibold flex items-center gap-2 text-gray-800">
                        <InformationCircleIcon class="w-6 h-6 text-blue-600" />
                        Detalle Muestra:
                        {{
                            detailRecord["Id.Amostra"] || detailRecord.cdamostra
                        }}
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
                                <ListBulletIcon class="w-4 h-4" /> Detalles
                                Principales
                            </h3>
                            <ul class="space-y-1.5 text-xs">
                                <template v-for="col in definedColumns.filter(
                                    (c) =>
                                        columnVisibility[c.field] ||
                                        ['cdamostra', 'cdunidade'].includes(
                                            c.field
                                        )
                                )" :key="'detail-' + col.field">
                                    <li v-if="
                                        detailRecord[col.field] != null &&
                                        detailRecord[col.field] !== ''
                                    " class="flex justify-between items-start py-0.5">
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
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">
                                        Moroso:
                                    </dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">
                                        {{ detailRecord.moroso }}
                                    </dd>
                                </div>
                                <div v-if="detailRecord.mrl != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">
                                        MRL:
                                    </dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">
                                        {{ detailRecord.mrl }}
                                    </dd>
                                </div>
                                <div v-if="detailRecord.mercados != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">
                                        Mercados:
                                    </dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">
                                        {{ detailRecord.mercados }}
                                    </dd>
                                </div>
                                <div v-if="detailRecord.retailers != null" class="flex justify-between items-start">
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">
                                        Retailers:
                                    </dt>
                                    <dd class="text-gray-800 ml-2 text-right break-words">
                                        {{ detailRecord.retailers }}
                                    </dd>
                                </div>
                                <div v-if="
                                    !detailRecord.moroso &&
                                    !detailRecord.mrl &&
                                    !detailRecord.mercados &&
                                    !detailRecord.retailers
                                " class="text-gray-500 italic text-center pt-2">
                                    N/A
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <h3 class="text-base font-semibold mb-2 pb-1.5 flex items-center gap-1.5 text-gray-700">
                            <TableCellsIcon class="w-4 h-4" /> Resultados del
                            Análisis
                        </h3>

                        <button v-if="sampleResults.length > 0 && !isLoadingResults" type="button"
                            @click="exportToExcel" title="Exportar detalle y resultados (Data preliminar de resultados)"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center"
                            :disabled="isGenerating">
                            <ArrowDownTrayIcon class="w-4 h-4 mr-1.5" />
                            Exportar Excel
                        </button>

                        <div v-if="isLoadingResults"
                            class="text-center p-5 text-blue-600 flex items-center justify-center gap-2 bg-blue-50 rounded border">
                            <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l2-2.647z">
                                </path>
                            </svg>
                            Cargando resultados...
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
                                            {{ col.header }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y">
                                    <tr v-for="(result, index) in sampleResults" :key="'res-d-' +
                                        index +
                                        '-' +
                                        result.NUMERO
                                        " class="hover:bg-blue-50/50">
                                        <td v-for="col in resultsColumns" :key="'res-c-' + col.field"
                                            class="p-2 text-start text-xs text-gray-700">
                                            {{ result[col.field] ?? "-" }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="
                            !resultsError && sampleResults.length === 0
                        " class="text-center p-4 text-gray-500 bg-gray-50 border rounded">
                            No hay resultados de análisis disponibles.
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
    <Transition name="fade-view">
        <div v-if="showMrlModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-60 z-40 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg relative" @click.stop>
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Informe MRL
                    </h3>
                    <button @click="showMrlModal = false" class="p-1 rounded-full hover:bg-gray-200">
                        <XMarkIcon class="w-5 h-5 text-gray-600" />
                    </button>
                </div>

                <div class="p-6">
                    <div v-if="isMrlLoading" class="text-center">
                        <p>Cargando opciones...</p>
                    </div>
                    <div v-else class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mercados</label>
                            <MultiSelectDropdown v-model="mrlSelections.markets" :options="mrlOptions.markets"
                                placeholder="Seleccionar mercados..." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Retailers</label>
                            <MultiSelectDropdown v-model="mrlSelections.retailers" :options="mrlOptions.retailers"
                                placeholder="Seleccionar retailers..." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Idioma</label>
                            <div class="flex items-center space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" v-model="mrlSelections.language" value="0"
                                        class="form-radio text-indigo-600" />
                                    <span class="ml-2">Español</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" v-model="mrlSelections.language" value="1"
                                        class="form-radio text-indigo-600" />
                                    <span class="ml-2">Inglés</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end items-center p-4 border-t bg-gray-50 rounded-b-lg">
                    <button @click="showMrlModal = false" type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button @click="handleGenerateMrlReport" type="button"
                        class="ml-3 px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700">
                        Generar
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
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
    background-color: #ebf8ff;
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
    padding: 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid #f3f4f6;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

/* Transición de Vista */
.fade-view-enter-active,
.fade-view-leave-active {
    transition: opacity 0.3s ease-out;
}

.fade-view-enter-from,
.fade-view-leave-to {
    opacity: 0;
}
</style>
