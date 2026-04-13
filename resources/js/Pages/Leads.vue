<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useLeadApi } from '@/composables/useLeadApi';
import { searchUsers } from '@/services/chatApi';

const { requireAuth } = useAuth();
const { success: showSuccess, error: showError, info: showInfo } = useAlerts();
const { listLeads, createLead, getLead } = useLeadApi();

const isLoading = ref(false);
const isCreating = ref(false);
const leads = ref([]);
const selectedLead = ref(null);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const isOwnerLookupLoading = ref(false);
const isAssignedLookupLoading = ref(false);
const ownerLookupQuery = ref('');
const assignedLookupQuery = ref('');
const ownerLookupResults = ref([]);
const assignedLookupResults = ref([]);

const filters = ref({
    search: '',
    status: '',
    start_date: '',
    end_date: '',
    per_page: 15,
    page: 1,
});

const leadForm = ref({
    name: '',
    phone: '',
    email: '',
    lead_owner_id: '',
    assigned_to: '',
    type: '',
    source: '',
    subject_interest: '',
    grade_level: '',
    location: '',
    message: '',
    status: 'new',
    priority: 'medium',
    estimated_value: '',
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
});

const statusOptions = ['new', 'contacted', 'qualified', 'converted', 'closed'];
const typeOptions = ['student', 'parent', 'institute', 'teacher'];
const sourceOptions = ['website', 'social_media', 'referral', 'advertisement', 'direct'];
const priorityOptions = ['low', 'medium', 'high', 'urgent'];

const createModalOpen = ref(false);

function openCreateModal() {
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

function onCreateModalKeydown(e) {
    if (e.key === 'Escape') closeCreateModal();
}

watch(createModalOpen, (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        document.addEventListener('keydown', onCreateModalKeydown);
    } else {
        document.removeEventListener('keydown', onCreateModalKeydown);
    }
});

onUnmounted(() => {
    if (typeof document === 'undefined') return;
    document.removeEventListener('keydown', onCreateModalKeydown);
    document.body.style.overflow = '';
});

const totalLabel = computed(() => `${meta.value.total || 0} lead${Number(meta.value.total || 0) === 1 ? '' : 's'}`);
const selectedLeadId = computed(() => Number(selectedLead.value?.id || 0) || null);

/** HTTP status from axios errors or from `api.js` interceptor rejections (`code` / `status`). */
function getErrorStatus(error) {
    if (!error) return null;
    const direct = error.code ?? error.status;
    if (direct != null && direct !== '') {
        const n = Number(direct);
        return Number.isFinite(n) ? n : null;
    }
    const ax = error.response?.status;
    if (ax != null) {
        const n = Number(ax);
        return Number.isFinite(n) ? n : null;
    }
    return null;
}

/** Lead API returned 401/403 — session invalid for API; same global redirect as other pages. */
function redirectToLoginIfUnauthorized(error) {
    const n = getErrorStatus(error);
    if ((n === 401 || n === 403) && typeof document !== 'undefined') {
        document.dispatchEvent(new CustomEvent('app:unauthorized'));
        return true;
    }
    return false;
}

const parseErrorMessage = error => {
    const data = error?.response?.data ?? error?.responsePayload;
    const fieldErrors = error?.errors ?? data?.errors;
    if (fieldErrors && typeof fieldErrors === 'object') {
        const firstKey = Object.keys(fieldErrors)[0];
        if (firstKey && Array.isArray(fieldErrors[firstKey]) && fieldErrors[firstKey][0]) {
            return fieldErrors[firstKey][0];
        }
    }
    return data?.message || error?.message || 'Request failed.';
};

const toSafeParams = () => {
    const params = {
        page: filters.value.page,
        per_page: Number(filters.value.per_page) || 15,
    };
    if (filters.value.search) params.search = filters.value.search;
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    return params;
};

const syncQueryState = () => {
    if (typeof window === 'undefined') return;
    const next = new URL(window.location.href);
    const params = new URLSearchParams();

    const safe = toSafeParams();
    Object.entries(safe).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params.set(key, String(value));
    });
    if (selectedLeadId.value) params.set('lead', String(selectedLeadId.value));

    const query = params.toString();
    const current = next.search.startsWith('?') ? next.search.slice(1) : next.search;
    if (current === query) return;

    next.search = query;
    window.history.replaceState({}, '', next.toString());
};

const initializeStateFromQuery = () => {
    if (typeof window === 'undefined') return null;
    const query = new URLSearchParams(window.location.search);
    const allowedPerPage = [15, 25, 50];
    const perPage = Number(query.get('per_page') || 15);
    const page = Number(query.get('page') || 1);

    filters.value.search = (query.get('search') || '').trim();
    filters.value.status = (query.get('status') || '').trim();
    filters.value.start_date = (query.get('start_date') || '').trim();
    filters.value.end_date = (query.get('end_date') || '').trim();
    filters.value.per_page = allowedPerPage.includes(perPage) ? perPage : 15;
    filters.value.page = Number.isFinite(page) && page > 0 ? page : 1;

    const lead = Number(query.get('lead'));
    return Number.isFinite(lead) && lead > 0 ? lead : null;
};

/** Single-lead responses from useLeadApi are already API `data` payloads; support a nested `.data` if present. */
const unwrapLeadRecord = raw => {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
    if (raw.id != null) return raw;
    const inner = raw.data;
    if (inner && typeof inner === 'object' && !Array.isArray(inner) && inner.id != null) return inner;
    return null;
};

const loadLeads = async () => {
    isLoading.value = true;
    try {
        const envelope = await listLeads(toSafeParams());
        const rows = envelope?.data;
        leads.value = Array.isArray(rows) ? rows : [];
        meta.value =
            envelope?.meta || {
                current_page: 1,
                last_page: 1,
                per_page: Number(filters.value.per_page) || 15,
                total: 0,
            };
        syncQueryState();
    } catch (error) {
        if (redirectToLoginIfUnauthorized(error)) return;
        showError(parseErrorMessage(error), 'Leads');
    } finally {
        isLoading.value = false;
    }
};

const selectLead = async leadId => {
    if (!leadId) return;
    try {
        const response = await getLead(leadId);
        selectedLead.value = unwrapLeadRecord(response);
        syncQueryState();
    } catch (error) {
        if (redirectToLoginIfUnauthorized(error)) return;
        showError(parseErrorMessage(error), 'Lead Details');
    }
};

const statusBadgeClass = status => {
    const key = String(status || '').toLowerCase();
    if (key === 'new') return 'bg-sky-50 text-sky-700 border-sky-200';
    if (key === 'contacted') return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    if (key === 'qualified') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (key === 'converted') return 'bg-green-50 text-green-700 border-green-200';
    if (key === 'closed') return 'bg-slate-100 text-slate-700 border-slate-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
};

const priorityBadgeClass = priority => {
    const key = String(priority || '').toLowerCase();
    if (key === 'urgent') return 'bg-rose-50 text-rose-700 border-rose-200';
    if (key === 'high') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (key === 'medium') return 'bg-blue-50 text-blue-700 border-blue-200';
    if (key === 'low') return 'bg-slate-100 text-slate-700 border-slate-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
};

const lookupUsersForOwner = async () => {
    const q = ownerLookupQuery.value.trim();
    if (q.length < 2) {
        ownerLookupResults.value = [];
        return;
    }
    isOwnerLookupLoading.value = true;
    try {
        const rows = await searchUsers({ q, limit: 8 });
        ownerLookupResults.value = Array.isArray(rows) ? rows : [];
    } catch (e) {
        if (redirectToLoginIfUnauthorized(e)) return;
        ownerLookupResults.value = [];
    } finally {
        isOwnerLookupLoading.value = false;
    }
};

const lookupUsersForAssignee = async () => {
    const q = assignedLookupQuery.value.trim();
    if (q.length < 2) {
        assignedLookupResults.value = [];
        return;
    }
    isAssignedLookupLoading.value = true;
    try {
        const rows = await searchUsers({ q, limit: 8 });
        assignedLookupResults.value = Array.isArray(rows) ? rows : [];
    } catch (e) {
        if (redirectToLoginIfUnauthorized(e)) return;
        assignedLookupResults.value = [];
    } finally {
        isAssignedLookupLoading.value = false;
    }
};

const pickOwner = user => {
    leadForm.value.lead_owner_id = Number(user?.id || 0) || '';
    ownerLookupQuery.value = user?.name || user?.email || '';
    ownerLookupResults.value = [];
};

const pickAssignee = user => {
    leadForm.value.assigned_to = Number(user?.id || 0) || '';
    assignedLookupQuery.value = user?.name || user?.email || '';
    assignedLookupResults.value = [];
};

const resetLeadForm = () => {
    leadForm.value = {
        name: '',
        phone: '',
        email: '',
        lead_owner_id: '',
        assigned_to: '',
        type: '',
        source: '',
        subject_interest: '',
        grade_level: '',
        location: '',
        message: '',
        status: 'new',
        priority: 'medium',
        estimated_value: '',
        utm_source: '',
        utm_medium: '',
        utm_campaign: '',
    };
    ownerLookupQuery.value = '';
    assignedLookupQuery.value = '';
    ownerLookupResults.value = [];
    assignedLookupResults.value = [];
};

const submitLead = async () => {
    if (!leadForm.value.name || !leadForm.value.phone || !leadForm.value.lead_owner_id) {
        showInfo('Name, phone, and lead owner ID are required.', 'Create Lead');
        return;
    }
    if (!Number.isFinite(Number(leadForm.value.lead_owner_id)) || Number(leadForm.value.lead_owner_id) <= 0) {
        showInfo('Lead owner ID must be a valid positive number.', 'Create Lead');
        return;
    }
    if (leadForm.value.assigned_to && (!Number.isFinite(Number(leadForm.value.assigned_to)) || Number(leadForm.value.assigned_to) <= 0)) {
        showInfo('Assigned ID must be a valid positive number.', 'Create Lead');
        return;
    }

    const payload = {
        ...leadForm.value,
        lead_owner_id: Number(leadForm.value.lead_owner_id),
        assigned_to: leadForm.value.assigned_to ? Number(leadForm.value.assigned_to) : null,
        estimated_value: leadForm.value.estimated_value ? Number(leadForm.value.estimated_value) : null,
    };

    Object.keys(payload).forEach(key => {
        if (payload[key] === '' || payload[key] === null) delete payload[key];
    });

    isCreating.value = true;
    try {
        const response = await createLead(payload);
        const created = unwrapLeadRecord(response);
        showSuccess('Lead created successfully.', 'Leads');
        resetLeadForm();
        createModalOpen.value = false;
        filters.value.page = 1;
        await loadLeads();
        if (created?.id) await selectLead(created.id);
    } catch (error) {
        if (redirectToLoginIfUnauthorized(error)) return;
        showError(parseErrorMessage(error), 'Create Lead');
    } finally {
        isCreating.value = false;
    }
};

const applyFilters = async () => {
    filters.value.page = 1;
    await loadLeads();
};

const clearFilters = async () => {
    filters.value.search = '';
    filters.value.status = '';
    filters.value.start_date = '';
    filters.value.end_date = '';
    filters.value.page = 1;
    selectedLead.value = null;
    await loadLeads();
};

const prevPage = async () => {
    if ((meta.value.current_page || 1) <= 1) return;
    filters.value.page = (meta.value.current_page || 1) - 1;
    await loadLeads();
};

const nextPage = async () => {
    if ((meta.value.current_page || 1) >= (meta.value.last_page || 1)) return;
    filters.value.page = (meta.value.current_page || 1) + 1;
    await loadLeads();
};

const formatDateTime = value => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleString();
};

onMounted(async () => {
    if (!requireAuth()) return;
    const leadIdFromQuery = initializeStateFromQuery();
    if (typeof window !== 'undefined') {
        const q = new URLSearchParams(window.location.search);
        if (q.get('create') === '1') {
            createModalOpen.value = true;
            q.delete('create');
            const next = new URL(window.location.href);
            next.search = q.toString();
            window.history.replaceState({}, '', next.pathname + (next.search ? `?${next.search}` : '') + next.hash);
        }
    }
    await loadLeads();
    if (leadIdFromQuery) await selectLead(leadIdFromQuery);
});
</script>

<template>
    <Head title="Leads" />
    <AppLayout>
        <template #breadcrumb>Leads</template>

        <div class="space-y-5">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 p-6 text-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-white/70">Lead API</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight">Lead Management</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-slate-900 shadow-sm transition hover:bg-indigo-50"
                            @click="openCreateModal"
                        >
                            Create lead
                        </button>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-right">
                            <p class="text-[11px] font-black uppercase tracking-wide text-white/70">Total</p>
                            <p class="text-lg font-black">{{ totalLabel }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="space-y-4 xl:col-span-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-6">
                            <input v-model="filters.search" type="text" placeholder="Search leads..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" />
                            <select v-model="filters.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All Status</option>
                                <option v-for="item in statusOptions" :key="`status-${item}`" :value="item">{{ item }}</option>
                            </select>
                            <input v-model="filters.start_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            <input v-model="filters.end_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            <select v-model.number="filters.per_page" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-700" :disabled="isLoading" @click="applyFilters">
                                {{ isLoading ? 'Loading...' : 'Apply Filters' }}
                            </button>
                            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50" :disabled="isLoading" @click="clearFilters">
                                Clear
                            </button>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-sm font-black text-slate-900">Leads List</h2>
                            <span class="text-xs font-bold text-slate-500">Page {{ meta.current_page || 1 }}/{{ meta.last_page || 1 }}</span>
                        </div>

                        <div v-if="isLoading" class="py-8 text-center text-sm font-semibold text-slate-500">Loading leads...</div>
                        <div v-else-if="leads.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">
                            No leads found for current filters.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="px-2 py-2">Lead</th>
                                        <th class="px-2 py-2">Status</th>
                                        <th class="px-2 py-2">Priority</th>
                                        <th class="px-2 py-2">Owner</th>
                                        <th class="px-2 py-2">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="lead in leads"
                                        :key="lead.id"
                                        class="cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                                        :class="selectedLead?.id === lead.id ? 'bg-indigo-50/50' : ''"
                                        @click="selectLead(lead.id)"
                                    >
                                        <td class="px-2 py-2">
                                            <p class="font-bold text-slate-900">{{ lead.name || '-' }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ lead.email || lead.phone || '-' }}</p>
                                        </td>
                                        <td class="px-2 py-2">
                                            <span class="rounded-full border px-2 py-1 text-xs font-black" :class="statusBadgeClass(lead.status)">{{ lead.status || '-' }}</span>
                                        </td>
                                        <td class="px-2 py-2">
                                            <span class="rounded-full border px-2 py-1 text-xs font-black" :class="priorityBadgeClass(lead.priority)">{{ lead.priority || '-' }}</span>
                                        </td>
                                        <td class="px-2 py-2">{{ lead.lead_owner?.name || lead.lead_owner_id || '-' }}</td>
                                        <td class="px-2 py-2 text-xs text-slate-500">{{ formatDateTime(lead.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 flex items-center justify-between">
                            <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 disabled:opacity-50" :disabled="(meta.current_page || 1) <= 1 || isLoading" @click="prevPage">Prev</button>
                            <span class="text-xs font-bold text-slate-500">{{ meta.total || 0 }} total</span>
                            <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 disabled:opacity-50" :disabled="(meta.current_page || 1) >= (meta.last_page || 1) || isLoading" @click="nextPage">Next</button>
                        </div>
                    </article>
                </div>

                <div class="space-y-4">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 class="text-sm font-black text-slate-900">Lead Details</h2>
                        <div v-if="!selectedLead" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-xs font-semibold text-slate-500">
                            Select a lead from the list to view full details.
                        </div>
                        <div v-else class="mt-3 space-y-2 text-sm">
                            <p><span class="font-black text-slate-800">Lead ID:</span> <span class="font-semibold text-slate-600">{{ selectedLead.lead_id || selectedLead.id }}</span></p>
                            <p><span class="font-black text-slate-800">Name:</span> <span class="font-semibold text-slate-600">{{ selectedLead.name || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Phone:</span> <span class="font-semibold text-slate-600">{{ selectedLead.phone || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Email:</span> <span class="font-semibold text-slate-600">{{ selectedLead.email || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Status:</span> <span class="font-semibold text-slate-600">{{ selectedLead.status || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Priority:</span> <span class="font-semibold text-slate-600">{{ selectedLead.priority || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Owner:</span> <span class="font-semibold text-slate-600">{{ selectedLead.lead_owner?.name || selectedLead.lead_owner_id || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Assigned To:</span> <span class="font-semibold text-slate-600">{{ selectedLead.assigned_to?.name || selectedLead.assigned_to || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Source:</span> <span class="font-semibold text-slate-600">{{ selectedLead.source || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Type:</span> <span class="font-semibold text-slate-600">{{ selectedLead.type || '-' }}</span></p>
                            <p><span class="font-black text-slate-800">Created:</span> <span class="font-semibold text-slate-600">{{ formatDateTime(selectedLead.created_at) }}</span></p>
                            <p><span class="font-black text-slate-800">Message:</span> <span class="font-semibold text-slate-600">{{ selectedLead.message || '-' }}</span></p>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="createModalOpen"
                class="fixed inset-0 z-[180] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="leads-create-title"
                @click.self="closeCreateModal"
            >
                <div class="flex max-h-[min(90vh,880px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/90 px-5 py-4">
                        <div>
                            <h2 id="leads-create-title" class="text-lg font-black text-slate-900">Create lead</h2>
                            <p class="mt-0.5 text-xs font-semibold text-slate-500">Add a lead for a tutor (lead owner).</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-slate-50"
                            aria-label="Close"
                            @click="closeCreateModal"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <form class="space-y-4" @submit.prevent="submitLead">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Lead owner (search user) *</span>
                                    <input
                                        v-model="ownerLookupQuery"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Type name or email (min 2 chars)"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        @input="lookupUsersForOwner"
                                    />
                                    <div v-if="isOwnerLookupLoading" class="mt-1 text-xs text-slate-500">Searching…</div>
                                    <ul v-else-if="ownerLookupResults.length" class="mt-1 max-h-40 overflow-auto rounded-lg border border-slate-200 bg-slate-50 text-sm">
                                        <li
                                            v-for="u in ownerLookupResults"
                                            :key="u.id"
                                            class="cursor-pointer border-b border-slate-100 px-3 py-2 last:border-0 hover:bg-white"
                                            @click="pickOwner(u)"
                                        >
                                            {{ u.name || u.email }} <span class="text-slate-500">#{{ u.id }}</span>
                                        </li>
                                    </ul>
                                    <p v-if="leadForm.lead_owner_id" class="mt-1 text-xs font-semibold text-emerald-700">Selected owner ID: {{ leadForm.lead_owner_id }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Name *</span>
                                    <input v-model="leadForm.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Phone *</span>
                                    <input v-model="leadForm.phone" type="tel" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Email</span>
                                    <input v-model="leadForm.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>

                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Assign to (optional)</span>
                                    <input
                                        v-model="assignedLookupQuery"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Search user to assign"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        @input="lookupUsersForAssignee"
                                    />
                                    <div v-if="isAssignedLookupLoading" class="mt-1 text-xs text-slate-500">Searching…</div>
                                    <ul v-else-if="assignedLookupResults.length" class="mt-1 max-h-40 overflow-auto rounded-lg border border-slate-200 bg-slate-50 text-sm">
                                        <li
                                            v-for="u in assignedLookupResults"
                                            :key="u.id"
                                            class="cursor-pointer border-b border-slate-100 px-3 py-2 last:border-0 hover:bg-white"
                                            @click="pickAssignee(u)"
                                        >
                                            {{ u.name || u.email }} <span class="text-slate-500">#{{ u.id }}</span>
                                        </li>
                                    </ul>
                                    <p v-if="leadForm.assigned_to" class="mt-1 text-xs font-semibold text-emerald-700">Assigned user ID: {{ leadForm.assigned_to }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Type</span>
                                    <select v-model="leadForm.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Source</span>
                                    <select v-model="leadForm.source" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        <option v-for="s in sourceOptions" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</span>
                                    <select v-model="leadForm.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Priority</span>
                                    <select v-model="leadForm.priority" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option v-for="p in priorityOptions" :key="p" :value="p">{{ p }}</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Subject interest</span>
                                    <input v-model="leadForm.subject_interest" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Grade / level</span>
                                    <input v-model="leadForm.grade_level" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Estimated value</span>
                                    <input v-model="leadForm.estimated_value" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Location</span>
                                    <input v-model="leadForm.location" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Message</span>
                                    <textarea v-model="leadForm.message" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">UTM source</span>
                                    <input v-model="leadForm.utm_source" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">UTM medium</span>
                                    <input v-model="leadForm.utm_medium" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">UTM campaign</span>
                                    <input v-model="leadForm.utm_campaign" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </label>
                            </div>

                            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                                <button
                                    type="submit"
                                    class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-black text-white hover:bg-slate-700 disabled:opacity-50"
                                    :disabled="isCreating"
                                >
                                    {{ isCreating ? 'Creating…' : 'Create lead' }}
                                </button>
                                <button type="button" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-black text-slate-700 hover:bg-slate-50" @click="closeCreateModal">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

