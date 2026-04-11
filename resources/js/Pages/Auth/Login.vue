<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import api, { sanitizeString, ensureCsrf } from '@/api';
import {
    AUTH_DEVICE_TOKEN_KEY,
    AUTH_IDENTIFIER_KEY,
    PAYMENT_DETAILS_KEY,
    POST_VERIFY_LOGIN_NOTICE_KEY,
} from '@/constants/authStorage';
import { useOtpCountdown } from '@/composables/useOtpCountdown';
import { useAuthStore } from '@/stores/auth';
import { useAlerts } from '@/composables/useAlerts';

const props = defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String },
    openOtpVerify: { type: Boolean, default: false },
});

const form = reactive({
    identifier: '',
    password: '',
    remember: false,
    device_name: 'Web Browser',
});

/** 'auth' = password / send-otp tabs; 'verify' = 6-digit code (same page). */
const phase = ref('auth');
const mode = ref('password');
const loading = ref(false);
const otpLoading = ref(false);
const fieldErrors = ref({});
const otpStatus = ref(null);
const loginAttempts = ref(0);
const lockoutUntil = ref(0);

const otpVerifyIdentifier = ref('');
const otpDigits = ref(['', '', '', '', '', '']);
const otpInputs = ref([]);
const trustThisBrowser = ref(false);
const verifyLoading = ref(false);
const resendLoading = ref(false);
const resendCountdown = ref(0);
let resendCountdownTimer = null;

const verifyAttempts = ref(0);
const verifyLockoutUntil = ref(0);
const MAX_VERIFY_ATTEMPTS = 5;
const VERIFY_LOCKOUT_MS = 120_000;

const authStore = useAuthStore();
const { error: showError, success: showSuccess } = useAlerts();
const { countdownMessage, isCountingDown, parseAndStartCountdown } = useOtpCountdown();

const persistDeviceTokenFromLoginResponse = response => {
    const deviceTok = response?.data?.device_token;
    if (typeof deviceTok === 'string' && deviceTok !== '') {
        localStorage.setItem(AUTH_DEVICE_TOKEN_KEY, deviceTok);
    }
};

const goToDashboardFullLoad = () => {
    window.location.assign(route('dashboard'));
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

const isVerifyLockedOut = () => {
    if (verifyLockoutUntil.value && Date.now() < verifyLockoutUntil.value) return true;
    if (verifyLockoutUntil.value && Date.now() >= verifyLockoutUntil.value) {
        verifyLockoutUntil.value = 0;
        verifyAttempts.value = 0;
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

const enterOtpVerifyStep = (identifierRaw, opts = {}) => {
    const id = sanitizeString(String(identifierRaw || '').trim());
    if (!id) return;
    otpVerifyIdentifier.value = id;
    if (!opts.skipStore) {
        localStorage.setItem(AUTH_IDENTIFIER_KEY, id);
    }
    phase.value = 'verify';
    otpDigits.value = ['', '', '', '', '', ''];
    authStore.setRequiresOtp(true);
    nextTick(() => {
        otpInputs.value?.[0]?.focus();
    });
};

const backToSignIn = () => {
    phase.value = 'auth';
    authStore.setRequiresOtp(false);
    localStorage.removeItem(AUTH_IDENTIFIER_KEY);
    otpDigits.value = ['', '', '', '', '', ''];
    verifyAttempts.value = 0;
    verifyLockoutUntil.value = 0;
};

const startResendCountdown = () => {
    if (resendCountdownTimer) clearInterval(resendCountdownTimer);
    resendCountdown.value = 60;
    resendCountdownTimer = setInterval(() => {
        resendCountdown.value--;
        if (resendCountdown.value <= 0 && resendCountdownTimer) {
            clearInterval(resendCountdownTimer);
            resendCountdownTimer = null;
        }
    }, 1000);
};

const handleOtpVerifyInput = (index, event) => {
    const val = (event.target.value || '').replace(/\D/g, '');
    otpDigits.value[index] = val;
    if (val && index < 5) otpInputs.value[index + 1]?.focus();
};

const handleOtpVerifyKeyDown = (index, event) => {
    if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
        otpInputs.value[index - 1]?.focus();
    }
};

const handleOtpVerifyPaste = event => {
    const text = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (text.length === 6) {
        event.preventDefault();
        for (let i = 0; i < 6; i++) otpDigits.value[i] = text[i];
        otpInputs.value[5]?.focus();
    }
};

const submitOtpVerify = async () => {
    if (isVerifyLockedOut()) {
        const secs = Math.ceil((verifyLockoutUntil.value - Date.now()) / 1000);
        showError(`Too many attempts. Try again in ${secs}s.`);
        return;
    }

    const code = otpDigits.value.join('').replace(/\D/g, '');
    if (code.length !== 6) return;
    verifyLoading.value = true;

    try {
        const response = await api.post('/auth/login/verify', {
            identifier: otpVerifyIdentifier.value,
            otp: code,
            remember_device: trustThisBrowser.value,
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
            verifyAttempts.value++;
            if (verifyAttempts.value >= MAX_VERIFY_ATTEMPTS) {
                verifyLockoutUntil.value = Date.now() + VERIFY_LOCKOUT_MS;
                showError('Too many failed attempts. Please wait 2 minutes.');
            } else {
                showError(response.message || 'Verification failed.');
            }
            otpDigits.value = ['', '', '', '', '', ''];
            otpInputs.value[0]?.focus();
            return;
        }
        if (response.success) {
            verifyAttempts.value = 0;
            showSuccess('Verification successful.');
            authStore.setRequiresOtp(false);
            localStorage.removeItem(AUTH_IDENTIFIER_KEY);
            persistDeviceTokenFromLoginResponse(response);
            goToDashboardFullLoad();
        }
    } catch (err) {
        verifyAttempts.value++;
        if (verifyAttempts.value >= MAX_VERIFY_ATTEMPTS) {
            verifyLockoutUntil.value = Date.now() + VERIFY_LOCKOUT_MS;
            showError('Too many failed attempts. Please wait 2 minutes.');
        }
        const requiresPayment = !!(err?.errors?.requires_registration_payment);
        if (requiresPayment) {
            localStorage.setItem(PAYMENT_DETAILS_KEY, JSON.stringify(err));
            router.visit(route('auth.payment.required'));
        } else {
            showError(err.message || 'Verification failed.');
            otpDigits.value = ['', '', '', '', '', ''];
            otpInputs.value[0]?.focus();
        }
    } finally {
        verifyLoading.value = false;
    }
};

const resendOtp = async () => {
    if (resendCountdown.value > 0 || isCountingDown.value) return;
    resendLoading.value = true;

    try {
        const response = await api.post('/auth/login/send-otp', { identifier: otpVerifyIdentifier.value });
        if (response.success) {
            startResendCountdown();
            verifyAttempts.value = 0;
            showSuccess('OTP resent successfully.');
        }
    } catch (err) {
        if (err?.code === 429 && err?.message) {
            if (parseAndStartCountdown(err.message)) {
                return;
            }
        }
        showError(err.message || 'Resend failed.');
    } finally {
        resendLoading.value = false;
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

    if (props.openOtpVerify) {
        const stored = sanitizeString(localStorage.getItem(AUTH_IDENTIFIER_KEY) || '');
        if (stored) {
            enterOtpVerifyStep(stored, { skipStore: true });
        }
    }
});

onBeforeUnmount(() => {
    if (resendCountdownTimer) clearInterval(resendCountdownTimer);
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
            otpStatus.value = response?.message || 'OTP sent successfully.';
            showSuccess(response?.message || 'OTP sent successfully.');
            enterOtpVerifyStep(otpIdentifier);
            startResendCountdown();
            return;
        }

        if (response.success && !response.data?.requires_otp) {
            loginAttempts.value = 0;
            authStore.setRequiresOtp(false);
            persistDeviceTokenFromLoginResponse(response);
            goToDashboardFullLoad();
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
            const sentTo = sanitizeString(response?.data?.identifier || identifier);
            otpStatus.value = `Code sent to ${sentTo}. Enter it below.`;
            showSuccess(response?.message || 'Code sent. Enter the 6-digit code.');
            enterOtpVerifyStep(sentTo);
            startResendCountdown();
        }
    } catch (err) {
        if (err?.code === 429 && err?.message) {
            if (parseAndStartCountdown(err.message)) {
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
        <Head :title="phase === 'verify' ? 'Verify code' : 'Sign In'" />

        <template #title>{{ phase === 'verify' ? 'Two-step verification' : 'Sign in' }}</template>
        <template #subtitle>
            <template v-if="phase === 'verify'">
                Enter the 6-digit code sent to
                <span class="text-gray-900 font-bold">{{ otpVerifyIdentifier }}</span>
            </template>
            <template v-else>
                Sign in with password or request a one-time code — verification stays on this page.
            </template>
        </template>

        <div v-if="phase === 'verify'" class="space-y-8">
            <div class="flex justify-between gap-2 sm:gap-3">
                <input
                    v-for="slot in 6"
                    :key="slot - 1"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="1"
                    ref="otpInputs"
                    v-model="otpDigits[slot - 1]"
                    @input="handleOtpVerifyInput(slot - 1, $event)"
                    @keydown="handleOtpVerifyKeyDown(slot - 1, $event)"
                    @paste="handleOtpVerifyPaste($event)"
                    class="w-full h-14 sm:h-16 text-center text-2xl font-black bg-white border border-gray-100 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none shadow-sm"
                />
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left">
                <input
                    v-model="trustThisBrowser"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="text-xs font-semibold leading-snug text-slate-600">
                    Trust this browser (optional). May reduce extra verification on future sign-ins when your account supports it.
                </span>
            </label>

            <SuButton
                type="button"
                :loading="verifyLoading"
                :disabled="otpDigits.join('').replace(/\D/g, '').length !== 6"
                class="w-full"
                @click="submitOtpVerify"
            >
                Verify &amp; continue
            </SuButton>

            <div class="text-center space-y-3">
                <p v-if="isCountingDown" class="text-xs font-bold text-red-600 bg-red-50 p-3 rounded-xl">
                    {{ countdownMessage }}
                </p>
                <p v-else class="text-xs font-bold text-gray-500 uppercase tracking-widest">
                    Missing the code?
                    <button
                        type="button"
                        class="text-orange-500 hover:text-orange-600 disabled:opacity-50 transition-colors ml-1"
                        :disabled="resendLoading || resendCountdown > 0"
                        @click="resendOtp"
                    >
                        {{ resendCountdown > 0 ? `Retry in ${resendCountdown}s` : 'Send again' }}
                    </button>
                </p>
            </div>

            <button
                type="button"
                class="w-full text-sm font-bold text-blue-600 hover:text-blue-700 hover:underline"
                @click="backToSignIn"
            >
                ← Back to sign in
            </button>
        </div>

        <template v-else>
            <div class="mb-6 rounded-xl bg-slate-100 p-1 grid grid-cols-2 gap-1">
                <button
                    type="button"
                    @click="mode = 'password'"
                    :class="[
                        'py-2.5 rounded-lg text-sm font-bold transition-all',
                        mode === 'password' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900',
                    ]"
                >
                    Password
                </button>
                <button
                    type="button"
                    @click="mode = 'otp'"
                    :class="[
                        'py-2.5 rounded-lg text-sm font-bold transition-all',
                        mode === 'otp' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900',
                    ]"
                >
                    OTP login
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
                            <input v-model="form.remember" type="checkbox" class="sr-only" />
                            <div
                                :class="[
                                    'w-10 h-5 rounded-full transition-colors duration-300',
                                    form.remember ? 'bg-blue-600' : 'bg-gray-200',
                                ]"
                            ></div>
                            <div
                                :class="[
                                    'absolute top-1 left-1 w-3 h-3 bg-white rounded-full transition-transform duration-300 transform',
                                    form.remember ? 'translate-x-5' : '',
                                ]"
                            ></div>
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

                <SuButton :loading="loading" class="w-full">Sign in</SuButton>
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
                    We will send a code to your email or phone. You will enter it on this page — no extra screen.
                </p>

                <SuButton type="button" :loading="otpLoading" class="w-full" :disabled="isCountingDown" @click="handleSendOtp">
                    Send code
                </SuButton>

                <div v-if="isCountingDown" class="p-3 rounded-xl border border-red-200 bg-red-50 text-xs font-bold text-red-700">
                    {{ countdownMessage }}
                </div>
                <div v-else-if="otpStatus" class="p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700">
                    {{ otpStatus }}
                </div>
            </div>
        </template>

        <template #footer>
            <div class="space-y-3">
                <p>
                    Need an account?
                    <Link :href="route('register')" class="text-blue-600 font-bold hover:underline">Sign up here</Link>
                </p>
                <p class="text-xs border-t border-slate-100 pt-3">
                    Questions?
                    <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link>
                </p>
            </div>
        </template>
    </AuthLayout>
</template>

<style scoped>
@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-5px);
    }
    75% {
        transform: translateX(5px);
    }
}
.animate-shake {
    animation: shake 0.4s ease-in-out;
}
@keyframes fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
.animate-fade-in {
    animation: fade-in 0.3s ease-out forwards;
}
</style>
