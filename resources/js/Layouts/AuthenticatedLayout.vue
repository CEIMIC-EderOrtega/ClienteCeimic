<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

import {
    HomeIcon,
    ChevronDownIcon,
    UserIcon,
    ChevronRightIcon,
    UserCircleIcon,
    MagnifyingGlassIcon,
    GlobeAmericasIcon,
    FingerPrintIcon,
    BuildingOffice2Icon,
} from '@heroicons/vue/24/outline';

const showingNavigationDropdown = ref(false);
const showingResponsiveProfileDropdown = ref(false);

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
                                        'hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': route().current('dashboard'),
                                        'border-transparent': !route().current('dashboard')
                                    }">
                                    <MagnifyingGlassIcon class="h-5 w-5 mr-1 text-white" /> Muestras
                                </NavLink>

                                <NavLink :href="route('admin.countries.index')"
                                    :active="route().current('admin.countries.index')" :class="{
                                        'text-white': true,
                                        'hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': route().current('admin.countries.index'),
                                        'border-transparent': !route().current('admin.countries.index')
                                    }">
                                    <GlobeAmericasIcon class="h-5 w-5 mr-1 text-white" /> Paises
                                </NavLink>

                                <NavLink :href="route('admin.roles.index')" :active="route().current('admin.roles.*')"
                                    :class="{
                                        'text-white': true,
                                        'hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': route().current('admin.roles.*'),
                                        'border-transparent': !route().current('admin.roles.*')
                                    }">
                                    <FingerPrintIcon class="h-5 w-5 mr-1 text-white" /> Roles
                                </NavLink>

                                <NavLink :href="route('admin.companies.index')"
                                    :active="route().current('admin.companies.*')" :class="{
                                        'text-white': true,
                                        'hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': route().current('admin.companies.*'),
                                        'border-transparent': !route().current('admin.companies.*')
                                    }">
                                    <BuildingOffice2Icon class="h-5 w-5 mr-1 text-white" /> Empresas
                                </NavLink>

                                <NavLink :href="route('admin.users.index')" :active="route().current('admin.users.*')"
                                    :class="{
                                        'text-white': true,
                                        'hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': route().current('admin.users.*'),
                                        'border-transparent': !route().current('admin.users.*')
                                    }">
                                    <UserIcon class="h-5 w-5 mr-1 text-white" /> Usuarios
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                class="inline-flex items-center px-3 py-2 rounded-md font-medium text-white hover:bg-white/10 transition duration-150 ease-in-out whitespace-nowrap min-w-[120px] justify-center">
                                                <UserCircleIcon class="h-6 w-6 mr-1" />
                                                {{ $page.props.auth.user.name }}
                                                <ChevronDownIcon
                                                    class="ms-2 -me-0.5 h-4 w-4 transition duration-150 ease-in-out" />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Cerrar sesión
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-white transition duration-150 ease-in-out hover:bg-white/10 focus:bg-white/10 focus:outline-none">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="sm:hidden bg-[#485F84] py-2">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" :class="{
                                'flex items-center px-4 py-2': true, /* Quitamos text-white para control granular */
                                'bg-white/20': route().current('dashboard'), /* Fondo más claro para activo */
                                'text-white': !route().current('dashboard'), /* Texto blanco si no está activo */
                                'text-gray-800': route().current('dashboard'), /* Texto oscuro si está activo */
                                'hover:bg-white/10 focus:bg-white/10': true, /* Fondo hover más claro */
                                'hover:text-gray-900 focus:text-gray-900': true, /* Texto oscuro al hover */
                            }">
                            <MagnifyingGlassIcon class="h-5 w-5 mr-1" :class="{ 'text-gray-800': route().current('dashboard'), 'text-white': !route().current('dashboard') }" />
                            Muestras
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('admin.countries.index')"
                            :active="route().current('admin.countries.index')" :class="{
                                'flex items-center px-4 py-2': true,
                                'bg-white/20': route().current('admin.countries.index'),
                                'text-white': !route().current('admin.countries.index'),
                                'text-gray-800': route().current('admin.countries.index'),
                                'hover:bg-white/10 focus:bg-white/10': true,
                                'hover:text-gray-900 focus:text-gray-900': true,
                            }">
                            <GlobeAmericasIcon class="h-5 w-5 mr-1" :class="{ 'text-gray-800': route().current('admin.countries.index'), 'text-white': !route().current('admin.countries.index') }" />
                            Paises
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('admin.roles.index')" :active="route().current('admin.roles.*')"
                            :class="{
                                'flex items-center px-4 py-2': true,
                                'bg-white/20': route().current('admin.roles.*'),
                                'text-white': !route().current('admin.roles.*'),
                                'text-gray-800': route().current('admin.roles.*'),
                                'hover:bg-white/10 focus:bg-white/10': true,
                                'hover:text-gray-900 focus:text-gray-900': true,
                            }">
                            <FingerPrintIcon class="h-5 w-5 mr-1" :class="{ 'text-gray-800': route().current('admin.roles.*'), 'text-white': !route().current('admin.roles.*') }" />
                            Roles
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('admin.companies.index')"
                            :active="route().current('admin.companies.*')" :class="{
                                'flex items-center px-4 py-2': true,
                                'bg-white/20': route().current('admin.companies.*'),
                                'text-white': !route().current('admin.companies.*'),
                                'text-gray-800': route().current('admin.companies.*'),
                                'hover:bg-white/10 focus:bg-white/10': true,
                                'hover:text-gray-900 focus:text-gray-900': true,
                            }">
                            <BuildingOffice2Icon class="h-5 w-5 mr-1" :class="{ 'text-gray-800': route().current('admin.companies.*'), 'text-white': !route().current('admin.companies.*') }" />
                            Empresas
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('admin.users.index')" :active="route().current('admin.users.*')"
                            :class="{
                                'flex items-center px-4 py-2': true,
                                'bg-white/20': route().current('admin.users.*'),
                                'text-white': !route().current('admin.users.*'),
                                'text-gray-800': route().current('admin.users.*'),
                                'hover:bg-white/10 focus:bg-white/10': true,
                                'hover:text-gray-900 focus:text-gray-900': true,
                            }">
                            <UserIcon class="h-5 w-5 mr-1" :class="{ 'text-gray-800': route().current('admin.users.*'), 'text-white': !route().current('admin.users.*') }" />
                            Usuarios
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4 flex items-center justify-between cursor-pointer"
                            @click="toggleResponsiveProfileDropdown">
                            <div>
                                <div class="text-base font-medium text-white truncate max-w-[calc(100vw-120px)]">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="text-sm font-medium text-gray-300 truncate max-w-[calc(100vw-120px)]">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                            <ChevronDownIcon
                                :class="{ 'rotate-180': showingResponsiveProfileDropdown, 'rotate-0': !showingResponsiveProfileDropdown }"
                                class="ms-2 -me-0.5 h-4 w-4 transition duration-150 ease-in-out text-white" />
                        </div>

                        <div class="mt-3 space-y-1" v-if="showingResponsiveProfileDropdown">
                            <ResponsiveNavLink :href="route('profile.edit')"
                                class="px-4 py-2 text-white hover:bg-white/10 focus:bg-white/10 hover:text-gray-900 focus:text-gray-900">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button"
                                class="px-4 py-2 text-white hover:bg-white/10 focus:bg-white/10 hover:text-gray-900 focus:text-gray-900">
                                Cerrar sesión
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