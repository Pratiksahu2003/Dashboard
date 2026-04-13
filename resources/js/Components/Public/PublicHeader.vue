<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    nav: { type: Object, required: true },
});

const mobileOpen = ref(false);
</script>

<template>
    <header class="sticky top-0 z-50 bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Logo -->
                <a
                    :href="nav.logo.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center shrink-0"
                >
                    <img :src="nav.logo.src" :alt="nav.logo.alt" class="h-9 w-auto" />
                </a>

                <!-- Desktop nav -->
                <nav class="hidden md:flex items-center gap-1">
                    <a
                        v-for="item in nav.nav"
                        :key="item.label"
                        :href="item.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-3 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
                    >
                        {{ item.label }}
                    </a>
                </nav>

                <!-- CTA buttons -->
                <div class="hidden md:flex items-center gap-2">
                    <Link
                        :href="route(nav.cta.login.route)"
                        class="px-4 py-2 rounded-lg text-sm font-bold text-slate-700 hover:bg-slate-100 transition"
                    >
                        {{ nav.cta.login.label }}
                    </Link>
                    <Link
                        :href="route(nav.cta.register.route)"
                        class="px-4 py-2 rounded-lg text-sm font-black text-white bg-slate-900 hover:bg-slate-700 transition"
                    >
                        {{ nav.cta.register.label }}
                    </Link>
                </div>

                <!-- Mobile hamburger -->
                <button
                    type="button"
                    class="md:hidden h-9 w-9 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600"
                    :aria-expanded="mobileOpen"
                    aria-label="Toggle navigation"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div v-if="mobileOpen" class="md:hidden border-t border-slate-200 bg-white px-4 pb-4 pt-2 space-y-1">
            <a
                v-for="item in nav.nav"
                :key="item.label"
                :href="item.href"
                target="_blank"
                rel="noopener noreferrer"
                class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                @click="mobileOpen = false"
            >
                {{ item.label }}
            </a>
            <div class="pt-2 flex flex-col gap-2">
                <Link
                    :href="route(nav.cta.login.route)"
                    class="block text-center px-4 py-2.5 rounded-lg text-sm font-bold text-slate-700 border border-slate-200 hover:bg-slate-50 transition"
                >
                    {{ nav.cta.login.label }}
                </Link>
                <Link
                    :href="route(nav.cta.register.route)"
                    class="block text-center px-4 py-2.5 rounded-lg text-sm font-black text-white bg-slate-900 hover:bg-slate-700 transition"
                >
                    {{ nav.cta.register.label }}
                </Link>
            </div>
        </div>
    </header>
</template>
