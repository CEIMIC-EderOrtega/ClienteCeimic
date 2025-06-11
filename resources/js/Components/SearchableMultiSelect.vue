<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    options: { type: Array, required: true },
    modelValue: { type: Array, required: true },
    placeholder: { type: String, default: 'Seleccionar...' }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchTerm = ref('');
const dropdownRef = ref(null);

const filteredOptions = computed(() => {
    if (!searchTerm.value) {
        return props.options;
    }
    return props.options.filter(option =>
        String(option).toLowerCase().includes(searchTerm.value.toLowerCase())
    );
});

const toggleSelection = (option) => {
    const newSelection = [...props.modelValue];
    const index = newSelection.indexOf(option);
    if (index > -1) {
        newSelection.splice(index, 1);
    } else {
        newSelection.push(option);
    }
    emit('update:modelValue', newSelection);
};

// Lógica para los botones de acción
const selectAll = () => {
    emit('update:modelValue', [...props.options]);
};

const deselectAll = () => {
    emit('update:modelValue', []);
};


const closeDropdown = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeDropdown));
onBeforeUnmount(() => document.removeEventListener('click', closeDropdown));

</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button @click="isOpen = !isOpen" type="button"
            class="relative w-full cursor-default rounded-md bg-white py-1.5 pl-3 pr-10 text-left text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-xs sm:leading-6 h-8">
            <span class="block truncate">
                <span v-if="modelValue.length === 0">{{ placeholder }}</span>
                <span v-else-if="modelValue.length === 1">{{ modelValue[0] }}</span>
                <span v-else>{{ modelValue.length }} seleccionados</span>
            </span>
            <span class="pointer-events-none absolute inset-y-0 right-0 ml-3 flex items-center pr-2">
                <ChevronUpDownIcon class="h-5 w-5 text-gray-400" />
            </span>
        </button>

        <transition leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-show="isOpen"
                class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-xs">
                <div class="p-2">
                    <input v-model="searchTerm" type="text" placeholder="Buscar..."
                        class="w-full rounded-md border-gray-300 shadow-sm sm:text-xs h-8">
                </div>

                <div class="flex justify-between border-b border-gray-200 px-3 py-1.5">
                    <button @click="selectAll" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                        Seleccionar Todos
                    </button>
                    <button v-if="modelValue.length > 0" @click="deselectAll" class="text-xs font-medium text-gray-600 hover:text-gray-800">
                        Limpiar
                    </button>
                </div>

                <ul>
                    <li v-for="option in filteredOptions" :key="option" @click="toggleSelection(option)"
                        class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-indigo-600 hover:text-white">
                        <div class="flex items-center">
                            <span :class="[modelValue.includes(option) ? 'font-semibold' : 'font-normal', 'ml-3 block truncate']">
                                {{ option }}
                            </span>
                            <span v-if="modelValue.includes(option)"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 group-hover:text-white">
                                <CheckIcon class="h-5 w-5" />
                            </span>
                        </div>
                    </li>
                    <li v-if="filteredOptions.length === 0" class="px-3 py-2 text-center text-gray-500">
                        No se encontraron resultados.
                    </li>
                </ul>
            </div>
        </transition>
    </div>
</template>