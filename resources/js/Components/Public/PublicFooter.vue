<script setup>
defineProps({
    footer: { type: Object, required: true },
});

const socialIcons = {
    facebook: 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z',
    instagram: 'M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01M6.5 19.5h11a3 3 0 003-3v-11a3 3 0 00-3-3h-11a3 3 0 00-3 3v11a3 3 0 003 3z',
    youtube:   'M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 001.46 6.42 29 29 0 001 12a29 29 0 00.46 5.58 2.78 2.78 0 001.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z',
    linkedin:  'M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z M4 6a2 2 0 100-4 2 2 0 000 4z',
};
</script>

<template>
    <footer class="bg-slate-900 text-slate-300">
        <!-- Main footer grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-10">

                <!-- Brand column -->
                <div class="lg:col-span-2">
                    <a href="https://www.suganta.com" target="_blank" rel="noopener noreferrer">
                        <img src="/logo/Su2501.png" alt="SuGanta" class="h-10 w-auto brightness-0 invert" />
                    </a>
                    <p class="mt-4 text-sm leading-relaxed text-slate-400 max-w-xs">
                        {{ footer.tagline }}
                    </p>

                    <!-- Social links -->
                    <div class="mt-6 flex items-center gap-3">
                        <a
                            v-for="social in footer.social"
                            :key="social.label"
                            :href="social.href"
                            :aria-label="social.label"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="h-9 w-9 flex items-center justify-center rounded-lg bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="socialIcons[social.icon]" />
                            </svg>
                        </a>
                    </div>

                    <!-- App store badges -->
                    <div class="mt-6 flex flex-col gap-2">
                        <a
                            v-for="app in footer.app_links"
                            :key="app.store"
                            :href="app.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition w-fit"
                        >
                            <!-- Apple icon -->
                            <svg v-if="app.store === 'apple'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                            </svg>
                            <!-- Google Play icon -->
                            <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M3.18 23.76c.3.17.64.24.99.2l12.5-7.22-2.8-2.8-10.69 9.82zM.5 1.4C.19 1.73 0 2.24 0 2.9v18.2c0 .66.19 1.17.5 1.5l.08.07 10.2-10.2v-.24L.58 1.33.5 1.4zM20.3 10.37l-2.9-1.68-3.14 3.14 3.14 3.14 2.92-1.69c.83-.48.83-1.27-.02-1.91zM4.17.24L16.67 7.46l-2.8 2.8L3.18.44c.35-.04.7.03.99.2v-.4z"/>
                            </svg>
                            {{ app.label }}
                        </a>
                    </div>
                </div>

                <!-- Link columns -->
                <div
                    v-for="col in footer.columns"
                    :key="col.heading"
                    class="lg:col-span-1"
                >
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">
                        {{ col.heading }}
                    </h3>
                    <ul class="space-y-2.5">
                        <li v-for="link in col.links" :key="link.label">
                            <a
                                :href="link.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-slate-400 hover:text-white transition"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="border-t border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-xs text-slate-500">{{ footer.copyright }}</p>
                <div class="flex items-center gap-4">
                    <a
                        v-for="link in footer.columns.find(c => c.heading === 'Legal')?.links ?? []"
                        :key="link.label"
                        :href="link.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs text-slate-500 hover:text-slate-300 transition"
                    >
                        {{ link.label }}
                    </a>
                </div>
            </div>
        </div>
    </footer>
</template>
