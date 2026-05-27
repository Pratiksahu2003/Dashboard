<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuButton from '@/Components/SuButton.vue';
import SuInput from '@/Components/SuInput.vue';
import {
    extractAuthToken,
    needsPayment,
    persistAuthToken,
    persistPaymentGate,
    unwrapAuthPayload,
} from '@/services/authFlow';
import { ensureCsrf, socialPost } from '@/services/socialApi';
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

const step = ref('phone');
const otpSent = ref(false);
const sendingOtp = ref(false);
const verifyingOtp = ref(false);
const completing = ref(false);
const verifiedPhone = ref('');
const fieldErrors = ref({});
const { error: showError, success: showSuccess } = useAlerts();

const cleanPhone = computed(() => form.phone.trim().replace(/[^\d+]/g, ''));
const canSendOtp = computed(() => cleanPhone.value.length >= 8);
const canVerifyOtp = computed(() => cleanPhone.value.length >= 8 && /^\d{6}$/.test(form.otp));
const canComplete = computed(() => !!verifiedPhone.value && !!form.role);

const goToDashboard = token => {
    router.post(route('auth.sync-cache'), {
        token: token || null,
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
        const response = await socialPost('/auth/send-otp', { phone: cleanPhone.value });
        if (response?.success === false) {
            throw response;
        }
        otpSent.value = true;
        step.value = 'otp';
        showSuccess(response?.message || 'OTP sent successfully.');
    } catch (err) {
        fieldErrors.value = err?.errors || {};
        showError(err?.message || 'Unable to send OTP.');
    } finally {
        sendingOtp.value = false;
    }
};

const verifyOtp = async () => {
    if (!canVerifyOtp.value) {
        showError('Enter the 6-digit OTP sent to your phone.');
        return;
    }

    verifyingOtp.value = true;
    fieldErrors.value = {};
    try {
        const response = await socialPost('/auth/verify-otp', {
            phone: cleanPhone.value,
            otp: form.otp,
        });
        if (response?.success === false) {
            throw response;
        }
        const payload = unwrapAuthPayload(response);
        verifiedPhone.value = payload?.phone || cleanPhone.value;
        step.value = 'role';
        showSuccess(response?.message || 'Phone verified successfully.');
    } catch (err) {
        fieldErrors.value = err?.errors || {};
        showError(err?.message || 'Unable to verify OTP.');
    } finally {
        verifyingOtp.value = false;
    }
};

const completeProfile = async () => {
    if (!canComplete.value) return;

    completing.value = true;
    fieldErrors.value = {};
    try {
        const response = await socialPost('/auth/complete-profile', {
            phone: verifiedPhone.value,
            role: form.role,
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

const editPhone = () => {
    step.value = 'phone';
    otpSent.value = false;
    verifiedPhone.value = '';
    form.otp = '';
    form.role = '';
    fieldErrors.value = {};
};

onMounted(async () => {
    try {
        await ensureCsrf();
    } catch {
        router.visit(route('login'), { replace: true });
    }
});
</script>

<template>
    <AuthLayout>
        <Head title="Complete Profile" />

        <template #title>Complete your profile</template>
        <template #subtitle>
            First verify your phone number, then choose your role. Payment appears only if your selected role requires it.
        </template>

        <div class="mb-5 grid grid-cols-3 gap-2 text-center text-[10px] font-black uppercase tracking-wide">
            <span :class="step === 'phone' ? 'rounded-full bg-blue-600 px-3 py-2 text-white' : 'rounded-full bg-slate-100 px-3 py-2 text-slate-500'">1. Phone</span>
            <span :class="step === 'otp' ? 'rounded-full bg-blue-600 px-3 py-2 text-white' : 'rounded-full bg-slate-100 px-3 py-2 text-slate-500'">2. Verify</span>
            <span :class="step === 'role' ? 'rounded-full bg-blue-600 px-3 py-2 text-white' : 'rounded-full bg-slate-100 px-3 py-2 text-slate-500'">3. Role</span>
        </div>

        <form v-if="step === 'phone'" class="space-y-5" @submit.prevent="sendOtp">
            <SuInput
                v-model="form.phone"
                label="Phone number"
                placeholder="+91 98765 43210"
                :error="fieldErrors.phone?.[0]"
                required
            />

            <p class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs font-semibold leading-relaxed text-blue-900">
                Enter the mobile number you want linked to your SuGanta account. We’ll send a secure OTP to verify it.
            </p>

            <SuButton class="w-full" :loading="sendingOtp" :disabled="!canSendOtp">
                {{ otpSent ? 'Send OTP again' : 'Send OTP' }}
            </SuButton>
        </form>

        <form v-else-if="step === 'otp'" class="space-y-5" @submit.prevent="verifyOtp">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-700">
                OTP sent to <span class="text-slate-950">{{ cleanPhone }}</span>.
                <button type="button" class="ml-1 font-black text-blue-700 hover:underline" @click="editPhone">Change number</button>
            </div>

            <label class="block space-y-1.5">
                <span class="block text-xs font-black text-slate-700 tracking-tight">Verification code</span>
                <input
                    v-model="form.otp"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    autocomplete="one-time-code"
                    placeholder="6-digit OTP"
                    class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-xl font-black tracking-[0.35em] shadow-[0_1px_0_rgba(15,23,42,0.04)] outline-none transition-all focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                    required
                />
                <span v-if="fieldErrors.otp?.[0]" class="block text-xs font-medium text-red-600">{{ fieldErrors.otp[0] }}</span>
            </label>

            <SuButton class="w-full" :loading="verifyingOtp" :disabled="!canVerifyOtp">
                Verify phone
            </SuButton>

            <button type="button" class="w-full text-xs font-black uppercase tracking-wide text-slate-500 hover:text-blue-700" :disabled="sendingOtp" @click="sendOtp">
                Resend OTP
            </button>
        </form>

        <form v-else class="space-y-5" @submit.prevent="completeProfile">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-900">
                Phone verified: <span class="font-black">{{ verifiedPhone }}</span>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-black text-slate-700 tracking-tight">Select your role</label>
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

            <p class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs font-semibold leading-relaxed text-amber-950">
                Student accounts continue for free. Teacher, institute, and university roles may require a one-time registration payment after this step.
            </p>

            <SuButton class="w-full" :loading="completing" :disabled="!canComplete">
                Continue
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
