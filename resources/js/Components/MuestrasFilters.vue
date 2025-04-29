<template>
    <div class="border-b border-gray-200 pb-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 items-end">
            <div>
                <label for="unit-selector" class="block text-xs font-medium text-gray-600">Unidad</label>
                <select id="unit-selector" v-model="localFilters.unit" @change="emitFilters"
                    class="mt-1 block w-full pl-2 pr-8 py-1.5 h-8 text-xs border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                    <option value="Enviro">Enviro</option>
                    <option value="Food">Food</option>
                </select>
            </div>

            <template v-if="localFilters.unit === 'Food'">
                <div>
                    <label for="status-selector" class="block text-xs font-medium text-gray-600">Situación</label>
                    <select id="status-selector" v-model="localFilters.status" @change="emitFilters"
                        class="mt-1 block w-full pl-2 pr-8 py-1.5 h-8 text-xs border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                        <option value="3">Finalizada</option>
                        <option value="4">Publicada (No Enviada)</option>
                        <option value="111">Enviada a Portal</option>
                        <option value="">Todas (si aplica)</option>
                    </select>
                </div>

                <div>
                    <label for="date-from" class="block text-xs font-medium text-gray-600">Publicada Desde</label>
                    <input type="date" id="date-from" v-model="localFilters.desde" @change="emitFilters"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs h-8 px-2 py-1.5" />
                </div>

                <div>
                    <label for="date-to" class="block text-xs font-medium text-gray-600">Publicada Hasta</label>
                    <input type="date" id="date-to" v-model="localFilters.hasta" @change="emitFilters"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs h-8 px-2 py-1.5" />
                </div>

                <div
                    class="col-span-1 sm:col-span-2 md:col-span-3 lg:col-span-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 gap-2 mt-2">
                    <input type="text" v-model="localFilters.search_idamostra" placeholder="Id. Amostra"
                        @input="debouncedEmitFilters" class="simple-input" />
                    <input type="text" v-model="localFilters.search_cdamostra" placeholder="Código Amostra"
                        @input="debouncedEmitFilters" class="simple-input" />
                    <input type="text" v-model="localFilters.search_solicitante" placeholder="Solicitante"
                        @input="debouncedEmitFilters" class="simple-input" />
                    <input type="text" v-model="localFilters.search_tipo" placeholder="Matriz (Tipo)"
                        @input="debouncedEmitFilters" class="simple-input" />
                    <input type="text" v-model="localFilters.search_grupo" placeholder="Grupo"
                        @input="debouncedEmitFilters" class="simple-input" />
                    <input type="text" v-model="localFilters.search_processo" placeholder="Proceso"
                        @input="debouncedEmitFilters" class="simple-input" />
                    <input type="text" v-model="localFilters.search_numero" placeholder="Número"
                        @input="debouncedEmitFilters" class="simple-input" />
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, reactive } from "vue";
import { defineProps, defineEmits } from "vue";
import { debounce } from "lodash-es"; // Para evitar peticiones en cada tecla

const props = defineProps({
    // Recibe los filtros actuales del backend para inicializar
    initialFilters: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(["update-filters"]);

// Estado local reactivo para los filtros
const localFilters = reactive({
    unit: "Enviro",
    status: "3", // Default situation for Food
    desde: "",
    hasta: "",
    search_grupo: "",
    search_processo: "",
    search_numero: "",
    search_idamostra: "",
    search_cdamostra: "",
    search_solicitante: "",
    search_tipo: "",
    // ... otros filtros que necesites inicializar ...
});

// Función para calcular fechas default (últimos 30 días)
const setDefaultDates = () => {
    const today = new Date();
    const pastDate = new Date(today);
    pastDate.setDate(today.getDate() - 30);

    // Formato YYYY-MM-DD requerido por input type="date"
    const formatDate = (date) => date.toISOString().split("T")[0];

    localFilters.desde = formatDate(pastDate);
    localFilters.hasta = formatDate(today);
};

// Inicializar filtros locales con los valores del backend o defaults
const initializeFilters = () => {
    localFilters.unit = props.initialFilters.unit || "Enviro";
    localFilters.status = props.initialFilters.status || "3"; // Usa el status que viene o default 3
    localFilters.desde = props.initialFilters.desde || "";
    localFilters.hasta = props.initialFilters.hasta || "";
    localFilters.search_grupo = props.initialFilters.search_grupo || "";
    localFilters.search_processo = props.initialFilters.search_processo || "";
    localFilters.search_numero = props.initialFilters.search_numero || "";
    localFilters.search_idamostra = props.initialFilters.search_idamostra || "";
    localFilters.search_cdamostra = props.initialFilters.search_cdamostra || "";
    localFilters.search_solicitante =
        props.initialFilters.search_solicitante || "";
    localFilters.search_tipo = props.initialFilters.search_tipo || "";

    // Establecer fechas por defecto si no vienen del backend y la unidad es Food
    if (
        localFilters.unit === "Food" &&
        !localFilters.desde &&
        !localFilters.hasta
    ) {
        setDefaultDates();
    }
};

// Observar cambios en initialFilters (por si cambia por navegación externa)
watch(() => props.initialFilters, initializeFilters, {
    deep: true,
    immediate: true,
});

// Función para emitir los filtros al componente padre
const emitFilters = () => {
    // Solo emitir filtros relevantes para la unidad seleccionada
    const filtersToEmit = { unit: localFilters.unit };
    if (localFilters.unit === "Food") {
        filtersToEmit.status = localFilters.status;
        filtersToEmit.desde = localFilters.desde;
        filtersToEmit.hasta = localFilters.hasta;
        filtersToEmit.search_grupo = localFilters.search_grupo;
        filtersToEmit.search_processo = localFilters.search_processo;
        filtersToEmit.search_numero = localFilters.search_numero;
        filtersToEmit.search_idamostra = localFilters.search_idamostra;
        filtersToEmit.search_cdamostra = localFilters.search_cdamostra;
        filtersToEmit.search_solicitante = localFilters.search_solicitante;
        filtersToEmit.search_tipo = localFilters.search_tipo;
    }
    // Limpiar valores nulos o vacíos antes de emitir (opcional, depende de tu backend)
    Object.keys(filtersToEmit).forEach(key => {
        if (filtersToEmit[key] === null || filtersToEmit[key] === '') {
            delete filtersToEmit[key];
        }
    });
    console.log("Emitiendo filtros:", filtersToEmit);
    emit("update-filters", filtersToEmit);
};

// Versión con debounce para inputs de texto
const debouncedEmitFilters = debounce(emitFilters, 500); // Espera 500ms después de dejar de teclear

// Observar cambios en unit para resetear/aplicar defaults si es necesario
watch(
    () => localFilters.unit,
    (newUnit, oldUnit) => {
        if (newUnit === "Food" && !localFilters.desde && !localFilters.hasta) {
            setDefaultDates();
        }
        // Emitir inmediatamente al cambiar de unidad para recargar datos
        emitFilters();
    }
);
</script>

<style scoped>
/* Estilos más compactos para inputs de texto */
.simple-input {
    @apply block w-full border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs h-8 px-2 py-1;
    /* Reducido padding y altura */
}

/* Placeholder más pequeño */
.simple-input::placeholder {
    @apply text-gray-400 text-xs italic;
    /* Hecho un poco más distintivo */
}
</style>
