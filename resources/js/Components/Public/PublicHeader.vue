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
    scrolled.value = typeof window !== 'undefined' && window.scrollY > 12;
}

function onDocumentClick(e) {
    if (!userMenuOpen.value) return;
    const el = userMenuRef.value;
    if (el && !el.contains(e.target)) userMenuOpen.value = false;
}

function onDocumentKeydown(e) {
    if (e.key === 'Escape') {
        userMenuOpen.value = false;
    }
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

const headerShellClass = computed(() =>
    [
        'relative sticky top-0 z-50 transition-[box-shadow,background-color] duration-300',
        scrolled.value
            ? 'border-b border-slate-200/90 bg-white/90 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] backdrop-blur-xl supports-[backdrop-filter]:bg-white/75'
            : 'border-b border-transparent bg-white/70 backdrop-blur-md supports-[backdrop-filter]:bg-white/60',
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
    <header :class="headerShellClass">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-[4.25rem] items-center justify-between gap-4">
                <!-- Logo -->
                <a
                    :href="nav.logo.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group flex shrink-0 items-center gap-3 rounded-xl py-1 outline-none ring-offset-2 ring-offset-white transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <img
                        :src="nav.logo.src"
                        :alt="nav.logo.alt"
                        class="h-9 w-auto object-contain drop-shadow-sm transition group-hover:scale-[1.02]"
                    />
                    <span class="hidden flex-col leading-none sm:flex">
                        <span class="text-sm font-bold tracking-tight text-slate-900">{{ nav.logo.alt }}</span>
                        <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-indigo-600/90">Learning platform</span>
                    </span>
                </a>

                <!-- Desktop: pill nav -->
                <nav class="hidden max-w-xl flex-1 justify-center md:flex" aria-label="Primary">
                    <div
                        class="flex items-center gap-0.5 rounded-full border border-slate-200/80 bg-slate-100/90 p-1 shadow-inner shadow-slate-200/50"
                    >
                        <a
                            v-for="item in nav.nav"
                            :key="item.label"
                            :href="item.href"
                            :target="isExternal(item) ? '_blank' : undefined"
                            :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                            class="whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-semibold text-slate-600 transition duration-200 hover:bg-white hover:text-indigo-700 hover:shadow-sm lg:px-4"
                        >
                            {{ item.label }}
                        </a>
                    </div>
                </nav>

                <!-- Desktop: logged-in user menu -->
                <div v-if="isLoggedIn" ref="userMenuRef" class="relative hidden shrink-0 md:block">
                    <button
                        type="button"
                        class="flex max-w-[16rem] items-center gap-3 rounded-2xl border border-slate-200/90 bg-white py-1.5 pl-1.5 pr-3 text-left shadow-sm transition hover:border-slate-300 hover:shadow-md"
                        :aria-expanded="userMenuOpen"
                        aria-haspopup="true"
                        @click.stop="toggleUserMenu"
                    >
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-indigo-600 text-sm font-bold text-white shadow-inner"
                        >
                            {{ userInitials }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold text-slate-900">{{ displayName }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ displayEmail }}</span>
                        </span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                            :class="userMenuOpen ? '-rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-1 opacity-0"
                    >
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 z-[80] mt-2 w-72 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xl shadow-slate-900/10 ring-1 ring-slate-900/5"
                            role="menu"
                        >
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-bold text-slate-900">{{ displayName }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ displayEmail }}</p>
                            </div>
                            <div class="p-1.5">
                                <Link
                                    :href="route('dashboard')"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                    role="menuitem"
                                    @click="userMenuOpen = false"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-sky-400 to-blue-500 text-white shadow-sm"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                            />
                                        </svg>
                                    </span>
                                    Dashboard
                                </Link>
                                <Link
                                    :href="route('profile')"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                    role="menuitem"
                                    @click="userMenuOpen = false"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-emerald-500 text-white shadow-sm"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                            />
                                        </svg>
                                    </span>
                                    Profile
                                </Link>
                            </div>
                            <div class="border-t border-slate-100 p-1.5">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                    role="menuitem"
                                    @click="signOut"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-rose-400 to-pink-500 text-white shadow-sm"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                            />
                                        </svg>
                                    </span>
                                    Sign Out
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Desktop: guest CTAs -->
                <div v-else class="hidden shrink-0 items-center gap-2 sm:gap-3 md:flex">
                    <Link
                        :href="route(nav.cta.login.route)"
                        class="rounded-full px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900"
                    >
                        {{ nav.cta.login.label }}
                    </Link>
                    <Link
                        :href="route(nav.cta.register.route)"
                        class="group relative inline-flex items-center justify-center overflow-hidden rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-500/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        <span class="relative z-10">{{ nav.cta.register.label }}</span>
                        <span
                            class="absolute inset-0 -translate-x-full bg-gradient-to-r from-white/0 via-white/25 to-white/0 transition duration-500 group-hover:translate-x-full"
                            aria-hidden="true"
                        />
                    </Link>
                </div>

                <!-- Mobile menu button -->
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200/90 bg-white text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/80 hover:text-indigo-800 md:hidden"
                    :aria-expanded="mobileOpen"
                    aria-controls="public-mobile-nav"
                    aria-label="Toggle navigation"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile menu: teleported so full-screen backdrop works (header uses backdrop-filter) -->
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
                    class="fixed inset-0 z-[60] bg-slate-900/35 backdrop-blur-[2px] md:hidden"
                    aria-hidden="true"
                    @click="mobileOpen = false"
                />
            </Transition>

            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    id="public-mobile-nav"
                    class="fixed left-4 right-4 top-[4.75rem] z-[70] overflow-hidden rounded-2xl border border-slate-200/90 bg-white/95 shadow-2xl shadow-slate-900/20 backdrop-blur-xl md:hidden"
                >
                    <div class="max-h-[min(85vh,32rem)] overflow-y-auto overscroll-contain px-3 py-3">
                        <nav class="space-y-0.5" aria-label="Mobile primary">
                            <a
                                v-for="item in nav.nav"
                                :key="item.label"
                                :href="item.href"
                                :target="isExternal(item) ? '_blank' : undefined"
                                :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                                class="flex items-center justify-between rounded-xl px-4 py-3.5 text-sm font-semibold text-slate-800 transition hover:bg-gradient-to-r hover:from-indigo-50 hover:to-violet-50 hover:text-indigo-900"
                                @click="mobileOpen = false"
                            >
                                {{ item.label }}
                                <svg
                                    v-if="isExternal(item)"
                                    class="h-4 w-4 shrink-0 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                    />
                                </svg>
                            </a>
                        </nav>

                        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                            <template v-if="isLoggedIn">
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-indigo-600 text-sm font-bold text-white"
                                    >
                                        {{ userInitials }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ displayName }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ displayEmail }}</p>
                                    </div>
                                </div>
                                <Link
                                    :href="route('dashboard')"
                                    class="flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                    @click="mobileOpen = false"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-sky-400 to-blue-500 text-white shadow-sm"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                            />
                                        </svg>
                                    </span>
                                    Dashboard
                                </Link>
                                <Link
                                    :href="route('profile')"
                                    class="flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                    @click="mobileOpen = false"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-emerald-500 text-white shadow-sm"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                            />
                                        </svg>
                                    </span>
                                    Profile
                                </Link>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-4 py-3.5 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                    @click="signOut"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-rose-400 to-pink-500 text-white shadow-sm"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                            />
                                        </svg>
                                    </span>
                                    Sign Out
                                </button>
                            </template>
                            <template v-else>
                                <Link
                                    :href="route(nav.cta.login.route)"
                                    class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-center text-sm font-bold text-slate-800 transition hover:border-slate-300 hover:bg-white"
                                    @click="mobileOpen = false"
                                >
                                    {{ nav.cta.login.label }}
                                </Link>
                                <Link
                                    :href="route(nav.cta.register.route)"
                                    class="block w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3.5 text-center text-sm font-bold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500"
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
