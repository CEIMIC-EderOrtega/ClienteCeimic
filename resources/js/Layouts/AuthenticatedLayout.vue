<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue'; // Dropdown para escritorio
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue'; // NavLink para escritorio
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'; // ResponsiveNavLink para móvil
import { Link } from '@inertiajs/vue3';

import {
    HomeIcon,
    
    ChevronDownIcon,
    ChevronRightIcon, // Importa el icono de flecha derecha para indicar que se puede expandir
    UserCircleIcon, // Importa el icono de usuario/imagen de perfil
} from '@heroicons/vue/24/outline'; // O @heroicons/vue/20/solid si prefieres sólidos

const showingNavigationDropdown = ref(false); // Menú hamburguesa principal
const showingResponsiveProfileDropdown = ref(false); // Estado para mostrar/ocultar enlaces de perfil en responsive

const toggleResponsiveProfileDropdown = () => {
    showingResponsiveProfileDropdown.value = !showingResponsiveProfileDropdown.value;
};

</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-[#485F84]">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="h-12 w-auto text-white" />
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')" :class="{
                                    'text-white': true,
                                    'hover:bg-[#3a4c6b] focus:bg-[#3a4c6b]': true,
                                    'border-b-2 border-white': route().current('dashboard'),
                                    'border-transparent': !route().current('dashboard')
                                }">
                                    <HomeIcon class="h-5 w-5 mr-1 text-white" /> Muestras
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center px-3 py-2 rounded-md font-medium text-white hover:bg-[#3a4c6b] transform hover:scale-105 transition duration-150 ease-in-out"
                                            >
                                                <UserCircleIcon class="h-6 w-6 mr-1" />
                                                {{ $page.props.auth.user.name }}
                                                <ChevronDownIcon
                                                    class="ms-2 -me-0.5 h-4 w-4 transition duration-150 ease-in-out"
                                                />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-white transition duration-150 ease-in-out hover:bg-[#3a4c6b] focus:bg-[#3a4c6b] focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden bg-[#485F84] py-2">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" :class="{
                            'flex items-center px-4 py-2 text-white': true,
                            'hover:bg-[#3a4c6b] focus:bg-[#3a4c6b]': true,
                             // Cambiado para usar fondo en lugar de borde lateral blanco cuando está activo
                            'bg-[#3a4c6b]': route().current('dashboard'),
                            'border-transparent': !route().current('dashboard') // Mantén el borde transparente si no está activo
                        }">
                            <HomeIcon class="h-5 w-5 mr-1 text-white" /> Muestras
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4 flex items-center justify-between cursor-pointer" @click="toggleResponsiveProfileDropdown">
                             <div>
                                <div class="text-base font-medium text-white">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="text-sm font-medium text-gray-300">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                            <ChevronDownIcon
                                :class="{'rotate-180': showingResponsiveProfileDropdown, 'rotate-0': !showingResponsiveProfileDropdown}"
                                class="ms-2 -me-0.5 h-4 w-4 transition duration-150 ease-in-out text-white"
                            />
                        </div>

                        <div class="mt-3 space-y-1" v-if="showingResponsiveProfileDropdown">
                            <ResponsiveNavLink :href="route('profile.edit')"
                                class="px-4 py-2 text-white hover:bg-[#3a4c6b] focus:bg-[#3a4c6b]">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button"
                                class="px-4 py-2 text-white hover:bg-[#3a4c6b] focus:bg-[#3a4c6b]">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>