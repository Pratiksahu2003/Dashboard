<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const items = [
    { label: 'Overview', route: 'google-workspace.overview', icon: 'O', tint: 'bg-slate-200 text-slate-700' },
    { label: 'Calendar', route: 'google-workspace.calendar', icon: 'C', tint: 'bg-red-100 text-red-700' },
    { label: 'Drive', route: 'google-workspace.drive', icon: 'D', tint: 'bg-blue-100 text-blue-700' },
    { label: 'YouTube', route: 'google-workspace.youtube', icon: 'Y', tint: 'bg-rose-100 text-rose-700' },
];

const current = computed(() => {
    try {
        return route().current();
    } catch {
        return '';
    }
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="flex flex-wrap gap-2">
            <Link
                v-for="item in items"
                :key="item.route"
                :href="route(item.route)"
                :class="[
                    'px-3 py-1.5 rounded-lg text-xs font-black transition inline-flex items-center gap-1.5',
                    current === item.route
                        ? 'bg-slate-900 text-white'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                ]"
            >
                <span class="inline-flex h-4 w-4 items-center justify-center rounded text-[10px]" :class="item.tint">{{ item.icon }}</span>
                {{ item.label }}
            </Link>
        </div>
    </div>
</template>

