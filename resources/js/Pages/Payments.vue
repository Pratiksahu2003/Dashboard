<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';

const { requireAuth } = useAuth();
const { error: showError, info: showInfo } = useAlerts();
const invoiceBaseUrl = String(import.meta.env.VITE_INVOICE_BASE_URL || 'https://www.suganta.com').replace(/\/+$/, '');
const paymentsApiBaseUrl = String(import.meta.env.VITE_API_BASE_URL || `${(import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '')}/api/v1`).replace(/\/+$/, '');

const isLoading = ref(false);
const invoiceLoadingOrderId = ref('');
const items = ref([]);
const meta = ref(null);
const paymentFlowSteps = [
    'Create order from Notes/Subscriptions',
    'Open secure Cashfree checkout',
    'Track order status (pending to success)',
    'Unlock access and invoice instantly',
];

const status = ref('');
const perPage = ref(15);
const page = ref(1);
let pendingRefreshInterval = null;
const statusOptions = [
    { value: '', label: 'All' },
    { value: 'success', label: 'Success' },
    { value: 'pending', label: 'Pending' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'refunded', label: 'Refunded' },
];

const hasPrev = computed(() => (meta.value?.current_page || 1) > 1);
const hasNext = computed(() => (meta.value?.current_page || 1) < (meta.value?.last_page || 1));
const summary = computed(() => {
    const rows = Array.isArray(items.value) ? items.value : [];
    return rows.reduce((acc, row) => {
        const statusValue = String(row?.status || '').toLowerCase();
        if (statusValue === 'success') acc.success += 1;
        if (statusValue === 'pending') acc.pending += 1;
        if (statusValue === 'failed' || statusValue === 'cancelled') acc.failed += 1;
        return acc;
    }, { success: 0, pending: 0, failed: 0 });
});

const formatDateTime = value => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString();
};

const formatMoney = (amount, currency) => {
    const numericAmount = Number(amount ?? 0);
    if (Number.isNaN(numericAmount)) return '-';
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency || 'INR',
            maximumFractionDigits: 2,
        }).format(numericAmount);
    } catch {
        return `${currency || 'INR'} ${numericAmount.toFixed(2)}`;
    }
};

const statusClass = value => {
    const normalized = String(value || '').toLowerCase();
    if (normalized === 'success') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (normalized === 'pending') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (normalized === 'failed') return 'bg-rose-50 text-rose-700 border-rose-200';
    if (normalized === 'cancelled') return 'bg-slate-100 text-slate-700 border-slate-300';
    if (normalized === 'refunded') return 'bg-violet-50 text-violet-700 border-violet-200';
    return 'bg-slate-100 text-slate-700 border-slate-300';
};
const isInvoiceAvailable = payment => String(payment?.status || '').toLowerCase() === 'success';

const loadPayments = async () => {
    isLoading.value = true;

    try {
        const response = await api.get('/payments', {
            params: {
                per_page: perPage.value,
                page: page.value,
                status: status.value || undefined,
            },
        });
        items.value = response?.data?.data || [];
        meta.value = response?.data?.meta || null;
    } catch (error) {
        showError(error?.message || 'Unable to load payment history.', 'Payments');
    } finally {
        isLoading.value = false;
    }
};

const applyFilters = () => {
    page.value = 1;
    loadPayments();
};

const nextPage = () => {
    if (!hasNext.value) return;
    page.value += 1;
    loadPayments();
};

const prevPage = () => {
    if (!hasPrev.value) return;
    page.value -= 1;
    loadPayments();
};

const openInvoice = async payment => {
    const orderId = String(payment?.order_id || '').trim();
    if (!orderId) {
        showInfo('Order ID is missing for this payment.', 'Invoice');
        return;
    }
    if (String(payment?.status || '').toLowerCase() !== 'success') {
        showInfo('Invoice is only available for successful payments.', 'Invoice');
        return;
    }

    const directInvoiceUrl = `${invoiceBaseUrl}/payment/invoice/${encodeURIComponent(orderId)}`;

    if (payment?.invoice_url) {
        window.open(payment.invoice_url, '_blank', 'noopener,noreferrer');
        return;
    }

    invoiceLoadingOrderId.value = orderId;
    try {
        const response = await api.get(`/payments/invoice/${encodeURIComponent(orderId)}`);
        const invoiceUrl = response?.data?.invoice_url || directInvoiceUrl;
        window.open(invoiceUrl, '_blank', 'noopener,noreferrer');
    } catch (error) {
        if (orderId) {
            window.open(directInvoiceUrl, '_blank', 'noopener,noreferrer');
            return;
        }
        showError(error?.message || 'Unable to generate invoice URL right now.', 'Invoice');
    } finally {
        invoiceLoadingOrderId.value = '';
    }
};

const refreshPendingStatuses = async () => {
    const pendingRows = (Array.isArray(items.value) ? items.value : []).filter(payment => String(payment?.status || '').toLowerCase() === 'pending');
    if (pendingRows.length === 0) return;
    try {
        const statusRows = await Promise.all(
            pendingRows.map(async payment => {
                const orderId = String(payment?.order_id || '').trim();
                if (!orderId) return null;
                const response = await api.get('/payments/status', {
                    params: { order_id: orderId },
                });
                return {
                    orderId,
                    status: String(response?.data?.status || '').toLowerCase(),
                    processedAt: response?.data?.processed_at || null,
                };
            }),
        );

        const statusMap = new Map(statusRows.filter(Boolean).map(row => [row.orderId, row]));
        if (statusMap.size === 0) return;

        items.value = items.value.map(payment => {
            const key = String(payment?.order_id || '').trim();
            const next = statusMap.get(key);
            if (!next?.status || next.status === String(payment?.status || '').toLowerCase()) return payment;
            return {
                ...payment,
                status: next.status,
                processed_at: next.processedAt || payment?.processed_at || null,
            };
        });
    } catch {
        // Silent fail to avoid noisy alerts during background refresh.
    }
};

onMounted(() => {
    if (!requireAuth()) return;
    loadPayments();
    pendingRefreshInterval = setInterval(refreshPendingStatuses, 5000);
});

onBeforeUnmount(() => {
    if (pendingRefreshInterval) clearInterval(pendingRefreshInterval);
    pendingRefreshInterval = null;
});
</script>

<template>
    <Head title="Payments" />

    <AppLayout>
        <template #breadcrumb>Payments</template>

        <div class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Payments</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Payment History</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            Browse your payment records and open invoice URLs for successful payments.
                        </p>
                        <p class="mt-1 text-xs font-bold text-slate-500">
                            API Base: {{ paymentsApiBaseUrl }}
                        </p>
                        <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span class="text-[11px] font-black uppercase tracking-wide text-emerald-700">
                                Invoice available only for success status
                            </span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</span>
                                <select
                                    v-model="status"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                                    :disabled="isLoading"
                                >
                                    <option v-for="item in statusOptions" :key="item.value || 'all'" :value="item.value">
                                        {{ item.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Per Page</span>
                                <select
                                    v-model.number="perPage"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                                    :disabled="isLoading"
                                >
                                    <option :value="10">10</option>
                                    <option :value="15">15</option>
                                    <option :value="20">20</option>
                                    <option :value="50">50</option>
                                </select>
                            </label>

                            <div class="flex items-end">
                                <button
                                    type="button"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                                    :disabled="isLoading"
                                    @click="applyFilters"
                                >
                                    {{ isLoading ? 'Loading...' : 'Apply Filters' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-900">Payment Workflow</h2>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-center">
                            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Success</p>
                            <p class="text-lg font-black text-emerald-800">{{ summary.success }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-center">
                            <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Pending</p>
                            <p class="text-lg font-black text-amber-800">{{ summary.pending }}</p>
                        </div>
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-center">
                            <p class="text-[10px] font-black uppercase tracking-wider text-rose-700">Failed</p>
                            <p class="text-lg font-black text-rose-800">{{ summary.failed }}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-4">
                    <div
                        v-for="(step, idx) in paymentFlowSteps"
                        :key="`payment-flow-step-${idx}`"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Step {{ idx + 1 }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ step }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5">
                <div v-if="isLoading" class="space-y-3">
                    <div v-for="i in 8" :key="`payment-loading-${i}`" class="h-24 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>

                <div v-else-if="items.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No payments found for the selected filters.</p>
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="payment in items"
                        :key="payment.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-black text-slate-900">{{ formatMoney(payment.amount, payment.currency) }}</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-600 break-words">
                                    {{ payment.description || payment.type || 'Payment' }}
                                </p>
                                <p class="mt-1 text-xs font-bold text-slate-500">
                                    Order: {{ payment.order_id || '-' }} | Reference: {{ payment.reference_id || '-' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase"
                                    :class="statusClass(payment.status)"
                                >
                                    {{ payment.status || 'unknown' }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md border px-3 py-1.5 text-[11px] font-black uppercase tracking-wide transition disabled:cursor-not-allowed"
                                    :class="isInvoiceAvailable(payment)
                                        ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 disabled:opacity-60'
                                        : 'border-slate-200 bg-slate-100 text-slate-400 disabled:opacity-100'"
                                    :disabled="!isInvoiceAvailable(payment) || invoiceLoadingOrderId === payment.order_id"
                                    @click="openInvoice(payment)"
                                >
                                    {{
                                        invoiceLoadingOrderId === payment.order_id
                                            ? 'Opening...'
                                            : 'View Invoice'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 grid grid-cols-1 gap-1 text-[11px] font-bold text-slate-500 sm:grid-cols-2">
                            <p>Created: {{ formatDateTime(payment.created_at) }}</p>
                            <p>Processed: {{ formatDateTime(payment.processed_at) }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs font-bold text-slate-500">
                    Showing {{ meta?.from || 0 }}-{{ meta?.to || 0 }} of {{ meta?.total || 0 }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                        :disabled="!hasPrev || isLoading"
                        @click="prevPage"
                    >
                        Previous
                    </button>
                    <span class="text-xs font-black text-slate-700">
                        Page {{ meta?.current_page || 1 }} / {{ meta?.last_page || 1 }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                        :disabled="!hasNext || isLoading"
                        @click="nextPage"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

