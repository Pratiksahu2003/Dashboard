<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import * as studyApi from '@/services/studyRequirementsApi';

const { requireAuth, getUser } = useAuth();
const { error: showError, success: showSuccess, info: showInfo } = useAlerts();

const activeTab = ref('browse');
const currentUser = ref(null);

const browseLoading = ref(false);
const requirements = ref([]);
const browseMeta = ref(null);
const browseFilters = ref({
    status: '',
    learning_mode: '',
    search: '',
    per_page: 15,
    page: 1,
});

const createLoading = ref(false);
const createForm = ref({
    contact_role: 'student',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    student_name: '',
    student_grade: '',
    subjects_text: '',
    learning_mode: 'both',
    preferred_days: '',
    preferred_time: '',
    location_city: '',
    location_state: '',
    location_area: '',
    location_pincode: '',
    budget_min: '',
    budget_max: '',
    requirements: '',
});

const myConnectionsLoading = ref(false);
const myConnections = ref([]);
const myConnectionsMeta = ref(null);
const myConnectionsFilters = ref({
    status: '',
    per_page: 15,
    page: 1,
});

const detailsModalOpen = ref(false);
const detailsLoading = ref(false);
const selectedRequirement = ref(null);
const connectLoading = ref(false);
const connectMessage = ref('');

const browseHasPrev = computed(() => (browseMeta.value?.current_page || 1) > 1);
const browseHasNext = computed(() => (browseMeta.value?.current_page || 1) < (browseMeta.value?.last_page || 1));
const myConnectionsHasPrev = computed(() => (myConnectionsMeta.value?.current_page || 1) > 1);
const myConnectionsHasNext = computed(() => (myConnectionsMeta.value?.current_page || 1) < (myConnectionsMeta.value?.last_page || 1));

const normalizedAuthName = computed(() => {
    const u = currentUser.value || {};
    const fromNames = `${u.first_name || ''} ${u.last_name || ''}`.trim();
    return fromNames || u.name || '';
});

const normalizedAuthEmail = computed(() => (currentUser.value?.email || '').trim());
const normalizedAuthPhone = computed(() => {
    const u = currentUser.value || {};
    return (
        u.phone ||
        u.phone_number ||
        u.mobile ||
        u.mobile_number ||
        u.contact_number ||
        u.contact_phone ||
        u.whatsapp_number ||
        ''
    )
        .toString()
        .trim();
});
const isAuthNameLocked = computed(() => !!normalizedAuthName.value);
const isAuthEmailLocked = computed(() => !!normalizedAuthEmail.value);
const isAuthPhoneLocked = computed(() => !!normalizedAuthPhone.value);

const formatDateTime = value => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString();
};

const statusClass = status => {
    const value = String(status || '').toLowerCase();
    if (value === 'new') return 'bg-blue-50 border-blue-200 text-blue-700';
    if (value === 'in_review') return 'bg-amber-50 border-amber-200 text-amber-700';
    if (value === 'matched') return 'bg-emerald-50 border-emerald-200 text-emerald-700';
    if (value === 'closed') return 'bg-slate-100 border-slate-200 text-slate-700';
    if (value === 'pending') return 'bg-amber-50 border-amber-200 text-amber-700';
    if (value === 'accepted') return 'bg-emerald-50 border-emerald-200 text-emerald-700';
    if (value === 'rejected') return 'bg-rose-50 border-rose-200 text-rose-700';
    return 'bg-slate-100 border-slate-200 text-slate-700';
};

const parseSubjects = text =>
    String(text || '')
        .split(',')
        .map(v => v.trim())
        .filter(Boolean);

const hydrateLockedContactFields = () => {
    if (isAuthNameLocked.value) createForm.value.contact_name = normalizedAuthName.value;
    if (isAuthEmailLocked.value) createForm.value.contact_email = normalizedAuthEmail.value;
    if (isAuthPhoneLocked.value) createForm.value.contact_phone = normalizedAuthPhone.value;

    if (!createForm.value.student_name) {
        createForm.value.student_name = normalizedAuthName.value;
    }
};

const normalizePincode = value => String(value || '').replace(/\D/g, '').slice(0, 6);

const loadBrowse = async () => {
    browseLoading.value = true;
    try {
        const data = await studyApi.listStudyRequirements({
            status: browseFilters.value.status || undefined,
            learning_mode: browseFilters.value.learning_mode || undefined,
            search: browseFilters.value.search || undefined,
            per_page: browseFilters.value.per_page,
            page: browseFilters.value.page,
        });
        requirements.value = data?.data || [];
        browseMeta.value = data?.meta || null;
    } catch (e) {
        showError(e?.message || 'Unable to load study requirements.', 'Study Requirements');
    } finally {
        browseLoading.value = false;
    }
};

const loadMyConnections = async () => {
    myConnectionsLoading.value = true;
    try {
        const data = await studyApi.listMyConnections({
            status: myConnectionsFilters.value.status || undefined,
            per_page: myConnectionsFilters.value.per_page,
            page: myConnectionsFilters.value.page,
        });
        myConnections.value = data?.data || [];
        myConnectionsMeta.value = data?.meta || null;
    } catch (e) {
        showError(e?.message || 'Unable to load your connected requirements.', 'Study Requirements');
    } finally {
        myConnectionsLoading.value = false;
    }
};

const applyBrowseFilters = async () => {
    browseFilters.value.page = 1;
    await loadBrowse();
};

const resetCreateForm = () => {
    createForm.value = {
        contact_role: 'student',
        contact_name: normalizedAuthName.value,
        contact_email: normalizedAuthEmail.value,
        contact_phone: normalizedAuthPhone.value,
        student_name: normalizedAuthName.value,
        student_grade: '',
        subjects_text: '',
        learning_mode: 'both',
        preferred_days: '',
        preferred_time: '',
        location_city: '',
        location_state: '',
        location_area: '',
        location_pincode: '',
        budget_min: '',
        budget_max: '',
        requirements: '',
    };
};

const submitCreateRequirement = async () => {
    if (!createForm.value.contact_name || !createForm.value.contact_email || !createForm.value.contact_phone) {
        showInfo('Your account must include name, email, and phone to create a study requirement.', 'Profile Info Required');
        return;
    }

    const pincode = normalizePincode(createForm.value.location_pincode);
    createForm.value.location_pincode = pincode;
    if (pincode && !/^\d{6}$/.test(pincode)) {
        showError('Pincode must be exactly 6 digits.', 'Validation');
        return;
    }

    createLoading.value = true;
    try {
        const payload = {
            contact_role: createForm.value.contact_role,
            contact_name: createForm.value.contact_name,
            contact_email: createForm.value.contact_email,
            contact_phone: createForm.value.contact_phone,
            student_name: createForm.value.student_name || undefined,
            student_grade: createForm.value.student_grade || undefined,
            subjects: parseSubjects(createForm.value.subjects_text),
            learning_mode: createForm.value.learning_mode || 'both',
            preferred_days: createForm.value.preferred_days || undefined,
            preferred_time: createForm.value.preferred_time || undefined,
            location_city: createForm.value.location_city || undefined,
            location_state: createForm.value.location_state || undefined,
            location_area: createForm.value.location_area || undefined,
            location_pincode: createForm.value.location_pincode || undefined,
            budget_min: createForm.value.budget_min === '' ? undefined : Number(createForm.value.budget_min),
            budget_max: createForm.value.budget_max === '' ? undefined : Number(createForm.value.budget_max),
            requirements: createForm.value.requirements || undefined,
        };

        await studyApi.createStudyRequirement(payload);
        showSuccess('Study requirement created successfully.', 'Study Requirements');
        resetCreateForm();
        activeTab.value = 'browse';
        browseFilters.value.page = 1;
        await loadBrowse();
    } catch (e) {
        showError(e?.message || 'Unable to create study requirement.', 'Study Requirements');
    } finally {
        createLoading.value = false;
    }
};

const openRequirementDetails = async requirementId => {
    if (!requirementId) return;
    detailsModalOpen.value = true;
    detailsLoading.value = true;
    selectedRequirement.value = null;
    connectMessage.value = '';
    try {
        const data = await studyApi.getStudyRequirement(requirementId);
        selectedRequirement.value = data || null;
    } catch (e) {
        showError(e?.message || 'Unable to load requirement details.', 'Study Requirements');
        detailsModalOpen.value = false;
    } finally {
        detailsLoading.value = false;
    }
};

const connectToRequirement = async () => {
    if (!selectedRequirement.value?.id) return;
    connectLoading.value = true;
    try {
        await studyApi.connectToRequirement(selectedRequirement.value.id, {
            message: connectMessage.value || undefined,
        });
        showSuccess('Successfully connected to this requirement.', 'Study Requirements');
        selectedRequirement.value.is_connected = true;
        await Promise.all([loadBrowse(), loadMyConnections()]);
    } catch (e) {
        showError(e?.message || 'Unable to connect to requirement.', 'Study Requirements');
    } finally {
        connectLoading.value = false;
    }
};

onMounted(async () => {
    if (!requireAuth()) return;
    currentUser.value = getUser();
    hydrateLockedContactFields();
    await Promise.all([loadBrowse(), loadMyConnections()]);
});
</script>

<template>
    <Head title="Study Requirements" />

    <AppLayout>
        <template #breadcrumb>Study Requirements</template>

        <div class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Learning Requests</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Study Requirements</h1>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Create tutoring requirements, browse requests, and connect with learners.</p>
                    </div>
                    <div class="flex rounded-xl bg-slate-100 p-1 gap-1 w-full md:w-auto">
                        <button
                            type="button"
                            class="flex-1 md:flex-none rounded-lg px-3 py-2 text-xs font-black transition"
                            :class="activeTab === 'browse' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="activeTab = 'browse'"
                        >
                            Browse
                        </button>
                        <button
                            type="button"
                            class="flex-1 md:flex-none rounded-lg px-3 py-2 text-xs font-black transition"
                            :class="activeTab === 'create' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="activeTab = 'create'"
                        >
                            Create
                        </button>
                        <button
                            type="button"
                            class="flex-1 md:flex-none rounded-lg px-3 py-2 text-xs font-black transition"
                            :class="activeTab === 'connections' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="activeTab = 'connections'"
                        >
                            My Connections
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'browse'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <input
                        v-model="browseFilters.search"
                        type="text"
                        placeholder="Search reference, name, city"
                        class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700"
                    />
                    <select v-model="browseFilters.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">
                        <option value="">All status</option>
                        <option value="new">New</option>
                        <option value="in_review">In review</option>
                        <option value="matched">Matched</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select v-model="browseFilters.learning_mode" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">
                        <option value="">All mode</option>
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                        <option value="both">Both</option>
                    </select>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                        :disabled="browseLoading"
                        @click="applyBrowseFilters"
                    >
                        {{ browseLoading ? 'Loading...' : 'Apply Filters' }}
                    </button>
                </div>

                <div v-if="browseLoading" class="space-y-3">
                    <div v-for="i in 4" :key="`browse-loading-${i}`" class="h-24 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>
                <div v-else-if="requirements.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No study requirements found.</p>
                </div>
                <div v-else class="space-y-3">
                    <article
                        v-for="row in requirements"
                        :key="row.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-black text-slate-900 truncate">
                                    {{ row.reference_id }} - {{ row.student_name || row.contact_name || 'Requirement' }}
                                </h3>
                                <p class="mt-1 text-sm font-semibold text-slate-600">
                                    {{ (row.subjects || []).join(', ') || 'No subjects specified' }} | {{ row.learning_mode || '-' }} | {{ row.location_city || '-' }}
                                </p>
                                <p class="mt-1 text-xs font-bold text-slate-500">
                                    Budget: {{ row.budget_min || 0 }} - {{ row.budget_max || 0 }} | Created: {{ formatDateTime(row.created_at) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase" :class="statusClass(row.status)">
                                    {{ row.status || 'new' }}
                                </span>
                                <span v-if="row.is_connected" class="text-[11px] font-black rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700 uppercase">
                                    Connected
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition"
                                    @click="openRequirementDetails(row.id)"
                                >
                                    View
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs font-bold text-slate-500">
                        Showing {{ browseMeta?.from || 0 }}-{{ browseMeta?.to || 0 }} of {{ browseMeta?.total || 0 }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!browseHasPrev || browseLoading"
                            @click="browseFilters.page -= 1; loadBrowse()"
                        >
                            Previous
                        </button>
                        <span class="text-xs font-black text-slate-700">
                            Page {{ browseMeta?.current_page || 1 }} / {{ browseMeta?.last_page || 1 }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!browseHasNext || browseLoading"
                            @click="browseFilters.page += 1; loadBrowse()"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'create'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-lg font-black text-slate-900">Create Study Requirement</h2>
                    <p class="text-xs font-semibold text-slate-500">Basic contact info is auto-filled from your authenticated account and locked.</p>
                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h3 class="text-sm font-black text-slate-900">Basic Information</h3>
                            <span class="text-[10px] font-black uppercase tracking-wide rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">Auto-filled account details locked</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Contact Role</span>
                                <select v-model="createForm.contact_role" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                                    <option value="student">Student</option>
                                    <option value="parent">Parent</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Contact Name (Auto)</span>
                                <input
                                    v-model="createForm.contact_name"
                                    type="text"
                                    :disabled="isAuthNameLocked"
                                    :placeholder="isAuthNameLocked ? '' : 'Enter contact name'"
                                    :class="isAuthNameLocked
                                        ? 'w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 cursor-not-allowed'
                                        : 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700'"
                                />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Contact Email (Auto)</span>
                                <input
                                    v-model="createForm.contact_email"
                                    type="email"
                                    :disabled="isAuthEmailLocked"
                                    :placeholder="isAuthEmailLocked ? '' : 'Enter contact email'"
                                    :class="isAuthEmailLocked
                                        ? 'w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 cursor-not-allowed'
                                        : 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700'"
                                />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Contact Phone (Auto)</span>
                                <input
                                    v-model="createForm.contact_phone"
                                    type="text"
                                    :disabled="isAuthPhoneLocked"
                                    :placeholder="isAuthPhoneLocked ? '' : 'Enter contact phone'"
                                    :class="isAuthPhoneLocked
                                        ? 'w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 cursor-not-allowed'
                                        : 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700'"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-black text-slate-900 mb-3">Student & Subject Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Student Name</span>
                                <input v-model="createForm.student_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Student Grade</span>
                                <input v-model="createForm.student_grade" type="text" placeholder="Class 10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Subjects (comma separated)</span>
                                <input v-model="createForm.subjects_text" type="text" placeholder="Mathematics, Physics" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-black text-slate-900 mb-3">Schedule & Location</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Learning Mode</span>
                                <select v-model="createForm.learning_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">
                                    <option value="online">Online</option>
                                    <option value="offline">Offline</option>
                                    <option value="both">Both</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Preferred Days</span>
                                <input v-model="createForm.preferred_days" type="text" placeholder="Mon, Wed, Fri" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Preferred Time</span>
                                <input v-model="createForm.preferred_time" type="text" placeholder="4:00 PM - 6:00 PM" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">City</span>
                                <input v-model="createForm.location_city" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">State</span>
                                <input v-model="createForm.location_state" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Area</span>
                                <input v-model="createForm.location_area" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Pincode</span>
                                <input
                                    v-model="createForm.location_pincode"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="6"
                                    placeholder="6-digit pincode"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700"
                                    @input="createForm.location_pincode = normalizePincode(createForm.location_pincode)"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-black text-slate-900 mb-3">Budget & Additional Notes</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Budget Min</span>
                                <input v-model="createForm.budget_min" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Budget Max</span>
                                <input v-model="createForm.budget_max" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" />
                            </label>

                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Requirements / Notes</span>
                                <textarea v-model="createForm.requirements" rows="4" maxlength="5000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 resize-y"></textarea>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 text-white px-5 py-2.5 text-sm font-black hover:bg-slate-800 transition disabled:opacity-50"
                        :disabled="createLoading"
                        @click="submitCreateRequirement"
                    >
                        {{ createLoading ? 'Submitting...' : 'Submit Requirement' }}
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'connections'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <select v-model="myConnectionsFilters.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">
                        <option value="">All status</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition disabled:opacity-60"
                        :disabled="myConnectionsLoading"
                        @click="myConnectionsFilters.page = 1; loadMyConnections()"
                    >
                        {{ myConnectionsLoading ? 'Loading...' : 'Apply Filters' }}
                    </button>
                </div>

                <div v-if="myConnectionsLoading" class="space-y-3">
                    <div v-for="i in 4" :key="`conn-loading-${i}`" class="h-20 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>
                <div v-else-if="myConnections.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No connected requirements found.</p>
                </div>
                <div v-else class="space-y-3">
                    <article v-for="conn in myConnections" :key="conn.id" class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-black text-slate-900">
                                    {{ conn.requirement?.reference_id || '-' }} - {{ conn.requirement?.student_name || conn.requirement?.contact_name || 'Requirement' }}
                                </h3>
                                <p class="mt-1 text-sm font-semibold text-slate-600">
                                    {{ (conn.requirement?.subjects || []).join(', ') || 'No subjects' }} | {{ conn.requirement?.learning_mode || '-' }}
                                </p>
                                <p class="mt-1 text-xs font-bold text-slate-500">Connected: {{ formatDateTime(conn.connected_at) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase" :class="statusClass(conn.status)">
                                    {{ conn.status || 'pending' }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-black text-slate-700 hover:bg-slate-100 transition"
                                    @click="openRequirementDetails(conn.requirement?.id)"
                                >
                                    View
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs font-bold text-slate-500">
                        Showing {{ myConnectionsMeta?.from || 0 }}-{{ myConnectionsMeta?.to || 0 }} of {{ myConnectionsMeta?.total || 0 }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!myConnectionsHasPrev || myConnectionsLoading"
                            @click="myConnectionsFilters.page -= 1; loadMyConnections()"
                        >
                            Previous
                        </button>
                        <span class="text-xs font-black text-slate-700">
                            Page {{ myConnectionsMeta?.current_page || 1 }} / {{ myConnectionsMeta?.last_page || 1 }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                            :disabled="!myConnectionsHasNext || myConnectionsLoading"
                            @click="myConnectionsFilters.page += 1; loadMyConnections()"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="detailsModalOpen" max-width="2xl" @close="detailsModalOpen = false">
            <div class="px-5 py-5">
                <h2 class="text-lg font-black text-slate-900">Requirement Details</h2>
                <div v-if="detailsLoading" class="mt-3 h-28 rounded-xl bg-slate-100 animate-pulse"></div>
                <div v-else-if="selectedRequirement" class="mt-3 space-y-3">
                    <div class="rounded-xl border border-slate-200 p-3">
                        <p class="text-sm font-black text-slate-900">{{ selectedRequirement.reference_id }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            {{ selectedRequirement.contact_name }} | {{ selectedRequirement.contact_email }} | {{ selectedRequirement.contact_phone }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            Student: {{ selectedRequirement.student_name || '-' }} | Grade: {{ selectedRequirement.student_grade || '-' }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            Subjects: {{ (selectedRequirement.subjects || []).join(', ') || '-' }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            Mode: {{ selectedRequirement.learning_mode || '-' }} | Status: {{ selectedRequirement.status || '-' }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            Requirements: {{ selectedRequirement.requirements || '-' }}
                        </p>
                    </div>

                    <div v-if="!selectedRequirement.is_connected" class="rounded-xl border border-slate-200 p-3">
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Connection Message (Optional)</span>
                            <textarea v-model="connectMessage" rows="3" maxlength="2000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700"></textarea>
                        </label>
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="rounded-lg bg-slate-900 text-white px-4 py-2 text-xs font-black hover:bg-slate-800 transition disabled:opacity-50"
                                :disabled="connectLoading"
                                @click="connectToRequirement"
                            >
                                {{ connectLoading ? 'Connecting...' : 'Connect to Requirement' }}
                            </button>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">
                        You have already connected to this requirement.
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

