<script setup>
/**
 * Registration fee / activation (API: errors.requires_registration_payment, payment_link, payment_session_id).
 * Shown after login or email verification when the backend requires payment before dashboard access.
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
    const m = paymentData.value?.message;
    return typeof m === 'string' && m.trim() ? m.trim() : '';
});

onMounted(() => {
    paymentData.value = parsePaymentPayload();
    const info = paymentData.value?.errors && typeof paymentData.value.errors === 'object'
        ? paymentData.value.errors
        : paymentData.value;
    if (!info?.requires_registration_payment) {
        router.visit(route('login'));
    }
});
</script>

<template>
    <AuthLayout>
        <Head title="Account Activation" />

        <template #title>Activation Required</template>
        <template #subtitle>
            {{ paymentInfo.description || 'To access your professional tools, please complete the one-time registration fee payment.' }}
        </template>

        <p
            v-if="apiMessage"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-950"
        >
            {{ apiMessage }}
        </p>

        <div class="space-y-10">
            <div class="relative group">
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
                <SuButton :disabled="!paymentInfo.payment_link" class="w-full" @click="proceedToPayment">
                    Complete Activation via Cashfree
                </SuButton>
                <p v-if="!paymentInfo.payment_link && paymentInfo.payment_session_id" class="text-center text-xs text-amber-800 font-semibold">
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
