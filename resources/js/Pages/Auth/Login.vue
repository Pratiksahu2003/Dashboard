<script setup>
import { onMounted, ref, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import api, { sanitizeString } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';

const props = defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String },
});

const form = reactive({
    identifier: '',
    password: '',
    otp: '',
    remember: false,
    device_name: 'Web Browser',
});

const mode = ref('password');
const loading = ref(false);
const otpLoading = ref(false);
const verifyLoading = ref(false);
const fieldErrors = ref({});
const otpStatus = ref(null);
const otpSent = ref(false);
const loginAttempts = ref(0);
const lockoutUntil = ref(0);
const { setSession } = useAuth();
const { error: showError, success: showSuccess } = useAlerts();

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
    otpSent.value = false;
    otpStatus.value = null;
    form.otp = '';
});

onMounted(() => {
    const postVerify = sessionStorage.getItem('post_verify_notice');
    if (postVerify) {
        showSuccess(sanitizeString(postVerify));
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
                    'payment_details',
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
            otpStatus.value = response?.message || 'OTP sent successfully.';
            showSuccess(response?.message || 'OTP sent successfully.');
            router.visit(route('auth.otp.verify'));
            return;
        }

        if (response.success && response.data?.token) {
            loginAttempts.value = 0;
            const u = response.data.user;
            const merged = u
                ? {
                      ...u,
                      email_verified_at: response.data.email_verified_at ?? u.email_verified_at,
                      registration_fee_status:
                          response.data.registration_fee_status ?? u.registration_fee_status,
                  }
                : u;
            setSession({ token: response.data.token, user: merged });
            router.visit(route('dashboard'));
        }
    } catch (err) {
        trackFailedAttempt();
        const requiresPayment = !!(err?.errors?.requires_registration_payment);
        if (requiresPayment) {
            localStorage.setItem('payment_details', JSON.stringify(err));
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
        const response = await api.post('/auth/login/send-otp', { identifier });
        if (response.success) {
            localStorage.setItem('auth_identifier', identifier);
            otpSent.value = true;
            const sentTo = sanitizeString(response?.data?.identifier || identifier);
            otpStatus.value = `OTP sent successfully to ${sentTo}. Please enter the 6-digit code.`;
            showSuccess('OTP sent successfully.');
        }
    } catch (err) {
        trackFailedAttempt();
        showError(err.message || 'OTP request failed. Please check your data.');
        otpSent.value = false;
    } finally {
        otpLoading.value = false;
    }
};

const handleVerifyOtp = async () => {
    if (isLockedOut()) {
        const secs = Math.ceil((lockoutUntil.value - Date.now()) / 1000);
        showError(`Too many attempts. Try again in ${secs}s.`);
        return;
    }

    if (!otpSent.value) return;
    if (!/^\d{6}$/.test(form.otp.trim())) {
        showError('Please enter a valid 6-digit OTP.');
        return;
    }

    verifyLoading.value = true;
    fieldErrors.value = {};

    try {
        const response = await api.post('/auth/login/verify', {
            identifier: sanitizeString(form.identifier.trim()),
            otp: form.otp.trim().replace(/\D/g, ''),
            device_name: form.device_name,
        });

        if (response && response.success === false) {
            const requiresPayment = !!(response.errors?.requires_registration_payment);
            if (requiresPayment) {
                localStorage.setItem(
                    'payment_details',
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
            showError(response.message || 'Unable to complete sign in.');
            return;
        }

        if (response.success && response.data?.token) {
            loginAttempts.value = 0;
            const u = response.data.user;
            const merged = u
                ? {
                      ...u,
                      email_verified_at: response.data.email_verified_at ?? u.email_verified_at,
                      registration_fee_status:
                          response.data.registration_fee_status ?? u.registration_fee_status,
                  }
                : u;
            setSession({ token: response.data.token, user: merged });
            localStorage.removeItem('auth_identifier');
            router.visit(route('dashboard'));
        }
    } catch (err) {
        trackFailedAttempt();
        const requiresPayment = !!(err?.errors?.requires_registration_payment);
        if (requiresPayment) {
            localStorage.setItem('payment_details', JSON.stringify(err));
            router.visit(route('auth.payment.required'));
        } else {
            fieldErrors.value = err.errors || {};
            showError(err.message || 'OTP verification failed. Please check your code.');
        }
    } finally {
        verifyLoading.value = false;
    }
};
</script>

<template>
    <AuthLayout>
        <Head title="Sign In" />

        <template #title>Sign in</template>
        <template #subtitle>Enter your email or phone number to sign in with password or OTP.</template>

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

            <SuButton
                type="button"
                :loading="otpLoading"
                class="w-full"
                @click="handleSendOtp"
            >
                Send OTP
            </SuButton>

            <div v-if="otpStatus" class="p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700">
                {{ otpStatus }}
            </div>

            <template v-if="otpSent">
                <SuInput
                    v-model="form.otp"
                    label="Verification Code"
                    placeholder="000000"
                    :error="fieldErrors.otp?.[0]"
                    required
                />
                <p class="text-xs text-slate-500 -mt-2">Enter the 6-digit code sent to your email or phone.</p>
            </template>

            <SuButton
                type="button"
                :loading="verifyLoading"
                :disabled="!otpSent || !form.otp"
                class="w-full"
                @click="handleVerifyOtp"
            >
                Verify & Sign In
            </SuButton>
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
