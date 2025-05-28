<template>
    <div class="border-b border-gray-200 pb-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-3 items-end mb-3">
            <div>
                <label for="status-selector" class="block text-xs font-medium text-gray-600">Situación</label>
                <select id="status-selector" v-model="localFilters.status"
                    class="mt-1 block w-full pl-2 pr-8 py-1.5 h-8 text-xs border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                    <option value="2">Recibida</option>
                    <option value="10">En proceso</option>
                    <option value="3">Finalizada</option>
                    <option value="4">Publicada</option>
                </select>
            </div>
            <div>
                <label for="date-from" class="block text-xs font-medium text-gray-600">Publicada Desde</label>
                <input type="date" id="date-from" v-model="localFilters.desde"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs h-8 px-2 py-1.5" />
            </div>
            <div>
                <label for="date-to" class="block text-xs font-medium text-gray-600">Publicada Hasta</label>
                <input type="date" id="date-to" v-model="localFilters.hasta"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs h-8 px-2 py-1.5" />
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-x-3 gap-y-2">
            <input type="text" v-model.lazy="localFilters.search_idamostra" placeholder="Id. Amostra"
                class="simple-input" />
            <input type="text" v-model.lazy="localFilters.search_cdamostra" placeholder="Código Amostra"
                class="simple-input" />
            <input type="text" v-model.lazy="localFilters.search_solicitante" placeholder="Solicitante"
                class="simple-input" />
            <input type="text" v-model.lazy="localFilters.search_tipo" placeholder="Matriz (Tipo)"
                class="simple-input" />
            <input type="text" v-model.lazy="localFilters.search_grupo" placeholder="Grupo" class="simple-input" />
            <input type="text" v-model.lazy="localFilters.search_processo" placeholder="Proceso" class="simple-input" />
            <input type="text" v-model.lazy="localFilters.search_numero" placeholder="Número" class="simple-input" />

            <div class="flex items-end space-x-2">
                <button @click="triggerEmitFilters"
                    class="flex-1 h-8 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        class="w-4 h-4 mr-1">
                        <path fill-rule="evenodd"
                            d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                            clip-rule="evenodd" />
                    </svg>
                    Filtrar
                </button>

                <button @click="resetFilters"
                    title="Limpiar filtros y mostrar todos los registros según fechas por defecto"
                    class="flex-1 h-8 px-3 py-1.5 bg-gray-500 text-white text-xs font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-1">
                        <path fill-rule="evenodd" d="M4.25 5.25a.75.75 0 011.06 0L10 9.44l4.69-4.19a.75.75 0 111.06 1.06L11.06 10l4.69 4.19a.75.75 0 11-1.06 1.06L10 10.56l-4.69 4.19a.75.75 0 01-1.06-1.06L8.94 10 4.25 5.81a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                    Limpiar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, watch } from "vue";
import { defineProps, defineEmits } from "vue";

const props = defineProps({
    initialFilters: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(["update-filters"]);

// Estado local reactivo para los filtros
const localFilters = reactive({
    unit: "Food", // FIJO
    status: "4",  // Default situation for Food (Publicada)
    desde: "",
    hasta: "",
    search_grupo: "",
    search_processo: "",
    search_numero: "",
    search_idamostra: "",
    search_cdamostra: "",
    search_solicitante: "",
    search_tipo: "",
});

// Función para calcular fechas default (últimos 30 días)
const setDefaultDates = () => {
    const today = new Date();
    const pastDate = new Date(today);
    pastDate.setDate(today.getDate() - 30);
    const formatDate = (date) => date.toISOString().split("T")[0];
    localFilters.desde = formatDate(pastDate);
    localFilters.hasta = formatDate(today);
};

// Inicializar filtros locales con los valores del backend o defaults
const initializeFilters = () => {
    localFilters.status = props.initialFilters.status || "4"; // Usar "4" como default si no viene de props
    localFilters.desde = props.initialFilters.desde || "";
    localFilters.hasta = props.initialFilters.hasta || "";
    localFilters.search_grupo = props.initialFilters.search_grupo || "";
    localFilters.search_processo = props.initialFilters.search_processo || "";
    localFilters.search_numero = props.initialFilters.search_numero || "";
    localFilters.search_idamostra = props.initialFilters.search_idamostra || "";
    localFilters.search_cdamostra = props.initialFilters.search_cdamostra || "";
    localFilters.search_solicitante = props.initialFilters.search_solicitante || "";
    localFilters.search_tipo = props.initialFilters.search_tipo || "";

    if (!localFilters.desde && !localFilters.hasta) {
        setDefaultDates();
    }
};

watch(() => props.initialFilters, initializeFilters, {
    deep: true,
    immediate: true,
});

// Función para emitir los filtros al componente padre (llamada por botón "Filtrar")
const triggerEmitFilters = () => {
    const filtersToEmit = {
        unit: localFilters.unit,
        status: localFilters.status,
        desde: localFilters.desde,
        hasta: localFilters.hasta,
        search_grupo: localFilters.search_grupo,
        search_processo: localFilters.search_processo,
        search_numero: localFilters.search_numero,
        search_idamostra: localFilters.search_idamostra,
        search_cdamostra: localFilters.search_cdamostra,
        search_solicitante: localFilters.search_solicitante,
        search_tipo: localFilters.search_tipo,
    };
    console.log("Emitiendo filtros:", filtersToEmit);
    emit("update-filters", filtersToEmit);
};

// --- NUEVO MÉTODO PARA LIMPIAR FILTROS ---
const resetFilters = () => {
    // Restablecer al estado por defecto definido en 'localFilters' y 'setDefaultDates'
    localFilters.status = "4"; // Default 'Publicada'

    // Restablecer fechas por defecto (últimos 30 días)
    setDefaultDates();

    // Limpiar todos los campos de búsqueda
    localFilters.search_grupo = "";
    localFilters.search_processo = "";
    localFilters.search_numero = "";
    localFilters.search_idamostra = "";
    localFilters.search_cdamostra = "";
    localFilters.search_solicitante = "";
    localFilters.search_tipo = "";

    console.log("Filtros reseteados. Emitiendo:", { ...localFilters }); // Log para verificar
    // Emitir los filtros reseteados para que Dashboard.vue recargue los datos
    triggerEmitFilters();
};
// --- FIN NUEVO MÉTODO ---

</script>

<style scoped>

.simple-input {
    @apply block w-full border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs h-8 px-2 py-1;
}
.simple-input::placeholder {
    @apply text-gray-400 text-xs italic;
}
</style>
