<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Tu layout

// Importa iconos (usando HomeIcon como en el ejemplo de tu layout para Empresas)
import {
    HomeIcon, // Icono para el título de la página (o usa BuildingOfficeIcon si lo tienes)
    PlusCircleIcon, // Icono para agregar nuevo
    PencilIcon, // Icono para editar
    TrashIcon, // Icono para eliminar
    CheckIcon, // Icono para botón Guardar/Actualizar
    XMarkIcon, // Icono para cancelar edición
} from '@heroicons/vue/24/outline'; // O '20/solid' si prefieres sólidos

// Define las props que vienen del controlador (App\Http\Controllers\CompanyController@index)
const props = defineProps({
    companies: Array, // Esperamos un array de empresas
});

// --- Estado para el formulario (sirve para crear y editar) ---
const form = useForm({
    razon_social: '',
    nombre_comercial: '', // Antes nombre_fantasia
    tipo_identificacion: '', // Nuevo
    numero_identificacion: '', // Nuevo
    domicilio_legal: '', // Nuevo
    telefono: '', // Nuevo
    correo_electronico: '', // Nuevo
    sitio_web: '', // Nuevo
    // otros_datos eliminado
});

// Estado para saber qué empresa se está editando (null si estamos creando)
const editingCompany = ref(null);

// --- Propiedades computadas para la UI dinámica ---

// Título del formulario (Agregar vs Editar)
const formTitle = computed(() => {
    return editingCompany.value ? 'Editar Empresa' : 'Agregar Nueva Empresa';
});

// Texto del botón principal del formulario (Agregar vs Actualizar)
const formButtonText = computed(() => {
    if (form.processing) {
        return editingCompany.value ? 'Actualizando...' : 'Guardando...';
    }
    return editingCompany.value ? 'Actualizar Empresa' : 'Agregar Empresa';
});

// Icono del botón principal del formulario
const formButtonIcon = computed(() => {
     return editingCompany.value ? CheckIcon : PlusCircleIcon;
});


// --- Funciones para Acciones ---

// Prepara el formulario para crear una nueva empresa
const prepareCreate = () => {
    editingCompany.value = null;
    form.reset();
    form.clearErrors();
};

// Inicia el modo de edición: carga los datos de la empresa en el formulario
const startEditing = (company) => {
    editingCompany.value = company; // Guarda el objeto empresa completo
    form.razon_social = company.razon_social;
    form.nombre_comercial = company.nombre_comercial;
    form.tipo_identificacion = company.tipo_identificacion;
    form.numero_identificacion = company.numero_identificacion;
    form.domicilio_legal = company.domicilio_legal;
    form.telefono = company.telefono;
    form.correo_electronico = company.correo_electronico;
    form.sitio_web = company.sitio_web;
    form.clearErrors(); // Limpia errores si los hubo de una edición anterior

    // Opcional: Hacer scroll suave hacia el formulario
    // Asegúrate de que el div del formulario tenga id="company-form"
    document.getElementById('company-form').scrollIntoView({ behavior: 'smooth' });
};

// Cancela el modo de edición y limpia el formulario
const cancelEditing = () => {
    prepareCreate(); // Vuelve al estado de creación
};

// Envía el formulario (crear o editar)
const submitForm = () => {
    if (editingCompany.value) {
        // Si editingCompany tiene un valor, estamos editando
        form.put(route('admin.companies.update', editingCompany.value.id), {
            onSuccess: () => {
                cancelEditing(); // Vuelve a modo crear después de actualizar
                // Notificación de éxito aquí
            },
            onError: (errors) => {
                console.error('Error al actualizar empresa:', errors);
                // Inertia llena form.errors automáticamente
            },
        });
    } else {
        // Si editingCompany es null, estamos creando
        form.post(route('admin.companies.store'), {
            onSuccess: () => {
                form.reset(); // Limpia solo el formulario después de crear
                form.clearErrors();
                // Notificación de éxito aquí
            },
            onError: (errors) => {
                console.error('Error al crear empresa:', errors);
                // Inertia llena form.errors automáticamente
            },
        });
    }
};

// Función para eliminar una empresa (usando confirmación nativa)
const deleteCompany = (company) => {
    if (confirm(`¿Estás seguro de que deseas eliminar la empresa "${company.razon_social}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('admin.companies.destroy', company.id), {
            onSuccess: () => {
                // Opcional: si la empresa eliminada era la que se estaba editando, cancelar la edición
                if(editingCompany.value && editingCompany.value.id === company.id) {
                    cancelEditing();
                }
                // Notificación de éxito aquí
            },
            onError: (errors) => {
                console.error('Error al eliminar empresa:', errors);
                // Manejar errores (ej. si la empresa tiene relaciones y no se puede eliminar)
                 // Esto se maneja mejor con un flash message del backend como en el controlador actualizado
                 alert('No se pudo eliminar la empresa. Consulta los logs o verifica dependencias.'); // Fallback simple
            },
        });
    }
};

</script>

<template>
    <Head :title="formTitle" /> <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight flex items-center gap-2 text-[#485F84]">
                <HomeIcon class="w-6 h-6 inline-block text-[#485F84]" /> {{ formTitle }} </h2>
        </template>

        <div class="py-6 md:py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 md:p-8">

                    <div id="company-form" class="mb-6 p-4 bg-gray-50 rounded-md shadow-inner">
                         <h3 class="text-lg font-semibold mb-4 flex items-center gap-2 text-gray-700">
                             <component :is="formButtonIcon" class="w-5 h-5" /> {{ formTitle }}
                         </h3>
                        <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                            <div class="col-span-1 md:col-span-2"> <label for="razon_social" class="block text-sm font-medium text-gray-700">Razón Social</label>
                                <input type="text" id="razon_social" v-model="form.razon_social"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.razon_social }">
                                <p v-if="form.errors.razon_social" class="mt-1 text-sm text-red-600">{{ form.errors.razon_social }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="nombre_comercial" class="block text-sm font-medium text-gray-700">Nombre Comercial</label>
                                <input type="text" id="nombre_comercial" v-model="form.nombre_comercial"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.nombre_comercial }">
                                <p v-if="form.errors.nombre_comercial" class="mt-1 text-sm text-red-600">{{ form.errors.nombre_comercial }}</p>
                            </div>

                            <div class="col-span-1">
                                 <label for="tipo_identificacion" class="block text-sm font-medium text-gray-700">Tipo Identificación</label>
                                 <select id="tipo_identificacion" v-model="form.tipo_identificacion"
                                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                     :class="{ 'border-red-500': form.errors.tipo_identificacion }">
                                     <option value="">Seleccione...</option>
                                     <option value="RUC">Perú: RUC</option>
                                     <option value="EIN">EE. UU.: EIN</option>
                                     <option value="NIT">Colombia: NIT</option>
                                     <option value="VAT">UE: VAT</option>
                                      <option value="Otro">Otro</option>
                                 </select>
                                <p v-if="form.errors.tipo_identificacion" class="mt-1 text-sm text-red-600">{{ form.errors.tipo_identificacion }}</p>
                            </div>

                             <div class="col-span-1">
                                <label for="numero_identificacion" class="block text-sm font-medium text-gray-700">Número Identificación</label>
                                <input type="text" id="numero_identificacion" v-model="form.numero_identificacion"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.numero_identificacion }">
                                <p v-if="form.errors.numero_identificacion" class="mt-1 text-sm text-red-600">{{ form.errors.numero_identificacion }}</p>
                            </div>

                            <div class="col-span-1 md:col-span-2"> <label for="domicilio_legal" class="block text-sm font-medium text-gray-700">Domicilio Legal Completo</label>
                                <textarea id="domicilio_legal" v-model="form.domicilio_legal" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.domicilio_legal }"></textarea>
                                <p v-if="form.errors.domicilio_legal" class="mt-1 text-sm text-red-600">{{ form.errors.domicilio_legal }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono Corporativo</label>
                                <input type="text" id="telefono" v-model="form.telefono"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.telefono }">
                                <p v-if="form.errors.telefono" class="mt-1 text-sm text-red-600">{{ form.errors.telefono }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="correo_electronico" class="block text-sm font-medium text-gray-700">Correo Electrónico Corporativo</label>
                                <input type="email" id="correo_electronico" v-model="form.correo_electronico"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.correo_electronico }">
                                <p v-if="form.errors.correo_electronico" class="mt-1 text-sm text-red-600">{{ form.errors.correo_electronico }}</p>
                            </div>

                            <div class="col-span-1">
                                <label for="sitio_web" class="block text-sm font-medium text-gray-700">Sitio Web</label>
                                <input type="url" id="sitio_web" v-model="form.sitio_web"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.sitio_web }">
                                <p v-if="form.errors.sitio_web" class="mt-1 text-sm text-red-600">{{ form.errors.sitio_web }}</p>
                            </div>


                             <div class="col-span-1 md:col-span-2 self-end flex space-x-2"> <button type="submit" :disabled="form.processing"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    :class="{ 'opacity-25': form.processing }">
                                    <component :is="formButtonIcon" class="w-4 h-4 mr-2" />
                                    {{ formButtonText }}
                                </button>
                                <button type="button" v-if="editingCompany" @click="cancelEditing"
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
                                        Razón Social
                                    </th>
                                     <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nombre Comercial
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Identificación
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contacto
                                    </th>
                                    <th scope="col" class="relative px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="company in companies" :key="company.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ company.id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ company.razon_social }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ company.nombre_comercial ?? 'N/A' }}
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ company.tipo_identificacion ? `${company.tipo_identificacion}: ${company.numero_identificacion ?? ''}` : (company.numero_identificacion ?? 'N/A') }}
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                         {{ company.telefono ?? 'N/A' }} <br>
                                         {{ company.correo_electronico ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                         <button @click="startEditing(company)"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3 inline-flex items-center"
                                             :disabled="editingCompany !== null && editingCompany.id === company.id">
                                            <PencilIcon class="w-5 h-5 mr-1" /> Editar
                                        </button>
                                        <button @click="deleteCompany(company)"
                                            class="text-red-600 hover:text-red-900 inline-flex items-center">
                                            <TrashIcon class="w-5 h-5 mr-1" /> Eliminar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="companies.length === 0">
                                    <td colspan="6"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No hay empresas registradas.
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