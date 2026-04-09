<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuButton from '@/Components/SuButton.vue';
import api, { sanitizeString } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';

const identifier = ref(sanitizeString(localStorage.getItem('auth_identifier') || ''));
const otp = ref(['', '', '', '', '', '']);
const inputs = ref([]);

const loading = ref(false);
const resending = ref(false);
const countdown = ref(0);
const countdownTimer = ref(null);
const verifyAttempts = ref(0);
const verifyLockoutUntil = ref(0);
const { setSession } = useAuth();
const { error: showError, success: showSuccess } = useAlerts();

const MAX_VERIFY_ATTEMPTS = 5;
const VERIFY_LOCKOUT_MS = 120_000;

const isVerifyLockedOut = () => {
    if (verifyLockoutUntil.value && Date.now() < verifyLockoutUntil.value) return true;
    if (verifyLockoutUntil.value && Date.now() >= verifyLockoutUntil.value) {
        verifyLockoutUntil.value = 0;
        verifyAttempts.value = 0;
    }
    return false;
};

const handleInput = (index, event) => {
    const val = (event.target.value || '').replace(/\D/g, '');
    otp.value[index] = val;
    if (val && index < 5) inputs.value[index + 1].focus();
};

const handleKeyDown = (index, event) => {
    if (event.key === 'Backspace' && !otp.value[index] && index > 0) inputs.value[index - 1].focus();
};

const handlePaste = (event) => {
    const text = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (text.length === 6) {
        event.preventDefault();
        for (let i = 0; i < 6; i++) otp.value[i] = text[i];
        if (inputs.value[5]) inputs.value[5].focus();
    }
};

const verifyOtp = async () => {
    if (isVerifyLockedOut()) {
        const secs = Math.ceil((verifyLockoutUntil.value - Date.now()) / 1000);
        showError(`Too many attempts. Try again in ${secs}s.`);
        return;
    }

    const code = otp.value.join('').replace(/\D/g, '');
    if (code.length !== 6) return;
    loading.value = true;

    try {
        const response = await api.post('/auth/login/verify', {
            identifier: identifier.value,
            otp: code,
            device_name: 'Web Browser'
        });
        if (response.success && response.data?.token) {
            verifyAttempts.value = 0;
            showSuccess('Verification successful.');
            setSession({ token: response.data.token, user: response.data.user });
            localStorage.removeItem('auth_identifier');
            router.visit(route('dashboard'));
        }
    } catch (err) {
        verifyAttempts.value++;
        if (verifyAttempts.value >= MAX_VERIFY_ATTEMPTS) {
            verifyLockoutUntil.value = Date.now() + VERIFY_LOCKOUT_MS;
            showError('Too many failed attempts. Please wait 2 minutes.');
        }
        const requiresPayment = !!(err?.errors?.requires_registration_payment);
        if (requiresPayment) {
            localStorage.setItem('payment_details', JSON.stringify(err));
            router.visit(route('auth.payment.required'));
        } else {
            showError(err.message || 'Verification failed.');
            otp.value = ['', '', '', '', '', ''];
            if (inputs.value[0]) inputs.value[0].focus();
        }
    } finally {
        loading.value = false;
    }
};

const resendOtp = async () => {
    if (countdown.value > 0) return;
    resending.value = true;

    try {
        const response = await api.post('/auth/login/send-otp', { identifier: identifier.value });
        if (response.success) {
            startCountdown();
            verifyAttempts.value = 0;
            showSuccess('OTP resent successfully.');
        }
    } catch (err) {
        showError(err.message || 'Resend failed.');
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

onMounted(() => {
    if (!identifier.value) router.visit(route('login'));
    if (inputs.value[0]) inputs.value[0].focus();
});

onBeforeUnmount(() => {
    if (countdownTimer.value) clearInterval(countdownTimer.value);
});
</script>

<template>
    <AuthLayout>
        <Head title="Secure Verification" />

        <template #title>Two-Step Verification</template>
        <template #subtitle>
            Protecting your identity. Enter the secure 6-digit code sent to <span class="text-gray-900 dark:text-white font-bold">{{ identifier }}</span>
        </template>

        <div class="space-y-10">
            <div class="flex justify-between gap-3">
                <input
                    v-for="(digit, index) in 6"
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

            <SuButton @click="verifyOtp" :loading="loading" :disabled="otp.join('').length !== 6" class="w-full">
                Verify Secure Identity
            </SuButton>
            <div class="text-center space-y-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                    Missing the code? 
                    <button @click="resendOtp" :disabled="resending || countdown > 0" class="text-orange-500 hover:text-orange-600 disabled:opacity-50 transition-colors ml-1">
                        {{ countdown > 0 ? `Retry in ${countdown}s` : 'Request Again' }}
                    </button>
                </p>
            </div>
        </div>

        <template #footer>
            <div class="space-y-3">
                <p>Wrong identity? <Link :href="route('login')" class="text-blue-600 font-bold hover:underline">Change Account</Link></p>
                <p class="text-xs border-t border-slate-100 pt-3 text-gray-500 font-medium">Locked out or issues? <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link></p>
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
</style>
