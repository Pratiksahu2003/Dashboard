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
const items = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const statusFilter = ref('all');
const sortFilter = ref('latest');
const perPage = ref(10);

const loadList = async (pageNum = 1) => {
    listState.value = pageNum === 1 ? 'loading' : 'appending';
    listError.value = null;
    try {
        const res = await listMyReviews({
            page: pageNum,
            per_page: perPage.value,
            sort: sortFilter.value,
            status: statusFilter.value,
        });
        if (pageNum === 1) {
            items.value = res.items;
        } else {
            items.value = [...items.value, ...res.items];
        }
        pagination.value = res.pagination;
        listState.value = 'ok';
    } catch (e) {
        if (redirectToLoginIfSessionStale(e, { always: true })) return;
        listError.value = e?.message || 'Could not load your reviews.';
        listState.value = 'error';
    }
};

const loadMore = () => {
    if (listState.value === 'appending') return;
    const p = pagination.value;
    if (p.current_page >= p.last_page) return;
    loadList(p.current_page + 1);
};

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
        <div class="h-full overflow-y-auto">
            <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 sm:text-3xl">My reviews</h1>
                        <p class="mt-1 text-sm text-slate-600">
                            Reviews you wrote about teachers. Create new ones from here (by teacher ID) or from a teacher profile.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('teachers')"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-50"
                        >
                            Browse teachers
                        </Link>
                        <button
                            type="button"
                            class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500"
                            @click="openCreate"
                        >
                            New review
                        </button>
                    </div>
                </div>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</span>
                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        >
                            <option value="all">All</option>
                            <option value="published">Published</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                            <option value="hidden">Hidden</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Sort</span>
                        <select
                            v-model="sortFilter"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        >
                            <option value="latest">Latest</option>
                            <option value="oldest">Oldest</option>
                            <option value="highest">Highest rating</option>
                            <option value="lowest">Lowest rating</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Per page</span>
                        <select
                            v-model.number="perPage"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        >
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="30">30</option>
                        </select>
                    </label>
                </div>

                <div v-if="listState === 'loading'" class="mt-10 space-y-4 animate-pulse">
                    <div class="h-28 rounded-2xl bg-slate-200/80"></div>
                    <div class="h-28 rounded-2xl bg-slate-200/80"></div>
                </div>

                <div v-else-if="listState === 'error'" class="mt-10 rounded-2xl border border-rose-100 bg-rose-50/80 p-6 text-sm text-rose-800">
                    {{ listError }}
                    <button
                        type="button"
                        class="mt-3 block rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-bold text-rose-900 hover:bg-rose-50"
                        @click="loadList(1)"
                    >
                        Retry
                    </button>
                </div>

                <template v-else>
                    <p v-if="pagination.total != null" class="mt-6 text-sm font-semibold text-slate-600">
                        {{ pagination.total }} {{ pagination.total === 1 ? 'review' : 'reviews' }}
                    </p>

                    <ul v-if="items.length" class="mt-4 flex flex-col gap-4">
                        <li
                            v-for="rev in items"
                            :key="rev.id"
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex rounded-lg px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1"
                                            :class="statusBadgeClass(rev.status)"
                                        >
                                            {{ rev.status || '—' }}
                                        </span>
                                        <span class="text-xs text-slate-500">{{ rev.time_ago || rev.reviewed_at }}</span>
                                    </div>
                                    <p class="mt-2 flex flex-wrap items-baseline gap-x-1.5 text-sm font-bold text-slate-500">
                                        <span>For</span>
                                        <Link
                                            v-if="profilePathForReviewable(rev.reviewable)"
                                            :href="profilePathForReviewable(rev.reviewable)"
                                            class="text-indigo-600 hover:text-violet-600"
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
                                        >
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </div>
                                    <h2 v-if="rev.title" class="mt-2 text-lg font-bold text-slate-900">{{ rev.title }}</h2>
                                    <p v-if="rev.comment" class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ rev.comment }}</p>
                                    <div v-if="rev.reply" class="mt-4 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Their reply</p>
                                        <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ rev.reply }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-row gap-2 sm:flex-col">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50"
                                        @click="openEdit(rev)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-rose-100 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-800 hover:bg-rose-100"
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
                        class="mt-10 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-16 text-center text-sm text-slate-600"
                    >
                        No reviews yet. Open a teacher profile or use
                        <button type="button" class="font-bold text-indigo-600 hover:text-violet-600" @click="openCreate">New review</button>
                        with their user ID from the profile URL.
                    </div>

                    <div
                        v-if="items.length && pagination.last_page > pagination.current_page"
                        class="mt-8 flex justify-center"
                    >
                        <button
                            type="button"
                            class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
                            :disabled="listState === 'appending'"
                            @click="loadMore"
                        >
                            {{ listState === 'appending' ? 'Loading…' : 'Load more' }}
                        </button>
                    </div>
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
                                class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
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
                        <p class="text-sm text-slate-600">
                            Open any teacher profile — the first number in the URL is their user ID. Or start from
                            <Link :href="route('teachers')" class="font-semibold text-indigo-600 hover:text-violet-600" @click="closeCreate">Teachers</Link>.
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
                                class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
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
                                class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
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
