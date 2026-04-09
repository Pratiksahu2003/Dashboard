<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';

const { requireAuth } = useAuth();
const { error: showError } = useAlerts();

const loading = ref(false);
const options = ref({ priorities: {}, statuses: {}, categories: {} });
const tickets = ref([]);
const meta = ref(null);

const filters = ref({
    status: '',
    priority: '',
    category: '',
    per_page: 15,
    page: 1,
});

const statusEntries = computed(() => Object.entries(options.value.statuses || {}));
const priorityEntries = computed(() => Object.entries(options.value.priorities || {}));
const categoryEntries = computed(() => Object.entries(options.value.categories || {}));
const hasPrev = computed(() => (meta.value?.current_page || 1) > 1);
const hasNext = computed(() => (meta.value?.current_page || 1) < (meta.value?.last_page || 1));

const formatDateTime = value => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString();
};

const decodeHtmlEntities = value => {
    const text = String(value || '');
    if (!text) return '';
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
};

const toPreviewText = value => {
    const decoded = decodeHtmlEntities(value);
    if (!decoded) return '-';
    const plain = decoded.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return plain || '-';
};

const badgeClass = (type, value) => {
    const key = String(value || '').toLowerCase();
    if (type === 'status') {
        if (['resolved', 'closed'].includes(key)) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        if (['open', 'in_progress', 'waiting_for_user'].includes(key)) return 'bg-blue-50 text-blue-700 border-blue-200';
    }
    if (type === 'priority') {
        if (key === 'urgent') return 'bg-rose-50 text-rose-700 border-rose-200';
        if (key === 'high') return 'bg-amber-50 text-amber-700 border-amber-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
};

const fetchOptions = async () => {
    try {
        const response = await api.get('/support-tickets/options');
        options.value = response?.data || { priorities: {}, statuses: {}, categories: {} };
    } catch {
        options.value = { priorities: {}, statuses: {}, categories: {} };
    }
};

const fetchTickets = async () => {
    loading.value = true;
    try {
        const params = {
            per_page: filters.value.per_page,
            page: filters.value.page,
        };
        if (filters.value.status) params.status = filters.value.status;
        if (filters.value.priority) params.priority = filters.value.priority;
        if (filters.value.category) params.category = filters.value.category;

        const response = await api.get('/support-tickets', { params });
        tickets.value = response?.data?.data || [];
        meta.value = response?.data || null;
    } catch (error) {
        showError(error?.message || 'Unable to load existing tickets.');
    } finally {
        loading.value = false;
    }
};

const applyFilters = () => {
    filters.value.page = 1;
    fetchTickets();
};

const resetFilters = () => {
    filters.value = {
        ...filters.value,
        status: '',
        priority: '',
        category: '',
        page: 1,
    };
    fetchTickets();
};

const nextPage = () => {
    if (!hasNext.value) return;
    filters.value.page += 1;
    fetchTickets();
};

const prevPage = () => {
    if (!hasPrev.value) return;
    filters.value.page -= 1;
    fetchTickets();
};

onMounted(async () => {
    if (!requireAuth()) return;
    await fetchOptions();
    await fetchTickets();
});
</script>

<template>
    <Head title="Existing Tickets" />
    <AppLayout>
        <template #breadcrumb>Existing Tickets</template>

        <div class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-xl font-black text-slate-900">Existing Support Tickets</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">Filter and open ticket details in a dedicated page.</p>
                    </div>
                    <Link :href="route('support-tickets-create')" class="btn-dark">Create New Ticket</Link>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    <label class="field-wrap"><span class="field-title">Status</span><select v-model="filters.status" class="field-input bg-white"><option value="">All statuses</option><option v-for="[value, label] in statusEntries" :key="`status-filter-${value}`" :value="value">{{ label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Priority</span><select v-model="filters.priority" class="field-input bg-white"><option value="">All priorities</option><option v-for="[value, label] in priorityEntries" :key="`priority-filter-${value}`" :value="value">{{ label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Category</span><select v-model="filters.category" class="field-input bg-white"><option value="">All categories</option><option v-for="[value, label] in categoryEntries" :key="`category-filter-${value}`" :value="value">{{ label }}</option></select></label>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-dark" :disabled="loading" @click="applyFilters">{{ loading ? 'Loading...' : 'Apply Filters' }}</button>
                    <button type="button" class="btn-soft" :disabled="loading" @click="resetFilters">Reset</button>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-black text-slate-900">Tickets</h2>
                    <p class="text-xs font-semibold text-slate-500">Showing {{ meta?.from || 0 }}-{{ meta?.to || 0 }} of {{ meta?.total || 0 }}</p>
                </div>

                <div v-if="loading" class="mt-3 space-y-2">
                    <div v-for="i in 8" :key="`loading-${i}`" class="h-16 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>
                <div v-else-if="tickets.length === 0" class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-600">No support tickets found.</div>
                <div v-else class="mt-3 space-y-2">
                    <article v-for="ticket in tickets" :key="ticket.id" class="rounded-xl border border-slate-200 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-black text-slate-900 truncate">{{ ticket.subject }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ ticket.ticket_number }} | {{ ticket.user?.name || '-' }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="badge" :class="badgeClass('priority', ticket.priority)">{{ options.priorities?.[ticket.priority] || ticket.priority }}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs font-medium text-slate-600 line-clamp-2">{{ toPreviewText(ticket.message) }}</p>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-[11px] font-bold text-slate-500">{{ formatDateTime(ticket.created_at) }}</p>
                            <Link :href="route('support-ticket-details', { supportTicket: ticket.id })" class="btn-soft">Open Details</Link>
                        </div>
                    </article>
                </div>

                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="btn-soft" :disabled="!hasPrev || loading" @click="prevPage">Previous</button>
                    <span class="text-xs font-black text-slate-700">Page {{ meta?.current_page || 1 }} / {{ meta?.last_page || 1 }}</span>
                    <button type="button" class="btn-soft" :disabled="!hasNext || loading" @click="nextPage">Next</button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.field-wrap { display: flex; flex-direction: column; gap: 0.35rem; }
.field-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: rgb(71 85 105); }
.field-input { border-radius: 0.5rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 600; color: rgb(51 65 85); }
.field-input:focus { outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); border-color: rgb(59 130 246); }
.badge { font-size: 11px; font-weight: 900; border-width: 1px; border-style: solid; border-radius: 0.5rem; padding: 0.15rem 0.5rem; text-transform: uppercase; }
.btn-dark, .btn-soft { border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900; padding: 0.45rem 0.9rem; transition: 150ms ease; }
.btn-dark { color: #fff; background: rgb(15 23 42); border: 1px solid rgb(15 23 42); }
.btn-dark:hover { background: rgb(30 41 59); }
.btn-soft { color: rgb(51 65 85); background: #fff; border: 1px solid rgb(203 213 225); }
.btn-soft:hover { background: rgb(248 250 252); }
button:disabled { opacity: 0.65; cursor: not-allowed; }
</style>
