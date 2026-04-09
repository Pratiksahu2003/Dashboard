<script setup>
/**
 * Registration fee / activation (API: errors.requires_registration_payment, payment_link, payment_session_id).
 * After email verification, payload may include success + verified_user (see VerifyEmail.vue).
 */
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuButton from '@/Components/SuButton.vue';
import api from '@/api';
import { PAYMENT_DETAILS_KEY } from '@/constants/authStorage';
import { POST_VERIFY_LOGIN_NOTICE_KEY, useAuth } from '@/composables/useAuth';

const parsePaymentPayload = () => {
    try {
        return JSON.parse(localStorage.getItem(PAYMENT_DETAILS_KEY) || '{}');
    } catch {
        return {};
    }
};

const paymentData = ref(parsePaymentPayload());
const { clearSession } = useAuth();

const paymentInfo = computed(() => {
    const p = paymentData.value;
    if (!p || typeof p !== 'object') return {};
    return p.errors && typeof p.errors === 'object' ? p.errors : p;
});

/** Email verification succeeded (200) but payment still required — richer payload from VerifyEmail. */
const isEmailVerificationSuccess = computed(
    () =>
        paymentData.value?.source === 'email_verification' &&
        paymentData.value?.success === true &&
        paymentData.value?.verified_user &&
        typeof paymentData.value.verified_user === 'object',
);

const verifiedUser = computed(() =>
    isEmailVerificationSuccess.value ? paymentData.value.verified_user : null,
);

const verificationSuccessMessage = computed(() => {
    const m = paymentData.value?.message;
    return typeof m === 'string' && m.trim() ? m.trim() : 'Email verified successfully.';
});

const formatRole = role => {
    if (!role || typeof role !== 'string') return '—';
    return role.charAt(0).toUpperCase() + role.slice(1).toLowerCase();
};

const formatVerificationStatus = status => {
    if (status == null || status === '') return '—';
    if (typeof status !== 'string') return String(status);
    const key = status.toLowerCase();
    const map = {
        pending: 'Pending — complete payment to activate your account',
        verified: 'Verified',
    };
    return map[key] || status;
};

const formatFeeStatus = status => {
    if (status == null || status === '') return 'Not set';
    if (typeof status === 'boolean') return status ? 'Paid' : 'Due';
    if (typeof status === 'string') {
        const lower = status.toLowerCase();
        if (lower === 'paid' || lower === 'completed') return 'Paid';
        if (lower === 'pending' || lower === 'unpaid') return 'Due';
    }
    return String(status);
};

const formatVerifiedAt = iso => {
    if (!iso || typeof iso !== 'string') return '—';
    try {
        return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
};

const hasPricing = computed(() => {
    const e = paymentInfo.value;
    const d = Number(e?.discounted_price);
    const a = Number(e?.actual_price);
    return (Number.isFinite(d) && d > 0) || (Number.isFinite(a) && a > 0);
});

const handleLogout = async () => {
    try {
        await api.post('/auth/logout');
    } catch (e) {
        /* ignore */
    }
    clearSession();
    localStorage.setItem(
        POST_VERIFY_LOGIN_NOTICE_KEY,
        'Please sign in again to continue. If you completed payment, use your email and password to access the dashboard.',
    );
    router.visit(route('login'));
};

const proceedToPayment = () => {
    const info = paymentInfo.value;
    const link = info?.payment_link;
    if (link) {
        window.location.href = link;
        return;
    }
    const sessionId = info?.payment_session_id;
    if (sessionId && typeof sessionId === 'string') {
        console.warn('payment_link missing; payment_session_id present — configure checkout URL on backend or extend this handler.');
    }
};

const apiMessage = computed(() => {
    if (isEmailVerificationSuccess.value) return '';
    const m = paymentData.value?.message;
    return typeof m === 'string' && m.trim() ? m.trim() : '';
});

const canProceedToCheckout = computed(() => !!paymentInfo.value?.payment_link);

onMounted(() => {
    paymentData.value = parsePaymentPayload();
    const root = paymentData.value;
    const info =
        root?.errors && typeof root.errors === 'object' ? root.errors : root;
    if (!info?.requires_registration_payment) {
        router.visit(route('login'));
    }
});
</script>

<template>
    <AuthLayout>
        <Head title="Account Activation" />

        <template #title>
            {{ isEmailVerificationSuccess ? 'Almost there' : 'Activation Required' }}
        </template>
        <template #subtitle>
            <template v-if="isEmailVerificationSuccess">
                {{ paymentInfo.description || 'Your email is confirmed. One more step to unlock your dashboard.' }}
            </template>
            <template v-else>
                {{ paymentInfo.description || 'To access your professional tools, please complete the one-time registration fee payment.' }}
            </template>
        </template>

        <div
            v-if="isEmailVerificationSuccess"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100"
            role="status"
        >
            <p class="font-black text-emerald-900 dark:text-emerald-50">{{ verificationSuccessMessage }}</p>
            <p class="mt-2 text-xs font-semibold leading-relaxed opacity-95">
                For security, your previous session was ended. After payment, sign in with your email and password to continue.
            </p>
        </div>

        <div
            v-if="verifiedUser"
            class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-4 text-left dark:border-slate-700 dark:bg-slate-900/50"
        >
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Your account</p>
            <dl class="mt-3 space-y-2 text-xs font-semibold text-slate-800 dark:text-slate-200">
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500 dark:text-slate-400">Email</dt>
                    <dd class="text-right font-bold text-slate-900 dark:text-white">{{ verifiedUser.email || '—' }}</dd>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500 dark:text-slate-400">Role</dt>
                    <dd class="text-right">{{ formatRole(verifiedUser.role) }}</dd>
                </div>
                <div v-if="verifiedUser.phone" class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500 dark:text-slate-400">Phone</dt>
                    <dd class="text-right">{{ verifiedUser.phone }}</dd>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500 dark:text-slate-400">Verified at</dt>
                    <dd class="text-right text-emerald-700 dark:text-emerald-400">
                        {{ formatVerifiedAt(verifiedUser.email_verified_at) }}
                    </dd>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500 dark:text-slate-400">Account status</dt>
                    <dd class="text-right">{{ formatVerificationStatus(verifiedUser.verification_status) }}</dd>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500 dark:text-slate-400">Registration fee</dt>
                    <dd class="text-right">{{ formatFeeStatus(verifiedUser.registration_fee_status) }}</dd>
                </div>
            </dl>
        </div>

        <p
            v-if="apiMessage"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
        >
            {{ apiMessage }}
        </p>

        <div class="space-y-10">
            <div v-if="hasPricing" class="relative group">
                <div class="absolute inset-0 bg-gradient-to-r from-orange-500 to-blue-600 rounded-[40px] blur-2xl opacity-10 group-hover:opacity-20 transition-opacity"></div>
                <div class="bg-white/50 dark:bg-slate-900/50 backdrop-blur-xl border-2 border-white dark:border-slate-800 p-10 rounded-[40px] text-center relative z-10 shadow-xl">
                    <span class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 mb-4 block">Limited Membership Offer</span>
                    <div class="flex items-center justify-center gap-4 mb-2">
                        <span class="text-6xl font-black text-gray-900 dark:text-white tracking-tighter">₹{{ paymentInfo.discounted_price }}</span>
                        <div class="flex flex-col items-start">
                            <span class="text-lg text-gray-400 line-through font-bold">₹{{ paymentInfo.actual_price }}</span>
                            <span class="text-[10px] font-black text-green-500 uppercase tracking-widest">Active Discount</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="isEmailVerificationSuccess" class="rounded-2xl border border-dashed border-slate-200 bg-white/40 px-4 py-6 text-center text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-400">
                Pricing will appear here when your checkout link is ready. If you do not see a payment button, sign in after your administrator enables billing, or contact support.
            </div>

            <div class="space-y-4 px-2">
                <div
                    v-for="(benefit, i) in ['Lifetime Dashboard Access', 'Verified Professional Badge', 'Global Network Connectivity', 'Priority Support']"
                    :key="i"
                    class="flex items-center text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                >
                    <div class="w-5 h-5 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center mr-3 scale-90">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    {{ benefit }}
                </div>
            </div>

            <div class="space-y-4">
                <SuButton :disabled="!canProceedToCheckout" class="w-full" @click="proceedToPayment">
                    {{ canProceedToCheckout ? 'Complete Activation via Cashfree' : 'Payment link unavailable' }}
                </SuButton>
                <p v-if="!canProceedToCheckout && paymentInfo.payment_session_id" class="text-center text-xs text-amber-800 font-semibold dark:text-amber-200">
                    Checkout link not provided — contact support with your order reference.
                </p>

                <button
                    type="button"
                    class="w-full text-[10px] font-black text-gray-300 hover:text-gray-500 uppercase tracking-[0.2em] transition-colors italic"
                    @click="handleLogout"
                >
                    Cancel & return to sign in
                </button>
            </div>
        </div>
        <template #footer>
            <div class="space-y-3">
                <p>Payment issues? <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link></p>
            </div>
        </template>
    </AuthLayout>
</template>
