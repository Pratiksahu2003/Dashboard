<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuButton from '@/Components/SuButton.vue';
import api, { sanitizeString } from '@/api';
import { PAYMENT_DETAILS_KEY } from '@/constants/authStorage';
import {
    EMAIL_VERIFY_LOGIN_FLOW_KEY,
    ensureRegistrationPaymentDetails,
    isEmailVerified,
    isRegistrationFeeSatisfied,
    POST_EMAIL_VERIFY_RELOGIN_MESSAGE,
    POST_VERIFY_LOGIN_NOTICE_KEY,
    useAuth,
} from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useAuthStore } from '@/stores/auth';

const otp = ref(['', '', '', '', '', '']);
const inputs = ref([]);
const loading = ref(false);
const resending = ref(false);
const countdown = ref(0);
const countdownTimer = ref(null);

/** Uses public `login/send-otp` + `login/verify` (no Bearer). */
const loginOtpMode = ref(false);
const loginFlowIdentifier = ref('');
/** Maps to API `remember_device` → optional `device_token` for trusted browser (see AuthApi.md). */
const trustThisBrowser = ref(false);
/** After successful Bearer `/auth/verification/verify` — show confirmation before leaving the page. */
const emailVerificationSucceeded = ref(false);
let redirectAfterVerifyTimer = null;

const {
    getToken,
    getUser,
    clearSession,
    setSession,
    getRegistrationChargesContext,
    setRegistrationChargesContext,
} = useAuth();
const { error: showError, success: showSuccess } = useAlerts();
const authStore = useAuthStore();

const user = computed(() => getUser());
const email = computed(() => sanitizeString(String(user.value?.email || '')));
const charges = computed(() => getRegistrationChargesContext());

const displayTarget = computed(() =>
    loginOtpMode.value ? loginFlowIdentifier.value : email.value,
);

const showFeeHint = computed(() => {
    if (loginOtpMode.value) return false;
    const c = charges.value;
    if (!c || typeof c !== 'object') return false;
    return Number(c.discounted_price) > 0 || Number(c.actual_price) > 0;
});

const feeSatisfiedHint = computed(() => {
    const u = user.value;
    if (!u) return true;
    return isRegistrationFeeSatisfied(u);
});

const readLoginFlow = () => {
    try {
        const raw = sessionStorage.getItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
};

const sendLoginOtp = async () => {
    const id = loginFlowIdentifier.value;
    if (!id) return;
    const response = await api.post('/auth/login/send-otp', { identifier: id });
    if (response && response.success === false) {
        showError(response.message || 'Could not send code.');
        return false;
    }
    if (response?.success) {
        showSuccess(response.message || 'Verification code sent.');
        startCountdown();
        return true;
    }
    return false;
};

onMounted(async () => {
    const flow = readLoginFlow();
    const token = getToken();
    const u = getUser();

    // Bearer token (e.g. right after registration): use /auth/verification/* — stay on this page until verified.
    if (token && u) {
        sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
        loginOtpMode.value = false;
        if (isEmailVerified(u)) {
            clearSession();
            localStorage.setItem(
                POST_VERIFY_LOGIN_NOTICE_KEY,
                'Your email is already verified. Please sign in.',
            );
            router.visit(route('login'), { replace: true });
            return;
        }
        return;
    }

    // No Bearer: login OTP path (identifier in sessionStorage from login screen).
    if (flow?.identifier) {
        loginOtpMode.value = true;
        const id = sanitizeString(String(flow.identifier));
        loginFlowIdentifier.value = id;
        localStorage.setItem('auth_identifier', id);

        if (!flow.otpAlreadySent) {
            try {
                await sendLoginOtp();
            } catch (e) {
                showError(e.message || 'Could not send code. Tap Resend to try again.');
            }
        } else {
            showSuccess('Enter the code sent to your email or phone.');
            startCountdown();
        }
        return;
    }

    router.replace(route('login'));
});

const codeString = computed(() => otp.value.join('').replace(/\D/g, ''));

const handleInput = (index, event) => {
    const val = (event.target.value || '').replace(/\D/g, '');
    otp.value[index] = val.slice(-1);
    if (val && index < 5) inputs.value[index + 1]?.focus();
};

const handleKeyDown = (index, event) => {
    if (event.key === 'Backspace' && !otp.value[index] && index > 0) inputs.value[index - 1]?.focus();
};

const handlePaste = event => {
    const text = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (text.length === 6) {
        event.preventDefault();
        for (let i = 0; i < 6; i++) otp.value[i] = text[i];
        inputs.value[5]?.focus();
    }
};

const resend = async () => {
    if (countdown.value > 0) return;
    resending.value = true;
    try {
        if (loginOtpMode.value) {
            await sendLoginOtp();
        } else {
            const response = await api.post('/auth/verification/resend', { type: 'email' });
            if (response && response.success === false) {
                showError(response.message || 'Could not resend code.');
                return;
            }
            if (response.success) {
                showSuccess(response.message || 'Verification code sent.');
                startCountdown();
            }
        }
    } catch (err) {
        showError(err.message || 'Could not resend code.');
    } finally {
        resending.value = false;
    }
};

const startCountdown = () => {
    if (countdownTimer.value) clearInterval(countdownTimer.value);
    countdown.value = 60;
    countdownTimer.value = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0 && countdownTimer.value) {
            clearInterval(countdownTimer.value);
            countdownTimer.value = null;
        }
    }, 1000);
};

const verifyLoginOtp = async code => {
    const response = await api.post('/auth/login/verify', {
        identifier: loginFlowIdentifier.value,
        otp: code,
        remember_device: trustThisBrowser.value,
        device_name: 'Web Browser',
    });

    if (response && response.success === false) {
        const requiresPayment = !!(response.errors?.requires_registration_payment);
        if (requiresPayment) {
            sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
            localStorage.setItem(
                PAYMENT_DETAILS_KEY,
                JSON.stringify({
                    success: false,
                    message: response.message,
                    errors: response.errors,
                    code: response.code,
                }),
            );
            router.replace(route('auth.payment.required'));
            return;
        }
        showError(response.message || 'Unable to complete sign in.');
        return;
    }

    if (response.success && response.data?.token) {
        sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
        const u = response.data.user;
        const merged = u
            ? {
                  ...u,
                  email_verified_at: response.data.email_verified_at ?? u.email_verified_at,
                  registration_fee_status:
                      response.data.registration_fee_status ?? u.registration_fee_status,
              }
            : u;
        setSession({
            token: response.data.token,
            user: merged,
            deviceToken: response.data.device_token,
        });
        authStore.setRequiresOtp(false);
        localStorage.removeItem('auth_identifier');
        router.replace(route('dashboard'));
    }
};

const verify = async () => {
    const code = codeString.value;
    if (code.length !== 6) return;
    loading.value = true;
    try {
        if (loginOtpMode.value) {
            await verifyLoginOtp(code);
            return;
        }

        const response = await api.post('/auth/verification/verify', { email_otp: code });

        if (response && response.success === false) {
            const requiresPayment = !!(response.errors?.requires_registration_payment);
            if (requiresPayment) {
                // Same envelope as login when fee is unpaid (AuthApi.md); session is typically ended — pay then sign in.
                clearSession();
                localStorage.setItem(
                    PAYMENT_DETAILS_KEY,
                    JSON.stringify({
                        success: false,
                        message: response.message,
                        errors: response.errors,
                        code: response.code,
                    }),
                );
                router.visit(route('auth.payment.required'), { replace: true });
                return;
            }
            showError(response.message || 'Invalid or expired code.');
            otp.value = ['', '', '', '', '', ''];
            inputs.value[0]?.focus();
            return;
        }

        if (response.success && response.data?.user) {
            const verifiedUser = response.data.user;
            const paymentRequired = verifiedUser?.payment_required === true;
            const savedCharges = getRegistrationChargesContext();

            clearSession();

            if (paymentRequired) {
                if (savedCharges && typeof savedCharges === 'object') {
                    setRegistrationChargesContext(savedCharges);
                }
                ensureRegistrationPaymentDetails(verifiedUser, () => savedCharges);

                const defaultDescription =
                    'Your email is verified. Complete the one-time registration fee, then sign in to access your dashboard.';
                let mergedErrors = {
                    requires_registration_payment: true,
                    role: verifiedUser.role,
                    description: defaultDescription,
                };
                try {
                    const raw = localStorage.getItem(PAYMENT_DETAILS_KEY);
                    const parsed = raw ? JSON.parse(raw) : {};
                    if (parsed.errors && typeof parsed.errors === 'object') {
                        mergedErrors = {
                            ...parsed.errors,
                            requires_registration_payment: true,
                            role: verifiedUser.role ?? parsed.errors.role,
                        };
                    }
                } catch {
                    /* ignore */
                }
                if (
                    !mergedErrors.description ||
                    typeof mergedErrors.description !== 'string' ||
                    !mergedErrors.description.trim()
                ) {
                    mergedErrors.description =
                        (savedCharges &&
                            typeof savedCharges === 'object' &&
                            typeof savedCharges.description === 'string' &&
                            savedCharges.description.trim()) ||
                        defaultDescription;
                }

                localStorage.setItem(
                    PAYMENT_DETAILS_KEY,
                    JSON.stringify({
                        success: true,
                        message:
                            typeof response.message === 'string' && response.message.trim()
                                ? response.message.trim()
                                : 'Email verified successfully.',
                        code: response.code ?? 200,
                        source: 'email_verification',
                        verified_user: verifiedUser,
                        errors: mergedErrors,
                    }),
                );
                router.visit(route('auth.payment.required'), { replace: true });
                return;
            }

            localStorage.setItem(POST_VERIFY_LOGIN_NOTICE_KEY, POST_EMAIL_VERIFY_RELOGIN_MESSAGE);
            emailVerificationSucceeded.value = true;
            redirectAfterVerifyTimer = window.setTimeout(() => {
                router.visit(route('login'), { replace: true });
            }, 1400);
        }
    } catch (err) {
        const requiresPayment = !!(err?.errors?.requires_registration_payment);
        if (requiresPayment) {
            sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
            clearSession();
            localStorage.setItem(PAYMENT_DETAILS_KEY, JSON.stringify(err));
            router.visit(route('auth.payment.required'), { replace: true });
            return;
        }
        showError(err.message || 'Verification failed.');
        otp.value = ['', '', '', '', '', ''];
        inputs.value[0]?.focus();
    } finally {
        loading.value = false;
    }
};

const goLogin = () => {
    sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
    clearSession();
    router.replace(route('login'));
};

onBeforeUnmount(() => {
    if (countdownTimer.value) clearInterval(countdownTimer.value);
    if (redirectAfterVerifyTimer) clearTimeout(redirectAfterVerifyTimer);
});
</script>

<template>
    <AuthLayout>
        <Head title="Verify Email" />

        <template #title>Verify your email</template>
        <template #subtitle>
            <template v-if="loginOtpMode">
                Enter the 6-digit code sent to
                <span class="text-slate-900 dark:text-white font-bold">{{ displayTarget || 'your inbox' }}</span>
                to confirm your account and sign in.
            </template>
            <template v-else>
                Enter the code we sent to
                <span class="text-slate-900 dark:text-white font-bold">{{ displayTarget || 'your inbox' }}</span>.
                You must verify before you can use the dashboard.
            </template>
        </template>

        <div v-if="showFeeHint && !feeSatisfiedHint" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-900">
            After verification, sign in to complete onboarding. A registration fee may apply for your role
            <span v-if="charges?.description">({{ charges.description }})</span>.
        </div>

        <div
            v-if="emailVerificationSucceeded"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-semibold text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100"
            role="status"
        >
            <p class="font-black text-emerald-900 dark:text-emerald-50">Email verified</p>
            <p class="mt-1 text-xs leading-relaxed opacity-95">
                Your session was ended for security. Taking you to sign in — use the same email and password you registered with.
            </p>
        </div>

        <div class="space-y-8" :class="{ 'pointer-events-none opacity-60': emailVerificationSucceeded }">
            <div class="flex justify-between gap-2 sm:gap-3">
                <input
                    v-for="(_, index) in 6"
                    :key="index"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="1"
                    ref="inputs"
                    v-model="otp[index]"
                    @input="handleInput(index, $event)"
                    @keydown="handleKeyDown(index, $event)"
                    @paste="handlePaste($event)"
                    class="w-full h-14 sm:h-16 text-center text-2xl font-black bg-white/50 dark:bg-slate-900/50 backdrop-blur-md border border-gray-100 dark:border-slate-800 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none dark:text-white shadow-sm"
                />
            </div>

            <label
                v-if="loginOtpMode"
                class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-left dark:border-slate-700 dark:bg-slate-900/40"
            >
                <input v-model="trustThisBrowser" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                <span class="text-xs font-semibold leading-snug text-slate-600 dark:text-slate-300">
                    Trust this browser (optional). If your account allows it, we will remember this device for fewer prompts on future sign-ins.
                </span>
            </label>

            <SuButton
                :loading="loading"
                :disabled="emailVerificationSucceeded || codeString.length !== 6"
                class="w-full"
                @click="verify"
            >
                {{ loginOtpMode ? 'Verify & continue' : 'Verify email' }}
            </SuButton>

            <div class="text-center text-xs font-bold text-slate-500">
                Did not receive it?
                <button
                    type="button"
                    class="ml-1 text-blue-600 hover:underline disabled:opacity-50"
                    :disabled="resending || countdown > 0"
                    @click="resend"
                >
                    {{ countdown > 0 ? `Resend in ${countdown}s` : 'Resend code' }}
                </button>
            </div>

            <button
                type="button"
                class="w-full text-center text-[11px] font-bold text-slate-400 hover:text-slate-600"
                @click="goLogin"
            >
                Cancel and return to sign in
            </button>
        </div>

        <template #footer>
            <div class="space-y-3">
                <p class="text-xs text-slate-500">
                    Wrong email?
                    <Link :href="route('register')" class="text-blue-600 font-bold hover:underline">Start over</Link>
                </p>
            </div>
        </template>
    </AuthLayout>
</template>
