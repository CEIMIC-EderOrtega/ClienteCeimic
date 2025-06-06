<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>

        <Head title="Forgot Password" />

        <div class="mb-4 text-sm text-gray-600">
            ¿Olvidaste tu contraseña? No hay problema, simplemente indícanos tu correo y enviaremos un enlace para restablecer tu contraseña.
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Correo" />

                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autofocus
                    autocomplete="username" />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex flex-col items-end gap-y-4"> <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Enlace para restablecer contraseña
                </PrimaryButton>
                <Link :href="route('login')"
                    class="inline-flex items-center justify-center h-10 px-4 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:text-gray-400 dark:bg-zinc-800 dark:border-zinc-700 dark:hover:bg-zinc-700 dark:focus:ring-offset-zinc-900">
                    Volver al Login
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
