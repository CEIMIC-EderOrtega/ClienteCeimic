<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Tu layout

// Importa iconos
import {
    UserIcon, // Icono principal para usuarios
    PlusCircleIcon, // Icono agregar
    PencilIcon, // Icono editar
    TrashIcon, // Icono eliminar
    CheckIcon, // Icono guardar
    XMarkIcon, // Icono cancelar
} from '@heroicons/vue/24/outline'; // O '20/solid' si prefieres sólidos

// Define las props que vienen del controlador (App\Http\Controllers\UserController@index)
const props = defineProps({
    users: Object, // Esperamos un objeto paginado de usuarios (Inertia's pagination)
    roles: Array, // Lista de todos los roles
    countries: Array, // Lista de todos los países
    companies: Array, // Lista de todas las empresas
});

// --- Estado para el formulario (sirve para crear y editar) ---
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '', // Necesario para la validación 'confirmed'
    direccion: '',
    telefono: '',
    country_id: '', // Usar string vacío para select si es nullable
    company_id: '', // Usar string vacío para select si es nullable
    roles: [], // Array para guardar los IDs de los roles seleccionados (checkboxes)
});

// Estado para saber qué usuario se está editando (null si estamos creando)
const editingUser = ref(null);

// --- Propiedades computadas para la UI dinámica ---

// Título del formulario (Agregar vs Editar)
const formTitle = computed(() => {
    return editingUser.value ? 'Editar Usuario' : 'Agregar Nuevo Usuario';
});

// Texto del botón principal del formulario (Agregar vs Actualizar)
const formButtonText = computed(() => {
    if (form.processing) {
        return editingUser.value ? 'Actualizando...' : 'Guardando...';
    }
    return editingUser.value ? 'Actualizar Usuario' : 'Agregar Usuario';
});

// Icono del botón principal del formulario
const formButtonIcon = computed(() => {
     return editingUser.value ? CheckIcon : PlusCircleIcon;
});


// --- Funciones para Acciones ---

// Prepara el formulario para crear un nuevo usuario
const prepareCreate = () => {
    editingUser.value = null;
    form.reset(); // Limpia todos los campos del formulario
    form.clearErrors(); // Limpia errores
    form.roles = []; // Asegura que el array de roles esté vacío
};

// Inicia el modo de edición: carga los datos del usuario en el formulario
const startEditing = (user) => {
    editingUser.value = user; // Guarda el objeto usuario completo
    form.name = user.name;
    form.email = user.email;
    // No cargamos la contraseña, ya que no la editamos directamente
    form.password = '';
    form.password_confirmation = '';
    form.direccion = user.direccion;
    form.telefono = user.telefono;
    form.country_id = user.country_id ?? ''; // Usar '' si es null para el select
    form.company_id = user.company_id ?? ''; // Usar '' si es null para el select
    // Cargar los IDs de los roles asociados al usuario en el array del formulario
    form.roles = user.roles.map(role => role.id);

    form.clearErrors(); // Limpia errores si los hubo de una edición anterior

    // Opcional: Hacer scroll suave hacia el formulario
    // Asegúrate de que el div del formulario tenga id="user-form"
    document.getElementById('user-form').scrollIntoView({ behavior: 'smooth' });
};

// Cancela el modo de edición y limpia el formulario
const cancelEditing = () => {
    prepareCreate(); // Vuelve al estado de creación
};

// Envía el formulario (crear o editar)
const submitForm = () => {
    if (editingUser.value) {
        // Si editingUser tiene un valor, estamos editando
        // Usa post para manejar la data, Inertia lo convierte a PUT si usas form.put
        form.put(route('admin.users.update', editingUser.value.id), {
            onSuccess: () => {
                cancelEditing(); // Vuelve a modo crear después de actualizar
                // Notificación de éxito aquí
            },
            onError: (errors) => {
                console.error('Error al actualizar usuario:', errors);
                // Inertia llena form.errors automáticamente
            },
             // PreserveState: 'errors', // Opcional: mantener errores en la UI
             // PreserveScroll: true, // Opcional: mantener posición del scroll
        });
    } else {
        // Si editingUser es null, estamos creando
         // Usa post para enviar el formulario de creación
        form.post(route('admin.users.store'), {
            onSuccess: () => {
                form.reset(); // Limpia solo los campos del formulario después de crear
                form.clearErrors();
                form.roles = []; // Asegura que los checkboxes se desmarquen
                // Notificación de éxito aquí
            },
            onError: (errors) => {
                console.error('Error al crear usuario:', errors);
                // Inertia llena form.errors automáticamente
            },
            // PreserveState: 'errors', // Opcional
            // PreserveScroll: true, // Opcional
        });
    }
};

// Función para eliminar un usuario (usando confirmación nativa)
const deleteUser = (user) => {
    if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${user.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('admin.users.destroy', user.id), {
            onSuccess: () => {
                // Opcional: si el usuario eliminado era el que se estaba editando, cancelar la edición
                if(editingUser.value && editingUser.value.id === user.id) {
                    cancelEditing();
                }
                // Notificación de éxito aquí
            },
            onError: (errors) => {
                console.error('Error al eliminar usuario:', errors);
                 alert('Ocurrió un error al eliminar el usuario.'); // Fallback simple
            },
        });
    }
};

// Función auxiliar para paginación (si usas paginación en el controlador)
const visitUrl = (url) => {
    router.visit(url, { preserveState: true }); // Mantener el estado actual (filtros, etc.)
};


</script>

<template>
    <Head :title="formTitle" /> <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight flex items-center gap-2 text-[#485F84]">
                <UserIcon class="w-6 h-6 inline-block text-[#485F84]" /> {{ formTitle }} </h2>
        </template>

        <div class="py-6 md:py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 md:p-8">

                    <div id="user-form" class="mb-6 p-4 bg-gray-50 rounded-md shadow-inner">
                         <h3 class="text-lg font-semibold mb-4 flex items-center gap-2 text-gray-700">
                             <component :is="formButtonIcon" class="w-5 h-5" /> {{ formTitle }}
                         </h3>
                        <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">

                            <div class="col-span-1">
                                <label for="name" class="block text-sm font-medium text-gray-700">Nombre Completo</label>
                                <input type="text" id="name" v-model="form.name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.name }">
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                                <input type="email" id="email" v-model="form.email"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.email }">
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña <span v-if="editingUser" class="text-gray-500 text-xs">(Dejar vacío para no cambiar)</span></label>
                                <input type="password" id="password" v-model="form.password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.password }">
                                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar Contraseña</label>
                                <input type="password" id="password_confirmation" v-model="form.password_confirmation"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.password_confirmation }">
                                <p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ form.errors.password_confirmation }}</p>
                            </div>

                             <div class="col-span-1">
                                <label for="direccion" class="block text-sm font-medium text-gray-700">Dirección</label>
                                <input type="text" id="direccion" v-model="form.direccion"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.direccion }">
                                <p v-if="form.errors.direccion" class="mt-1 text-sm text-red-600">{{ form.errors.direccion }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                <input type="text" id="telefono" v-model="form.telefono"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.telefono }">
                                <p v-if="form.errors.telefono" class="mt-1 text-sm text-red-600">{{ form.errors.telefono }}</p>
                            </div>

                             <div class="col-span-1">
                                 <label for="country_id" class="block text-sm font-medium text-gray-700">País</label>
                                 <select id="country_id" v-model="form.country_id"
                                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                     :class="{ 'border-red-500': form.errors.country_id }">
                                     <option value="">Seleccione un país</option>
                                     <option v-for="country in countries" :key="country.id" :value="country.id">
                                         {{ country.name }}
                                     </option>
                                 </select>
                                <p v-if="form.errors.country_id" class="mt-1 text-sm text-red-600">{{ form.errors.country_id }}</p>
                            </div>

                            <div class="col-span-1">
                                 <label for="company_id" class="block text-sm font-medium text-gray-700">Empresa</label>
                                 <select id="company_id" v-model="form.company_id"
                                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                     :class="{ 'border-red-500': form.errors.company_id }">
                                     <option value="">Sin empresa</option> <option v-for="company in companies" :key="company.id" :value="company.id">
                                         {{ company.razon_social }}
                                     </option>
                                 </select>
                                 <p class="mt-1 text-sm text-gray-500">Asigna una empresa si no es administrador.</p>
                                <p v-if="form.errors.company_id" class="mt-1 text-sm text-red-600">{{ form.errors.company_id }}</p>
                            </div>

                            <div class="col-span-1 md:col-span-2">
                                 <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
                                 <div class="mt-1 space-y-2">
                                     <div v-for="role in roles" :key="role.id" class="flex items-center">
                                         <input type="checkbox" :id="`role-${role.id}`" :value="role.id" v-model="form.roles"
                                             class="h-4 w-4 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                         <label :for="`role-${role.id}`" class="ml-2 block text-sm text-gray-900">
                                             {{ role.name }}
                                         </label>
                                     </div>
                                 </div>
                                <p v-if="form.errors.roles" class="mt-1 text-sm text-red-600">{{ form.errors.roles }}</p>
                                <p v-if="form.errors['roles.*']" class="mt-1 text-sm text-red-600">Error en la selección de roles.</p>
                            </div>


                             <div class="col-span-1 md:col-span-2 self-end flex space-x-2"> <button type="submit" :disabled="form.processing"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    :class="{ 'opacity-25': form.processing }">
                                    <component :is="formButtonIcon" class="w-4 h-4 mr-2" />
                                    {{ formButtonText }}
                                </button>
                                <button type="button" v-if="editingUser" @click="cancelEditing"
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID
                                    </th>
                                     <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nombre
                                    </th>
                                     <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contacto
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        País
                                    </th>
                                     <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Empresa
                                    </th>
                                     <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Roles
                                    </th>
                                    <th scope="col" class="relative px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="user in users.data" :key="user.id"> <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ user.id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ user.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ user.email }}
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                         <span v-if="user.telefono">{{ user.telefono }}</span> <br>
                                         <span v-if="user.direccion">{{ user.direccion }}</span>
                                         <span v-if="!user.telefono && !user.direccion">N/A</span>
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ user.country?.name ?? 'N/A' }}
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ user.company?.razon_social ?? 'N/A' }}
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span v-if="user.roles.length > 0" class="flex flex-wrap gap-1">
                                             <span v-for="role in user.roles" :key="role.id" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                 {{ role.name }}
                                             </span>
                                        </span>
                                         <span v-else>Sin roles</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                         <button @click="startEditing(user)"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3 inline-flex items-center"
                                             :disabled="editingUser !== null && editingUser.id === user.id">
                                            <PencilIcon class="w-5 h-5 mr-1" /> Editar
                                        </button>
                                        <button @click="deleteUser(user)"
                                            class="text-red-600 hover:text-red-900 inline-flex items-center">
                                            <TrashIcon class="w-5 h-5 mr-1" /> Eliminar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="8"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.links.length > 3" class="flex justify-center mt-4">
                         <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                             <template v-for="(link, key) in users.links" :key="key">
                                 <div v-if="link.url === null"
                                      class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default leading-5 rounded-md">
                                     <span v-html="link.label"></span>
                                 </div>
                                  <Link v-else
                                       :href="link.url"
                                       @click.prevent="visitUrl(link.url)"
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium leading-5 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring ring-gray-300 transition ease-in-out duration-150"
                                       :class="{ 'bg-blue-50 text-blue-700': link.active, 'bg-white': ! link.active }"
                                       aria-current="page">
                                      <span v-html="link.label"></span>
                                  </Link>
                             </template>
                         </nav>
                     </div>


                </div>
            </div>
        </div>

        </AuthenticatedLayout>
</template>