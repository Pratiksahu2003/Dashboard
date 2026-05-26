<script setup>
/**
 * Registration fee / activation (API: errors.requires_registration_payment, payment_link, payment_session_id).
 * UI: forced light / white surfaces (no dark: — readable when OS prefers dark mode).
 */
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuButton from '@/Components/SuButton.vue';
import api from '@/api';
import { AUTH_TOKEN_KEY, PAYMENT_DETAILS_KEY } from '@/constants/authStorage';
import { POST_VERIFY_LOGIN_NOTICE_KEY, useAuth } from '@/composables/useAuth';

const parsePaymentPayload = () => {
    try {
        return JSON.parse(localStorage.getItem(PAYMENT_DETAILS_KEY) || '{}');
    } catch {
        return {};
    }
};

const paymentData = ref(parsePaymentPayload());
const orderLoading = ref(false);
const paymentError = ref('');
const { clearSession } = useAuth();

const paymentInfo = computed(() => {
    const p = paymentData.value;
    if (!p || typeof p !== 'object') return {};
    return p.errors && typeof p.errors === 'object' ? p.errors : p;
});

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
    const d = Number(e?.discounted_price ?? e?.amount);
    const a = Number(e?.actual_price);
    return (Number.isFinite(d) && d > 0) || (Number.isFinite(a) && a > 0);
});

const checkoutLink = computed(() => paymentInfo.value?.payment_link || paymentInfo.value?.checkout_url || '');

const shouldCreateRegistrationOrder = computed(() => {
    const info = paymentInfo.value;
    return !!(info?.requires_registration_payment || info?.needs_payment)
        && !checkoutLink.value
        && !info?.already_paid;
});

const handleLogout = async () => {
    clearSession();
    localStorage.setItem(
        POST_VERIFY_LOGIN_NOTICE_KEY,
        'Please sign in again to continue. If you completed payment, use your email and password to access the dashboard.',
    );
    try {
        await router.post(route('logout'), {}, {
            replace: true,
            preserveState: false,
            preserveScroll: false,
        });
    } catch {
        window.location.assign(route('login'));
    }
};

const proceedToPayment = () => {
    const info = paymentInfo.value;
    const link = checkoutLink.value;
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

const canProceedToCheckout = computed(() => !!checkoutLink.value);

const mergePaymentOrder = order => {
    const data = order?.data && typeof order.data === 'object' ? order.data : order;
    if (!data || typeof data !== 'object') return;

    const root = paymentData.value && typeof paymentData.value === 'object' ? paymentData.value : {};
    const info = paymentInfo.value;
    const nextErrors = {
        ...info,
        ...data,
        requires_registration_payment: true,
        payment_link: data.payment_link || data.checkout_url || info.payment_link,
        discounted_price: data.amount ?? info.discounted_price,
        currency: data.currency || info.currency || 'INR',
    };

    const next = {
        ...root,
        success: false,
        message: root.message || data.message || 'Registration fee payment is required to complete login.',
        errors: nextErrors,
        code: root.code || data.code || 200,
    };
    paymentData.value = next;
    localStorage.setItem(PAYMENT_DETAILS_KEY, JSON.stringify(next));
};

const ensurePaymentOrder = async () => {
    if (!shouldCreateRegistrationOrder.value || orderLoading.value) return;

    orderLoading.value = true;
    paymentError.value = '';
    try {
        const response = await api.post('/payment/create-order', {});
        mergePaymentOrder(response);
        if (response?.already_paid || response?.data?.already_paid) {
            router.post(route('auth.sync-cache'), { token: localStorage.getItem(AUTH_TOKEN_KEY) || null }, {
                replace: true,
                preserveState: false,
                preserveScroll: false,
            });
        }
    } catch (err) {
        paymentError.value = err?.message || 'Unable to create payment order. Please try again.';
    } finally {
        orderLoading.value = false;
    }
};

onMounted(() => {
    paymentData.value = parsePaymentPayload();
    const root = paymentData.value;
    const info =
        root?.errors && typeof root.errors === 'object' ? root.errors : root;
    if (!info?.requires_registration_payment && !info?.needs_payment) {
        router.visit(route('login'));
        return;
    }
    ensurePaymentOrder();
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

        <!-- Light-only shell: white bg, dark text — ignores system dark palette for this flow -->
        <div class="space-y-5 text-slate-900 bg-white [color-scheme:light] rounded-2xl">
            <div
                v-if="isEmailVerificationSuccess"
                class="rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-4 text-sm text-emerald-950 shadow-sm"
                role="status"
            >
                <p class="font-bold text-emerald-900 leading-snug">{{ verificationSuccessMessage }}</p>
                <p class="mt-2 text-xs font-medium text-emerald-900/85 leading-relaxed">
                    For security, your previous session was ended. After payment, sign in with your email and password to continue.
                </p>
            </div>

            <div
                v-if="verifiedUser"
                class="rounded-2xl border border-slate-200 bg-white px-5 py-5 text-left shadow-sm"
            >
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Your account</p>
                <dl class="mt-4 space-y-3 text-xs font-medium">
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="text-right font-semibold text-slate-900 max-w-[220px] break-all">{{ verifiedUser.email || '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                        <dt class="text-slate-500">Role</dt>
                        <dd class="text-right text-slate-800">{{ formatRole(verifiedUser.role) }}</dd>
                    </div>
                    <div v-if="verifiedUser.phone" class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                        <dt class="text-slate-500">Phone</dt>
                        <dd class="text-right text-slate-800">{{ verifiedUser.phone }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                        <dt class="text-slate-500">Verified at</dt>
                        <dd class="text-right font-medium text-emerald-700">
                            {{ formatVerifiedAt(verifiedUser.email_verified_at) }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                        <dt class="text-slate-500">Account status</dt>
                        <dd class="text-right text-slate-800 max-w-[200px]">{{ formatVerificationStatus(verifiedUser.verification_status) }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2">
                        <dt class="text-slate-500">Registration fee</dt>
                        <dd class="text-right text-slate-800">{{ formatFeeStatus(verifiedUser.registration_fee_status) }}</dd>
                    </div>
                </dl>
            </div>

            <p
                v-if="apiMessage"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-950"
            >
                {{ apiMessage }}
            </p>

            <p
                v-if="paymentError"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800"
            >
                {{ paymentError }}
            </p>

            <div v-if="hasPricing" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <span class="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">Limited membership offer</span>
                <div class="mt-5 flex flex-wrap items-end justify-center gap-4">
                    <span class="text-5xl font-black tabular-nums tracking-tight text-slate-900">₹{{ paymentInfo.discounted_price ?? paymentInfo.amount }}</span>
                    <div class="flex flex-col items-start pb-1">
                        <span class="text-base text-slate-400 line-through font-semibold tabular-nums">₹{{ paymentInfo.actual_price }}</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Active discount</span>
                    </div>
                </div>
            </div>

            <div
                v-else-if="isEmailVerificationSuccess"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-5 text-center text-xs font-medium text-slate-600 leading-relaxed"
            >
                Pricing will appear here when your checkout link is ready. If you do not see a payment button, sign in after your administrator enables billing, or contact support.
            </div>

            <ul class="space-y-3 px-0.5">
                <li
                    v-for="(benefit, i) in ['Lifetime dashboard access', 'Verified professional badge', 'Global network connectivity', 'Priority support']"
                    :key="i"
                    class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wide text-slate-600"
                >
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                    {{ benefit }}
                </li>
            </ul>

            <div class="space-y-3 pt-1">
                <SuButton
                    :variant="canProceedToCheckout ? 'primary' : 'secondary'"
                    :disabled="!canProceedToCheckout || orderLoading"
                    :loading="orderLoading"
                    class="w-full"
                    @click="proceedToPayment"
                >
                    {{ orderLoading ? 'Preparing checkout...' : (canProceedToCheckout ? 'Complete activation via Cashfree' : 'Payment link unavailable') }}
                </SuButton>
                <p v-if="!canProceedToCheckout && paymentInfo.payment_session_id" class="text-center text-xs font-medium text-amber-800">
                    Checkout link not provided — contact support with your order reference.
                </p>

                <button
                    type="button"
                    class="w-full py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500 transition-colors hover:text-slate-800"
                    @click="handleLogout"
                >
                    Cancel & return to sign in
                </button>
            </div>
        </div>

        <template #footer>
            <div class="space-y-3 text-sm text-slate-600">
                <p>Payment issues? <Link :href="route('contact')" class="text-blue-600 font-semibold hover:underline">Contact support</Link></p>
            </div>
        </template>
    </AuthLayout>
</template>
