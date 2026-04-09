<script setup>
import { ref, reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import api from '@/api';
import { useAlerts } from '@/composables/useAlerts';

const form = reactive({ email: '' });
const loading = ref(false);
const fieldErrors = ref({});
const { error: showError, success: showSuccess } = useAlerts();

const handleSubmit = async () => {
    loading.value = true;
    fieldErrors.value = {};

    try {
        const response = await api.post('/auth/forgot-password', form);
        if (response.success) showSuccess(response.message || 'Recovery email dispatched.');
    } catch (err) {
        fieldErrors.value = err.errors || {};
        showError(err.message || 'Recovery failed.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AuthLayout>
        <Head title="Secure Recovery" />

        <template #title>Password Recovery</template>
        <template #subtitle>Enter your registered email and we'll send a secure link to reset your access.</template>

        <form @submit.prevent="handleSubmit" class="space-y-8">
            <SuInput
                v-model="form.email"
                label="Registered Email"
                type="email"
                placeholder="john.doe@example.com"
                :error="fieldErrors.email?.[0]"
                required
                autofocus
                icon="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
            />

            <SuButton :loading="loading" class="w-full">
                Dispatch Recovery Link
            </SuButton>
        </form>

        <template #footer>
            <div class="space-y-3">
                <p>Remembered? <Link :href="route('login')" class="text-blue-600 font-bold hover:underline">Back to Gateway</Link></p>
                <p class="text-xs border-t border-slate-100 pt-3 text-gray-500 font-medium">Locked out? <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link></p>
            </div>
        </template>
    </AuthLayout>
</template>

<style scoped>
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
.animate-shake { animation: shake 0.4s ease-in-out; }
</style>
