<script setup>
import { computed } from 'vue';
import {
  Listbox,
  ListboxButton,
  ListboxOptions,
  ListboxOption,
} from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';

// --- PROPS Y EMITS ---
const props = defineProps({
  // v-model para las selecciones. Espera un array de IDs/values.
  modelValue: {
    type: Array,
    required: true,
  },
  // Las opciones a mostrar en el dropdown. Debe ser un array de objetos con 'value' y 'label'.
  options: {
    type: Array,
    required: true,
    default: () => [],
  },
  // Etiqueta por defecto para el botón del dropdown.
  placeholder: {
    type: String,
    default: 'Seleccionar...',
  },
});

const emit = defineEmits(['update:modelValue']);

// --- LÓGICA DEL COMPONENTE ---

// Determina si una opción está actualmente seleccionada.
function isSelected(value) {
  return props.modelValue.includes(value);
}

// Maneja la selección de una opción.
function handleSelect(value) {
  const selected = [...props.modelValue];
  const index = selected.indexOf(value);

  if (index > -1) {
    // Si ya está seleccionado, lo quita
    selected.splice(index, 1);
  } else {
    // Si no, lo añade
    selected.push(value);
  }
  // Emite el evento para actualizar el v-model en el componente padre.
  emit('update:modelValue', selected);
}

// Texto que se muestra en el botón del dropdown.
const buttonLabel = computed(() => {
  if (props.modelValue.length === 0) {
    return props.placeholder;
  }
  if (props.modelValue.length === 1) {
    const selectedOption = props.options.find(opt => opt.value === props.modelValue[0]);
    return selectedOption ? selectedOption.label : '1 item seleccionado';
  }
  return `${props.modelValue.length} items seleccionados`;
});

</script>

<template>
  <Listbox
    :model-value="modelValue"
    @update:model-value="value => emit('update:modelValue', value)"
    multiple
    as="div"
    class="relative"
  >
    <ListboxButton
      class="relative w-full cursor-default rounded-md bg-white py-2 pl-3 pr-10 text-left border border-gray-300 shadow-sm focus:outline-none focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-white/75 focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-300 sm:text-sm"
    >
      <span class="block truncate">{{ buttonLabel }}</span>
      <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
        <ChevronUpDownIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
      </span>
    </ListboxButton>

    <transition
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <ListboxOptions
        class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm"
      >
        <ListboxOption
          v-for="option in options"
          v-slot="{ active, selected }"
          :key="option.value"
          :value="option.value"
          as="template"
        >
          <li
            :class="[
              active ? 'bg-indigo-100 text-indigo-900' : 'text-gray-900',
              'relative cursor-default select-none py-2 pl-10 pr-4',
            ]"
          >
            <span :class="[selected ? 'font-medium' : 'font-normal', 'block truncate']">
              {{ option.label }}
            </span>
            <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3 text-indigo-600">
              <CheckIcon class="h-5 w-5" aria-hidden="true" />
            </span>
          </li>
        </ListboxOption>
      </ListboxOptions>
    </transition>
  </Listbox>
</template>