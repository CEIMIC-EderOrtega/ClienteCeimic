<script setup>
import Checkbox from '@/Components/Checkbox.vue';
// Asegúrate de que este import esté correcto para tu estructura de carpetas
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
// Necesitamos el componente TextInput original
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
// Importa el componente ApplicationLogo (asumo que es tu logo CEIMIC)
import LogoCeimic from "@/assets/images/logo_ceimic.png";

// Importa los iconos que quieras usar
// Usaremos EnvelopeIcon para email y LockClosedIcon para password como ejemplo.
// Puedes importar otros de tu lista si encuentras dónde aplicarlos lógicamente.
import {
    EnvelopeIcon, // Icono para Email
    LockClosedIcon, // Icono para Password
    // Importa otros iconos si los necesitas aquí:
    // HomeIcon,
    // PlusCircleIcon,
    // PencilIcon,
    // TrashIcon,
    // CheckIcon,
    // XMarkIcon,
} from '@heroicons/vue/24/outline'; // O '20/solid' si prefieres sólidos. Asegúrate de usar el tamaño correcto.


defineProps({
    canResetPassword: { type: Boolean, default: false },
    // La prop canRegister ya no se usa para mostrar/ocultar el link
    canRegister: { type: Boolean, default: false },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>

        <Head title="Iniciar Session" />

        <div class="flex justify-center mb-6">

            <img :src="LogoCeimic" alt="Logo CEIMIC" class="block h-14 w-auto object-contain">
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 flex items-center ps-3.5 pointer-events-none">
                        <EnvelopeIcon class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    </div>
                    <TextInput id="email" type="email" class="block w-full ps-10" v-model="form.email" required
                        autofocus autocomplete="username" />
                </div>
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 flex items-center ps-3.5 pointer-events-none">
                        <LockClosedIcon class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    </div>
                    <TextInput id="password" type="password" class="block w-full ps-10" v-model="form.password" required
                        autocomplete="current-password" />
                </div>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Recordarme</span>
                </label>
            </div>

            <div class="mt-6 flex flex-col items-center gap-4">

                <PrimaryButton class="w-full justify-center" :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing">
                    Iniciar Session
                </PrimaryButton>

                <Link :href="route('register')"
                    class="w-full flex justify-center rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                Registrar
                </Link>

                <Link v-if="canResetPassword" :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-zinc-900">
                Olvidaste tu clave?
                </Link>

            </div>
        </form>
    </GuestLayout>
</template>
