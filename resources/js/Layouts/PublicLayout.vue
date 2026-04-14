<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicHeader from '@/Components/Public/PublicHeader.vue';
import PublicFooter from '@/Components/Public/PublicFooter.vue';

/**
 * Public layout — header + footer driven by config/public_nav.php.
 * The config is shared via HandleInertiaRequests (or passed as a prop).
 */
const props = defineProps({
    /** Passed from the controller or Inertia shared props (publicNav key). */
    publicNav: {
        type: Object,
        default: null,
    },
});

// Prefer prop, fall back to Inertia shared props.
const page = usePage();
const nav = props.publicNav ?? page.props.publicNav ?? null;
const company = computed(() => page.props.company ?? {});
</script>

<template>
    <div
        class="flex min-h-screen w-full max-w-[100vw] flex-col overflow-x-hidden bg-white font-sans text-slate-900 antialiased"
    >
        <PublicHeader v-if="nav" :nav="nav.header" />

        <main class="min-w-0 flex-1 w-full">
            <slot />
        </main>

        <PublicFooter v-if="nav" :footer="nav.footer" :company="company" />
    </div>
</template>
