<script setup>
import { reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import api from '@/api';
import { useAlerts } from '@/composables/useAlerts';
import { getLoginUrl } from '@/utils/authRedirect';

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
});

const loading = ref(false);
const fieldErrors = ref({});
const { error: showError, success: showSuccess } = useAlerts();
const loginUrl = getLoginUrl();

const handleSubmit = async () => {
    loading.value = true;
    fieldErrors.value = {};

    try {
        const response = await api.post('/contacts', form);
        if (response.success) {
            showSuccess(response.message || 'Contact form submitted successfully.');
            // Reset form
            form.first_name = '';
            form.last_name = '';
            form.email = '';
            form.phone = '';
            form.subject = '';
            form.message = '';
        }
    } catch (err) {
        fieldErrors.value = err.errors || {};
        showError(err.message || 'Failed to submit contact form. Please try again.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AuthLayout>
        <Head title="Contact Us" />

        <template #title>Contact Us</template>
        <template #subtitle>Have questions? We're here to help. Send us a message and we'll get back to you shortly.</template>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <SuInput
                    v-model="form.first_name"
                    label="First Name"
                    placeholder="Jane"
                    :error="fieldErrors.first_name?.[0]"
                    required
                />
                <SuInput
                    v-model="form.last_name"
                    label="Last Name"
                    placeholder="Smith"
                    :error="fieldErrors.last_name?.[0]"
                    required
                />
            </div>

            <SuInput
                v-model="form.email"
                type="email"
                label="Email Address"
                placeholder="jane@example.com"
                :error="fieldErrors.email?.[0]"
                required
            />

            <SuInput
                v-model="form.phone"
                label="Phone Number (Optional)"
                placeholder="+919876543210"
                :error="fieldErrors.phone?.[0]"
            />

            <SuInput
                v-model="form.subject"
                label="Subject"
                placeholder="How can we help?"
                :error="fieldErrors.subject?.[0]"
                required
            />

            <div class="space-y-1.5">
                <label class="text-sm font-bold text-gray-700">Message</label>
                <textarea
                    v-model="form.message"
                    rows="4"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all resize-none text-gray-900 placeholder:text-gray-400 font-medium"
                    placeholder="Tell us more about your inquiry..."
                    required
                ></textarea>
                <p v-if="fieldErrors.message?.[0]" class="text-xs font-bold text-red-500 mt-1">{{ fieldErrors.message[0] }}</p>
            </div>

            <SuButton :loading="loading" class="w-full h-12 text-base">
                Send Message
            </SuButton>
        </form>

        <template #footer>
            Already have an account? <Link :href="loginUrl" class="text-blue-600 font-bold hover:underline">Sign in here</Link>
        </template>
    </AuthLayout>
</template>

<style scoped>
/* Any additional specific styles for the contact page */
</style>
