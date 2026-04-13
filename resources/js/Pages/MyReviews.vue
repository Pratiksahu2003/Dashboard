<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAlerts } from '@/composables/useAlerts';
import {
    listMyReviews,
    submitTeacherReview,
    updateTeacherReview,
    deleteTeacherReview,
    checkReviewEligibility,
} from '@/services/reviewApi';
import { getTeacher, teacherProfilePath } from '@/services/teacherApi';

const page = usePage();
const { success: alertSuccess, error: alertError } = useAlerts();

const isLoggedIn = computed(() => page.props?.auth?.user != null);

function redirectToLoginIfSessionStale(errOrCode, options = {}) {
    const always = options.always === true;
    let code;
    if (typeof errOrCode === 'object' && errOrCode !== null) {
        code = errOrCode?.code ?? errOrCode?.status;
    } else {
        code = errOrCode;
    }
    const n = Number(code);
    if ((n === 401 || n === 403) && typeof document !== 'undefined') {
        if (always || isLoggedIn.value) {
            document.dispatchEvent(new CustomEvent('app:unauthorized'));
            return true;
        }
    }
    return false;
}

function parseReviewApiError(err) {
    const errors = err?.errors;
    if (errors && typeof errors === 'object') {
        const k = Object.keys(errors)[0];
        if (k && Array.isArray(errors[k]) && errors[k][0]) return errors[k][0];
    }
    return err?.message || 'Something went wrong.';
}

function profilePathForReviewable(reviewable) {
    if (!reviewable || reviewable.type !== 'user') return null;
    return teacherProfilePath({ id: reviewable.id, name: reviewable.name });
}

const listState = ref('idle');
const listError = ref(null);
const pageLoading = ref(false);
const items = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const statusFilter = ref('all');
const sortFilter = ref('latest');
const perPage = ref(10);
const scrollRoot = ref(null);

const hasPrevPage = computed(() => (pagination.value.current_page || 1) > 1);
const hasNextPage = computed(
    () => (pagination.value.current_page || 1) < (pagination.value.last_page || 1),
);
/** Show numeric page buttons when there are few pages; otherwise rely on prev/next + label. */
const showPageNumberButtons = computed(() => (pagination.value.last_page || 1) <= 12);
const allPageNumbers = computed(() => {
    const last = Math.max(1, Number(pagination.value.last_page) || 1);
    return Array.from({ length: last }, (_, i) => i + 1);
});

const loadList = async (pageNum = 1) => {
    const n = Number(pageNum) > 0 ? Number(pageNum) : 1;
    if (n === 1) {
        listState.value = 'loading';
    } else {
        pageLoading.value = true;
    }
    listError.value = null;
    try {
        const res = await listMyReviews({
            page: n,
            per_page: perPage.value,
            sort: sortFilter.value,
            status: statusFilter.value,
        });
        items.value = res.items;
        pagination.value = res.pagination;
        listState.value = 'ok';
        if (typeof window !== 'undefined' && scrollRoot.value) {
            scrollRoot.value.scrollTop = 0;
        }
    } catch (e) {
        if (redirectToLoginIfSessionStale(e, { always: true })) return;
        if (n === 1) {
            listError.value = e?.message || 'Could not load your reviews.';
            listState.value = 'error';
        } else {
            alertError(e?.message || 'Could not load this page.', 'Reviews');
        }
    } finally {
        pageLoading.value = false;
    }
};

function goPrevPage() {
    if (!hasPrevPage.value || pageLoading.value) return;
    loadList((pagination.value.current_page || 1) - 1);
}

function goNextPage() {
    if (!hasNextPage.value || pageLoading.value) return;
    loadList((pagination.value.current_page || 1) + 1);
}

watch([statusFilter, sortFilter, perPage], () => {
    loadList(1);
});

onMounted(() => {
    loadList(1);
});

const editOpen = ref(false);
const editing = ref(null);
const editRating = ref(5);
const editTitle = ref('');
const editComment = ref('');
const editSubmitting = ref(false);

function openEdit(rev) {
    editing.value = rev;
    editRating.value = Math.min(5, Math.max(1, Number(rev.rating) || 5));
    editTitle.value = rev.title ?? '';
    editComment.value = rev.comment ?? '';
    editOpen.value = true;
}

function closeEdit() {
    editOpen.value = false;
    editing.value = null;
}

async function submitEdit() {
    if (!editing.value?.id) return;
    const r = Number(editRating.value);
    if (!Number.isFinite(r) || r < 1 || r > 5) {
        alertError('Choose a rating from 1 to 5.', 'Review');
        return;
    }
    editSubmitting.value = true;
    try {
        await updateTeacherReview(editing.value.id, {
            rating: r,
            title: editTitle.value.trim(),
            comment: editComment.value.trim(),
        });
        alertSuccess('Review updated.', 'Review');
        closeEdit();
        await loadList(1);
    } catch (err) {
        if (redirectToLoginIfSessionStale(err, { always: true })) return;
        alertError(parseReviewApiError(err), 'Review');
    } finally {
        editSubmitting.value = false;
    }
}

const deleteOpen = ref(false);
const deleting = ref(null);
const deleteSubmitting = ref(false);

function openDelete(rev) {
    deleting.value = rev;
    deleteOpen.value = true;
}

function closeDelete() {
    deleteOpen.value = false;
    deleting.value = null;
}

async function confirmDelete() {
    if (!deleting.value?.id) return;
    deleteSubmitting.value = true;
    try {
        await deleteTeacherReview(deleting.value.id);
        alertSuccess('Review deleted.', 'Review');
        closeDelete();
        await loadList(1);
    } catch (err) {
        if (redirectToLoginIfSessionStale(err, { always: true })) return;
        alertError(parseReviewApiError(err), 'Review');
    } finally {
        deleteSubmitting.value = false;
    }
}

const createOpen = ref(false);
const createStep = ref('id');
const createTeacherIdInput = ref('');
const createTeacherLabel = ref('');
const createCheckLoading = ref(false);
const createCheckError = ref(null);
const createRating = ref(5);
const createTitle = ref('');
const createComment = ref('');
const createSubmitting = ref(false);
const createTargetUserId = ref(null);

function openCreate() {
    createOpen.value = true;
    createStep.value = 'id';
    createTeacherIdInput.value = '';
    createTeacherLabel.value = '';
    createCheckError.value = null;
    createRating.value = 5;
    createTitle.value = '';
    createComment.value = '';
    createTargetUserId.value = null;
}

function closeCreate() {
    createOpen.value = false;
}

function backCreateToId() {
    createStep.value = 'id';
    createTargetUserId.value = null;
}

async function continueCreate() {
    const id = Number(String(createTeacherIdInput.value).trim());
    if (!Number.isFinite(id) || id <= 0) {
        createCheckError.value = 'Enter a valid teacher user ID (the number in their profile URL).';
        return;
    }
    createCheckLoading.value = true;
    createCheckError.value = null;
    try {
        const [teacher, elig] = await Promise.all([
            getTeacher(id).catch(() => null),
            checkReviewEligibility(id),
        ]);
        if (teacher) {
            const nm =
                teacher?.user?.name
                ?? teacher?.profile?.display_name
                ?? [teacher?.profile?.first_name, teacher?.profile?.last_name].filter(Boolean).join(' ')
                ?? '';
            createTeacherLabel.value = nm || `User #${id}`;
        } else {
            createTeacherLabel.value = `User #${id}`;
        }
        if (elig.has_reviewed && elig.existing_review) {
            createCheckError.value = 'You already reviewed this teacher. You can edit or delete it in the list below.';
            createStep.value = 'id';
            await loadList(1);
            createCheckLoading.value = false;
            return;
        }
        if (!elig.can_review) {
            createCheckError.value =
                elig.existing_review
                    ? 'You already have a review for this user.'
                    : 'You cannot leave a review for this user (for example, it may be your own profile).';
            createCheckLoading.value = false;
            return;
        }
        createTargetUserId.value = id;
        createStep.value = 'form';
        createRating.value = 5;
        createTitle.value = '';
        createComment.value = '';
    } catch (e) {
        if (redirectToLoginIfSessionStale(e, { always: true })) {
            createCheckLoading.value = false;
            return;
        }
        createCheckError.value = e?.message || 'Could not verify eligibility.';
    } finally {
        createCheckLoading.value = false;
    }
}

async function submitCreate() {
    const id = createTargetUserId.value;
    if (!id) return;
    const r = Number(createRating.value);
    if (!Number.isFinite(r) || r < 1 || r > 5) {
        alertError('Choose a rating from 1 to 5.', 'Review');
        return;
    }
    createSubmitting.value = true;
    try {
        await submitTeacherReview(id, {
            rating: r,
            title: createTitle.value.trim(),
            comment: createComment.value.trim(),
        });
        alertSuccess('Thanks! Your review was submitted.', 'Review');
        closeCreate();
        await loadList(1);
    } catch (err) {
        if (redirectToLoginIfSessionStale(err, { always: true })) return;
        alertError(parseReviewApiError(err), 'Review');
    } finally {
        createSubmitting.value = false;
    }
}

function statusBadgeClass(s) {
    const v = String(s || '').toLowerCase();
    if (v === 'published') return 'bg-emerald-50 text-emerald-800 ring-emerald-100';
    if (v === 'pending') return 'bg-amber-50 text-amber-900 ring-amber-100';
    if (v === 'rejected') return 'bg-rose-50 text-rose-800 ring-rose-100';
    if (v === 'hidden') return 'bg-slate-100 text-slate-700 ring-slate-200';
    return 'bg-slate-50 text-slate-600 ring-slate-100';
}

const overlayOpen = computed(() => editOpen.value || deleteOpen.value || createOpen.value);

const reviewTotal = computed(() => {
    const t = pagination.value?.total;
    return Number.isFinite(Number(t)) && Number(t) >= 0 ? Number(t) : null;
});

function teacherInitial(name) {
    const s = String(name || '').trim();
    if (!s) return '?';
    return s[0].toUpperCase();
}

watch(overlayOpen, open => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
});

onUnmounted(() => {
    if (typeof document !== 'undefined') document.body.style.overflow = '';
});
</script>

<template>
    <Head title="My reviews" />

    <AppLayout>
        <template #breadcrumb>My reviews</template>

        <div
            ref="scrollRoot"
            class="h-full overflow-y-auto bg-gradient-to-b from-slate-100/90 via-white to-indigo-50/35 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:h-0 [&::-webkit-scrollbar]:w-0"
        >
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <header class="relative">
                    <div
                        class="pointer-events-none absolute -left-6 -top-6 h-40 w-40 rounded-full bg-indigo-400/15 blur-3xl sm:h-52 sm:w-52"
                        aria-hidden="true"
                    ></div>
                    <div
                        class="pointer-events-none absolute -right-10 top-0 h-36 w-36 rounded-full bg-violet-400/15 blur-3xl"
                        aria-hidden="true"
                    ></div>
                    <p class="relative text-[11px] font-black uppercase tracking-[0.2em] text-indigo-600/90">Your feedback</p>
                    <div class="relative mt-2 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-xl">
                            <h1 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">My reviews</h1>
                            <p class="mt-2 text-base leading-relaxed text-slate-600">
                                Everything you've shared about tutors: polish a review, remove one, or add another from a profile.
                            </p>
                            <div v-if="listState === 'ok' && reviewTotal != null" class="mt-4 inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ reviewTotal }} {{ reviewTotal === 1 ? 'review' : 'reviews' }} total
                            </div>
                        </div>
                        <button
                            type="button"
                            class="relative isolate shrink-0 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3.5 text-sm font-black text-white antialiased shadow-md shadow-slate-900/20 transition hover:from-indigo-500 hover:to-violet-500"
                            @click="openCreate"
                        >
                            <span class="relative z-10">+ New review</span>
                        </button>
                    </div>
                </header>

                <section class="relative mt-10" aria-label="Browse directories">
                    <h2 class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Jump to listings</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <Link
                            :href="route('teachers')"
                            class="group relative flex items-start gap-4 overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] transition hover:border-indigo-200 hover:shadow-[0_16px_40px_-16px_rgba(79,70,229,0.2)]"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/25"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-black text-slate-900">Teachers</p>
                                <p class="mt-1 text-sm font-medium leading-snug text-slate-600">
                                    Open a tutor profile and leave or edit a review there.
                                </p>
                            </div>
                            <svg
                                class="h-5 w-5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-indigo-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                        <Link
                            :href="route('institutes')"
                            class="group relative flex items-start gap-4 overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] transition hover:border-violet-200 hover:shadow-[0_16px_40px_-16px_rgba(124,58,237,0.18)]"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white shadow-md shadow-violet-500/25"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-black text-slate-900">Institutes</p>
                                <p class="mt-1 text-sm font-medium leading-snug text-slate-600">
                                    Browse schools and coaching centers, then open staff or linked tutors to review.
                                </p>
                            </div>
                            <svg
                                class="h-5 w-5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-violet-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </section>

                <section
                    class="mt-8 rounded-2xl border border-slate-200/70 bg-white/75 p-4 shadow-sm backdrop-blur-md sm:p-5"
                    aria-label="Filter reviews"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <label class="flex min-w-[10rem] flex-1 flex-col gap-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Status</span>
                            <select
                                v-model="statusFilter"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            >
                                <option value="all">All statuses</option>
                                <option value="published">Published</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                                <option value="hidden">Hidden</option>
                            </select>
                        </label>
                        <label class="flex min-w-[10rem] flex-1 flex-col gap-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Sort</span>
                            <select
                                v-model="sortFilter"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            >
                                <option value="latest">Newest first</option>
                                <option value="oldest">Oldest first</option>
                                <option value="highest">Highest rating</option>
                                <option value="lowest">Lowest rating</option>
                            </select>
                        </label>
                        <label class="flex w-full min-w-[8rem] flex-col gap-1.5 sm:w-auto sm:max-w-[10rem]">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Page size</span>
                            <select
                                v-model.number="perPage"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            >
                                <option :value="10">10 per page</option>
                                <option :value="20">20 per page</option>
                                <option :value="30">30 per page</option>
                            </select>
                        </label>
                    </div>
                </section>

                <div v-if="listState === 'loading'" class="mt-10 space-y-4">
                    <div class="h-32 animate-pulse rounded-2xl bg-gradient-to-r from-slate-100 to-slate-50/80"></div>
                    <div class="h-32 animate-pulse rounded-2xl bg-gradient-to-r from-slate-100 to-slate-50/80"></div>
                </div>

                <div
                    v-else-if="listState === 'error'"
                    class="mt-10 rounded-2xl border border-rose-200/80 bg-rose-50/90 p-6 shadow-sm"
                >
                    <p class="text-sm font-semibold text-rose-900">{{ listError }}</p>
                    <button
                        type="button"
                        class="mt-4 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-black text-rose-900 shadow-sm transition hover:bg-rose-50"
                        @click="loadList(1)"
                    >
                        Try again
                    </button>
                </div>

                <template v-else>
                    <ul v-if="items.length" class="mt-8 flex flex-col gap-5">
                        <li
                            v-for="rev in items"
                            :key="rev.id"
                            class="group relative overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-[0_8px_30px_-14px_rgba(15,23,42,0.1)] transition hover:border-indigo-200/60 hover:shadow-[0_20px_40px_-18px_rgba(79,70,229,0.15)]"
                        >
                            <div class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-indigo-500 via-violet-500 to-indigo-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                            <div class="relative flex flex-col gap-5 p-5 sm:flex-row sm:items-stretch sm:p-6">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-100 to-indigo-50 text-lg font-black text-indigo-700 ring-1 ring-slate-100"
                                >
                                    {{ teacherInitial(rev.reviewable?.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex rounded-lg px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1"
                                            :class="statusBadgeClass(rev.status)"
                                        >
                                            {{ rev.status || '—' }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-500">{{ rev.time_ago || rev.reviewed_at }}</span>
                                    </div>
                                    <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5 text-sm font-bold text-slate-500">
                                        <span>Review for</span>
                                        <Link
                                            v-if="profilePathForReviewable(rev.reviewable)"
                                            :href="profilePathForReviewable(rev.reviewable)"
                                            class="text-indigo-600 underline decoration-indigo-200 underline-offset-2 transition hover:text-violet-600"
                                        >
                                            {{ rev.reviewable?.name || 'Teacher' }}
                                        </Link>
                                        <span v-else class="text-slate-800">{{ rev.reviewable?.name || 'Teacher' }}</span>
                                    </p>
                                    <div class="mt-2 flex gap-0.5">
                                        <svg
                                            v-for="i in 5"
                                            :key="i"
                                            class="h-5 w-5"
                                            :class="i <= (rev.rating || 0) ? 'text-amber-400' : 'text-slate-200'"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                            aria-hidden="true"
                                        >
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </div>
                                    <h2 v-if="rev.title" class="mt-2 text-lg font-black tracking-tight text-slate-900">{{ rev.title }}</h2>
                                    <p v-if="rev.comment" class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ rev.comment }}</p>
                                    <div
                                        v-if="rev.reply"
                                        class="mt-4 rounded-xl border border-slate-100 bg-slate-50/90 p-4 ring-1 ring-slate-100/80"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Their reply</p>
                                        <p class="mt-1 whitespace-pre-line text-sm font-medium text-slate-800">{{ rev.reply }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-row gap-2 sm:flex-col sm:justify-start">
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 sm:flex-initial sm:min-w-[7.5rem]"
                                        @click="openEdit(rev)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-2.5 text-sm font-black text-rose-800 shadow-sm transition hover:bg-rose-100 sm:flex-initial sm:min-w-[7.5rem]"
                                        @click="openDelete(rev)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="mt-10 overflow-hidden rounded-2xl border border-dashed border-slate-300/80 bg-gradient-to-br from-white to-slate-50/80 px-6 py-16 text-center shadow-sm"
                    >
                        <div class="mx-auto max-w-md">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.696h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.696l1.519-4.674z" />
                                </svg>
                            </div>
                            <p class="text-base font-bold text-slate-900">No reviews yet</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Jump to Teachers or Institutes below, open a profile, or start with a teacher user ID.
                            </p>
                            <div class="mt-6 flex flex-wrap justify-center gap-3">
                                <Link
                                    :href="route('teachers')"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm transition hover:bg-slate-50"
                                >
                                    Teachers
                                </Link>
                                <Link
                                    :href="route('institutes')"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm transition hover:bg-slate-50"
                                >
                                    Institutes
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-black text-white antialiased shadow-md shadow-slate-900/20 transition hover:from-indigo-500 hover:to-violet-500"
                                    @click="openCreate"
                                >
                                    New review
                                </button>
                            </div>
                        </div>
                    </div>

                    <nav
                        v-if="items.length && (pagination.last_page || 1) > 1"
                        class="mt-10 flex flex-col items-center gap-3 pb-6"
                        aria-label="Review list pagination"
                    >
                        <p v-if="pageLoading" class="text-xs font-bold text-slate-500">Loading page…</p>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/60 disabled:cursor-not-allowed disabled:opacity-45"
                                :disabled="!hasPrevPage || pageLoading"
                                @click="goPrevPage"
                            >
                                Previous
                            </button>
                            <template v-if="showPageNumberButtons">
                                <button
                                    v-for="p in allPageNumbers"
                                    :key="`review-page-${p}`"
                                    type="button"
                                    class="min-w-[2.5rem] rounded-xl border px-3 py-2.5 text-sm font-black shadow-sm transition disabled:cursor-not-allowed"
                                    :class="
                                        p === pagination.current_page
                                            ? 'border-indigo-500 bg-indigo-600 text-white shadow-indigo-500/25'
                                            : 'border-slate-200 bg-white text-slate-800 hover:border-indigo-200 hover:bg-indigo-50/50'
                                    "
                                    :disabled="pageLoading"
                                    @click="loadList(p)"
                                >
                                    {{ p }}
                                </button>
                            </template>
                            <span
                                v-else
                                class="rounded-xl border border-slate-100 bg-slate-50/90 px-4 py-2.5 text-sm font-black tabular-nums text-slate-700"
                            >
                                Page {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/60 disabled:cursor-not-allowed disabled:opacity-45"
                                :disabled="!hasNextPage || pageLoading"
                                @click="goNextPage"
                            >
                                Next
                            </button>
                        </div>
                    </nav>
                </template>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="editOpen && editing"
                class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="edit-review-title"
                @click.self="closeEdit"
            >
                <div class="flex max-h-[min(90vh,640px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <h2 id="edit-review-title" class="text-lg font-bold text-slate-900">Edit review</h2>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-slate-50"
                            aria-label="Close"
                            @click="closeEdit"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form class="min-h-0 flex-1 overflow-y-auto px-5 py-4" @submit.prevent="submitEdit">
                        <p class="text-sm text-slate-600">
                            For <span class="font-semibold text-slate-900">{{ editing.reviewable?.name || 'teacher' }}</span>
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Rating</span>
                            <div class="flex gap-1">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    class="rounded-lg p-1 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    :aria-pressed="editRating >= star"
                                    :aria-label="`Rate ${star} out of 5`"
                                    @click="editRating = star"
                                >
                                    <svg
                                        class="h-8 w-8"
                                        :class="star <= editRating ? 'text-amber-400' : 'text-slate-300'"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <label class="mt-4 block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Title (optional)</span>
                            <input
                                v-model="editTitle"
                                type="text"
                                maxlength="255"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </label>
                        <label class="mt-3 block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Comment (optional)</span>
                            <textarea
                                v-model="editComment"
                                rows="3"
                                maxlength="5000"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </label>
                        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                            <button
                                type="submit"
                                class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white antialiased shadow-md shadow-slate-900/20 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
                                :disabled="editSubmitting"
                            >
                                {{ editSubmitting ? 'Saving…' : 'Save changes' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                @click="closeEdit"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="deleteOpen && deleting"
                class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-review-title"
                @click.self="closeDelete"
            >
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                    <h2 id="delete-review-title" class="text-lg font-bold text-slate-900">Delete this review?</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        This removes your review for
                        <span class="font-semibold text-slate-900">{{ deleting.reviewable?.name || 'this teacher' }}</span>.
                        You can write a new one later from their profile if allowed.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-rose-500 disabled:opacity-50"
                            :disabled="deleteSubmitting"
                            @click="confirmDelete"
                        >
                            {{ deleteSubmitting ? 'Deleting…' : 'Delete' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
                            @click="closeDelete"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="createOpen"
                class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="create-review-title"
                @click.self="closeCreate"
            >
                <div class="flex max-h-[min(90vh,680px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <h2 id="create-review-title" class="text-lg font-bold text-slate-900">
                            {{ createStep === 'id' ? 'New review' : 'Write your review' }}
                        </h2>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-slate-50"
                            aria-label="Close"
                            @click="closeCreate"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div v-if="createStep === 'id'" class="overflow-y-auto px-5 py-4">
                        <p class="text-sm leading-relaxed text-slate-600">
                            Open a teacher profile — the first number in the URL is their user ID. Browse
                            <Link :href="route('teachers')" class="font-semibold text-indigo-600 hover:text-violet-600" @click="closeCreate">Teachers</Link>
                            or
                            <Link :href="route('institutes')" class="font-semibold text-violet-600 hover:text-indigo-600" @click="closeCreate">Institutes</Link>
                            to find someone to review.
                        </p>
                        <label class="mt-4 block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Teacher user ID</span>
                            <input
                                v-model="createTeacherIdInput"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                placeholder="e.g. 123"
                            />
                        </label>
                        <p v-if="createCheckError" class="mt-3 text-sm text-red-700">{{ createCheckError }}</p>
                        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white antialiased shadow-md shadow-slate-900/20 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
                                :disabled="createCheckLoading"
                                @click="continueCreate"
                            >
                                {{ createCheckLoading ? 'Checking…' : 'Continue' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                @click="closeCreate"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <form v-else class="min-h-0 flex-1 overflow-y-auto px-5 py-4" @submit.prevent="submitCreate">
                        <p class="text-sm text-slate-600">
                            Reviewing
                            <span class="font-semibold text-slate-900">{{ createTeacherLabel }}</span>
                        </p>
                        <button
                            type="button"
                            class="mt-2 text-xs font-bold text-indigo-600 hover:text-violet-600"
                            @click="backCreateToId"
                        >
                            ← Change teacher ID
                        </button>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Rating</span>
                            <div class="flex gap-1">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    class="rounded-lg p-1 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    :aria-pressed="createRating >= star"
                                    :aria-label="`Rate ${star} out of 5`"
                                    @click="createRating = star"
                                >
                                    <svg
                                        class="h-8 w-8"
                                        :class="star <= createRating ? 'text-amber-400' : 'text-slate-300'"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <label class="mt-4 block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Title (optional)</span>
                            <input
                                v-model="createTitle"
                                type="text"
                                maxlength="255"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </label>
                        <label class="mt-3 block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Comment (optional)</span>
                            <textarea
                                v-model="createComment"
                                rows="3"
                                maxlength="5000"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </label>
                        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                            <button
                                type="submit"
                                class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white antialiased shadow-md shadow-slate-900/20 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
                                :disabled="createSubmitting"
                            >
                                {{ createSubmitting ? 'Submitting…' : 'Submit review' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                @click="closeCreate"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
