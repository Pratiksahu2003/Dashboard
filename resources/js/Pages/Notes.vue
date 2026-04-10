<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useNotesApi } from '@/composables/useNotesApi';

const { requireAuth, getToken } = useAuth();
const { error: showError, info: showInfo, success: showSuccess } = useAlerts();
const notesApi = useNotesApi();

const notesApiBaseUrl = `${(import.meta.env.VITE_API_DOMAIN || 'https://www.suganta.in').replace(/\/$/, '')}/api/v2/notes`;
const flowSteps = [
    'Select note and verify lock state',
    'Create payment order and open Cashfree',
    'Track order status until success',
    'Unlock and download instantly',
];

const isLoading = ref(false);
const notes = ref([]);
const meta = ref(null);
const categories = ref([]);
const types = ref([]);

const search = ref('');
const categoryId = ref('');
const noteTypeId = ref('');
const isPaid = ref('');
const perPage = ref(12);
const page = ref(1);
const activeTab = ref('notes');

const purchasesLoading = ref(false);
const purchases = ref([]);
const purchasesMeta = ref(null);
const purchaseHistoryStatus = ref('');
const purchasesPerPage = ref(10);
const purchasesPage = ref(1);

const detailsDrawerOpen = ref(false);
const detailNote = ref(null);

const purchaseModalOpen = ref(false);
const selectedNote = ref(null);
const purchasing = ref(false);
const purchaseStatus = ref('');
const purchaseOrderId = ref('');
const polling = ref(false);
const pollingMessage = ref('');
let pollTimer = null;
const purchaseTimelineSteps = [
    { key: 'created', label: 'Order Created' },
    { key: 'pending', label: 'Payment Pending' },
    { key: 'success', label: 'Access Unlocked' },
];

const hasPrev = computed(() => (meta.value?.current_page || 1) > 1);
const hasNext = computed(() => (meta.value?.current_page || 1) < (meta.value?.last_page || 1));
const hasPurchasesPrev = computed(() => (purchasesMeta.value?.current_page || 1) > 1);
const hasPurchasesNext = computed(() => (purchasesMeta.value?.current_page || 1) < (purchasesMeta.value?.last_page || 1));
const summary = computed(() => {
    const rows = Array.isArray(notes.value) ? notes.value : [];
    return rows.reduce((acc, note) => {
        const unlocked = note?.can_access || !note?.is_paid;
        if (unlocked) acc.unlocked += 1;
        else acc.locked += 1;
        if (note?.is_paid) acc.paid += 1;
        return acc;
    }, { unlocked: 0, locked: 0, paid: 0 });
});
const purchaseStepState = stepKey => {
    const current = String(purchaseStatus.value || '').toLowerCase();
    if (!current) return 'pending';
    if (['failed', 'cancelled', 'refunded'].includes(current)) {
        if (stepKey === 'success') return 'failed';
        if (stepKey === 'created' && ['created', 'pending', 'failed', 'cancelled', 'refunded'].includes(current)) return 'done';
        if (stepKey === 'pending' && ['pending', 'failed', 'cancelled', 'refunded'].includes(current)) return 'done';
        return 'pending';
    }
    if (stepKey === 'created' && ['created', 'pending', 'success'].includes(current)) return 'done';
    if (stepKey === 'pending' && ['pending', 'success'].includes(current)) return 'done';
    if (stepKey === 'success' && current === 'success') return 'done';
    return 'pending';
};

const formatMoney = (amount, currency = 'INR') => {
    const numericAmount = Number(amount ?? 0);
    if (Number.isNaN(numericAmount)) return '-';
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(numericAmount);
    } catch {
        return `${currency} ${numericAmount.toFixed(2)}`;
    }
};

const renderRichHtml = value => {
    const raw = String(value || '').trim();
    if (!raw) return '<p>No description available.</p>';
    if (typeof window === 'undefined' || typeof DOMParser === 'undefined') {
        return `<p>${raw}</p>`;
    }
    const parser = new DOMParser();
    const doc = parser.parseFromString(raw, 'text/html');
    doc.querySelectorAll('script, style, iframe, object, embed, link, meta').forEach(node => node.remove());
    doc.querySelectorAll('*').forEach(el => {
        [...el.attributes].forEach(attr => {
            const name = attr.name.toLowerCase();
            const val = String(attr.value || '').toLowerCase();
            if (name.startsWith('on')) el.removeAttribute(attr.name);
            if ((name === 'href' || name === 'src') && val.startsWith('javascript:')) el.removeAttribute(attr.name);
        });
    });
    return doc.body.innerHTML || '<p>No description available.</p>';
};

const statusClass = note => {
    if (!note?.is_paid) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (note?.can_access) return 'border-blue-200 bg-blue-50 text-blue-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
};

const statusText = note => {
    if (!note?.is_paid) return 'Free';
    if (note?.is_purchased) return 'Purchased';
    if (note?.has_subscription_access) return 'Subscription Access';
    if (note?.can_access) return 'Unlocked';
    return 'Locked';
};

const clearPolling = () => {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = null;
    polling.value = false;
};

const purchaseHistoryStatusClass = value => {
    const status = String(value || '').toLowerCase();
    if (status === 'completed' || status === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (status === 'pending') return 'border-amber-200 bg-amber-50 text-amber-700';
    if (status === 'failed' || status === 'cancelled') return 'border-rose-200 bg-rose-50 text-rose-700';
    if (status === 'refunded') return 'border-violet-200 bg-violet-50 text-violet-700';
    return 'border-slate-200 bg-slate-100 text-slate-700';
};

const loadNotes = async () => {
    isLoading.value = true;
    try {
        const payload = await notesApi.listNotes({
            categoryId: categoryId.value,
            noteTypeId: noteTypeId.value,
            search: search.value.trim(),
            isPaid: isPaid.value,
            perPage: perPage.value,
            page: page.value,
        });
        notes.value = payload.rows;
        meta.value = payload.meta;
    } catch (error) {
        showError(error?.message || 'Unable to load notes.', 'Notes');
    } finally {
        isLoading.value = false;
    }
};

const loadFilters = async () => {
    try {
        const [categoryRows, typeRows] = await Promise.all([
            notesApi.listCategories(),
            notesApi.listTypes(),
        ]);
        categories.value = categoryRows;
        types.value = typeRows;
    } catch (error) {
        showError(error?.message || 'Unable to load note filters.', 'Notes');
    }
};

const loadMyPurchases = async () => {
    purchasesLoading.value = true;
    try {
        const payload = await notesApi.listMyPurchases({
            status: purchaseHistoryStatus.value,
            perPage: purchasesPerPage.value,
            page: purchasesPage.value,
        });
        purchases.value = payload.rows;
        purchasesMeta.value = payload.meta;
    } catch (error) {
        purchases.value = [];
        purchasesMeta.value = null;
        showError(error?.message || 'Unable to load your note purchases.', 'Notes');
    } finally {
        purchasesLoading.value = false;
    }
};

const refreshAll = async () => {
    await Promise.all([loadFilters(), loadNotes(), loadMyPurchases()]);
};

const applyFilters = async () => {
    page.value = 1;
    await loadNotes();
};

const prevPage = async () => {
    if (!hasPrev.value) return;
    page.value -= 1;
    await loadNotes();
};

const nextPage = async () => {
    if (!hasNext.value) return;
    page.value += 1;
    await loadNotes();
};

const applyPurchaseFilters = async () => {
    purchasesPage.value = 1;
    await loadMyPurchases();
};

const prevPurchasesPage = async () => {
    if (!hasPurchasesPrev.value) return;
    purchasesPage.value -= 1;
    await loadMyPurchases();
};

const nextPurchasesPage = async () => {
    if (!hasPurchasesNext.value) return;
    purchasesPage.value += 1;
    await loadMyPurchases();
};

const setActiveTab = tab => {
    const allowed = new Set(['notes', 'purchases']);
    const next = allowed.has(tab) ? tab : 'notes';
    activeTab.value = next;
    router.replace({
        url: route('notes', { tab: next }),
        preserveState: true,
        preserveScroll: true,
    });
};

const openPurchaseModal = note => {
    selectedNote.value = note;
    purchaseModalOpen.value = true;
    purchaseStatus.value = '';
    purchaseOrderId.value = '';
    pollingMessage.value = '';
    clearPolling();
};

const closePurchaseModal = () => {
    if (purchasing.value || polling.value) return;
    purchaseModalOpen.value = false;
    selectedNote.value = null;
    purchaseStatus.value = '';
    purchaseOrderId.value = '';
    pollingMessage.value = '';
};

const openDetailsDrawer = note => {
    detailNote.value = note || null;
    detailsDrawerOpen.value = true;
};

const closeDetailsDrawer = () => {
    detailsDrawerOpen.value = false;
    detailNote.value = null;
};

const patchNoteAccess = async noteId => {
    if (!noteId) return;
    try {
        const access = await notesApi.checkAccess(noteId);
        notes.value = notes.value.map(row => {
            if (Number(row?.id) !== Number(noteId)) return row;
            return {
                ...row,
                can_access: !!access?.can_access,
                is_purchased: !!access?.is_purchased,
                has_subscription_access: !!access?.has_subscription_access,
            };
        });
    } catch {
        // Ignore.
    }
};

const pollPayment = async (orderId, noteId) => {
    polling.value = true;
    purchaseOrderId.value = orderId;
    purchaseStatus.value = 'pending';
    pollingMessage.value = 'Waiting for payment confirmation...';

    let attempts = 0;
    const maxAttempts = 120;
    const terminal = new Set(['success', 'failed', 'cancelled', 'refunded']);

    const run = async () => {
        attempts += 1;
        try {
            const state = await notesApi.getPaymentStatus(orderId);
            const status = String(state?.status || 'pending').toLowerCase();
            purchaseStatus.value = status;
            pollingMessage.value = `Payment status: ${status}`;

            if (status === 'success') {
                clearPolling();
                showSuccess('Payment successful. Note unlocked.', 'Notes');
                await patchNoteAccess(noteId);
                await loadNotes();
                await loadMyPurchases();
                purchaseModalOpen.value = false;
                return;
            }

            if (terminal.has(status)) {
                clearPolling();
                pollingMessage.value = 'Payment did not complete. You can retry.';
                return;
            }

            if (attempts >= maxAttempts) {
                clearPolling();
                purchaseStatus.value = 'pending';
                pollingMessage.value = 'Still pending. Please retry status check in a moment.';
                return;
            }

            pollTimer = setTimeout(run, 2500);
        } catch {
            if (attempts >= maxAttempts) {
                clearPolling();
                pollingMessage.value = 'Unable to verify payment right now. Please retry.';
                return;
            }
            pollTimer = setTimeout(run, 2500);
        }
    };

    await run();
};

const startPurchase = async () => {
    const note = selectedNote.value;
    if (!note?.id) return;

    // If note is already accessible (purchased or subscription), do not create payment again.
    if (note?.can_access || note?.is_purchased || note?.has_subscription_access || !note?.is_paid) {
        showInfo('You already have access to this note. No payment is required.', 'Notes');
        await patchNoteAccess(note.id);
        await loadNotes();
        purchaseModalOpen.value = false;
        return;
    }

    purchasing.value = true;
    purchaseStatus.value = 'created';
    pollingMessage.value = 'Creating payment order...';
    try {
        // Re-check latest server-side access before creating order.
        const access = await notesApi.checkAccess(note.id).catch(() => null);
        if (access?.can_access || access?.is_purchased || access?.has_subscription_access) {
            showInfo('Access already active through purchase/subscription. Payment skipped.', 'Notes');
            await patchNoteAccess(note.id);
            await loadNotes();
            purchaseModalOpen.value = false;
            return;
        }

        const payload = await notesApi.purchaseNote(note.id);
        const status = String(payload?.status || '').toLowerCase();
        if (payload.paymentRequired === false || status === 'already_paid') {
            purchaseStatus.value = 'success';
            pollingMessage.value = 'Access granted. Refreshing note state...';
            await patchNoteAccess(note.id);
            await loadNotes();
            await loadMyPurchases();
            showSuccess('Note is unlocked for your account.', 'Notes');
            purchaseModalOpen.value = false;
            return;
        }

        const orderId = String(payload?.payment?.order_id || '').trim();
        const checkoutUrl = String(payload?.checkoutUrl || '').trim();
        if (!orderId || !checkoutUrl) {
            throw new Error('Payment order created but checkout URL is missing.');
        }

        purchaseOrderId.value = orderId;
        purchaseStatus.value = 'pending';
        pollingMessage.value = 'Opening secure checkout...';
        window.open(checkoutUrl, '_blank', 'noopener,noreferrer');
        await pollPayment(orderId, note.id);
    } catch (error) {
        purchaseStatus.value = 'failed';
        pollingMessage.value = error?.message || 'Unable to initiate purchase.';
        showError(error?.message || 'Unable to start note purchase.', 'Notes');
    } finally {
        purchasing.value = false;
    }
};

const retryPolling = async () => {
    const note = selectedNote.value;
    const orderId = String(purchaseOrderId.value || '').trim();
    if (!note?.id || !orderId) {
        showInfo('No pending order found. Start purchase again.', 'Notes');
        return;
    }
    await pollPayment(orderId, note.id);
};

const downloadNote = async note => {
    if (!note?.id) return;
    const directUrl = String(note?.file_url || '').trim();
    if (directUrl) {
        window.open(directUrl, '_blank', 'noopener,noreferrer');
        return;
    }

    const token = getToken();
    if (!token) {
        showInfo('Please login again to continue.', 'Notes');
        return;
    }

    try {
        const response = await fetch(`${notesApiBaseUrl}/${encodeURIComponent(note.id)}/download`, {
            method: 'GET',
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: '*/*',
            },
        });
        if (!response.ok) throw new Error('Download failed or access denied.');
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `${String(note?.name || 'note').replace(/[^a-zA-Z0-9-_ ]/g, '').trim() || 'note'}.pdf`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        showError(error?.message || 'Unable to download note.', 'Notes');
    }
};

onMounted(async () => {
    if (!requireAuth()) return;
    try {
        const params = new URLSearchParams(window.location.search || '');
        const tab = String(params.get('tab') || '').toLowerCase();
        if (tab === 'purchases') activeTab.value = 'purchases';
    } catch {
        // Ignore URL parse errors.
    }
    await refreshAll();
});

onBeforeUnmount(() => {
    clearPolling();
});
</script>

<template>
    <Head title="Notes" />

    <AppLayout>
        <template #breadcrumb>Notes</template>

        <div class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Notes Store</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Notes Purchase Workflow</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            Browse notes, unlock paid content using Cashfree checkout, and download with proper access control.
                        </p>
                        <p class="mt-1 text-xs font-bold text-slate-500">API Base: {{ notesApiBaseUrl }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-center">
                            <p class="text-[10px] font-black uppercase tracking-wide text-blue-700">Unlocked</p>
                            <p class="text-lg font-black text-blue-800">{{ summary.unlocked }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-center">
                            <p class="text-[10px] font-black uppercase tracking-wide text-amber-700">Locked</p>
                            <p class="text-lg font-black text-amber-800">{{ summary.locked }}</p>
                        </div>
                        <div class="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-center">
                            <p class="text-[10px] font-black uppercase tracking-wide text-violet-700">Paid Notes</p>
                            <p class="text-lg font-black text-violet-800">{{ summary.paid }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-4">
                    <div
                        v-for="(step, idx) in flowSteps"
                        :key="`notes-flow-step-${idx}`"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Step {{ idx + 1 }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ step }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-xs font-black transition"
                        :class="activeTab === 'notes' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                        @click="setActiveTab('notes')"
                    >
                        Notes Catalog
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-xs font-black transition"
                        :class="activeTab === 'purchases' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                        @click="setActiveTab('purchases'); loadMyPurchases()"
                    >
                        My Purchases
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'notes'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-6">
                    <label class="block lg:col-span-2">
                        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Search</span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search notes by name..."
                            class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                            :disabled="isLoading"
                        />
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
                        <select
                            v-model="categoryId"
                            class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                            :disabled="isLoading"
                        >
                            <option value="">All</option>
                            <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                        <select
                            v-model="noteTypeId"
                            class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                            :disabled="isLoading"
                        >
                            <option value="">All</option>
                            <option v-for="item in types" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Price Type</span>
                        <select
                            v-model="isPaid"
                            class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                            :disabled="isLoading"
                        >
                            <option value="">All</option>
                            <option :value="false">Free</option>
                            <option :value="true">Paid</option>
                        </select>
                    </label>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                            :disabled="isLoading"
                            @click="applyFilters"
                        >
                            {{ isLoading ? 'Loading...' : 'Apply' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                            :disabled="isLoading"
                            @click="refreshAll"
                        >
                            Refresh
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'notes'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div v-if="isLoading" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="i in 9" :key="`note-loading-${i}`" class="h-44 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>

                <div v-else-if="notes.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No notes found for selected filters.</p>
                </div>

                <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="note in notes"
                        :key="note.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-base font-black text-slate-900 line-clamp-2">{{ note.name || 'Note' }}</h3>
                            <span class="text-[10px] font-black rounded-md border px-2 py-0.5 uppercase" :class="statusClass(note)">
                                {{ statusText(note) }}
                            </span>
                        </div>
                        <div
                            class="mt-2 text-sm font-medium text-slate-600 line-clamp-3 [&>*]:m-0"
                            v-html="renderRichHtml(note.description)"
                        />
                        <p class="mt-3 text-lg font-black text-slate-900">
                            {{ note.is_paid ? (note.formatted_price || formatMoney(note.price, 'INR')) : 'Free' }}
                        </p>
                        <p class="mt-1 text-[11px] font-bold text-slate-500">
                            Category: {{ note?.note_category?.name || '-' }} | Type: {{ note?.note_type?.name || '-' }}
                        </p>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button
                                v-if="note.can_access || !note.is_paid"
                                type="button"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100 transition"
                                @click="downloadNote(note)"
                            >
                                Download
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 transition"
                                @click="openPurchaseModal(note)"
                            >
                                Buy Now
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition"
                                @click="patchNoteAccess(note.id)"
                            >
                                Check Access
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition"
                                @click="openDetailsDrawer(note)"
                            >
                                View Details
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <div v-if="activeTab === 'notes'" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
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
                    <span class="text-xs font-black text-slate-700">Page {{ meta?.current_page || 1 }} / {{ meta?.last_page || 1 }}</span>
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

            <section v-if="activeTab === 'purchases'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</span>
                        <select
                            v-model="purchaseHistoryStatus"
                            class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                            :disabled="purchasesLoading"
                        >
                            <option value="">All</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Per Page</span>
                        <select
                            v-model.number="purchasesPerPage"
                            class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700"
                            :disabled="purchasesLoading"
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
                            :disabled="purchasesLoading"
                            @click="applyPurchaseFilters"
                        >
                            {{ purchasesLoading ? 'Loading...' : 'Apply Filters' }}
                        </button>
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                            :disabled="purchasesLoading"
                            @click="loadMyPurchases"
                        >
                            Refresh
                        </button>
                    </div>
                </div>

                <div v-if="purchasesLoading" class="mt-4 space-y-3">
                    <div v-for="i in 6" :key="`note-purchase-loading-${i}`" class="h-20 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>

                <div v-else-if="purchases.length === 0" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No note purchases found for selected filters.</p>
                </div>

                <div v-else class="mt-4 space-y-3">
                    <article
                        v-for="item in purchases"
                        :key="item.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-black text-slate-900">{{ item?.note?.name || 'Purchased Note' }}</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-600">
                                    {{ formatMoney(item.amount, item?.payment?.currency || 'INR') }} | Downloads: {{ item.download_count ?? 0 }}
                                </p>
                                <p class="mt-1 text-[11px] font-bold text-slate-500">
                                    Purchased: {{ item.purchased_at || '-' }}
                                </p>
                            </div>
                            <span
                                class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase"
                                :class="purchaseHistoryStatusClass(item.status)"
                            >
                                {{ item.status || 'unknown' }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700 hover:bg-emerald-100 transition disabled:opacity-50"
                                :disabled="!item.can_download"
                                @click="downloadNote(item.note)"
                            >
                                {{ item.can_download ? 'Download Note' : 'Locked' }}
                            </button>
                        </div>
                    </article>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs font-bold text-slate-500">
                        Showing {{ purchasesMeta?.from || 0 }}-{{ purchasesMeta?.to || 0 }} of {{ purchasesMeta?.total || 0 }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!hasPurchasesPrev || purchasesLoading"
                            @click="prevPurchasesPage"
                        >
                            Previous
                        </button>
                        <span class="text-xs font-black text-slate-700">
                            Page {{ purchasesMeta?.current_page || 1 }} / {{ purchasesMeta?.last_page || 1 }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!hasPurchasesNext || purchasesLoading"
                            @click="nextPurchasesPage"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>

    <div v-if="purchaseModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="closePurchaseModal"></div>
        <div class="relative w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Note Purchase</p>
                    <h3 class="mt-1 text-lg font-black text-slate-900">{{ selectedNote?.name || 'Selected Note' }}</h3>
                    <p class="mt-1 text-sm font-medium text-slate-600">
                        {{ selectedNote?.formatted_price || formatMoney(selectedNote?.price, 'INR') }} | Secure payment via Cashfree
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100"
                    :disabled="purchasing || polling"
                    @click="closePurchaseModal"
                >
                    Close
                </button>
            </div>

            <div class="mt-4 space-y-2">
                <div
                    v-if="selectedNote?.has_subscription_access || selectedNote?.can_access || selectedNote?.is_purchased || !selectedNote?.is_paid"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Payment Not Required</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-800">
                        You already have access via subscription or previous purchase.
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div
                        v-for="step in purchaseTimelineSteps"
                        :key="`purchase-timeline-${step.key}`"
                        class="rounded-xl border px-3 py-2"
                        :class="purchaseStepState(step.key) === 'done'
                            ? 'border-emerald-200 bg-emerald-50'
                            : purchaseStepState(step.key) === 'failed'
                                ? 'border-rose-200 bg-rose-50'
                                : 'border-slate-200 bg-slate-50'"
                    >
                        <p
                            class="text-[10px] font-black uppercase tracking-[0.14em]"
                            :class="purchaseStepState(step.key) === 'done'
                                ? 'text-emerald-700'
                                : purchaseStepState(step.key) === 'failed'
                                    ? 'text-rose-700'
                                    : 'text-slate-500'"
                        >
                            {{ step.label }}
                        </p>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Current State</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700">
                        {{ pollingMessage || 'Press proceed to create order and start checkout.' }}
                    </p>
                    <p v-if="purchaseOrderId" class="mt-1 text-[11px] font-bold text-slate-500">Order ID: {{ purchaseOrderId }}</p>
                    <p v-if="purchaseStatus" class="mt-1 text-[11px] font-black uppercase text-slate-600">Status: {{ purchaseStatus }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                    :disabled="purchasing || polling || selectedNote?.has_subscription_access || selectedNote?.can_access || selectedNote?.is_purchased || !selectedNote?.is_paid"
                    @click="startPurchase"
                >
                    {{ purchasing ? 'Creating...' : 'Proceed to Pay' }}
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-black text-amber-700 hover:bg-amber-100 transition disabled:opacity-60"
                    :disabled="purchasing || polling || !purchaseOrderId"
                    @click="retryPolling"
                >
                    {{ polling ? 'Checking...' : 'Retry Status Check' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="detailsDrawerOpen" class="fixed inset-0 z-40">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeDetailsDrawer"></div>
        <aside class="absolute right-0 top-0 h-full w-full max-w-md bg-white border-l border-slate-200 shadow-xl p-5 overflow-y-auto">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Note Details</p>
                    <h3 class="mt-1 text-lg font-black text-slate-900">{{ detailNote?.name || 'Note' }}</h3>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100"
                    @click="closeDetailsDrawer"
                >
                    Close
                </button>
            </div>

            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Description</p>
                    <div class="mt-1 text-sm font-medium text-slate-700 [&>*]:m-0" v-html="renderRichHtml(detailNote?.description)" />
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Pricing & Access</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700">
                        Price: {{ detailNote?.is_paid ? (detailNote?.formatted_price || formatMoney(detailNote?.price, 'INR')) : 'Free' }}
                    </p>
                    <p class="text-sm font-semibold text-slate-700">Status: {{ statusText(detailNote) }}</p>
                    <p class="text-sm font-semibold text-slate-700">Downloads: {{ detailNote?.download_count ?? 0 }}</p>
                    <p class="text-sm font-semibold text-slate-700">File Size: {{ detailNote?.file_size || '-' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Taxonomy</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700">Category: {{ detailNote?.note_category?.name || '-' }}</p>
                    <p class="text-sm font-semibold text-slate-700">Type: {{ detailNote?.note_type?.name || '-' }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <button
                    v-if="detailNote?.can_access || !detailNote?.is_paid"
                    type="button"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100 transition"
                    @click="downloadNote(detailNote)"
                >
                    Download
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 transition"
                    @click="openPurchaseModal(detailNote)"
                >
                    Buy Now
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition"
                    @click="patchNoteAccess(detailNote?.id)"
                >
                    Refresh Access
                </button>
            </div>
        </aside>
    </div>
</template>
