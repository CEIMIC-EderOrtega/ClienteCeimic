<template>
    <div class="border-b border-gray-200 pb-4 mb-4">
        <div class="flex flex-wrap gap-x-4 gap-y-3 items-end">

            <div>
                <label for="status-selector" class="block text-xs font-medium text-gray-600 mb-0.5">Situación</label>
                <select id="status-selector" v-model="localFilters.status" class="input-compact">
                    <option value="2">Recibida</option>
                    <option value="111">En Preparación</option>
                    <option value="133">En Extracción</option>
                    <option value="222">Analizando</option>
                    <option value="444">En Revisión</option>
                    <option value="3">Finalizada</option>
                    <option value="4">Publicada</option>
                </select>
            </div>

            <div>
                <label for="date-from" class="block text-xs font-medium text-gray-600 mb-0.5">Publicada Desde</label>
                <input type="date" id="date-from" v-model="localFilters.desde" class="input-compact" />
            </div>
            <div>
                <label for="date-to" class="block text-xs font-medium text-gray-600 mb-0.5">Publicada Hasta</label>
                <input type="date" id="date-to" v-model="localFilters.hasta" class="input-compact" />
            </div>

            <div>
                <input type="text" v-model.lazy="localFilters.search_cdamostra" placeholder="Código Lab."
                    class="input-compact placeholder:italic" />
            </div>
            <div>
                <input type="text" v-model.lazy="localFilters.search_solicitante" placeholder="Solicitante"
                    class="input-compact placeholder:italic" />
            </div>
            <div>
                <input type="text" v-model.lazy="localFilters.search_tipo" placeholder="Matriz (Tipo)"
                    class="input-compact placeholder:italic" />
            </div>
            <div>
                <input type="text" v-model.lazy="localFilters.search_grupo" placeholder="Grupo"
                    class="input-compact placeholder:italic" />
            </div>

            <div>
                <input type="text" v-model.lazy="localFilters.search_numero" placeholder="Número"
                    class="input-compact placeholder:italic" />
            </div>

            <div v-if="isAdmin" class="relative z-50 min-w-48">
                <label for="company-multiselect" class="block text-xs font-medium text-gray-600 mb-0.5">Empresa
                    (Solicitante)</label>
                <SearchableMultiSelect id="company-multiselect" v-model="selectedCompanies"
                    :options="props.companyOptions" placeholder="Filtrar por empresa..." class="mt-0.5" />
            </div>

            <div class="flex items-center space-x-2 ml-auto">
                <button @click="triggerEmitFilters" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        class="w-4 h-4 mr-1.5">
                        <path fill-rule="evenodd"
                            d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                            clip-rule="evenodd" />
                    </svg>
                    Filtrar
                </button>
                <button @click="resetFilters" title="Limpiar filtros" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        class="w-4 h-4 mr-1.5">
                        <path fill-rule="evenodd"
                            d="M4.25 5.25a.75.75 0 011.06 0L10 9.44l4.69-4.19a.75.75 0 111.06 1.06L11.06 10l4.69 4.19a.75.75 0 11-1.06 1.06L10 10.56l-4.69 4.19a.75.75 0 01-1.06-1.06L8.94 10 4.25 5.81a.75.75 0 010-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                    Limpiar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, watch, ref } from "vue";
import { defineProps, defineEmits } from "vue";
import SearchableMultiSelect from "./SearchableMultiSelect.vue";

const props = defineProps({
    initialFilters: { type: Object, default: () => ({}) },
    isAdmin: { type: Boolean, default: false },
    companyOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(["update-filters", "update-company-filter"]);

const localFilters = reactive({
    status: "4", // Default a "Publicada"
    desde: "",
    hasta: "",
    search_grupo: "",
    search_numero: "",
    search_idamostra: "",
    search_cdamostra: "",
    search_solicitante: "",
    search_tipo: "",
});

const selectedCompanies = ref([]);

watch(selectedCompanies, (newSelection) => {
    emit("update-company-filter", newSelection);
});

const setDefaultDates = () => {
    const today = new Date();
    const pastDate = new Date(today);
    pastDate.setDate(today.getDate() - 14);
    const formatDate = (date) => date.toISOString().split("T")[0];
    localFilters.desde = formatDate(pastDate);
    localFilters.hasta = formatDate(today);
};

const initializeFilters = () => {
    Object.assign(localFilters, {
        status: props.initialFilters.status || "4",
        desde: props.initialFilters.desde || "",
        hasta: props.initialFilters.hasta || "",
        search_grupo: props.initialFilters.search_grupo || "",
        search_numero: props.initialFilters.search_numero || "",
        search_idamostra: props.initialFilters.search_idamostra || "",
        search_cdamostra: props.initialFilters.search_cdamostra || "",
        search_solicitante: props.initialFilters.search_solicitante || "",
        search_tipo: props.initialFilters.search_tipo || "",
    });
    if (!localFilters.desde && !localFilters.hasta) setDefaultDates();
};

watch(() => props.initialFilters, initializeFilters, { deep: true, immediate: true });

const triggerEmitFilters = () => {
    const { unit, ...filtersToEmit } = localFilters;
    emit("update-filters", { ...filtersToEmit, unit: "Food" });
};

const resetFilters = () => {
    localFilters.status = "4"; 
    setDefaultDates();
    localFilters.search_grupo = "";
    localFilters.search_numero = "";
    localFilters.search_idamostra = "";
    localFilters.search_cdamostra = "";
    localFilters.search_solicitante = "";
    localFilters.search_tipo = "";
    selectedCompanies.value = [];
    triggerEmitFilters();
};
</script>

<style scoped>
/* Clases de utilidad para mantener la consistencia y el aspecto compacto */
.input-compact {
    @apply mt-0 block w-full min-w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs h-8 px-2 py-1.5;
}

.btn {
    @apply w-auto h-8 px-4 py-1.5 text-xs font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 flex items-center justify-center;
}

.btn-primary {
    @apply bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500;
}

.btn-secondary {
    @apply bg-gray-500 text-white hover:bg-gray-600 focus:ring-gray-400;
}
</style>