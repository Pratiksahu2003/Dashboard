<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { SUBSCRIPTION_TYPES, useSubscriptionsApi } from '@/composables/useSubscriptionsApi';

const { requireAuth } = useAuth();
const { error: showError, info: showInfo, success: showSuccess, confirmDanger } = useAlerts();
const subscriptionsApi = useSubscriptionsApi();
const subscriptionsApiBaseUrl = String(import.meta.env.VITE_API_BASE_URL || `${(import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '')}/api/v1`).replace(/\/+$/, '');
const subscriptionFlowSteps = [
    'Select subscription type and plan',
    'Create payment and redirect to Cashfree',
    'Payment confirms and subscription activates',
    'Notes access updates immediately',
];

const selectedSType = ref(1);
const plansLoading = ref(false);
const plans = ref([]);
const currentSubscriptionLoading = ref(false);
const currentSubscription = ref(null);
const subscriptionActionLoadingId = ref('');
const subscriptionsLoading = ref(false);
const subscriptions = ref([]);
const subscriptionsMeta = ref(null);
const subscriptionStatusFilter = ref('');
const subscriptionsPerPage = ref(10);
const subscriptionsPage = ref(1);

const hasSubscriptionsPrev = computed(() => (subscriptionsMeta.value?.current_page || 1) > 1);
const hasSubscriptionsNext = computed(() => (subscriptionsMeta.value?.current_page || 1) < (subscriptionsMeta.value?.last_page || 1));

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
    if (normalized === 'success' || normalized === 'active') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (normalized === 'pending') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (normalized === 'failed') return 'bg-rose-50 text-rose-700 border-rose-200';
    if (normalized === 'cancelled') return 'bg-slate-100 text-slate-700 border-slate-300';
    if (normalized === 'refunded') return 'bg-violet-50 text-violet-700 border-violet-200';
    if (normalized === 'expired') return 'bg-orange-50 text-orange-700 border-orange-200';
    return 'bg-slate-100 text-slate-700 border-slate-300';
};

const formatPlanFeatures = plan => {
    const features = plan?.features;
    if (Array.isArray(features)) return features.map(value => String(value)).filter(Boolean);

    if (features && typeof features === 'object') {
        return Object.entries(features).map(([key, value]) => {
            const label = key
                .replace(/_/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\b\w/g, ch => ch.toUpperCase());

            if (typeof value === 'boolean') return `${label}: ${value ? 'Yes' : 'No'}`;
            if (value === null || value === undefined || value === '') return `${label}: -`;
            return `${label}: ${value}`;
        });
    }

    if (typeof features === 'string' && features.trim()) {
        try {
            const parsed = JSON.parse(features);
            if (Array.isArray(parsed)) return parsed.map(value => String(value)).filter(Boolean);
            if (parsed && typeof parsed === 'object') {
                return Object.entries(parsed).map(([key, value]) => {
                    const label = key
                        .replace(/_/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim()
                        .replace(/\b\w/g, ch => ch.toUpperCase());

                    if (typeof value === 'boolean') return `${label}: ${value ? 'Yes' : 'No'}`;
                    if (value === null || value === undefined || value === '') return `${label}: -`;
                    return `${label}: ${value}`;
                });
            }
        } catch {
            return features.split(',').map(v => v.trim()).filter(Boolean);
        }
    }
    return [];
};

const canCancelSubscription = subscription => String(subscription?.status || '').toLowerCase() === 'active';
const canRenewSubscription = subscription => ['active', 'expired', 'cancelled'].includes(String(subscription?.status || '').toLowerCase());

const loadPlans = async () => {
    plansLoading.value = true;
    try {
        plans.value = await subscriptionsApi.listPlans(selectedSType.value);
    } catch (error) {
        plans.value = [];
        showError(error?.message || 'Unable to load subscription plans.', 'Subscriptions');
    } finally {
        plansLoading.value = false;
    }
};

const loadCurrentSubscription = async () => {
    currentSubscriptionLoading.value = true;
    try {
        currentSubscription.value = await subscriptionsApi.getCurrentSubscription(selectedSType.value);
    } catch (error) {
        currentSubscription.value = null;
        if (error?.code !== 404) showError(error?.message || 'Unable to load current subscription.', 'Subscriptions');
    } finally {
        currentSubscriptionLoading.value = false;
    }
};

const loadMySubscriptions = async () => {
    subscriptionsLoading.value = true;
    try {
        const payload = await subscriptionsApi.listMySubscriptions({
            sType: selectedSType.value,
            status: subscriptionStatusFilter.value,
            perPage: subscriptionsPerPage.value,
            page: subscriptionsPage.value,
        });
        subscriptions.value = payload.rows;
        subscriptionsMeta.value = payload.meta;
    } catch (error) {
        subscriptions.value = [];
        subscriptionsMeta.value = null;
        showError(error?.message || 'Unable to load your subscriptions.', 'Subscriptions');
    } finally {
        subscriptionsLoading.value = false;
    }
};

const refreshSubscriptionData = async () => {
    await Promise.all([loadPlans(), loadCurrentSubscription(), loadMySubscriptions()]);
};

const purchasePlan = async plan => {
    if (!plan?.id) return;
    subscriptionActionLoadingId.value = `buy-${plan.id}`;
    try {
        const data = await subscriptionsApi.purchaseSubscription(plan.id);
        if (data.checkoutUrl) {
            window.location.href = data.checkoutUrl;
            return;
        }
        showInfo('Payment created, but checkout URL was missing in API response.', 'Subscriptions');
    } catch (error) {
        showError(error?.message || 'Unable to start subscription purchase.', 'Subscriptions');
    } finally {
        subscriptionActionLoadingId.value = '';
    }
};

const renewSubscription = async subscription => {
    if (!subscription?.id) return;
    subscriptionActionLoadingId.value = `renew-${subscription.id}`;
    try {
        const data = await subscriptionsApi.renewSubscription(subscription.id);
        if (data.checkoutUrl) {
            window.location.href = data.checkoutUrl;
            return;
        }
        showInfo('Renewal payment created, but checkout URL was missing in API response.', 'Subscriptions');
    } catch (error) {
        showError(error?.message || 'Unable to start renewal payment.', 'Subscriptions');
    } finally {
        subscriptionActionLoadingId.value = '';
    }
};

const cancelCurrentSubscription = async subscription => {
    if (!subscription?.id) return;
    const confirmed = await confirmDanger({
        title: 'Cancel current subscription?',
        text: 'This will cancel the active subscription for this plan type.',
        confirmText: 'Yes, cancel it',
    });
    if (!confirmed) return;
    subscriptionActionLoadingId.value = `cancel-${subscription.id}`;
    try {
        await subscriptionsApi.cancelSubscription(subscription.id);
        showSuccess('Subscription cancelled successfully.', 'Subscriptions');
        await refreshSubscriptionData();
    } catch (error) {
        showError(error?.message || 'Unable to cancel subscription.', 'Subscriptions');
    } finally {
        subscriptionActionLoadingId.value = '';
    }
};

const onSTypeChange = async () => {
    subscriptionsPage.value = 1;
    await refreshSubscriptionData();
};

const applySubscriptionFilters = async () => {
    subscriptionsPage.value = 1;
    await loadMySubscriptions();
};

const nextSubscriptionsPage = async () => {
    if (!hasSubscriptionsNext.value) return;
    subscriptionsPage.value += 1;
    await loadMySubscriptions();
};

const prevSubscriptionsPage = async () => {
    if (!hasSubscriptionsPrev.value) return;
    subscriptionsPage.value -= 1;
    await loadMySubscriptions();
};

onMounted(() => {
    if (!requireAuth()) return;
    try {
        const params = new URLSearchParams(window.location.search || '');
        const requestedType = Number(params.get('s_type'));
        const validTypes = new Set(SUBSCRIPTION_TYPES.map(item => Number(item.value)));
        if (Number.isFinite(requestedType) && validTypes.has(requestedType)) {
            selectedSType.value = requestedType;
        }
    } catch {
        /* ignore invalid query */
    }
    refreshSubscriptionData();
});
</script>

<template>
    <Head title="Subscriptions" />

    <AppLayout>
        <template #breadcrumb>Subscriptions</template>

        <div class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Subscriptions</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Subscription Workflow</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            Browse plans, manage active subscription, and complete payments through Cashfree checkout.
                        </p>
                        <p class="mt-1 text-xs font-bold text-slate-500">
                            API Base: {{ subscriptionsApiBaseUrl }} | Notes Access Type: s_type=1
                        </p>
                    </div>
                    <div class="flex items-center justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                            :disabled="plansLoading || currentSubscriptionLoading || subscriptionsLoading"
                            @click="refreshSubscriptionData"
                        >
                            Refresh Subscriptions
                        </button>
                    </div>
                </div>

                <div class="mt-4 sticky top-0 z-20 rounded-2xl border border-slate-200 bg-slate-50/95 backdrop-blur p-3">
                    <p class="mb-2 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Plan Type</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="type in SUBSCRIPTION_TYPES"
                            :key="type.value"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs font-black transition"
                            :class="selectedSType === type.value
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                            :disabled="plansLoading || currentSubscriptionLoading || subscriptionsLoading"
                            @click="selectedSType = type.value; onSTypeChange()"
                        >
                            {{ type.label }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-black text-slate-900">Current Subscription</h2>
                        <span
                            class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase"
                            :class="canCancelSubscription(currentSubscription) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-300'"
                        >
                            {{ currentSubscription?.status || 'none' }}
                        </span>
                    </div>

                    <div v-if="currentSubscriptionLoading" class="mt-3 h-20 rounded-xl bg-slate-100 animate-pulse" />
                    <div v-else-if="!currentSubscription" class="mt-3 text-sm font-semibold text-slate-600">
                        No active subscription found for this type.
                    </div>
                    <div v-else class="mt-3 space-y-2">
                        <p class="text-sm font-semibold text-slate-700">
                            Plan: <span class="font-black text-slate-900">{{ currentSubscription?.plan?.name || 'Unknown' }}</span>
                        </p>
                        <p class="text-xs font-bold text-slate-500">
                            Starts: {{ formatDateTime(currentSubscription.starts_at) }} | Expires: {{ formatDateTime(currentSubscription.expires_at) }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="canRenewSubscription(currentSubscription)"
                                type="button"
                                class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-wide text-blue-700 hover:bg-blue-100 transition disabled:opacity-60"
                                :disabled="subscriptionActionLoadingId === `renew-${currentSubscription.id}`"
                                @click="renewSubscription(currentSubscription)"
                            >
                                {{ subscriptionActionLoadingId === `renew-${currentSubscription.id}` ? 'Starting...' : 'Renew' }}
                            </button>
                            <button
                                v-if="canCancelSubscription(currentSubscription)"
                                type="button"
                                class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-wide text-rose-700 hover:bg-rose-100 transition disabled:opacity-60"
                                :disabled="subscriptionActionLoadingId === `cancel-${currentSubscription.id}`"
                                @click="cancelCurrentSubscription(currentSubscription)"
                            >
                                {{ subscriptionActionLoadingId === `cancel-${currentSubscription.id}` ? 'Cancelling...' : 'Cancel' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Purchase Flow</h2>
                <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-4">
                    <div
                        v-for="(step, idx) in subscriptionFlowSteps"
                        :key="`subscription-flow-step-${idx}`"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Step {{ idx + 1 }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ step }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-900">Available Plans</h2>
                    <span class="text-xs font-bold text-slate-500">Only allowed plans are returned by API access rules.</span>
                </div>

                <div v-if="plansLoading" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div v-for="i in 4" :key="`plan-loading-${i}`" class="h-52 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>
                <div v-else-if="plans.length === 0" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No plans available for this subscription type.</p>
                </div>
                <div v-else class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <article
                        v-for="plan in plans"
                        :key="plan.id"
                        class="rounded-xl border p-4"
                        :class="plan.is_popular ? 'border-blue-300 bg-blue-50/30' : 'border-slate-200'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-black text-slate-900">{{ plan.name }}</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-600">{{ plan.description || 'Subscription plan' }}</p>
                            </div>
                            <span
                                v-if="plan.is_popular"
                                class="text-[10px] font-black uppercase tracking-wide rounded-md border border-blue-300 bg-blue-100 px-1.5 py-0.5 text-blue-700"
                            >
                                Popular
                            </span>
                        </div>

                        <p class="mt-3 text-lg font-black text-slate-900">{{ plan.formatted_price || formatMoney(plan.price, plan.currency) }}</p>
                        <p class="text-xs font-bold text-slate-500">Billing: {{ plan.billing_period || '-' }}</p>

                        <div v-if="formatPlanFeatures(plan).length" class="mt-3">
                            <p class="mb-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Features</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                <div
                                    v-for="feature in formatPlanFeatures(plan)"
                                    :key="`${plan.id}-${feature}`"
                                    class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 break-words leading-relaxed"
                                    :title="feature"
                                >
                                    {{ feature }}
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-4 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                            :disabled="subscriptionActionLoadingId === `buy-${plan.id}`"
                            @click="purchasePlan(plan)"
                        >
                            {{ subscriptionActionLoadingId === `buy-${plan.id}` ? 'Starting Payment...' : 'Purchase Plan' }}
                        </button>
                    </article>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <h2 class="text-lg font-black text-slate-900">My Subscriptions</h2>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</span>
                            <select
                                v-model="subscriptionStatusFilter"
                                class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                                :disabled="subscriptionsLoading"
                            >
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="expired">Expired</option>
                                <option value="pending">Pending</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Per Page</span>
                            <select
                                v-model.number="subscriptionsPerPage"
                                class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                                :disabled="subscriptionsLoading"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                            :disabled="subscriptionsLoading"
                            @click="applySubscriptionFilters"
                        >
                            {{ subscriptionsLoading ? 'Loading...' : 'Apply Filters' }}
                        </button>
                    </div>
                </div>

                <div v-if="subscriptionsLoading" class="mt-4 space-y-3">
                    <div v-for="i in 5" :key="`sub-loading-${i}`" class="h-20 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>
                <div v-else-if="subscriptions.length === 0" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No subscriptions found for the selected filters.</p>
                </div>
                <div v-else class="mt-4 space-y-3">
                    <article
                        v-for="sub in subscriptions"
                        :key="sub.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-black text-slate-900">{{ sub?.plan?.name || 'Subscription' }}</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-600">
                                    {{ formatMoney(sub.amount_paid, sub?.plan?.currency || 'INR') }} | {{ sub.payment_method || '-' }}
                                </p>
                                <p class="mt-1 text-xs font-bold text-slate-500 break-words">
                                    Txn: {{ sub.transaction_id || '-' }} | Starts: {{ formatDateTime(sub.starts_at) }} | Expires: {{ formatDateTime(sub.expires_at) }}
                                </p>
                            </div>
                            <span class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase" :class="statusClass(sub.status)">
                                {{ sub.status || 'unknown' }}
                            </span>
                        </div>
                    </article>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs font-bold text-slate-500">
                        Showing {{ subscriptionsMeta?.from || 0 }}-{{ subscriptionsMeta?.to || 0 }} of {{ subscriptionsMeta?.total || 0 }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!hasSubscriptionsPrev || subscriptionsLoading"
                            @click="prevSubscriptionsPage"
                        >
                            Previous
                        </button>
                        <span class="text-xs font-black text-slate-700">
                            Page {{ subscriptionsMeta?.current_page || 1 }} / {{ subscriptionsMeta?.last_page || 1 }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!hasSubscriptionsNext || subscriptionsLoading"
                            @click="nextSubscriptionsPage"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

