<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { teardownWebPush } from '@/services/firebaseWebPush';

defineProps({
    nav: { type: Object, required: true },
});

const page = usePage();
const { clearSession } = useAuth();

const mobileOpen = ref(false);
const scrolled = ref(false);
const userMenuOpen = ref(false);
const userMenuRef = ref(null);

const authUser = computed(() => page.props.auth?.user ?? null);
const isLoggedIn = computed(() => authUser.value != null);

const displayName = computed(() => {
    const u = authUser.value;
    if (!u) return '';
    const n = `${u.first_name ?? ''} ${u.last_name ?? ''}`.trim();
    return n || String(u.name ?? '').trim() || 'Account';
});

const displayEmail = computed(() => String(authUser.value?.email ?? '').trim());

const userInitials = computed(() => {
    const parts = displayName.value.split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return parts
        .slice(0, 2)
        .map((p) => p[0].toUpperCase())
        .join('');
});

function onScroll() {
    scrolled.value = typeof window !== 'undefined' && window.scrollY > 8;
}

function onDocumentClick(e) {
    if (!userMenuOpen.value) return;
    const el = userMenuRef.value;
    if (el && !el.contains(e.target)) userMenuOpen.value = false;
}

function onDocumentKeydown(e) {
    if (e.key === 'Escape') userMenuOpen.value = false;
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

watch(mobileOpen, (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
    if (typeof document !== 'undefined') document.body.style.overflow = '';
});

function isExternal(item) {
    return item.external === true;
}

const barClass = computed(() =>
    [
        'relative z-50 border-b transition-[box-shadow,background-color] duration-200',
        scrolled.value
            ? 'border-slate-200/90 bg-white/95 shadow-[0_1px_0_0_rgba(15,23,42,0.06)] backdrop-blur-md supports-[backdrop-filter]:bg-white/90'
            : 'border-slate-200/70 bg-white',
    ].join(' '),
);

function toggleUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
}

async function signOut() {
    userMenuOpen.value = false;
    mobileOpen.value = false;
    await teardownWebPush().catch(() => {});
    clearSession();
    try {
        await router.post(route('logout'), {}, {
            replace: true,
            preserveState: false,
            preserveScroll: false,
        });
    } catch {
        clearSession();
        window.location.assign(route('login'));
    }
}
</script>

<template>
    <header :class="barClass">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-600 opacity-90"
            aria-hidden="true"
        />
        <div class="relative mx-auto flex h-14 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <!-- Brand -->
            <a
                :href="nav.logo.href"
                target="_blank"
                rel="noopener noreferrer"
                class="flex shrink-0 items-center gap-2.5 rounded-lg py-0.5 outline-none ring-offset-2 ring-offset-white focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <img
                    :src="nav.logo.src"
                    :alt="nav.logo.alt"
                    class="h-8 w-auto object-contain"
                />
                <span class="hidden leading-tight sm:block">
                    <span class="block text-sm font-bold tracking-tight text-slate-900">{{ nav.logo.alt }}</span>
                </span>
            </a>

            <!-- Desktop nav -->
            <nav class="hidden min-w-0 flex-1 justify-center md:flex" aria-label="Primary">
                <ul class="flex items-center gap-0.5">
                    <li v-for="item in nav.nav" :key="item.label">
                        <a
                            :href="item.href"
                            :target="isExternal(item) ? '_blank' : undefined"
                            :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                            class="rounded-md px-3 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-indigo-50 hover:text-indigo-700"
                        >
                            {{ item.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Desktop actions -->
            <div class="hidden shrink-0 items-center gap-2 md:flex">
                <template v-if="isLoggedIn">
                    <div ref="userMenuRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg border border-slate-200/90 bg-slate-50/80 py-1 pl-1 pr-2 transition hover:border-slate-300 hover:bg-white"
                            :aria-expanded="userMenuOpen"
                            aria-haspopup="true"
                            @click.stop="toggleUserMenu"
                        >
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-indigo-600 to-violet-600 text-xs font-bold text-white"
                            >
                                {{ userInitials }}
                            </span>
                            <span class="max-w-[9rem] truncate text-left text-[13px] font-semibold text-slate-800 xl:max-w-[11rem]">
                                {{ displayName }}
                            </span>
                            <svg
                                class="h-3.5 w-3.5 shrink-0 text-slate-400"
                                :class="userMenuOpen ? '-rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="translate-y-1 opacity-0"
                            enter-to-class="translate-y-0 opacity-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="translate-y-0 opacity-100"
                            leave-to-class="translate-y-1 opacity-0"
                        >
                            <div
                                v-if="userMenuOpen"
                                class="absolute right-0 z-[80] mt-1.5 w-64 overflow-hidden rounded-xl border border-slate-200/90 bg-white py-1 shadow-lg shadow-slate-900/10"
                                role="menu"
                            >
                                <div class="border-b border-slate-100 px-3 py-2">
                                    <p class="truncate text-xs font-bold text-slate-900">{{ displayName }}</p>
                                    <p class="truncate text-[11px] text-slate-500">{{ displayEmail }}</p>
                                </div>
                                <div class="p-1">
                                    <Link
                                        :href="route('dashboard')"
                                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-semibold text-slate-700 hover:bg-slate-50"
                                        role="menuitem"
                                        @click="userMenuOpen = false"
                                    >
                                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-gradient-to-br from-sky-400 to-blue-600 text-white">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                            </svg>
                                        </span>
                                        Dashboard
                                    </Link>
                                    <Link
                                        :href="route('profile')"
                                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-semibold text-slate-700 hover:bg-slate-50"
                                        role="menuitem"
                                        @click="userMenuOpen = false"
                                    >
                                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-gradient-to-br from-teal-400 to-emerald-600 text-white">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </span>
                                        Profile
                                    </Link>
                                </div>
                                <div class="border-t border-slate-100 p-1">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-semibold text-rose-600 hover:bg-rose-50"
                                        role="menuitem"
                                        @click="signOut"
                                    >
                                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-gradient-to-br from-rose-400 to-pink-600 text-white">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                        </span>
                                        Sign out
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
                <template v-else>
                    <Link
                        :href="route(nav.cta.login.route)"
                        class="rounded-lg px-3 py-2 text-[13px] font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        {{ nav.cta.login.label }}
                    </Link>
                    <Link
                        :href="route(nav.cta.register.route)"
                        class="rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-[13px] font-semibold text-white shadow-md shadow-indigo-500/20 transition hover:from-indigo-500 hover:to-violet-500"
                    >
                        {{ nav.cta.register.label }}
                    </Link>
                </template>
            </div>

            <!-- Mobile toggle -->
            <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 md:hidden"
                :aria-expanded="mobileOpen"
                aria-controls="public-mobile-nav"
                aria-label="Menu"
                @click="mobileOpen = !mobileOpen"
            >
                <svg v-if="!mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    class="fixed inset-0 z-[60] bg-slate-900/30 backdrop-blur-[1px] md:hidden"
                    aria-hidden="true"
                    @click="mobileOpen = false"
                />
            </Transition>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="-translate-y-1 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-1 opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    id="public-mobile-nav"
                    class="fixed left-3 right-3 top-[3.75rem] z-[70] max-h-[min(82vh,24rem)] overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-xl md:hidden"
                >
                    <div class="max-h-[min(82vh,24rem)] overflow-y-auto overscroll-contain p-2">
                        <ul class="space-y-0.5" role="list">
                            <li v-for="item in nav.nav" :key="item.label">
                                <a
                                    :href="item.href"
                                    :target="isExternal(item) ? '_blank' : undefined"
                                    :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                                    class="flex items-center justify-between rounded-lg px-3 py-2.5 text-[13px] font-semibold text-slate-800 hover:bg-indigo-50"
                                    @click="mobileOpen = false"
                                >
                                    {{ item.label }}
                                    <svg
                                        v-if="isExternal(item)"
                                        class="h-3.5 w-3.5 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </li>
                        </ul>
                        <div class="mt-2 space-y-2 border-t border-slate-100 pt-2">
                            <template v-if="isLoggedIn">
                                <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 text-xs font-bold text-white">
                                        {{ userInitials }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold text-slate-900">{{ displayName }}</p>
                                        <p class="truncate text-[11px] text-slate-500">{{ displayEmail }}</p>
                                    </div>
                                </div>
                                <Link :href="route('dashboard')" class="block rounded-lg px-3 py-2 text-[13px] font-semibold text-slate-800 hover:bg-slate-50" @click="mobileOpen = false">Dashboard</Link>
                                <Link :href="route('profile')" class="block rounded-lg px-3 py-2 text-[13px] font-semibold text-slate-800 hover:bg-slate-50" @click="mobileOpen = false">Profile</Link>
                                <button type="button" class="w-full rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-rose-600 hover:bg-rose-50" @click="signOut">Sign out</button>
                            </template>
                            <template v-else>
                                <Link
                                    :href="route(nav.cta.login.route)"
                                    class="block rounded-lg border border-slate-200 py-2.5 text-center text-[13px] font-semibold text-slate-800"
                                    @click="mobileOpen = false"
                                >
                                    {{ nav.cta.login.label }}
                                </Link>
                                <Link
                                    :href="route(nav.cta.register.route)"
                                    class="block rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 text-center text-[13px] font-semibold text-white"
                                    @click="mobileOpen = false"
                                >
                                    {{ nav.cta.register.label }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </header>
</template>
