<script setup>
import { onMounted, ref, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import api, { sanitizeString, ensureCsrf } from '@/api';
import { AUTH_BEARER_TOKEN_KEY, AUTH_DEVICE_TOKEN_KEY, PAYMENT_DETAILS_KEY } from '@/constants/authStorage';
import { EMAIL_VERIFY_LOGIN_FLOW_KEY, POST_VERIFY_LOGIN_NOTICE_KEY, useAuth } from '@/composables/useAuth';
import { useOtpCountdown } from '@/composables/useOtpCountdown';
import { useAuthStore } from '@/stores/auth';
import { useAlerts } from '@/composables/useAlerts';

const props = defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String },
});

const form = reactive({
    identifier: '',
    password: '',
    remember: false,
    device_name: 'Web Browser',
});

const mode = ref('password');
const loading = ref(false);
const otpLoading = ref(false);
const fieldErrors = ref({});
const otpStatus = ref(null);
const loginAttempts = ref(0);
const lockoutUntil = ref(0);
const { canAccessDashboard } = useAuth();
const authStore = useAuthStore();
const { error: showError, success: showSuccess } = useAlerts();
const { countdownMessage, isCountingDown, parseAndStartCountdown } = useOtpCountdown();

const persistSanctumAuthFromLoginResponse = response => {
    const d = response?.data;
    const t = d?.token;
    if (typeof t === 'string' && t !== '') {
        localStorage.setItem(AUTH_BEARER_TOKEN_KEY, t);
    }
    const deviceTok = d?.device_token;
    if (typeof deviceTok === 'string' && deviceTok !== '') {
        localStorage.setItem(AUTH_DEVICE_TOKEN_KEY, deviceTok);
    }
};

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_DURATION_MS = 60_000;

const isLockedOut = () => {
    if (lockoutUntil.value && Date.now() < lockoutUntil.value) return true;
    if (lockoutUntil.value && Date.now() >= lockoutUntil.value) {
        lockoutUntil.value = 0;
        loginAttempts.value = 0;
    }
    return false;
};

const trackFailedAttempt = () => {
    loginAttempts.value++;
    if (loginAttempts.value >= MAX_LOGIN_ATTEMPTS) {
        lockoutUntil.value = Date.now() + LOCKOUT_DURATION_MS;
        showError('Too many failed attempts. Please wait 60 seconds.');
    }
};

watch(() => form.identifier, () => {
    otpStatus.value = null;
});

onMounted(() => {
    const postVerifyLs = localStorage.getItem(POST_VERIFY_LOGIN_NOTICE_KEY);
    const postVerifySs = sessionStorage.getItem('post_verify_notice');
    const postVerify = postVerifyLs || postVerifySs;
    if (postVerify) {
        showSuccess(sanitizeString(postVerify));
        localStorage.removeItem(POST_VERIFY_LOGIN_NOTICE_KEY);
        sessionStorage.removeItem('post_verify_notice');
    }
    if (props.status) showSuccess(props.status);
    const reason = localStorage.getItem('auth_redirect_reason');
    if (reason) {
        showError(sanitizeString(reason));
        localStorage.removeItem('auth_redirect_reason');
    }
});

const handlePasswordLogin = async () => {
    if (isLockedOut()) {
        const secs = Math.ceil((lockoutUntil.value - Date.now()) / 1000);
        showError(`Too many attempts. Try again in ${secs}s.`);
        return;
    }

    fieldErrors.value = {};
    const identifier = sanitizeString(form.identifier.trim());
    if (!identifier || !form.password) {
        showError('Email/phone and password are required.');
        return;
    }

    loading.value = true;
    authStore.setRequiresOtp(false);

    try {
        await ensureCsrf();
    } catch {
        showError('Unable to establish a secure connection. Please check your network and try again.');
        loading.value = false;
        return;
    }

    try {
        const response = await api.post('/auth/login', {
            email: identifier,
            password: form.password,
            device_name: form.device_name,
        });

        if (response && response.success === false) {
            const requiresPayment = !!(response.errors?.requires_registration_payment);
            if (requiresPayment) {
                localStorage.setItem(
                    PAYMENT_DETAILS_KEY,
                    JSON.stringify({
                        success: false,
                        message: response.message,
                        errors: response.errors,
                        code: response.code,
                    }),
                );
                router.visit(route('auth.payment.required'));
                return;
            }
            showError(response.message || 'Unable to sign in.');
            return;
        }

        if (response.success && response.data?.requires_otp) {
            const otpIdentifier = sanitizeString(response?.data?.identifier || identifier);
            localStorage.setItem('auth_identifier', otpIdentifier);
            authStore.setRequiresOtp(true);
            otpStatus.value = response?.message || 'OTP sent successfully.';
            showSuccess(response?.message || 'OTP sent successfully.');
            router.visit(route('auth.otp.verify'));
            return;
        }

        if (response.success && !response.data?.requires_otp) {
            loginAttempts.value = 0;
            authStore.setRequiresOtp(false);
            persistSanctumAuthFromLoginResponse(response);
            router.visit(route('dashboard'));
        }
    } catch (err) {
        trackFailedAttempt();
        const requiresPayment = !!(err?.errors?.requires_registration_payment);
        if (requiresPayment) {
            localStorage.setItem(PAYMENT_DETAILS_KEY, JSON.stringify(err));
            router.visit(route('auth.payment.required'));
        } else {
            fieldErrors.value = err.errors || {};
            showError(err.message || 'Action failed. Please check your data.');
        }
    } finally {
        loading.value = false;
    }
};

const handleSendOtp = async () => {
    if (isLockedOut()) {
        const secs = Math.ceil((lockoutUntil.value - Date.now()) / 1000);
        showError(`Too many attempts. Try again in ${secs}s.`);
        return;
    }

    fieldErrors.value = {};
    const identifier = sanitizeString(form.identifier.trim());
    if (!identifier) {
        showError('Please enter your email or phone number first.');
        return;
    }

    otpLoading.value = true;
    otpStatus.value = null;

    try {
        await ensureCsrf();
    } catch {
        showError('Unable to establish a secure connection. Please check your network and try again.');
        otpLoading.value = false;
        return;
    }

    try {
        const response = await api.post('/auth/login/send-otp', { identifier });
        if (response.success) {
            localStorage.setItem('auth_identifier', identifier);
            const sentTo = sanitizeString(response?.data?.identifier || identifier);
            otpStatus.value = `Opening verification… code sent to ${sentTo}.`;
            showSuccess(response?.message || 'Code sent. Continue on the next screen.');
            router.visit(route('auth.otp.verify'));
        }
    } catch (err) {
        if (err?.code === 429 && err?.message) {
            if (parseAndStartCountdown(err.message)) {
                // Return early so we don't track it as a normal failed attempt or show a toast
                return;
            }
        }
        trackFailedAttempt();
        showError(err.message || 'OTP request failed. Please check your data.');
    } finally {
        otpLoading.value = false;
    }
};
</script>

<template>
    <AuthLayout>
        <Head title="Sign In" />

        <template #title>Sign in</template>
        <template #subtitle>
            Enter your email or phone to sign in with password, or use OTP to open the verification screen and complete sign-in.
        </template>

        <div class="mb-6 rounded-xl bg-slate-100 p-1 grid grid-cols-2 gap-1">
            <button
                type="button"
                @click="mode = 'password'"
                :class="[
                    'py-2.5 rounded-lg text-sm font-bold transition-all',
                    mode === 'password' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                ]"
            >
                Password
            </button>
            <button
                type="button"
                @click="mode = 'otp'"
                :class="[
                    'py-2.5 rounded-lg text-sm font-bold transition-all',
                    mode === 'otp' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                ]"
            >
                OTP Login
            </button>
        </div>

        <form v-if="mode === 'password'" @submit.prevent="handlePasswordLogin" class="space-y-6">
            <SuInput
                v-model="form.identifier"
                label="Email or Phone Number"
                placeholder="Email or phone number"
                :error="fieldErrors.email?.[0] || fieldErrors.identifier?.[0]"
                required
                autofocus
            />

            <SuInput
                v-model="form.password"
                label="Password"
                type="password"
                placeholder="••••••••"
                :error="fieldErrors.password?.[0]"
                required
            />

            <div class="flex items-center">
                <label class="flex items-center cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" v-model="form.remember" class="sr-only" />
                        <div :class="['w-10 h-5 rounded-full transition-colors duration-300', form.remember ? 'bg-blue-600' : 'bg-gray-200']"></div>
                        <div :class="['absolute top-1 left-1 w-3 h-3 bg-white rounded-full transition-transform duration-300 transform', form.remember ? 'translate-x-5' : '']"></div>
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-700">Remember me for 30 days</span>
                </label>
            </div>

            <div v-if="canResetPassword" class="text-right -mt-2">
                <Link
                    :href="route('password.request')"
                    class="text-sm font-bold text-blue-600 hover:text-blue-700 hover:underline"
                >
                    Forgot password?
                </Link>
            </div>

            <SuButton :loading="loading" class="w-full">
                Sign in
            </SuButton>
        </form>

        <div v-else class="space-y-5">
            <SuInput
                v-model="form.identifier"
                label="Email or Phone Number"
                placeholder="Enter your email or phone number"
                :error="fieldErrors.identifier?.[0] || fieldErrors.email?.[0]"
                required
                autofocus
            />

            <p class="text-xs text-slate-600">
                We will send a code to your email or phone, then take you to the <strong>Verify your email</strong> screen to enter it and finish signing in.
            </p>

            <SuButton type="button" :loading="otpLoading" class="w-full" @click="handleSendOtp" :disabled="isCountingDown">
                Send OTP &amp; continue
            </SuButton>

            <div v-if="isCountingDown" class="p-3 rounded-xl border border-red-200 bg-red-50 text-xs font-bold text-red-700">
                {{ countdownMessage }}
            </div>
            <div v-else-if="otpStatus" class="p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700">
                {{ otpStatus }}
            </div>
        </div>

        <template #footer>
            <div class="space-y-3">
                <p>Need an account? <Link :href="route('register')" class="text-blue-600 font-bold hover:underline">Sign up here</Link></p>
                <p class="text-xs border-t border-slate-100 pt-3">Questions? <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link></p>
            </div>
        </template>
    </AuthLayout>
</template>

<style scoped>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
.animate-shake { animation: shake 0.4s ease-in-out; }
@keyframes fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
.animate-fade-in { animation: fade-in 0.3s ease-out forwards; }
</style>
