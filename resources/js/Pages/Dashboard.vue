<script setup>
// Dashboard.vue
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { defineProps } from 'vue';

import MuestrasTable from '@/Components/MuestrasTable.vue'; // <-- Añadido .vue

const props = defineProps({
    registros: {
        type: Array,
        default: () => []
    }
});
</script>

<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <!--  <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Amostras / Muestras
      </h2>
    </template>-->

        <div class="py-12">

            <div class="py-8 mx-auto max-w-[85vw] sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-4 text-gray-900">
                        <MuestrasTable :items="registros" :initial-per-page="20" :rows="20" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>