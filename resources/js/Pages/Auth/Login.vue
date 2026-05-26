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
    AUTH_REDIRECT_REASON_KEY,
    AUTH_RETURN_TO_KEY,
    PAYMENT_DETAILS_KEY,
    POST_VERIFY_LOGIN_NOTICE_KEY,
} from '@/constants/authStorage';
import { useOtpCountdown } from '@/composables/useOtpCountdown';
import { useAuthStore } from '@/stores/auth';
import { useAlerts } from '@/composables/useAlerts';
import { describeFirebaseAuthError, hasFirebaseAuthConfig, signInWithFirebaseProvider } from '@/services/firebaseAuth';
import { describePasskeyError, isPasskeySupported, loginWithPasskey } from '@/services/passkeys';
import {
    deviceName,
    extractAuthToken,
    needsOnboarding,
    needsPayment,
    persistAuthToken,
    persistPaymentGate,
} from '@/services/authFlow';
import { socialPost } from '@/services/socialApi';

const props = defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String },
    openOtpVerify: { type: Boolean, default: false },
    returnTo: { type: String, default: '' },
    message: { type: String, default: '' },
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
const socialLoading = ref('');
const passkeyLoading = ref(false);
const passkeySupported = ref(false);
let resendCountdownTimer = null;

const verifyAttempts = ref(0);
const verifyLockoutUntil = ref(0);
const MAX_VERIFY_ATTEMPTS = 5;
const VERIFY_LOCKOUT_MS = 120_000;

const authStore = useAuthStore();
const { error: showError, success: showSuccess, info: showInfo } = useAlerts();
const { countdownMessage, isCountingDown, parseAndStartCountdown } = useOtpCountdown();
const firebaseConfigured = hasFirebaseAuthConfig();
const socialProviders = [
    { id: 'google', label: 'Google' },
    { id: 'facebook', label: 'Facebook' },
];

const persistDeviceTokenFromLoginResponse = response => {
    const deviceTok = response?.data?.device_token;
    if (typeof deviceTok === 'string' && deviceTok !== '') {
        localStorage.setItem(AUTH_DEVICE_TOKEN_KEY, deviceTok);
    }
};

const parseAllowedRedirectHosts = () =>
    String(import.meta.env.VITE_AUTH_REDIRECT_ALLOWED_HOSTS || '')
        .split(',')
        .map(host => host.trim().toLowerCase())
        .filter(Boolean);

const isAllowedRedirectHost = host => {
    const normalized = String(host || '').trim().toLowerCase();
    if (!normalized) return false;

    if (typeof window !== 'undefined' && normalized === window.location.hostname.toLowerCase()) {
        return true;
    }

    if (normalized === 'suganta.com' || normalized.endsWith('.suganta.com')) {
        return true;
    }

    const configuredHosts = parseAllowedRedirectHosts();
    return configuredHosts.some((allowed) =>
        normalized === allowed || normalized.endsWith(`.${allowed}`),
    );
};

const normalizeInternalPath = value => {
    if (typeof value !== 'string') return '';
    if (!value.startsWith('/') || value.startsWith('//')) return '';
    return value;
};

const sanitizePostLoginTarget = raw => {
    const candidate = sanitizeString(String(raw || '').trim());
    if (!candidate) return '';

    const internalPath = normalizeInternalPath(candidate);
    if (internalPath) return internalPath;

    if (typeof window === 'undefined') return '';

    try {
        const parsed = new URL(candidate, window.location.origin);
        if (parsed.origin === window.location.origin) {
            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }

        const host = parsed.hostname.toLowerCase();
        if (isAllowedRedirectHost(host)) {
            return parsed.toString();
        }
    } catch {
        return '';
    }

    return '';
};

const resolvePostLoginTarget = () => {
    if (typeof window === 'undefined') return '';

    const fromProp = sanitizePostLoginTarget(props.returnTo);
    if (fromProp) return fromProp;

    try {
        const qp = new URLSearchParams(window.location.search);
        const fromQuery = sanitizePostLoginTarget(qp.get('redirect') || '');
        if (fromQuery) return fromQuery;
    } catch {
        // Ignore malformed search params.
    }

    const fromReferrer = sanitizePostLoginTarget(document.referrer || '');
    if (fromReferrer) return fromReferrer;

    return '';
};

const storePostLoginTarget = target => {
    if (typeof window === 'undefined') return;
    if (!target) {
        sessionStorage.removeItem(AUTH_RETURN_TO_KEY);
        return;
    }
    sessionStorage.setItem(AUTH_RETURN_TO_KEY, target);
};

const takeStoredPostLoginTarget = () => {
    if (typeof window === 'undefined') return '';
    const target = sanitizePostLoginTarget(sessionStorage.getItem(AUTH_RETURN_TO_KEY) || '');
    sessionStorage.removeItem(AUTH_RETURN_TO_KEY);
    return target;
};

const isExternalTarget = target => /^https?:\/\//i.test(String(target || ''));

/** Bust server-side SPA auth cache, then open destination (avoids stale "logged out" cache after OTP/login). */
const goToPostLoginDestination = (token = null) => {
    const target = takeStoredPostLoginTarget();

    if (target && isExternalTarget(target) && !token) {
        window.location.assign(target);
        return;
    }

    const payload = {
        redirect_to: target && !isExternalTarget(target) ? target : null,
    };
    if (token) payload.token = token;

    router.post(route('auth.sync-cache'), payload, {
        replace: true,
        preserveState: false,
        preserveScroll: false,
        onSuccess: () => {
            if (target && isExternalTarget(target)) {
                window.location.assign(target);
            }
        },
        onError: () => {
            if (target) {
                window.location.assign(target);
                return;
            }
            window.location.assign(route('dashboard'));
        },
    });
};

const finishModernAuthFlow = response => {
    const token = extractAuthToken(response);
    if (token) persistAuthToken(token);

    if (needsOnboarding(response)) {
        router.visit(route('auth.onboarding'), { replace: true });
        return;
    }

    if (needsPayment(response)) {
        persistPaymentGate(response);
        router.visit(route('auth.payment.required'), { replace: true });
        return;
    }

    goToPostLoginDestination(token);
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
            goToPostLoginDestination();
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

onMounted(async () => {
    passkeySupported.value = isPasskeySupported();

    try {
        const me = await api.get('/auth/user');
        const payload = me?.data;
        if (payload?.authenticated === true && payload?.user && typeof payload.user.id !== 'undefined') {
            goToPostLoginDestination();
            return;
        }
    } catch {
        /* stay on login */
    }

    storePostLoginTarget(resolvePostLoginTarget());

    const postVerifyLs = localStorage.getItem(POST_VERIFY_LOGIN_NOTICE_KEY);
    const postVerifySs = sessionStorage.getItem('post_verify_notice');
    const postVerify = postVerifyLs || postVerifySs;
    if (postVerify) {
        showSuccess(sanitizeString(postVerify));
        localStorage.removeItem(POST_VERIFY_LOGIN_NOTICE_KEY);
        sessionStorage.removeItem('post_verify_notice');
    }
    const reason = localStorage.getItem(AUTH_REDIRECT_REASON_KEY);
    if (reason) {
        showError(sanitizeString(reason));
        localStorage.removeItem(AUTH_REDIRECT_REASON_KEY);
    }

    if (props.openOtpVerify) {
        const stored = sanitizeString(localStorage.getItem(AUTH_IDENTIFIER_KEY) || '');
        if (stored) {
            enterOtpVerifyStep(stored, { skipStore: true });
        }
    }
});

const lastShownStatus = ref('');
const lastShownMessage = ref('');

watch(
    () => props.status,
    (status) => {
        const normalized = sanitizeString(status || '');
        if (!normalized || normalized === lastShownStatus.value) return;
        lastShownStatus.value = normalized;
        showSuccess(normalized);
    },
    { immediate: true },
);

watch(
    () => props.message,
    (message) => {
        const normalized = sanitizeString(message || '');
        if (!normalized || normalized === lastShownMessage.value) return;
        lastShownMessage.value = normalized;
        showInfo(normalized, 'Notice');
    },
    { immediate: true },
);

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
            goToPostLoginDestination();
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

const handleSocialLogin = async provider => {
    if (!firebaseConfigured) {
        showError('Firebase social login is not configured for this environment.');
        return;
    }

    socialLoading.value = provider;
    fieldErrors.value = {};
    try {
        const firebase = await signInWithFirebaseProvider(provider);
        const response = await socialPost('/auth/social-login', {
            provider,
            token: firebase.token,
            id_token: firebase.token,
            firebase_token: firebase.token,
            device_name: deviceName(),
        }, { skipAuthRedirect: true });

        if (response?.success === false && !needsPayment(response)) {
            throw response;
        }

        finishModernAuthFlow(response);
    } catch (err) {
        if (err?.code === 'auth/popup-closed-by-user' || err?.code === 'auth/cancelled-popup-request') {
            showInfo('Social sign in was cancelled.', 'Sign in cancelled');
        } else {
            fieldErrors.value = err?.errors || {};
            showError(describeFirebaseAuthError(err, provider));
        }
    } finally {
        socialLoading.value = '';
    }
};

const handlePasskeyLogin = async () => {
    if (!passkeySupported.value) {
        showError('Passkeys are not supported in this browser.');
        return;
    }

    passkeyLoading.value = true;
    fieldErrors.value = {};
    try {
        const identifier = sanitizeString(form.identifier.trim());
        const response = await loginWithPasskey(identifier);

        if (response?.success === false && !needsPayment(response)) {
            throw response;
        }

        finishModernAuthFlow(response);
    } catch (err) {
        fieldErrors.value = err?.errors || {};
        showError(describePasskeyError(err));
    } finally {
        passkeyLoading.value = false;
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
            <div class="mb-4 space-y-2.5">
                <div v-if="firebaseConfigured" class="grid grid-cols-2 gap-2">
                    <button
                        v-for="provider in socialProviders"
                        :key="provider.id"
                        type="button"
                        :class="[
                            'flex h-11 items-center justify-center gap-2 rounded-xl px-3 text-[13px] font-black leading-none shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60',
                            provider.id === 'google'
                                ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                : 'border border-[#1877F2] bg-[#1877F2] text-white hover:bg-[#166FE5]',
                        ]"
                        :disabled="!!socialLoading || loading || otpLoading || passkeyLoading"
                        @click="handleSocialLogin(provider.id)"
                    >
                        <svg v-if="provider.id === 'google'" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.5-.2-2.2H12v4.2h6.5c-.3 1.4-1.1 2.7-2.3 3.5v2.9h3.7c2.2-2 3.6-4.9 3.6-8.4z" />
                            <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.7-2.9c-1 .7-2.4 1.1-4.2 1.1-3.1 0-5.7-2.1-6.6-4.9H1.6v3C3.5 21.3 7.4 24 12 24z" />
                            <path fill="#FBBC05" d="M5.4 14.4c-.2-.7-.4-1.5-.4-2.4s.1-1.6.4-2.4v-3H1.6C.6 8.2 0 10.1 0 12s.6 3.8 1.6 5.4l3.8-3z" />
                            <path fill="#EA4335" d="M12 4.7c1.8 0 3.3.6 4.6 1.8L20 3.1C17.9 1.2 15.2 0 12 0 7.4 0 3.5 2.7 1.6 6.6l3.8 3C6.3 6.8 8.9 4.7 12 4.7z" />
                        </svg>
                        <svg v-else class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="currentColor" d="M24 12.1C24 5.4 18.6 0 12 0S0 5.4 0 12.1c0 6 4.4 11 10.1 11.9v-8.4h-3v-3.5h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9v2.3h3.4l-.5 3.5h-2.9V24c5.8-.9 10.2-5.9 10.2-11.9z" />
                        </svg>
                        <span>{{ socialLoading === provider.id ? 'Connecting...' : provider.label }}</span>
                    </button>
                </div>

                <button
                    v-if="passkeySupported"
                    type="button"
                    class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-[13px] font-black text-emerald-800 shadow-sm transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="passkeyLoading || !!socialLoading || loading || otpLoading"
                    @click="handlePasskeyLogin"
                >
                    <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7.5 10.5a4.5 4.5 0 1 1 8.6 1.8l3.4 3.4a1 1 0 0 1 .3.7V19a1 1 0 0 1-1 1h-2.2a1 1 0 0 1-1-1v-1.1h-1.2a1 1 0 0 1-1-1v-1.2l-1.2-1.2a4.5 4.5 0 0 1-4.7-4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M10.5 9.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    {{ passkeyLoading ? 'Checking...' : 'Passkey' }}
                </button>

                <div v-if="firebaseConfigured || passkeySupported" class="flex items-center gap-3 pt-1">
                    <span class="h-px flex-1 bg-slate-200"></span>
                    <span class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">or</span>
                    <span class="h-px flex-1 bg-slate-200"></span>
                </div>
            </div>

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
                    We’ll send a secure 6-digit code to your email or phone. Enter it here to continue.
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
