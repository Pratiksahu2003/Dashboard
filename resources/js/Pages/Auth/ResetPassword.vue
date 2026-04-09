<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import api from '@/api';
import { useAlerts } from '@/composables/useAlerts';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = reactive({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const loading = ref(false);
const fieldErrors = ref({});
const { error: showError, success: showSuccess } = useAlerts();

const handleSubmit = async () => {
    loading.value = true;
    fieldErrors.value = {};

    try {
        const response = await api.post('/auth/reset-password', form);
        if (response.success) {
            showSuccess(response.message || 'Password reset successful. Please sign in.');
            router.visit(route('login', { status: response.message || 'Password reset successful. Please sign in.' }));
        }
    } catch (err) {
        fieldErrors.value = err.errors || {};
        const firstError = err.errors ? Object.values(err.errors)[0]?.[0] : null;
        showError(firstError || err.message || 'Reset failed.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AuthLayout>
        <Head title="Secure Reset" />

        <template #title>Create Credentials</template>
        <template #subtitle>Establish a modern, high-entropy password to protect your SuGanta professional profile.</template>

        <form @submit.prevent="handleSubmit" class="space-y-8">
            <SuInput
                v-model="form.email"
                label="Account Identity"
                type="email"
                placeholder="john.doe@example.com"
                :error="fieldErrors.email?.[0]"
                required
                icon="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <SuInput
                    v-model="form.password"
                    type="password"
                    placeholder="New Password"
                    :error="fieldErrors.password?.[0]"
                    required
                    icon="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
                <SuInput
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="Confirm"
                    :error="fieldErrors.password_confirmation?.[0]"
                    required
                />
            </div>

            <SuButton :loading="loading" :disabled="form.password !== form.password_confirmation" class="w-full">
                Finalize Secure Reset
            </SuButton>
        </form>
        <template #footer>
            <div class="space-y-3">
                <p class="text-xs border-t border-slate-100 pt-3 text-gray-500 font-medium">Issues resetting? <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link></p>
            </div>
        </template>
    </AuthLayout>
</template>

<style scoped>
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
.animate-shake { animation: shake 0.4s ease-in-out; }
</style>
