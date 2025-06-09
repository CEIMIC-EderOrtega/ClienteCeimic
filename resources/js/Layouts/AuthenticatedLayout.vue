<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

import {
    ChevronDownIcon,
    UserIcon,
    UserCircleIcon,
    MagnifyingGlassIcon,
    GlobeAmericasIcon,
    FingerPrintIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const showingNavigationDropdown = ref(false);
const showingResponsiveProfileDropdown = ref(false);

const toggleResponsiveProfileDropdown = () => {
    showingResponsiveProfileDropdown.value = !showingResponsiveProfileDropdown.value;
};

const page = usePage();
const userRoles = computed(() => page.props.auth.user.roles || []);
const isAdmin = computed(() => userRoles.value.includes('Administrador'));
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-[#485F84]">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link href="/dashboard">
                                <ApplicationLogo class="h-12 w-auto text-white" />
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink href="/dashboard" :active="$page.url.startsWith('/dashboard')" :class="{
                                    'text-white hover:bg-white/10 focus:bg-white/10': true,
                                    'border-b-2 border-white': $page.url.startsWith('/dashboard'),
                                    'border-transparent': !$page.url.startsWith('/dashboard')
                                }">
                                    <MagnifyingGlassIcon class="h-5 w-5 mr-1 text-white" /> Muestras
                                </NavLink>
                                <NavLink href="/principal-dashboard" :active="$page.url.startsWith('/principal-dashboard')" :class="{
                                    'text-white hover:bg-white/10 focus:bg-white/10': true,
                                    'border-b-2 border-white': $page.url.startsWith('/principal-dashboard'),
                                    'border-transparent': !$page.url.startsWith('/principal-dashboard')
                                }">
                                    <ChartBarIcon class="h-5 w-5 mr-1 text-white" /> Dashboard
                                </NavLink>

                                <template v-if="isAdmin">
                                    <NavLink href="/admin/countries" :active="$page.url.startsWith('/admin/countries')" :class="{
                                        'text-white hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': $page.url.startsWith('/admin/countries'),
                                        'border-transparent': !$page.url.startsWith('/admin/countries')
                                    }">
                                        <GlobeAmericasIcon class="h-5 w-5 mr-1 text-white" /> Países
                                    </NavLink>
                                    <NavLink href="/admin/roles" :active="$page.url.startsWith('/admin/roles')" :class="{
                                        'text-white hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': $page.url.startsWith('/admin/roles'),
                                        'border-transparent': !$page.url.startsWith('/admin/roles')
                                    }">
                                        <FingerPrintIcon class="h-5 w-5 mr-1 text-white" /> Roles
                                    </NavLink>
                                    <NavLink href="/admin/companies" :active="$page.url.startsWith('/admin/companies')" :class="{
                                        'text-white hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': $page.url.startsWith('/admin/companies'),
                                        'border-transparent': !$page.url.startsWith('/admin/companies')
                                    }">
                                        <BuildingOffice2Icon class="h-5 w-5 mr-1 text-white" /> Empresas
                                    </NavLink>
                                    <NavLink href="/admin/users" :active="$page.url.startsWith('/admin/users')" :class="{
                                        'text-white hover:bg-white/10 focus:bg-white/10': true,
                                        'border-b-2 border-white': $page.url.startsWith('/admin/users'),
                                        'border-transparent': !$page.url.startsWith('/admin/users')
                                    }">
                                        <UserIcon class="h-5 w-5 mr-1 text-white" /> Usuarios
                                    </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 rounded-md font-medium text-white hover:bg-white/10 transition duration-150 ease-in-out">
                                                <UserCircleIcon class="h-6 w-6 mr-1" />
                                                {{ $page.props.auth.user.name }}
                                                <ChevronDownIcon class="ms-2 -me-0.5 h-4 w-4" />
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
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center rounded-md p-2 text-white transition">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden bg-[#485F84] py-2">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink href="/dashboard" :active="$page.url.startsWith('/dashboard')">
                            Muestras
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href="/principal-dashboard" :active="$page.url.startsWith('/principal-dashboard')">
                            Dashboard
                        </ResponsiveNavLink>

                        <template v-if="isAdmin">
                             <ResponsiveNavLink href="/admin/countries" :active="$page.url.startsWith('/admin/countries')">
                                Países
                            </ResponsiveNavLink>
                            <ResponsiveNavLink href="/admin/roles" :active="$page.url.startsWith('/admin/roles')">
                                Roles
                            </ResponsiveNavLink>
                            <ResponsiveNavLink href="/admin/companies" :active="$page.url.startsWith('/admin/companies')">
                                Empresas
                            </ResponsiveNavLink>
                            <ResponsiveNavLink href="/admin/users" :active="$page.url.startsWith('/admin/users')">
                                Usuarios
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <div class="border-t border-gray-600 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-white">{{ $page.props.auth.user.name }}</div>
                            <div class="text-sm font-medium text-gray-300">{{ $page.props.auth.user.email }}</div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
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
