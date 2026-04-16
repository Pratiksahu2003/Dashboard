<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { teardownWebPush } from '@/services/firebaseWebPush';
import { getLoginUrl } from '@/utils/authRedirect';

defineProps({
    nav: { type: Object, required: true },
});

const page = usePage();
const { clearSession } = useAuth();
const loginUrl = getLoginUrl();

const mobileOpen = ref(false);
const scrolled = ref(false);
const userMenuOpen = ref(false);
const userMenuRef = ref(null);

let scrollRaf = 0;

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
    if (typeof window === 'undefined') return;
    if (scrollRaf) cancelAnimationFrame(scrollRaf);
    scrollRaf = requestAnimationFrame(() => {
        scrollRaf = 0;
        scrolled.value = window.scrollY > 8;
    });
}

function onDocumentClick(e) {
    if (!userMenuOpen.value) return;
    const el = userMenuRef.value;
    if (el && !el.contains(e.target)) userMenuOpen.value = false;
}

function onDocumentKeydown(e) {
    if (e.key !== 'Escape') return;
    userMenuOpen.value = false;
    mobileOpen.value = false;
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
    if (scrollRaf) cancelAnimationFrame(scrollRaf);
    window.removeEventListener('scroll', onScroll);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
    if (typeof document !== 'undefined') document.body.style.overflow = '';
});

function isExternal(item) {
    return item.external === true;
}

function isAiAgent(item) {
    return String(item?.href || '').startsWith('https://ai.suganta.com');
}

const shellClass = computed(() =>
    [
        'relative flex min-h-11 w-full min-w-0 items-center gap-2 rounded-2xl border px-2.5 py-1.5 shadow-sm transition-[box-shadow,background-color,border-color,transform] duration-300 sm:min-h-12 sm:gap-3 sm:px-4',
        scrolled.value
            ? 'border-slate-200/90 bg-white/90 shadow-[0_12px_40px_-16px_rgba(15,23,42,0.18)] backdrop-blur-xl supports-[backdrop-filter]:bg-white/80'
            : 'border-slate-200/50 bg-white/65 backdrop-blur-lg supports-[backdrop-filter]:bg-white/55',
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
        window.location.assign(loginUrl);
    }
}

const deskNavLinkClass =
    'group relative whitespace-nowrap rounded-lg px-2.5 py-2 text-[13px] font-medium text-slate-600 outline-none transition-colors hover:bg-slate-900/[0.04] hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-indigo-500/60 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent lg:px-3';

const mobileNavLinkClass =
    'flex min-h-[2.75rem] items-center rounded-xl px-3 py-2.5 text-[15px] font-medium text-slate-800 outline-none transition hover:bg-slate-100/90 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500/40';
</script>

<template>
    <header class="sticky top-0 z-50 pt-[env(safe-area-inset-top,0px)]">
        <!-- Ambient wash behind the bar -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-indigo-100/40 via-violet-50/20 to-transparent opacity-80"
            aria-hidden="true"
        />
        <div
            class="relative mx-auto max-w-7xl pl-[max(0.75rem,env(safe-area-inset-left,0px))] pr-[max(0.75rem,env(safe-area-inset-right,0px))] pb-3 pt-2 sm:pl-[max(1rem,env(safe-area-inset-left,0px))] sm:pr-[max(1rem,env(safe-area-inset-right,0px))] sm:pb-3.5 sm:pt-2.5 lg:pl-[max(1.25rem,env(safe-area-inset-left,0px))] lg:pr-[max(1.25rem,env(safe-area-inset-right,0px))]"
        >
            <div :class="shellClass">
                <!-- Top accent: single creative stripe -->
                <div
                    class="pointer-events-none absolute inset-x-3 top-0 h-px rounded-full bg-gradient-to-r from-transparent via-indigo-400 to-transparent opacity-70 sm:inset-x-4"
                    aria-hidden="true"
                />

                <!-- Brand -->
                <a
                    :href="nav.logo.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="relative z-[1] flex min-w-0 shrink-0 items-center gap-2 rounded-xl py-0.5 outline-none transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-indigo-500/50 sm:gap-2.5"
                >
                    <img
                        :src="nav.logo.src"
                        :alt="nav.logo.alt"
                        class="h-8 w-auto object-contain sm:h-9"
                        decoding="async"
                        fetchpriority="high"
                    />
                
                </a>

                <!-- Desktop nav: one continuous row, no per-link external icons -->
                <nav class="relative z-[1] hidden min-w-0 flex-1 justify-center md:flex" aria-label="Primary">
                    <ul class="flex max-w-full flex-wrap items-center justify-center gap-0.5 sm:gap-1">
                        <li v-for="item in nav.nav" :key="item.label">
                            <a
                                :href="item.href"
                                :target="isExternal(item) ? '_blank' : undefined"
                                :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                                :class="[
                                    deskNavLinkClass,
                                    isAiAgent(item)
                                        ? 'bg-gradient-to-r from-orange-500 to-blue-600 text-white hover:text-white hover:bg-gradient-to-r hover:from-orange-400 hover:to-blue-500 shadow-[0_10px_24px_-12px_rgba(37,99,235,0.8)]'
                                        : ''
                                ]"
                            >
                                <span
                                    class="absolute inset-x-1 -bottom-px h-px scale-x-0 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 opacity-0 transition duration-200 group-hover:scale-x-100 group-hover:opacity-100 group-focus-visible:scale-x-100 group-focus-visible:opacity-100"
                                    aria-hidden="true"
                                />
                                {{ item.label }}
                                <span v-if="isExternal(item)" class="sr-only"> (opens in new tab)</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Desktop actions -->
                <div class="relative z-[1] hidden shrink-0 items-center gap-2 md:flex">
                    <template v-if="isLoggedIn">
                        <div ref="userMenuRef" class="relative">
                            <button
                                id="public-user-menu-button"
                                type="button"
                                class="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/90 py-1 pl-1 pr-2.5 shadow-sm outline-none transition hover:border-indigo-200/80 hover:shadow-md focus-visible:ring-2 focus-visible:ring-indigo-500/50"
                                :aria-expanded="userMenuOpen"
                                aria-haspopup="menu"
                                aria-controls="public-user-menu"
                                @click.stop="toggleUserMenu"
                            >
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-800 text-[11px] font-bold text-white shadow-sm"
                                >
                                    {{ userInitials }}
                                </span>
                                <span class="max-w-[8.5rem] truncate text-left text-[13px] font-semibold text-slate-800 xl:max-w-[10rem]">
                                    {{ displayName }}
                                </span>
                                <svg
                                    class="h-3.5 w-3.5 shrink-0 text-slate-400 transition duration-200"
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
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="translate-y-1 scale-[0.98] opacity-0"
                                enter-to-class="translate-y-0 scale-100 opacity-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="translate-y-0 opacity-100"
                                leave-to-class="translate-y-1 opacity-0"
                            >
                                <div
                                    v-if="userMenuOpen"
                                    id="public-user-menu"
                                    class="absolute right-0 z-[80] mt-2 w-64 origin-top-right overflow-hidden rounded-2xl border border-slate-200/90 bg-white py-1 shadow-2xl shadow-slate-900/12"
                                    role="menu"
                                    aria-labelledby="public-user-menu-button"
                                >
                                    <div class="border-b border-slate-100 bg-slate-50/80 px-3.5 py-3">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ displayName }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ displayEmail }}</p>
                                    </div>
                                    <div class="p-1.5">
                                        <Link
                                            :href="route('dashboard')"
                                            class="flex items-center gap-3 rounded-xl px-2.5 py-2.5 text-[13px] font-semibold text-slate-700 transition hover:bg-slate-50"
                                            role="menuitem"
                                            @click="userMenuOpen = false"
                                        >
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500 text-white shadow-sm">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                </svg>
                                            </span>
                                            Dashboard
                                        </Link>
                                        <Link
                                            :href="route('profile')"
                                            class="flex items-center gap-3 rounded-xl px-2.5 py-2.5 text-[13px] font-semibold text-slate-700 transition hover:bg-slate-50"
                                            role="menuitem"
                                            @click="userMenuOpen = false"
                                        >
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-sm">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </span>
                                            Profile
                                        </Link>
                                    </div>
                                    <div class="border-t border-slate-100 p-1.5">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left text-[13px] font-semibold text-rose-600 transition hover:bg-rose-50"
                                            role="menuitem"
                                            @click="signOut"
                                        >
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500 text-white shadow-sm">
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
                            class="rounded-xl px-3.5 py-2 text-[13px] font-semibold text-slate-700 outline-none transition hover:bg-slate-900/[0.06] hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-indigo-500/50"
                        >
                            {{ nav.cta.login.label }}
                        </Link>
                        <Link
                            :href="route(nav.cta.register.route)"
                            class="relative overflow-hidden rounded-xl bg-gradient-to-r from-indigo-600 via-violet-600 to-indigo-700 px-4 py-2 text-[13px] font-semibold text-white shadow-md shadow-indigo-500/20 outline-none ring-1 ring-white/20 transition hover:brightness-105 hover:shadow-lg hover:shadow-indigo-500/25 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2"
                        >
                            <span class="relative z-[1]">{{ nav.cta.register.label }}</span>
                            <span
                                class="pointer-events-none absolute -right-6 -top-6 h-16 w-16 rounded-full bg-white/20 blur-xl"
                                aria-hidden="true"
                            />
                        </Link>
                    </template>
                </div>

                <!-- Mobile menu -->
                <a
                    href="https://ai.suganta.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="relative z-[1] ml-auto mr-2 inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-[11px] font-black text-slate-900 shadow-sm outline-none transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-slate-400/60 focus-visible:ring-offset-1 md:hidden"
                    aria-label="Open Kaalo Ai"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-7.5a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 006 21h7.5a2.25 2.25 0 002.25-2.25V15m-6-3h11.25m0 0l-3-3m3 3l-3 3"/>
                    </svg>
                    Kaalo Ai
                </a>
                <button
                    type="button"
                    class="relative z-[1] flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200/80 bg-white/80 text-slate-800 outline-none transition hover:bg-white md:hidden"
                    :class="mobileOpen ? 'border-indigo-300 bg-indigo-50 text-indigo-900' : ''"
                    :aria-expanded="mobileOpen"
                    aria-controls="public-mobile-nav"
                    :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
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
                    class="fixed inset-0 z-[60] bg-slate-950/35 backdrop-blur-sm md:hidden"
                    aria-hidden="true"
                    @click="mobileOpen = false"
                />
            </Transition>

            <Transition
                enter-active-class="transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)]"
                enter-from-class="translate-y-3 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    id="public-mobile-nav"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Site menu"
                    class="fixed z-[70] max-h-[min(85dvh,32rem)] overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-900/15 md:hidden left-[max(0.75rem,env(safe-area-inset-left,0px))] right-[max(0.75rem,env(safe-area-inset-right,0px))] top-[calc(env(safe-area-inset-top,0px)+5.25rem)]"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 to-violet-50/50 px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-indigo-700/90">Explore</p>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-xs font-bold text-slate-600 transition hover:bg-white/80"
                            @click="mobileOpen = false"
                        >
                            Close
                        </button>
                    </div>
                    <div class="max-h-[min(85dvh,32rem)] overflow-y-auto overscroll-contain px-3 pb-4 pt-2">
                        <ul class="space-y-1" role="list">
                            <li v-for="item in nav.nav" :key="item.label">
                                <a
                                    :href="item.href"
                                    :target="isExternal(item) ? '_blank' : undefined"
                                    :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                                    :class="[
                                        mobileNavLinkClass,
                                        isAiAgent(item)
                                            ? 'bg-gradient-to-r from-orange-500 to-blue-600 text-white hover:text-white'
                                            : ''
                                    ]"
                                    @click="mobileOpen = false"
                                >
                                    {{ item.label }}
                                    <span v-if="isExternal(item)" class="sr-only"> (opens in new tab)</span>
                                </a>
                            </li>
                        </ul>
                        <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                            <template v-if="isLoggedIn">
                                <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-xs font-bold text-white">
                                        {{ userInitials }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ displayName }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ displayEmail }}</p>
                                    </div>
                                </div>
                                <Link :href="route('dashboard')" :class="mobileNavLinkClass" @click="mobileOpen = false">Dashboard</Link>
                                <Link :href="route('profile')" :class="mobileNavLinkClass" @click="mobileOpen = false">Profile</Link>
                                <button
                                    type="button"
                                    class="w-full rounded-xl px-3 py-3 text-left text-[15px] font-semibold text-rose-600 transition hover:bg-rose-50"
                                    @click="signOut"
                                >
                                    Sign out
                                </button>
                            </template>
                            <template v-else>
                                <Link
                                    :href="route(nav.cta.login.route)"
                                    class="block rounded-xl border border-slate-200 py-3 text-center text-[15px] font-semibold text-slate-800 transition hover:bg-slate-50"
                                    @click="mobileOpen = false"
                                >
                                    {{ nav.cta.login.label }}
                                </Link>
                                <Link
                                    :href="route(nav.cta.register.route)"
                                    class="block rounded-xl bg-gradient-to-r from-indigo-600 via-violet-600 to-indigo-700 py-3 text-center text-[15px] font-semibold text-white shadow-lg shadow-indigo-500/20"
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
