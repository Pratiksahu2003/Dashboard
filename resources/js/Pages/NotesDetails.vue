<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useNotesApi } from '@/composables/useNotesApi';

const props = defineProps({
    noteId: {
        type: Number,
        required: true,
    },
});

const { requireAuth } = useAuth();
const { error: showError, info: showInfo, success: showSuccess } = useAlerts();
const notesApi = useNotesApi();

const isLoading = ref(false);
const note = ref(null);
const purchasing = ref(false);

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

const statusText = computed(() => {
    if (!note.value?.is_paid) return 'Free';
    if (note.value?.is_purchased) return 'Purchased';
    if (note.value?.has_subscription_access) return 'Subscription Access';
    if (note.value?.can_access) return 'Unlocked';
    return 'Locked';
});

const statusClass = computed(() => {
    if (!note.value?.is_paid) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (note.value?.can_access) return 'border-blue-200 bg-blue-50 text-blue-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
});

const refreshNote = async () => {
    if (!props.noteId) return;
    isLoading.value = true;
    try {
        note.value = await notesApi.getNote(props.noteId);
    } catch (error) {
        showError(error?.message || 'Unable to load note details.', 'Notes');
    } finally {
        isLoading.value = false;
    }
};

const refreshAccess = async () => {
    if (!note.value?.id) return;
    try {
        const access = await notesApi.checkAccess(note.value.id);
        note.value = {
            ...note.value,
            can_access: !!access?.can_access,
            is_purchased: !!access?.is_purchased,
            has_subscription_access: !!access?.has_subscription_access,
        };
        showSuccess('Access status refreshed.', 'Notes');
    } catch (error) {
        showError(error?.message || 'Unable to refresh access.', 'Notes');
    }
};

const downloadNote = () => {
    const directUrl = String(note.value?.file_url || '').trim();
    if (!directUrl) {
        showInfo('File URL not available yet for this note.', 'Notes');
        return;
    }
    window.open(directUrl, '_blank', 'noopener,noreferrer');
};

const buyNow = async () => {
    if (!note.value?.id) return;
    if (note.value?.can_access || note.value?.is_purchased || note.value?.has_subscription_access || !note.value?.is_paid) {
        showInfo('You already have access to this note.', 'Notes');
        return;
    }
    purchasing.value = true;
    try {
        const payload = await notesApi.purchaseNote(note.value.id);
        const status = String(payload?.status || '').toLowerCase();
        if (payload.paymentRequired === false || status === 'already_paid') {
            await refreshAccess();
            showSuccess('Note access unlocked.', 'Notes');
            return;
        }
        const checkoutUrl = String(payload?.checkoutUrl || '').trim();
        if (!checkoutUrl) throw new Error('Checkout URL is missing.');
        window.open(checkoutUrl, '_blank', 'noopener,noreferrer');
        showInfo('Checkout opened in a new tab. Complete payment and refresh access.', 'Notes');
    } catch (error) {
        showError(error?.message || 'Unable to start purchase flow.', 'Notes');
    } finally {
        purchasing.value = false;
    }
};

onMounted(async () => {
    if (!requireAuth()) return;
    await refreshNote();
});
</script>

<template>
    <Head :title="note?.name ? `${note.name} - Notes` : 'Note Details'" />

    <AppLayout>
        <template #breadcrumb>
            <span class="inline-flex items-center gap-2">
                <Link :href="route('notes')" class="text-slate-600 hover:text-slate-900">Notes</Link>
                <span>/</span>
                <span>Details</span>
            </span>
        </template>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h1 class="text-xl font-black text-slate-900">Note Details</h1>
                <Link :href="route('notes')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50">
                    Back to Notes
                </Link>
            </div>

            <div v-if="isLoading" class="h-52 rounded-2xl bg-slate-100 animate-pulse"></div>
            <div v-else-if="!note" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-600">
                Note not found.
            </div>
            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">{{ note.name || 'Note' }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            Category: {{ note?.note_category?.name || '-' }} | Type: {{ note?.note_type?.name || '-' }}
                        </p>
                    </div>
                    <span class="rounded-md border px-2 py-1 text-xs font-black uppercase" :class="statusClass">{{ statusText }}</span>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Description</p>
                    <div class="mt-2 text-sm font-medium text-slate-700 [&>*]:m-0" v-html="renderRichHtml(note.description)" />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Price</p>
                        <p class="mt-1 text-lg font-black text-slate-900">{{ note.is_paid ? (note.formatted_price || formatMoney(note.price, 'INR')) : 'Free' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Downloads</p>
                        <p class="mt-1 text-lg font-black text-slate-900">{{ note.download_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">File Size</p>
                        <p class="mt-1 text-lg font-black text-slate-900">{{ note.file_size || '-' }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="note.can_access || !note.is_paid"
                        type="button"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100 transition"
                        @click="downloadNote"
                    >
                        Download
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 transition disabled:opacity-60"
                        :disabled="purchasing"
                        @click="buyNow"
                    >
                        {{ purchasing ? 'Please wait...' : 'Buy Now' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition"
                        @click="refreshAccess"
                    >
                        Check Access
                    </button>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
