<script setup>
// Importa tu logo desde la ubicación donde lo guardaste
// Asegúrate de que la ruta sea correcta (asumimos que está en resources/js/assets/images)
import LogoCeimic from "@/assets/images/logo_ceimic_white.png";
</script>

<template>
    <img :src="LogoCeimic" alt="Logo CEIMIC" class="block h-14 w-auto object-contain">
</template>
