<script setup>
const props = defineProps({
    type: { type: String, default: 'submit' },
    loading: Boolean,
    disabled: Boolean,
    variant: { type: String, default: 'primary' }, // primary, secondary, ghost, danger
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        v-motion
        :initial="{ opacity: 0, y: 6, scale: 0.995 }"
        :enter="{ opacity: 1, y: 0, scale: 1, transition: { type: 'spring', stiffness: 520, damping: 36, mass: 0.7 } }"
        :hovered="disabled || loading ? {} : { y: -1, scale: 1.01, transition: { type: 'spring', stiffness: 700, damping: 30 } }"
        :pressed="disabled || loading ? {} : { scale: 0.985, transition: { type: 'spring', stiffness: 900, damping: 28 } }"
        class="relative flex items-center justify-center py-3 px-6 font-bold rounded-xl transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm will-change-transform"
        :class="[
            variant === 'primary' ? 'bg-[#1570ef] hover:bg-blue-700 text-white shadow-[0_10px_30px_rgba(21,112,239,0.20)]' : '',
            variant === 'secondary' ? 'bg-white border border-gray-200 text-gray-800 hover:bg-gray-50 shadow-sm' : '',
            variant === 'ghost' ? 'bg-transparent text-gray-700 hover:bg-gray-100/70' : '',
            variant === 'danger' ? 'bg-red-600 hover:bg-red-700 text-white shadow-[0_10px_30px_rgba(220,38,38,0.18)]' : '',
        ]"
    >
        <!-- Loading Spinner -->
        <div v-if="loading" class="animate-spin h-5 w-5 text-current absolute z-20">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        
        <span :class="{ 'opacity-0': loading }" class="flex items-center">
            <slot />
        </span>
    </button>
</template>
