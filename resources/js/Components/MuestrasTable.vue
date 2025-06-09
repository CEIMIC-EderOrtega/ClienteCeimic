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
    // Agrega estos si no los tienes aún, son útiles para los detalles:
    BuildingOfficeIcon, // Para Cliente, Dirección
    IdentificationIcon, // Para Número Identificador
    UserIcon, // Para Muestreado por, Muestreador (persona)
    DocumentTextIcon, // Para Descripción de la muestra, Información Adicional
    CalendarDaysIcon, // Para fechas
    ClockIcon, // Para Fechas con hora
    TagIcon, // Para Código Muestra Cliente, Variedad
    MapPinIcon, // Para Lugar de Muestreo, Predio
    BuildingStorefrontIcon, // Para Productor
    HashtagIcon, // Para Código de Productor, N° Registro Agrícola
    BanknotesIcon, // Para Moroso
    BeakerIcon, // Para MRL (si lo consideras apropiado)
    GlobeAltIcon, // Para Mercados
    ShoppingCartIcon, // Para Retailers
    // Puedes elegir otros si prefieres:
    // UserGroupIcon, // Para Cliente
    // ClipboardDocumentListIcon, // Para Registro Agrícola
    // HomeModernIcon // Para Predio
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


const showMrlButton = computed(() => {
    // 1. Pre-condición: Debe haber EXACTAMENTE UN ítem seleccionado.
    if (selectedItems.value.length !== 1) {
        return false;
    }

    const unicoItemSeleccionado = selectedItems.value[0];

    // 2. Pre-condición: Ese ítem debe tener mrl == 1.
    if (unicoItemSeleccionado.mrl != 1) {
        return false;
    }

    // 3. Pre-condición: Ese ítem debe tener una situación permitida.
    const isSituacaoOk = isSituacaoPermitida(unicoItemSeleccionado);

    // --- AÑADE ESTO: EL BOTÓN SÓLO SE MUESTRA SI mrlReportEnabled ES TRUE ---
    return props.mrlReportEnabled && isSituacaoOk;
    // --- FIN AÑADIDO ---
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
    mrlReportEnabled: { // <-- NUEVA PROP: Habilitar/Deshabilitar botón MRL desde backend
        type: Boolean,
        default: true // Por defecto visible si no se especifica
    }
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
const extendedDetailRecord = ref({});
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
    //{ field: "moroso", header: "Moroso" },
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
    async (newDetailRecord, oldDetailRecord) => { // <-- Añadimos 'async' aquí
        if (
            showDetailPanel.value &&
            newDetailRecord?.cdamostra &&
            newDetailRecord.cdamostra !== oldDetailRecord?.cdamostra
        ) {
            // Cargar los resultados de análisis (lo que ya funcionaba)
            await fetchSampleResults(newDetailRecord.cdamostra);

            // Cargar los detalles extendidos para los paneles (NUEVA LLAMADA)
            await fetchExtendedDetails(newDetailRecord.cdamostra); // <-- NUEVA LLAMADA
        } else if (!showDetailPanel.value) {
            // Cuando el panel se oculta, limpiar todo
            sampleResults.value = [];
            resultsError.value = null;
            extendedDetailRecord.value = {}; // <-- Asegurarse de limpiar también aquí
        }
    },
    { deep: true }
);

// --- NUEVO MÉTODO PARA EXPORTAR A EXCEL ---
async function exportToExcel() {
    if (
        (!detailRecord.value || Object.keys(detailRecord.value).length === 0) &&
        (!extendedDetailRecord.value || Object.keys(extendedDetailRecord.value).length === 0)
    ) {
        alert("No hay datos de detalle de muestra para exportar.");
        return;
    }

    try {
        let datosExcel = [];
        let currentRow = 0; // Para llevar el control de la fila actual en datosExcel

        // --- Definición de campos para los paneles Izquierdo y Derecho ---
        const camposPanelIzquierdo = [
            { field: "solicitante", label: "Cliente" },
            { field: "numero_identificador", label: "Número Identificador" },
            { field: "direccion", label: "Dirección" },
            { field: "muestreado_por", label: "Muestreado por" },
            { field: "descripcion_muestra", label: "Descripción de la muestra" },
            { field: "fecha_recepcion", label: "Fecha de recepción" },
            { field: "fecha_inicio_analisis", label: "Fecha de Inicio Análisis" },
            { field: "fecha_termino_analisis", label: "Fecha de Término Análisis" },
            { field: "datalaudo", label: "Fecha de Emisión" },
        ];

        const camposPanelDerecho = [
            { field: "codigo_muestra_cliente", label: "Código Muestra Cliente" },
            { field: "variedad", label: "Variedad" },
            { field: "fecha_muestreo", label: "Fecha de muestreo" },
            { field: "muestreador_persona", label: "Muestreador" },
            { field: "lugar_muestreo_detail", label: "Lugar de Muestreo" },
            { field: "nombre_productor", label: "Nombre Productor" },
            { field: "codigo_productor", label: "Código de Productor" },
            { field: "predio", label: "Predio" },
            { field: "n_registro_agricola", label: "N° Registro Agricola" },
            { field: "informacion_adicional", label: "Información Adicional" },
            { field: "moroso", label: "Moroso", source: detailRecord.value }, // Estos vienen del detailRecord original
            { field: "mrl", label: "MRL", source: detailRecord.value },
            { field: "mercados", label: "Mercados", source: detailRecord.value },
            { field: "retailers", label: "Retailers", source: detailRecord.value },
        ];

        // Determinar el número máximo de filas en los paneles para la disposición lado a lado
        const maxPanelRows = Math.max(camposPanelIzquierdo.length, camposPanelDerecho.length);

        // --- 1. Título General de Detalles ---
        datosExcel.push(["Detalle de Muestra"]); // Título principal para la sección de paneles
        const mainTitleRowIndex = currentRow;
        currentRow++;

        datosExcel.push([]); // Fila vacía para espacio
        currentRow++;

        // --- 2. Contenido de los Paneles Izquierdo y Derecho Lado a Lado ---
        // Definir el número de columnas para esta sección (Etiqueta Izq, Valor Izq, Etiqueta Der, Valor Der)
        const panelSectionColumnCount = 4; // Col A, B, C, D
        const panelSectionStartRow = currentRow; // Guarda la fila de inicio para la aplicación de estilos

        for (let i = 0; i < maxPanelRows; i++) {
            const row = [];
            // Columna 0 (Etiqueta Izquierda)
            const leftLabel = camposPanelIzquierdo[i]?.label ? camposPanelIzquierdo[i].label + ":" : "";
            row.push(leftLabel);

            // Columna 1 (Valor Izquierda)
            let leftValue = "";
            if (camposPanelIzquierdo[i]) {
                leftValue = extendedDetailRecord.value[camposPanelIzquierdo[i].field] ?? detailRecord.value[camposPanelIzquierdo[i].field] ?? 'S/INF';
                if (leftValue === "" || leftValue === "N/A") leftValue = "S/INF";
            }
            row.push(leftValue);

            // Columna 2 (Etiqueta Derecha)
            const rightLabel = camposPanelDerecho[i]?.label ? camposPanelDerecho[i].label + ":" : "";
            row.push(rightLabel);

            // Columna 3 (Valor Derecha)
            let rightValue = "";
            if (camposPanelDerecho[i]) {
                const source = camposPanelDerecho[i].source || extendedDetailRecord.value; // Usa source si está definido
                rightValue = source[camposPanelDerecho[i].field] ?? detailRecord.value[camposPanelDerecho[i].field] ?? 'S/INF';
                if (rightValue === "" || rightValue === "N/A") rightValue = "S/INF";
            }
            row.push(rightValue);
            datosExcel.push(row);
            currentRow++;
        }

        datosExcel.push([]); // Fila vacía como espaciador
        currentRow++;

        // --- 3. Título "Resultados del Análisis" ---
        datosExcel.push(["Resultados del Análisis"]);
        const resultadosTitleRowIndex = currentRow;
        currentRow++;

        // --- 4. Cabeceras y Filas de Resultados del Análisis ---
        if (sampleResults.value.length > 0) {
            const cabecerasResultados = resultsColumns.value.map((col) => col.header);
            datosExcel.push(cabecerasResultados);
            const resultadosTableHeaderRowIndex = currentRow; // Guarda la fila de cabeceras de resultados
            currentRow++;

            sampleResults.value.forEach((fila) => {
                const filaData = resultsColumns.value.map((col) => fila[col.field] ?? "S/INF");
                datosExcel.push(filaData);
                currentRow++;
            });
        } else {
            datosExcel.push(["No se encontraron resultados de análisis."]);
            currentRow++;
        }

        const ws = XLSX.utils.aoa_to_sheet(datosExcel);
        const sheetMerges = [];

        // --- Aplicar Estilos y Merges ---

        // Estilos para la sección de paneles
        const numColsDetails = panelSectionColumnCount; // 4 columnas para los paneles (A:D)

        // Título "Detalle de Muestra"
        if (ws[XLSX.utils.encode_cell({ r: mainTitleRowIndex, c: 0 })]) {
            ws[XLSX.utils.encode_cell({ r: mainTitleRowIndex, c: 0 })].s = excelStyles.titleSection;
            sheetMerges.push({ s: { r: mainTitleRowIndex, c: 0 }, e: { r: mainTitleRowIndex, c: numColsDetails - 1 } });
        }

        // Estilos para los pares Etiqueta-Valor de los paneles (lados izquierdo y derecho)
        for (let r = panelSectionStartRow; r < panelSectionStartRow + maxPanelRows; r++) {
            // Columna de etiquetas izquierdas (A)
            const labelLeftAddr = XLSX.utils.encode_cell({ r: r, c: 0 });
            if (ws[labelLeftAddr]) ws[labelLeftAddr].s = excelStyles.sampleInfoLabel;

            // Columna de valores izquierdos (B)
            const valueLeftAddr = XLSX.utils.encode_cell({ r: r, c: 1 });
            if (ws[valueLeftAddr]) ws[valueLeftAddr].s = excelStyles.sampleInfoValue;

            // Columna de etiquetas derechas (C)
            const labelRightAddr = XLSX.utils.encode_cell({ r: r, c: 2 });
            if (ws[labelRightAddr]) ws[labelRightAddr].s = excelStyles.sampleInfoLabel;

            // Columna de valores derechos (D)
            const valueRightAddr = XLSX.utils.encode_cell({ r: r, c: 3 });
            if (ws[valueRightAddr]) ws[valueRightAddr].s = excelStyles.sampleInfoValue;
        }

        // Título "Resultados del Análisis"
        const numColsResultados = resultsColumns.value.length > 0 ? resultsColumns.value.length : 1;
        if (ws[XLSX.utils.encode_cell({ r: resultadosTitleRowIndex, c: 0 })]) {
            ws[XLSX.utils.encode_cell({ r: resultadosTitleRowIndex, c: 0 })].s = excelStyles.titleSection;
            // El merge para el título de resultados debe ser al número de columnas de resultados, no de paneles
            sheetMerges.push({ s: { r: resultadosTitleRowIndex, c: 0 }, e: { r: resultadosTitleRowIndex, c: numColsResultados - 1 } });
        }

        // Cabeceras de la Tabla de Resultados
        if (sampleResults.value.length > 0) {
            const currentHeaderRow = resultadosTitleRowIndex + 1;
            for (let c = 0; c < resultsColumns.value.length; c++) {
                const cellAddr = XLSX.utils.encode_cell({ r: currentHeaderRow, c: c });
                if (ws[cellAddr]) ws[cellAddr].s = excelStyles.resultsTableHeader;
            }
        }

        // Celdas de Datos de la Tabla de Resultados (con efecto cebra)
        if (sampleResults.value.length > 0) {
            let isEvenRow = false;
            let currentDataRow = resultadosTitleRowIndex + 2; // Inicia después del título y las cabeceras
            for (let r = 0; r < sampleResults.value.length; r++) {
                const rowExcelIndex = currentDataRow + r;
                const currentStyle = isEvenRow ? excelStyles.resultsTableCellAlt : excelStyles.resultsTableCell;
                for (let c = 0; c < resultsColumns.value.length; c++) {
                    const cellAddr = XLSX.utils.encode_cell({ r: rowExcelIndex, c: c });
                    if (ws[cellAddr]) {
                        ws[cellAddr].s = currentStyle;
                        // Ya se maneja S/INF al construir los datos, solo asegurar el tipo
                        ws[cellAddr].t = (typeof sampleResults.value[r][resultsColumns.value[c].field] === "number") ? "n" : "s";
                    }
                }
                isEvenRow = !isEvenRow;
            }
        } else {
            // Si no hay resultados, aplicar estilo al mensaje "No se encontraron resultados..."
            const messageRowIndex = resultadosTitleRowIndex + 1; // Fila justo debajo del título de resultados
            const cellAddr = XLSX.utils.encode_cell({ r: messageRowIndex, c: 0 });
            if (ws[cellAddr]) {
                ws[cellAddr].s = excelStyles.resultsTableCell; // Estilo básico para el mensaje
                sheetMerges.push({ s: { r: messageRowIndex, c: 0 }, e: { r: messageRowIndex, c: numColsResultados - 1 } });
            }
        }

        ws["!merges"] = sheetMerges;

        // Ajustar anchos de columna dinámicamente
        const anchosColumnas = [];
        // Primero para las columnas de detalles (A, B, C, D)
        for (let i = 0; i < panelSectionColumnCount; i++) {
            let maxAncho = 0;
            for (let r = panelSectionStartRow; r < panelSectionStartRow + maxPanelRows; r++) {
                const cellValue = datosExcel[r] && datosExcel[r][i] !== undefined ? String(datosExcel[r][i]) : "";
                if (cellValue.length > maxAncho) {
                    maxAncho = cellValue.length;
                }
            }
            if (i % 2 === 0) { // Columnas de etiquetas (A, C)
                anchosColumnas.push({ wch: Math.max(20, Math.min(maxAncho + 2, 40)) });
            } else { // Columnas de valores (B, D)
                anchosColumnas.push({ wch: Math.max(30, Math.min(maxAncho + 2, 80)) });
            }
        }

        // Luego para las columnas de resultados (si las hay, empezando después de las de detalle)
        if (sampleResults.value.length > 0) {
            for (let i = 0; i < resultsColumns.value.length; i++) {
                let maxAncho = 0;
                // Considerar cabeceras y filas de datos
                const headerValue = String(resultsColumns.value[i].header);
                if (headerValue.length > maxAncho) maxAncho = headerValue.length;

                sampleResults.value.forEach(item => {
                    const cellValue = item[resultsColumns.value[i].field] !== undefined ? String(item[resultsColumns.value[i].field]) : "S/INF";
                    if (cellValue.length > maxAncho) maxAncho = cellValue.length;
                });
                anchosColumnas.push({ wch: Math.max(12, Math.min(maxAncho + 2, 60)) });
            }
        }
        ws["!cols"] = anchosColumnas;


        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Detalle Muestra");
        const nombreArchivo = `Resultados_Muestra_${detailRecord.value["Id. Amostra"] || detailRecord.value.cdamostra || "export"
            }.xlsx`;
        XLSX.writeFile(wb, nombreArchivo);
    } catch (error) {
        console.error("Error al exportar a Excel con estilos:", error);
        alert("Se produjo un error al generar el archivo Excel estilizado. Revise la consola.");
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
    extendedDetailRecord.value = {};
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
async function fetchExtendedDetails(cdamostra) {
    if (!cdamostra) {
        console.warn("fetchExtendedDetails llamado sin cdamostra");
        extendedDetailRecord.value = {};
        return;
    }

    // Puedes usar un loader específico si quieres, o solo `isLoadingResults` si abarca ambos.
    // Para no interferir con el loader de resultados, no usaré isLoadingResults aquí.
    // Podrías añadir un `isLoadingDetails` si lo necesitas.
    extendedDetailRecord.value = {}; // Limpia antes de la carga

    try {
        const response = await axios.post(route("muestras.getExtendedDetails"), { // <-- LLAMA A LA NUEVA RUTA
            cdamostra,
        });
        const data = response.data;

        if (data.success) {
            extendedDetailRecord.value = data.extended_details || {};
        } else {
            console.error("Error al cargar detalles extendidos:", data.message);
            toast.error(data.message || "Error al cargar detalles adicionales.", {
                timeout: 3000,
                position: POSITION.TOP_CENTER,
            });
            extendedDetailRecord.value = {};
        }
    } catch (error) {
        console.error("Error de red/API al cargar detalles extendidos:", error);
        toast.error("Error de conexión al obtener detalles adicionales.", {
            timeout: 3000,
            position: POSITION.TOP_CENTER,
        });
        extendedDetailRecord.value = {};
    }
}

async function ejecutarInforme() {
    // Aquí la validación se hace con `showInformeButton`, que ya considera la Situacao.
    if (!selectedItems.value.length || !showInformeButton.value) {
        toast.warning("Para generar informes, todas las muestras seleccionadas deben tener una situación que permita la acción.", {
            timeout: 5000,
            position: POSITION.TOP_CENTER,
        });
        return;
    }

    // Comprobar si alguna muestra seleccionada es morosa
    const moroseSamples = selectedItems.value.filter(item => item.moroso === 'S');

    if (moroseSamples.length > 0) {
        let sampleIds = moroseSamples.map(item => item['Id. Amostra'] || item.cdamostra).join(', ');
        if (sampleIds.length > 100) { // Cortar si la lista de IDs es muy larga para el mensaje
            sampleIds = sampleIds.substring(0, 100) + '...';
        }

        toast.error(`No es posible generar el informe. La(s) muestra(s) con ID(s) ${sampleIds} registra(n) un estado de morosidad. Por favor, contacte a su asesor de cuenta para regularizar la situación.`, {
            timeout: 8000, // Dar más tiempo para leer este mensaje importante
            position: POSITION.TOP_CENTER,
        });
        return; // Detener la ejecución si hay muestras morosas
    }

    isGenerating.value = true;
    try {
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
// --- NUEVA FUNCIÓN PARA EXPORTAR EXCEL DESDE EL BACKEND ---
async function handleExportBackend() {
    if (!detailRecord.value?.cdamostra) {
        toast.warning("Primero selecciona una muestra para exportar.", {
            timeout: 3000,
            position: POSITION.TOP_CENTER,
        });
        return;
    }

    isGenerating.value = true; // Activa el loader global
    try {
        const response = await axios.post(
            route("muestras.exportExcelBackend"),
            {
                cdamostra: detailRecord.value.cdamostra,
                detail_data: detailRecord.value,
                extended_detail_data: extendedDetailRecord.value,
            },
            { responseType: "blob", timeout: 300000 }
        );

        // --- CORRECCIÓN CLAVE AQUÍ: Definir el nombre del archivo directamente ---
        // Ya que el Content-Disposition del backend a veces no se parsea bien con responseType: 'blob'
        const filename = `Resultados_Muestra_${detailRecord.value["Id. Amostra"] || detailRecord.value.cdamostra || "export"
            }.xlsx`;
        // No necesitamos la lógica de `if (header)` si definimos el nombre directamente.

        // Comprobar si la respuesta es un error en formato JSON en lugar de un blob
        if (response.headers['content-type'] && response.headers['content-type'].includes('application/json')) {
            const errorData = JSON.parse(await response.data.text());
            throw new Error(errorData.message || "El servidor devolvió un error inesperado al generar el Excel.");
        }

        // Lógica de descarga
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })); // <--- ¡Aseguramos el MIME type aquí también!
        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", filename); // El nombre deseado
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        toast.success("Excel generado y descargado exitosamente.", {
            timeout: 3000,
            position: POSITION.TOP_CENTER,
        });

    } catch (error) {
        console.error("Error al exportar Excel desde el backend:", error);
        const errorMessage = error.response?.data?.message || error.message || "Error desconocido al generar el Excel.";
        toast.error(`Error al generar Excel: ${errorMessage}`, {
            timeout: 5000,
            position: POSITION.TOP_CENTER,
        });
    } finally {
        isGenerating.value = false; // Desactiva el loader
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
                        <div class="info-card bg-white p-4 rounded-md shadow-sm border border-gray-200">
                            <h3
                                class="text-base font-semibold mb-3 pb-1 border-b text-gray-800 flex items-center gap-1.5">
                                <ListBulletIcon class="w-5 h-5 text-blue-600" />
                                Detalles Principales
                            </h3>
                            <ul class="space-y-1.5 text-sm">
                                <li class="flex items-start py-0.5">
                                    <BuildingOfficeIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong
                                        class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Cliente:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.solicitante ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <IdentificationIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Número
                                        Identificador:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.numero_identificador ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <MapPinIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong
                                        class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Dirección:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.direccion
                                        ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <UserIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Muestreado
                                        por:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.muestreado_por ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <DocumentTextIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Descripción
                                        de la
                                        muestra:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.descripcion_muestra ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <CalendarDaysIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Fecha de
                                        recepción:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.fecha_recepcion ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <ClockIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Fecha de
                                        Inicio
                                        Análisis:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.fecha_inicio_analisis ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <ClockIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Fecha de
                                        Término
                                        Análisis:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.fecha_termino_analisis ?? 'S/INF' }}</span>
                                </li>
                                <li class="flex items-start py-0.5">
                                    <CalendarDaysIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <strong class="text-gray-600 font-medium w-2/5 flex-shrink-0 truncate">Fecha de
                                        Emisión:</strong>
                                    <span class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.datalaudo
                                        ?? 'S/INF' }}</span>
                                </li>
                            </ul>
                            <div v-if="Object.keys(extendedDetailRecord).every(key => !extendedDetailRecord[key])"
                                class="text-gray-500 italic text-center py-2">
                                No hay detalles principales disponibles.
                            </div>
                        </div>

                        <div class="info-card bg-white p-4 rounded-md shadow-sm border border-gray-200">
                            <h3
                                class="text-base font-semibold mb-3 pb-1 border-b text-gray-800 flex items-center gap-1.5">
                                <ArchiveBoxIcon class="w-5 h-5 text-blue-600" />
                                Otros Datos Adicionales
                            </h3>
                            <dl class="text-sm space-y-1.5">
                                <div class="flex items-start py-0.5">
                                    <TagIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Código Muestra Cliente:
                                    </dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.codigo_muestra_cliente ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <TagIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Variedad:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.variedad ??
                                        'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <CalendarDaysIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Fecha de muestreo:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.fecha_muestreo ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <UserIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Muestreador:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.muestreador_persona ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <MapPinIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Lugar de Muestreo:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.lugar_muestreo_detail ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <BuildingStorefrontIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Nombre Productor:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.nombre_productor ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <HashtagIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Código de Productor:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.codigo_productor ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <MapPinIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Predio:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.predio ??
                                        'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <HashtagIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">N° Registro Agricola:</dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.n_registro_agricola ?? 'S/INF' }}</dd>
                                </div>
                                <div class="flex items-start py-0.5">
                                    <DocumentTextIcon class="w-4 h-4 text-gray-500 mr-2 flex-shrink-0 mt-0.5" />
                                    <dt class="text-gray-600 font-medium w-2/5 flex-shrink-0">Información Adicional:
                                    </dt>
                                    <dd class="text-gray-800 flex-1 ml-2 break-words text-right">{{
                                        extendedDetailRecord.informacion_adicional ?? 'S/INF' }}</dd>
                                </div>


                                <div v-if="Object.keys(extendedDetailRecord).every(key => !extendedDetailRecord[key]) && detailRecord.moroso == null && detailRecord.mrl == null && detailRecord.mercados == null && detailRecord.retailers == null"
                                    class="text-gray-500 italic text-center py-2">
                                    No hay datos adicionales disponibles.
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
                            @click="handleExportBackend" title="Exportar detalle y resultados a Excel"
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
