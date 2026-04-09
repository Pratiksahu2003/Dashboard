<script setup>
const props = defineProps({
    label: String,
    desc: String,
    selected: Boolean,
});
</script>

<template>
    <div
        @click="$emit('click')"
        v-motion
        :initial="{ opacity: 0, y: 10, scale: 0.98 }"
        :enter="{ opacity: 1, y: 0, scale: 1, transition: { type: 'spring', stiffness: 520, damping: 38, mass: 0.7 } }"
        :hovered="{ y: -2, scale: 1.01, transition: { type: 'spring', stiffness: 700, damping: 30 } }"
        :pressed="{ scale: 0.985, transition: { type: 'spring', stiffness: 900, damping: 28 } }"
        :class="[
            'p-4 rounded-2xl border cursor-pointer transition-colors duration-200 flex items-start gap-4 will-change-transform',
            selected 
                ? 'border-blue-600 bg-gradient-to-b from-blue-50 to-white shadow-[0_12px_30px_rgba(37,99,235,0.12)]' 
                : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm'
        ]"
    >
        <div :class="['w-5 h-5 rounded-full border-2 flex items-center justify-center mt-1 shrink-0 transition-colors', selected ? 'border-blue-600 bg-blue-600' : 'border-slate-300']">
            <div v-if="selected" class="w-2 h-2 rounded-full bg-white"></div>
        </div>
        
        <div class="flex-1 text-left">
            <h4 :class="['text-sm font-black tracking-tight transition-colors', selected ? 'text-blue-950' : 'text-slate-900']">
                {{ label }}
            </h4>
            <p class="text-xs text-slate-600 font-medium leading-relaxed mt-1">
                {{ desc }}
            </p>
        </div>
    </div>
</template>
