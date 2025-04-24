<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Tu layout

// Importa iconos
import {
    UserCircleIcon, // Icono para el título de la página (usando el que mencionaste)
    PlusCircleIcon, // Icono para agregar nuevo
    PencilIcon, // Icono para editar
    TrashIcon, // Icono para eliminar
    CheckIcon, // Icono para botón Guardar/Actualizar
    XMarkIcon, // Icono para cancelar edición
} from '@heroicons/vue/24/outline'; // O '20/solid' si prefieres

// Define las props que vienen del controlador (App\Http\Controllers\RoleController@index)
const props = defineProps({
    roles: Array, // Esperamos un array de roles
});

// --- Estado para el formulario (sirve para crear y editar) ---
const form = useForm({
    name: '',
    // Los roles no tienen 'code', solo 'name'
});

// Estado para saber qué rol se está editando (null si estamos creando)
const editingRole = ref(null);

// --- Propiedades computadas para la UI dinámica ---

// Título del formulario (Agregar vs Editar)
const formTitle = computed(() => {
    return editingRole.value ? 'Editar Rol' : 'Agregar Nuevo Rol';
});

// Texto del botón principal del formulario (Agregar vs Actualizar)
const formButtonText = computed(() => {
    if (form.processing) {
        return editingRole.value ? 'Actualizando...' : 'Guardando...';
    }
    return editingRole.value ? 'Actualizar Rol' : 'Agregar Rol';
});

// Icono del botón principal del formulario
const formButtonIcon = computed(() => {
     return editingRole.value ? CheckIcon : PlusCircleIcon;
});


// --- Funciones para Acciones ---

// Prepara el formulario para crear un nuevo rol
const prepareCreate = () => {
    editingRole.value = null;
    form.reset();
    form.clearErrors();
};

// Inicia el modo de edición: carga los datos del rol en el formulario
const startEditing = (role) => {
    editingRole.value = role; // Guarda el objeto rol completo
    form.name = role.name;
    form.clearErrors(); // Limpia errores si los hubo de una edición anterior

    // Opcional: Hacer scroll suave hacia el formulario
    // Asegúrate de que el div del formulario tenga id="role-form"
    document.getElementById('role-form').scrollIntoView({ behavior: 'smooth' });
};

// Cancela el modo de edición y limpia el formulario
const cancelEditing = () => {
    prepareCreate(); // Vuelve al estado de creación
};

// Envía el formulario (crear o editar)
const submitForm = () => {
    if (editingRole.value) {
        // Si editingRole tiene un valor, estamos editando
        form.put(route('admin.roles.update', editingRole.value.id), {
            onSuccess: () => {
                cancelEditing(); // Vuelve a modo crear después de actualizar
                // Notificación de éxito aquí si usas alguna
            },
            onError: (errors) => {
                console.error('Error al actualizar rol:', errors);
                // Inertia llena form.errors automáticamente
            },
        });
    } else {
        // Si editingRole es null, estamos creando
        form.post(route('admin.roles.store'), {
            onSuccess: () => {
                form.reset(); // Limpia solo el formulario después de crear
                form.clearErrors();
                // Notificación de éxito aquí si usas alguna
            },
            onError: (errors) => {
                console.error('Error al crear rol:', errors);
                // Inertia llena form.errors automáticamente
            },
        });
    }
};

// Función para eliminar un rol (usando confirmación nativa)
const deleteRole = (role) => {
    if (confirm(`¿Estás seguro de que deseas eliminar el rol "${role.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('admin.roles.destroy', role.id), {
            onSuccess: () => {
                // Opcional: si el rol eliminado era el que se estaba editando, cancelar la edición
                if(editingRole.value && editingRole.value.id === role.id) {
                    cancelEditing();
                }
                // Notificación de éxito aquí si usas alguna
            },
            onError: (errors) => {
                console.error('Error al eliminar rol:', errors);
                 // Podrías chequear si el error es por Foreign Key Constraint (rol asociado)
                alert('No se pudo eliminar el rol. Asegúrate de que no esté asignado a ningún usuario u otro registro.');
            },
        });
    }
};

</script>

<template>
    <Head :title="formTitle" /> <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight flex items-center gap-2 text-[#485F84]">
                <UserCircleIcon class="w-6 h-6 inline-block text-[#485F84]" />
                {{ formTitle }} </h2>
        </template>

        <div class="py-6 md:py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 md:p-8">

                    <div id="role-form" class="mb-6 p-4 bg-gray-50 rounded-md shadow-inner">
                         <h3 class="text-lg font-semibold mb-4 flex items-center gap-2 text-gray-700">
                             <component :is="formButtonIcon" class="w-5 h-5" /> {{ formTitle }}
                         </h3>
                        <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start"> <div class="col-span-1">
                                <label for="name" class="block text-sm font-medium text-gray-700">Nombre del Rol</label>
                                <input type="text" id="name" v-model="form.name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.name }">
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div class="col-span-1 self-end flex space-x-2">
                                <button type="submit" :disabled="form.processing"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    :class="{ 'opacity-25': form.processing }">
                                    <component :is="formButtonIcon" class="w-4 h-4 mr-2" />
                                    {{ formButtonText }}
                                </button>
                                <button type="button" v-if="editingRole" @click="cancelEditing"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <XMarkIcon class="w-4 h-4 mr-2" /> Cancelar
                                </button>
                             </div>
                        </form>
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
                                        class="relative px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="role in roles" :key="role.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ role.id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ role.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                         <button @click="startEditing(role)"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3 inline-flex items-center"
                                             :disabled="editingRole !== null && editingRole.id === role.id">
                                            <PencilIcon class="w-5 h-5 mr-1" /> Editar
                                        </button>
                                        <button @click="deleteRole(role)"
                                            class="text-red-600 hover:text-red-900 inline-flex items-center">
                                            <TrashIcon class="w-5 h-5 mr-1" /> Eliminar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="roles.length === 0">
                                    <td colspan="3"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No hay roles registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        </AuthenticatedLayout>
</template>