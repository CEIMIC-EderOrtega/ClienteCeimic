<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Tu layout
// Importa iconos
import {
    FlagIcon,       // Icono para el título de la página
    PlusCircleIcon, // Icono para agregar nuevo
    PencilIcon,     // Icono para editar
    TrashIcon,      // Icono para eliminar
    XMarkIcon,      // Icono para cerrar modal
    ExclamationCircleIcon // Icono para confirmar eliminación
} from '@heroicons/vue/24/outline'; // O '20/solid' si prefieres

// Define las props que vienen del controlador (Admin/CountryController@index)
const props = defineProps({
    countries: Array, // Esperamos un array de países
});

// --- Estado y lógica para los modales ---
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Estado para el país que se está editando o eliminando
const editingCountry = ref(null);
const deletingCountry = ref(null);

// Formulario para crear un nuevo país
const createForm = useForm({
    name: '',
    code: '',
});

// Formulario para editar un país existente
const editForm = useForm({
    name: '',
    code: '',
});

// --- Funciones para Modales y Acciones ---

// Abrir modal de crear
const openCreateModal = () => {
    createForm.reset(); // Limpiar formulario anterior
    showCreateModal.value = true;
};

// Cerrar modal de crear
const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.reset(); // Limpiar formulario
    createForm.clearErrors(); // Limpiar errores de validación
};

// Enviar formulario de crear
const submitCreateForm = () => {
    // Post a la ruta store del CountryController
    createForm.post(route('admin.countries.store'), {
        onSuccess: () => {
            closeCreateModal(); // Cerrar modal al éxito
            // Laravel con with('success', '...') enviará un flash message
            // Inertia lo recibe en $page.props.flash.success
            // Puedes mostrar un mensaje de notificación aquí si lo deseas
        },
        onError: (errors) => {
            console.error('Error al crear país:', errors);
            // Inertia automáticamente llenará createForm.errors con los errores del backend
        },
    });
};

// Abrir modal de editar
const openEditModal = (country) => {
    editingCountry.value = country; // Guardar el país que se editará
    // Llenar el formulario de edición con los datos del país
    editForm.name = country.name;
    editForm.code = country.code;
    showEditModal.value = true;
};

// Cerrar modal de editar
const closeEditModal = () => {
    showEditModal.value = false;
    editingCountry.value = null; // Limpiar el país en edición
    editForm.reset(); // Limpiar formulario
    editForm.clearErrors(); // Limpiar errores
};

// Enviar formulario de editar
const submitEditForm = () => {
    if (!editingCountry.value) return; // No hacer nada si no hay país en edición

    // Put a la ruta update del CountryController
    editForm.put(route('admin.countries.update', editingCountry.value.id), {
        onSuccess: () => {
            closeEditModal(); // Cerrar modal al éxito
        },
        onError: (errors) => {
            console.error('Error al editar país:', errors);
            // Inertia llenará editForm.errors
        },
    });
};

// Abrir modal de eliminar
const openDeleteModal = (country) => {
    deletingCountry.value = country; // Guardar el país a eliminar
    showDeleteModal.value = true;
};

// Cerrar modal de eliminar
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingCountry.value = null; // Limpiar el país a eliminar
};

// Confirmar eliminación
const confirmDelete = () => {
    if (!deletingCountry.value) return;

    // Usar router.delete para enviar la petición DELETE
    // Inertia recargará la página o actualizará los datos automáticamente
    router.delete(route('admin.countries.destroy', deletingCountry.value.id), {
        onSuccess: () => {
            closeDeleteModal(); // Cerrar modal al éxito
        },
        onError: (errors) => {
            console.error('Error al eliminar país:', errors);
            // Manejar errores (ej. si el país tiene relaciones y no se puede eliminar)
            alert('No se pudo eliminar el país. Asegúrate de que no esté asociado a usuarios, etc.'); // Ejemplo simple de error
            closeDeleteModal(); // Cerrar modal de todas formas
        },
    });
};

// Para acceder a Inertia router (para delete)
import { router } from '@inertiajs/vue3';

// Para mostrar mensajes flash de éxito o error global (opcional, si los configuras)
// import { usePage } from '@inertiajs/vue3';
// const page = usePage(); // Accede a $page.props


</script>

<template>

    <Head title="Administración de Países" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight flex items-center gap-2 text-[#485F84]">
                <FlagIcon class="w-6 h-6 inline-block text-[#485F84]" />
                Administración de Países
            </h2>
        </template>

        <div class="py-6 md:py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 md:p-8">

                    <div class="flex justify-end mb-4">
                        <button @click="openCreateModal"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <PlusCircleIcon class="w-4 h-4 mr-2" /> Agregar País
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nombre
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código
                                    </th>
                                    <th scope="col"
                                        class="relative px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="country in countries" :key="country.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ country.id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ country.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ country.code ?? 'N/A' }} </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="openEditModal(country)"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <PencilIcon class="w-5 h-5 inline-block" /> Editar
                                        </button>
                                        <button @click="openDeleteModal(country)"
                                            class="text-red-600 hover:text-red-900">
                                            <TrashIcon class="w-5 h-5 inline-block" /> Eliminar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="countries.length === 0">
                                    <td colspan="4"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No hay países registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Agregar Nuevo País
                            </h3>
                            <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-500">
                                <XMarkIcon class="w-6 h-6" />
                            </button>
                        </div>

                        <form @submit.prevent="submitCreateForm">
                            <div class="mb-4">
                                <label for="create-name" class="block text-sm font-medium text-gray-700">Nombre del
                                    País</label>
                                <input type="text" id="create-name" v-model="createForm.name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': createForm.errors.name }">
                                <p v-if="createForm.errors.name" class="mt-2 text-sm text-red-600">{{
                                    createForm.errors.name }}
                                </p>
                            </div>
                            <div class="mb-4">
                                <label for="create-code" class="block text-sm font-medium text-gray-700">Código (Ej: PE,
                                    US)</label>
                                <input type="text" id="create-code" v-model="createForm.code"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': createForm.errors.code }">
                                <p v-if="createForm.errors.code" class="mt-2 text-sm text-red-600">{{
                                    createForm.errors.code }}
                                </p>
                            </div>

                            <div class="px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button type="submit" :disabled="createForm.processing"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto"
                                    :class="{ 'opacity-25': createForm.processing }">
                                    {{ createForm.processing ? 'Guardando...' : 'Guardar País' }}
                                </button>
                                <button type="button" @click="closeCreateModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Editar País
                            </h3>
                            <button @click="closeEditModal" class="text-gray-400 hover:text-gray-500">
                                <XMarkIcon class="w-6 h-6" />
                            </button>
                        </div>

                        <form v-if="editingCountry" @submit.prevent="submitEditForm">
                            <div class="mb-4">
                                <label for="edit-name" class="block text-sm font-medium text-gray-700">Nombre del
                                    País</label>
                                <input type="text" id="edit-name" v-model="editForm.name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': editForm.errors.name }">
                                <p v-if="editForm.errors.name" class="mt-2 text-sm text-red-600">{{ editForm.errors.name
                                }}</p>
                            </div>
                            <div class="mb-4">
                                <label for="edit-code" class="block text-sm font-medium text-gray-700">Código (Ej: PE,
                                    US)</label>
                                <input type="text" id="edit-code" v-model="editForm.code"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': editForm.errors.code }">
                                <p v-if="editForm.errors.code" class="mt-2 text-sm text-red-600">{{ editForm.errors.code
                                }}</p>
                            </div>

                            <div class="px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button type="submit" :disabled="editForm.processing"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                                    :class="{ 'opacity-25': editForm.processing }">
                                    {{ editForm.processing ? 'Actualizando...' : 'Actualizar País' }}
                                </button>
                                <button type="button" @click="closeEditModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <ExclamationCircleIcon class="h-6 w-6 text-red-600" aria-hidden="true" />
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Eliminar
                                    País</h3>
                                <div class="mt-2">
                                    <p v-if="deletingCountry" class="text-sm text-gray-500">
                                        ¿Estás seguro de que deseas eliminar el país "{{ deletingCountry.name }}"? Esta
                                        acción
                                        no se puede deshacer.
                                    </p>
                                    <p v-else class="text-sm text-gray-500">
                                        ¿Estás seguro de que deseas eliminar este país? Esta acción no se puede
                                        deshacer.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="button" @click="confirmDelete"
                            class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">
                            Eliminar
                        </button>
                        <button type="button" @click="closeDeleteModal"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>