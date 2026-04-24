<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    footer: { type: Object, required: true },
    company: {
        type: Object,
        default: () => ({}),
    },
});

const c = computed(() => props.company ?? {});

const companyName = computed(() => String(c.value.name ?? 'SuGanta').trim() || 'SuGanta');

const websiteHref = computed(() => {
    const w = String(c.value.contact?.website ?? '').trim();
    return w || 'https://www.suganta.com';
});

const legalLinks = computed(() => {
    const col = props.footer.columns?.find((x) => x.heading === 'Legal');
    return col?.links ?? [];
});

const copyrightLine = computed(() => {
    const y = new Date().getFullYear();
    return `© ${y} ${companyName.value} All rights reserved.`;
});

const SOCIAL_ORDER = [
    ['facebook', 'Facebook'],
    ['instagram', 'Instagram'],
    ['youtube', 'YouTube'],
    ['linkedin', 'LinkedIn'],
    ['twitter', 'X (Twitter)'],
    ['pinterest', 'Pinterest'],
    ['whatsapp', 'WhatsApp'],
    ['telegram', 'Telegram'],
];

const socialItems = computed(() => {
    const s = c.value.social ?? {};
    return SOCIAL_ORDER.map(([key, label]) => {
        const href = String(s[key] ?? '').trim();
        return href ? { key, label, href } : null;
    }).filter(Boolean);
});

function isExternalHref(href) {
    return /^https?:\/\//i.test(href);
}
</script>

<template>
    <footer
        class="relative mt-auto border-t border-slate-200/90 bg-white pb-[env(safe-area-inset-bottom,0px)] text-slate-700"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-600 opacity-90"
            aria-hidden="true"
        />

        <div
            class="mx-auto max-w-7xl pl-[max(1rem,env(safe-area-inset-left,0px))] pr-[max(1rem,env(safe-area-inset-right,0px))] sm:pl-[max(1.5rem,env(safe-area-inset-left,0px))] sm:pr-[max(1.5rem,env(safe-area-inset-right,0px))] lg:pl-[max(2rem,env(safe-area-inset-left,0px))] lg:pr-[max(2rem,env(safe-area-inset-right,0px))]"
        >
            <!-- Brand + nav links: stacks on small screens, side-by-side on lg -->
            <div
                class="flex flex-col gap-8 py-6 sm:py-7 lg:flex-row lg:items-start lg:justify-between lg:gap-10 lg:py-8"
            >
                <!-- Brand -->
                <div class="shrink-0 lg:max-w-sm">
                    <a
                        :href="websiteHref"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex rounded-md outline-none ring-offset-2 ring-offset-white focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        <img src="/logo/Su250.png" :alt="companyName" class="h-7 w-auto sm:h-8" />
                    </a>
                    <p class="mt-2.5 max-w-sm text-xs leading-relaxed text-slate-600 sm:text-[13px]">
                        {{ footer.tagline }}
                    </p>

                    <div v-if="socialItems.length" class="mt-3">
                        <ul class="flex flex-wrap gap-1.5" role="list" aria-label="Social links">
                            <li v-for="item in socialItems" :key="item.key">
                                <a
                                    :href="item.href"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-600 active:scale-95"
                                    :aria-label="item.label"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <svg
                                        v-if="item.key === 'facebook'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 23.98v-8.475H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.475C19.612 23.094 24 18.1 24 12.073z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'instagram'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'youtube'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'linkedin'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'twitter'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'pinterest'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'whatsapp'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.172.198-.297.298-.495.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="item.key === 'telegram'"
                                        class="h-4 w-4"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.147-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                                        />
                                    </svg>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div v-if="footer.app_links?.length" class="mt-3 flex flex-wrap gap-2">
                        <a
                            v-for="app in footer.app_links"
                            :key="app.store"
                            :href="app.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[11px] font-semibold text-slate-800 transition hover:border-indigo-200 hover:bg-indigo-50 sm:text-xs"
                        >
                            <svg
                                v-if="app.store === 'apple'"
                                class="h-3.5 w-3.5 shrink-0"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"
                                />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    d="M3.18 23.76c.3.17.64.24.99.2l12.5-7.22-2.8-2.8-10.69 9.82zM.5 1.4C.19 1.73 0 2.24 0 2.9v18.2c0 .66.19 1.17.5 1.5l.08.07 10.2-10.2v-.24L.58 1.33.5 1.4zM20.3 10.37l-2.9-1.68-3.14 3.14 3.14 3.14 2.92-1.69c.83-.48.83-1.27-.02-1.91zM4.17.24L16.67 7.46l-2.8 2.8L3.18.44c.35-.04.7.03.99.2v-.4z"
                                />
                            </svg>
                            <span class="max-w-[10rem] truncate sm:max-w-none">{{ app.label }}</span>
                        </a>
                    </div>
                </div>

                <!-- Link columns:2 cols phone, 4 from md -->
                <nav
                    v-if="footer.columns?.length"
                    class="grid min-w-0 flex-1 grid-cols-2 gap-x-4 gap-y-6 sm:gap-x-6 md:grid-cols-4 md:gap-x-6 lg:gap-x-8"
                    aria-label="Footer"
                >
                    <div v-for="col in footer.columns" :key="col.heading" class="min-w-0">
                        <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            {{ col.heading }}
                        </h3>
                        <ul class="mt-2 space-y-1.5" role="list">
                            <li v-for="link in col.links" :key="link.label" class="break-words">
                                <a
                                    v-if="isExternalHref(link.href)"
                                    :href="link.href"
                                    class="text-xs text-slate-600 underline-offset-2 transition hover:text-indigo-600 hover:underline sm:text-[13px]"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ link.label }}
                                </a>
                                <Link
                                    v-else
                                    :href="link.href"
                                    class="text-xs text-slate-600 underline-offset-2 transition hover:text-indigo-600 hover:underline sm:text-[13px]"
                                >
                                    {{ link.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="border-t border-slate-200/90 bg-slate-50/70">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 py-3 pl-[max(1rem,env(safe-area-inset-left,0px))] pr-[max(1rem,env(safe-area-inset-right,0px))] sm:flex-row sm:py-3.5 sm:pl-[max(1.5rem,env(safe-area-inset-left,0px))] sm:pr-[max(1.5rem,env(safe-area-inset-right,0px))] lg:pl-[max(2rem,env(safe-area-inset-left,0px))] lg:pr-[max(2rem,env(safe-area-inset-right,0px))]"
            >
                <p class="text-center text-[11px] leading-snug text-slate-500 sm:text-left sm:text-xs">
                    {{ copyrightLine }}
                </p>
                <div
                    v-if="legalLinks.length"
                    class="flex w-full flex-col items-center gap-2 sm:w-auto sm:flex-row sm:gap-4"
                >
                    <nav class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 sm:justify-end" aria-label="Legal">
                        <a
                            v-for="link in legalLinks"
                            :key="link.label"
                            :href="link.href"
                            class="text-[11px] text-slate-500 underline-offset-2 transition hover:text-slate-800 hover:underline sm:text-xs"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>
</template>
