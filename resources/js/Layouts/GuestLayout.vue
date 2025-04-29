<script setup>
// Importa la imagen de fondo
import FondoCeimic from "@/assets/images/FondoCeimic_1.png";
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-cover bg-center"
        :style="{ backgroundImage: 'url(' + FondoCeimic + ')' }">

        <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-lg overflow-hidden sm:rounded-lg"
            style="backdrop-filter: blur(5px); background-color: rgba(255, 255, 255, 0.95);"> <slot />
        </div>
    </div>
</template>
