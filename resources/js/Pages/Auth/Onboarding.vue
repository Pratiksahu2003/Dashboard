<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuButton from '@/Components/SuButton.vue';
import SuInput from '@/Components/SuInput.vue';
import api from '@/api';
import { AUTH_TOKEN_KEY } from '@/constants/authStorage';
import {
    extractAuthToken,
    needsPayment,
    persistAuthToken,
    persistPaymentGate,
    unwrapAuthPayload,
} from '@/services/authFlow';
import { useAlerts } from '@/composables/useAlerts';

const roles = [
    { value: 'student', label: 'Student' },
    { value: 'teacher', label: 'Teacher' },
    { value: 'institute', label: 'Institute' },
    { value: 'university', label: 'University' },
];

const form = reactive({
    phone: '',
    role: '',
    otp: '',
});

const otpSent = ref(false);
const sendingOtp = ref(false);
const completing = ref(false);
const fieldErrors = ref({});
const { error: showError, success: showSuccess } = useAlerts();

const cleanPhone = computed(() => form.phone.trim().replace(/[^\d+]/g, ''));
const canSendOtp = computed(() => cleanPhone.value.length >= 8);
const canComplete = computed(() => canSendOtp.value && form.role && /^\d{6}$/.test(form.otp));

const goToDashboard = token => {
    router.post(route('auth.sync-cache'), {
        token: token || localStorage.getItem(AUTH_TOKEN_KEY) || null,
        redirect_to: null,
    }, {
        replace: true,
        preserveState: false,
        preserveScroll: false,
    });
};

const sendOtp = async () => {
    if (!canSendOtp.value) {
        showError('Enter a valid phone number first.');
        return;
    }

    sendingOtp.value = true;
    fieldErrors.value = {};
    try {
        const response = await api.post('/auth/send-otp', { phone: cleanPhone.value });
        if (response?.success === false) {
            throw response;
        }
        otpSent.value = true;
        showSuccess(response?.message || 'OTP sent successfully.');
    } catch (err) {
        fieldErrors.value = err?.errors || {};
        showError(err?.message || 'Unable to send OTP.');
    } finally {
        sendingOtp.value = false;
    }
};

const completeProfile = async () => {
    if (!canComplete.value) return;

    completing.value = true;
    fieldErrors.value = {};
    try {
        const response = await api.post('/auth/complete-profile', {
            phone: cleanPhone.value,
            role: form.role,
            otp: form.otp,
        });
        const token = extractAuthToken(response);
        if (token) persistAuthToken(token);

        if (needsPayment(response)) {
            persistPaymentGate(response);
            router.visit(route('auth.payment.required'), { replace: true });
            return;
        }

        const payload = unwrapAuthPayload(response);
        showSuccess(payload?.message || response?.message || 'Profile completed successfully.');
        goToDashboard(token);
    } catch (err) {
        fieldErrors.value = err?.errors || {};
        showError(err?.message || 'Unable to complete profile.');
    } finally {
        completing.value = false;
    }
};

onMounted(() => {
    if (!localStorage.getItem(AUTH_TOKEN_KEY)) {
        router.visit(route('login'), { replace: true });
    }
});
</script>

<template>
    <AuthLayout>
        <Head title="Complete Profile" />

        <template #title>Complete your profile</template>
        <template #subtitle>
            Verify your phone and select your role to continue.
        </template>

        <form class="space-y-5" @submit.prevent="completeProfile">
            <SuInput
                v-model="form.phone"
                label="Phone number"
                placeholder="+91 98765 43210"
                :error="fieldErrors.phone?.[0]"
                required
            />

            <SuButton type="button" variant="secondary" class="w-full" :loading="sendingOtp" :disabled="!canSendOtp" @click="sendOtp">
                {{ otpSent ? 'Send OTP again' : 'Send phone OTP' }}
            </SuButton>

            <label class="block space-y-1.5">
                <span class="block text-xs font-black text-slate-700 tracking-tight">OTP</span>
                <input
                    v-model="form.otp"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    placeholder="6-digit code"
                    class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_1px_0_rgba(15,23,42,0.04)] outline-none transition-all focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                    required
                />
                <span v-if="fieldErrors.otp?.[0]" class="block text-xs font-medium text-red-600">{{ fieldErrors.otp[0] }}</span>
            </label>

            <div class="space-y-2">
                <label class="block text-xs font-black text-slate-700 tracking-tight">Role</label>
                <select
                    v-model="form.role"
                    class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 font-semibold text-slate-800 shadow-[0_1px_0_rgba(15,23,42,0.04)] outline-none transition-all focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                    required
                >
                    <option value="" disabled>Select your role</option>
                    <option v-for="role in roles" :key="role.value" :value="role.value">
                        {{ role.label }}
                    </option>
                </select>
                <p v-if="fieldErrors.role?.[0]" class="text-xs font-medium text-red-600">{{ fieldErrors.role[0] }}</p>
            </div>

            <SuButton class="w-full" :loading="completing" :disabled="!canComplete">
                Complete profile
            </SuButton>
        </form>

        <template #footer>
            <p>
                Need another account?
                <Link :href="route('login')" class="text-blue-600 font-bold hover:underline">Back to sign in</Link>
            </p>
        </template>
    </AuthLayout>
</template>
