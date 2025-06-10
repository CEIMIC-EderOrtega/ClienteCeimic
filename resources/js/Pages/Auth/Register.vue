<script setup>
import { ref, nextTick } from 'vue'; // Importamos nextTick para el scroll
// Asegúrate de que este import GuestLayout esté correcto
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue'; // Componente TextInput
import { Head, Link, useForm } from '@inertiajs/vue3';
// Importa tu componente de logo (asumo que es ApplicationLogo)
import LogoCeimic from "@/assets/images/logo_ceimic.png";

// Importa iconos
import {
    EnvelopeIcon, // Icono para Email
    LockClosedIcon, // Icono para Password
    CheckIcon, // Icono para Validar (o checkmark)
} from '@heroicons/vue/24/outline'; // O '20/solid' si prefieres sólidos.

// Definimos las props que esperamos del controlador (RegisteredUserController@create)
const props = defineProps({
    canLogin: {
        type: Boolean,
        default: false,
    },
    countries: { // Lista de países incluyendo id, name, y code (CDPAIS)
        type: Array,
        required: true,
    },
});

// --- Estado para controlar el flujo y mostrar campos ---
// ref para saber si el email ya pasó la validación inicial del paso 1 Y el resultado fue 'ok'
const emailValidatedStep1 = ref(false);
// ref para guardar el resultado completo de la validación del email ('ok', 'no', 'ni', 'error')
const validationResult = ref(null); // { status: 'ok'|'no'|'ni'|'error', message?: string, country_code?: string }

// --- Estado para el formulario (incluye todos los campos) ---
const form = useForm({
    email: '',
    name: '',
    password: '',
    password_confirmation: '',
    direccion: '', // Opcional
    telefono: '', // Opcional
    country_id: '', // Enlaza con el select de País
    // company_id NO se incluye
});

// --- Función para validar el email (Paso 1) ---
const validateEmail = () => {
    form.clearErrors();
    emailValidatedStep1.value = false;
    validationResult.value = null;
    form.country_id = ''; // Limpiar selección de país anterior por si acaso

    if (!form.email) {
        form.setError('email', 'El correo electrónico es obligatorio.');
        return;
    }

    // Llamada axios a la ruta de validación en web.php
    axios.post(route('register.checkEmail'), { email: form.email })
        .then(response => {
            validationResult.value = response.data;


            if (response.data.status === 'ok') {
                emailValidatedStep1.value = true;

                // *** Lógica para preseleccionar el país ***
                if (response.data.country_code) {
                    // Buscar el país local que coincida con el country_code (CDPAIS) recibido
                    const country = props.countries.find(c => c.code === String(response.data.country_code).trim()); // Aseguramos string y trim

                    if (country) {
                        form.country_id = country.id; // Preseleccionar el ID del país encontrado
                        console.log('País preseleccionado:', country.name); // Log para depuración
                    } else {
                        console.warn('Código de país externo no encontrado en lista local:', response.data.country_code);

                    }
                } else {
                    console.warn('Validación OK, pero no se recibió country_code.');
                    form.country_id = '';
                }



            } else {
                // Si el status NO es 'ok' (es 'ni', 'no', o 'error'), NO AVANZAMOS.
                emailValidatedStep1.value = false;
                form.country_id = ''; // Asegurar que el select de país esté vacío si no se avanza
            }
        })
        .catch(error => {
            // Manejar errores de la llamada (validación de formato de email en backend, red, error 500, etc.)
            emailValidatedStep1.value = false;
            validationResult.value = null;
            form.country_id = ''; // Asegurar que el select de país esté vacío en caso de error

            if (error.response && error.response.data.errors && error.response.data.errors.email) {
                form.errors.email = error.response.data.errors.email[0];
            } else if (error.response && error.response.data.message) {
                validationResult.value = { status: 'error', message: error.response.data.message };
            } else {
                form.errors.email = 'Ocurrió un error al validar el correo. Por favor, intenta de nuevo.';
                console.error('Error al validar email:', error);
            }
        });
};

// --- Función para enviar el formulario COMPLETO (Paso 2) ---
const submitRegistration = () => {
    // Solo enviar si el email ya fue validado exitosamente en el paso 1 (status 'ok')
    if (!emailValidatedStep1.value || validationResult.value?.status !== 'ok') {
        alert("Error interno: El estado de validación del correo no es correcto para registrarse.");
        return;
    }


    // Usamos form.post de Inertia para enviar todos los datos a la ruta de registro (POST /register)
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
        onSuccess: () => {
            // Redirección manejada por el backend
        },
        onError: (errors) => {
            console.error('Errores de validación en el registro:', errors);
            // Inertia llena form.errors automáticamente. Los mensajes aparecerán bajo los campos.
            // Si un error de email aparece aquí (ej: re-validación en backend falla), puedes volver al paso 1 si quieres:
            // if (errors.email) { emailValidatedStep1.value = false; validationResult.value = null; }
        }
    });
};

// --- Función única para el submit del formulario ---
const handleMainSubmit = () => {
    // Si ya se validó el email Y el resultado fue 'ok', enviar registro completo (Paso 2)
    if (emailValidatedStep1.value && validationResult.value?.status === 'ok') {
        submitRegistration();
    } else {
        // Si no se ha validado el email, o el resultado no fue 'ok', validar email (Paso 1)
        validateEmail();
    }
};

</script>

<template>
    <GuestLayout>

        <Head title="Registro de Usuario" />

        <div class="flex justify-center mb-6">

            <img :src="LogoCeimic" alt="Logo CEIMIC" class="block h-14 w-auto object-contain">
        </div>
        <h1 class="text-2xl font-bold text-center mb-6 text-gray-800 dark:text-gray-200">Registro de Usuario</h1>


        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="handleMainSubmit">

            <div>
                <InputLabel for="email" value="Correo Electrónico" />
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 flex items-center ps-3.5 pointer-events-none">
                        <EnvelopeIcon class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    </div>
                    <TextInput id="email" type="email" class="block w-full ps-10" v-model="form.email" required
                        autocomplete="username"
                        :disabled="form.processing || (emailValidatedStep1 && validationResult?.status === 'ok')" />
                </div>
                <InputError class="mt-2" :message="form.errors.email" />

                <p v-if="!form.errors.email && validationResult?.status && validationResult?.status !== 'ok'"
                    class="mt-2 text-sm text-red-600">
                    <span v-if="validationResult.status === 'ni'">
                        Este correo electrónico pertenece a un usuario interno y no puede registrarse a través de este
                        formulario.
                    </span>
                    <span v-else-if="validationResult.status === 'no'">
                        Este correo electrónico no fue encontrado en nuestro sistema externo. No se puede registrar.
                    </span>
                    <span v-else-if="validationResult.status === 'error'">
                        {{ validationResult.message || 'Ocurrió un error al validar el correo. Intenta de nuevo.' }}
                    </span>
                </p>
            </div>


            <div class="mt-4">
                <PrimaryButton v-if="!emailValidatedStep1 || validationResult?.status !== 'ok'"
                    :disabled="form.processing" class="w-full justify-center">
                    <span class="flex items-center justify-center">
                        <CheckIcon v-if="form.processing" class="animate-spin h-5 w-5 mr-2 text-white" />
                        <CheckIcon v-else class="h-5 w-5 mr-2 text-white" />
                        Validar Correo
                    </span>
                </PrimaryButton>




                <div v-else id="step2-fields">
                    <div class="mt-4">
                        <InputLabel for="name" value="Nombre Completo" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required
                            autocomplete="name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" value="Contraseña" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center ps-3.5 pointer-events-none">
                                <LockClosedIcon class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                            </div>
                            <TextInput id="password" type="password" class="block w-full ps-10" v-model="form.password"
                                required autocomplete="new-password" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password_confirmation" value="Confirmar Contraseña" />
                        <TextInput id="password_confirmation" type="password" class="mt-1 block w-full"
                            v-model="form.password_confirmation" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="direccion" value="Dirección (Opcional)" />
                        <TextInput id="direccion" type="text" class="mt-1 block w-full" v-model="form.direccion"
                            autocomplete="street-address" />
                        <InputError class="mt-2" :message="form.errors.direccion" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="telefono" value="Teléfono (Opcional)" />
                        <TextInput id="telefono" type="text" class="mt-1 block w-full" v-model="form.telefono"
                            autocomplete="tel" />
                        <InputError class="mt-2" :message="form.errors.telefono" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="country_id" value="País" />
                        <select id="country_id" v-model="form.country_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            :class="{ 'border-red-500': form.errors.country_id }">
                            <option value="" disabled>Seleccione un país</option>
                            <option v-for="country in countries" :key="country.id" :value="country.id">
                                {{ country.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.country_id" />
                        <p v-if="validationResult?.status === 'ok' && validationResult?.country_code"
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <!--Código externo encontrado: {{ validationResult.country_code }}-->
                        </p>
                    </div>

                    <div class="mt-6 flex items-center justify-end">




                    </div>
                    <div class="mt-4">
                        <PrimaryButton class="inline-flex items-center justify-center w-full "
                            :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Registrar
                        </PrimaryButton>

                    </div>
                </div>
                <div class="mt-4">
                    <Link :href="route('login')"
                        class="inline-flex items-center justify-center w-full h-10 px-4 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:text-gray-400 dark:bg-zinc-800 dark:border-zinc-700 dark:hover:bg-zinc-700 dark:focus:ring-offset-zinc-900">
                    Volver al Login
                    </Link>
                </div>
            </div>


        </form>
    </GuestLayout>
</template>
