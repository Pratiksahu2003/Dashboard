<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    modelValue: String,
    label: String,
    type: { type: String, default: 'text' },
    placeholder: String,
    required: Boolean,
    autocomplete: String,
    error: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div class="space-y-1.5 w-full">
        <label v-if="label" class="block text-xs font-black text-slate-700 tracking-tight">
            {{ label }}
        </label>
        
        <div class="relative">
            <input
                ref="input"
                :type="type"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                :placeholder="placeholder"
                :required="required"
                :autocomplete="autocomplete"
                :class="[
                    'block w-full px-4 py-3 bg-white/80 border rounded-2xl outline-none transition-all duration-200 shadow-[0_1px_0_rgba(15,23,42,0.04)]',
                    error 
                        ? 'border-red-300 focus:border-red-500 focus:ring-4 focus:ring-red-500/10' 
                        : 'border-slate-200 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 hover:border-slate-300'
                ]"
            />
            
            <!-- Error Indicator -->
            <div v-if="error" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-red-500">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>
        
        <p v-if="error" class="text-xs font-medium text-red-600 mt-1">
            {{ error }}
        </p>
    </div>
</template>
