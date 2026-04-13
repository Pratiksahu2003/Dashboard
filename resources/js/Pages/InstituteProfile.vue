<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, router, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InstituteCard from '@/Components/InstituteCard.vue';
import PublicReviewCard from '@/Components/PublicReviewCard.vue';
import CreateLeadForm from '@/Components/CreateLeadForm.vue';
import { useAlerts } from '@/composables/useAlerts';
import { getInstitute, instituteProfilePath, resolveInstituteUserId } from '@/services/instituteApi';
import {
  getTeacherReviewStats,
  listTeacherReviews,
  checkReviewEligibility,
  submitTeacherReview,
  updateTeacherReview,
} from '@/services/reviewApi';

const inertiaPage = usePage();
const { success: alertSuccess, error: alertError } = useAlerts();

const props = defineProps({
  id: { type: Number, required: true },
  slug: { type: String, default: null },
});

const payload = ref(null);
const loading = ref(false);
const error = ref(null);
const errorCode = ref(null);
const logoError = ref(false);
const coverError = ref(false);

const data = computed(() => payload.value);
const user = computed(() => data.value?.user ?? {});
const profile = computed(() => data.value?.profile ?? {});
const social = computed(() => data.value?.social ?? null);
const counts = computed(() => data.value?.counts ?? {});

const name = computed(
  () =>
    profile.value?.name
    ?? user.value?.name
    ?? '',
);

const initials = computed(() =>
  name.value.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('') || '?',
);

const logoUrl = computed(() => profile.value?.logo_url ?? null);
const coverUrl = computed(() => profile.value?.cover_url ?? null);
const galleryUrls = computed(() =>
  Array.isArray(profile.value?.gallery_urls) ? profile.value.gallery_urls.filter(Boolean) : [],
);

const city = computed(() => profile.value?.city ?? '');
const state = computed(() => profile.value?.state ?? '');
const area = computed(() => profile.value?.area ?? '');
const pincode = computed(() => profile.value?.pincode ?? '');
const cityState = computed(() => [city.value, state.value].filter(Boolean).join(', '));

function optLabel(v) {
  if (v == null) return '';
  if (typeof v === 'object') return v.label ?? '';
  return String(v);
}

const instituteTypeLabel = computed(() => optLabel(profile.value?.institute_type));
const instituteCategoryLabel = computed(() => optLabel(profile.value?.institute_category));
const establishmentLabel = computed(() => optLabel(profile.value?.establishment_year));

/** Normalize API text (CRLF, CR, literal newlines) for `whitespace-pre-line` display. */
function normalizeAboutText(raw) {
  if (raw == null || String(raw).trim() === '') return '';
  return String(raw).replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
}

const descriptionPlain = computed(() => {
  const v = normalizeAboutText(profile.value?.description ?? profile.value?.institute_description);
  return v || null;
});

const bioPlain = computed(() => {
  const v = normalizeAboutText(profile.value?.bio ?? user.value?.bio);
  return v || null;
});

/** Show Bio in its own section when present and not identical to the description (avoids duplicate blocks). */
const showBioSection = computed(() => {
  const b = bioPlain.value;
  if (!b) return false;
  const d = descriptionPlain.value;
  if (!d) return true;
  return b !== d;
});

const DEFAULT_ABOUT =
  'This institute lists programs and contact details on SuGanta. Explore courses, facilities, and reach out from this page.';

const metaAboutSnippet = computed(() => {
  const d = descriptionPlain.value;
  const b = bioPlain.value;
  if (d && b && b !== d) return `${d}\n${b}`;
  return d || b || '';
});

const specializations = computed(() =>
  Array.isArray(profile.value?.specializations) ? profile.value.specializations : [],
);
const coursesOffered = computed(() =>
  Array.isArray(profile.value?.courses_offered) ? profile.value.courses_offered : [],
);
const facilities = computed(() =>
  Array.isArray(profile.value?.facilities) ? profile.value.facilities : [],
);
const affiliations = computed(() =>
  Array.isArray(profile.value?.affiliations) ? profile.value.affiliations : [],
);
const accreditations = computed(() =>
  Array.isArray(profile.value?.accreditations) ? profile.value.accreditations : [],
);

const principalName = computed(() => profile.value?.principal_name ?? '');
const principalPhone = computed(() => profile.value?.principal_phone ?? '');
const principalEmail = computed(() => profile.value?.principal_email ?? '');
const phonePrimary = computed(() => profile.value?.phone_primary ?? '');
const whatsapp = computed(() => profile.value?.whatsapp ?? '');
const website = computed(() => profile.value?.website ?? '');
const address = computed(() => profile.value?.address ?? '');

/** Institute show API may nest portfolio under `profile.portfolio` (or top-level `portfolio`). */
const portfolioRecord = computed(() => {
  const p = profile.value?.portfolio ?? data.value?.portfolio;
  if (!p || typeof p !== 'object' || Array.isArray(p)) return null;
  return p;
});

const portfolioImages = computed(() => {
  const imgs = portfolioRecord.value?.images;
  if (!Array.isArray(imgs)) return [];
  return imgs
    .map((x) => (x && typeof x === 'object' && x.url ? String(x.url).trim() : null))
    .filter(Boolean);
});

const portfolioFiles = computed(() => {
  const files = portfolioRecord.value?.files;
  if (!Array.isArray(files)) return [];
  return files.filter((f) => f && typeof f === 'object' && f.url);
});

const portfolioTagList = computed(() => {
  const p = portfolioRecord.value;
  if (!p) return [];
  if (Array.isArray(p.tags_array) && p.tags_array.length) {
    return p.tags_array.map((t) => String(t).trim()).filter(Boolean);
  }
  if (typeof p.tags === 'string' && p.tags.trim()) {
    return p.tags.split(',').map((t) => t.trim()).filter(Boolean);
  }
  return [];
});

const portfolioCategoryLabels = computed(() => {
  const p = portfolioRecord.value;
  if (!p) return [];
  if (Array.isArray(p.categories_array) && p.categories_array.length) {
    return p.categories_array.map((c) => String(c).trim()).filter(Boolean);
  }
  if (p.category && String(p.category).trim()) {
    return [String(p.category).trim()];
  }
  return [];
});

const hasPortfolioContent = computed(() => {
  const p = portfolioRecord.value;
  if (!p) return false;
  const desc = p.description && String(p.description).replace(/<[^>]*>/g, '').trim();
  const title = p.title && String(p.title).trim();
  return !!(desc || title || portfolioImages.value.length || portfolioFiles.value.length);
});

/** Profile website, or portfolio public URL (many institutes only set the latter). */
const publicWebsiteUrl = computed(() => {
  const w = String(website.value ?? '').trim();
  if (w) return /^https?:\/\//i.test(w) ? w : `https://${w}`;
  const pu = portfolioRecord.value?.url;
  if (pu && String(pu).trim()) {
    const s = String(pu).trim();
    return /^https?:\/\//i.test(s) ? s : `https://${s}`;
  }
  return '';
});

const relatedInstitutes = computed(() =>
  Array.isArray(data.value?.related_institutes) ? data.value.related_institutes : [],
);

const socialLinks = computed(() => {
  const s = social.value;
  if (!s || typeof s !== 'object') return [];
  const tg = s.telegram_username ? String(s.telegram_username).replace(/^@/, '').trim() : '';
  const pairs = [
    ['Instagram', s.instagram_url],
    ['LinkedIn', s.linkedin_url],
    ['Facebook', s.facebook_url],
    ['YouTube', s.youtube_url],
    ['TikTok', s.tiktok_url],
    ['Twitter / X', s.twitter_url],
    ['Telegram', tg ? `https://t.me/${tg}` : null],
    ['GitHub', s.github_url],
    ['Portfolio', s.portfolio_url],
    ['Blog', s.blog_url],
    ['Website', s.website_url],
  ];
  return pairs.filter(([, url]) => url && String(url).trim());
});

const reviewModalOpen = ref(false);
const leadModalOpen = ref(false);
const galleryLightboxUrl = ref('');

const bodyScrollLocked = computed(
  () => reviewModalOpen.value || leadModalOpen.value || !!galleryLightboxUrl.value,
);

function onOverlayEscape(e) {
  if (e.key !== 'Escape') return;
  if (galleryLightboxUrl.value) {
    galleryLightboxUrl.value = '';
    return;
  }
  if (reviewModalOpen.value) reviewModalOpen.value = false;
  else if (leadModalOpen.value) leadModalOpen.value = false;
}

watch(bodyScrollLocked, (locked) => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = locked ? 'hidden' : '';
  if (locked) document.addEventListener('keydown', onOverlayEscape);
  else document.removeEventListener('keydown', onOverlayEscape);
});

onUnmounted(() => {
  if (typeof document === 'undefined') return;
  document.removeEventListener('keydown', onOverlayEscape);
  document.body.style.overflow = '';
});

const isLoggedIn = computed(() => inertiaPage.props?.auth?.user != null);
const authUser = computed(() => inertiaPage.props?.auth?.user ?? null);
const authUserIdNumber = computed(() => {
  const id = authUser.value?.id;
  const n = Number(id);
  return Number.isFinite(n) && n > 0 ? n : null;
});

const leadOwnerUserId = computed(() => resolveInstituteUserId(data.value) ?? props.id);

const viewerLeadName = computed(() => {
  const u = authUser.value;
  if (!u) return '';
  const fn = u.first_name ?? '';
  const ln = u.last_name ?? '';
  const joined = `${fn} ${ln}`.trim();
  if (joined) return joined;
  return String(u.name ?? '').trim();
});
const viewerLeadEmail = computed(() => String(authUser.value?.email ?? '').trim());
const viewerLeadPhone = computed(() => String(authUser.value?.phone ?? '').trim());
const defaultLeadSubject = computed(() => {
  if (coursesOffered.value[0]) return String(coursesOffered.value[0]);
  const pt = portfolioRecord.value?.title;
  if (pt && String(pt).trim()) return String(pt).trim();
  if (portfolioTagList.value[0]) return portfolioTagList.value[0];
  return '';
});

const isSelfProfile = computed(
  () =>
    authUserIdNumber.value != null
    && leadOwnerUserId.value != null
    && Number(authUserIdNumber.value) === Number(leadOwnerUserId.value),
);

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

const reviewsFetchState = ref('idle');
const reviewStats = ref(null);
const reviewList = ref([]);
const reviewPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const reviewsLoading = ref(false);
const reviewsError = ref(null);

const reviewEligibility = ref(null);
const reviewCheckLoading = ref(false);
const reviewCheckError = ref(null);
const reviewRating = ref(5);
const reviewTitle = ref('');
const reviewComment = ref('');
const reviewSubmitting = ref(false);

function parseReviewApiError(err) {
  const errors = err?.errors;
  if (errors && typeof errors === 'object') {
    const k = Object.keys(errors)[0];
    if (k && Array.isArray(errors[k]) && errors[k][0]) return errors[k][0];
  }
  return err?.message || 'Could not save your review.';
}

async function loadReviewEligibility() {
  reviewEligibility.value = null;
  reviewCheckError.value = null;
  if (!data.value || !isLoggedIn.value) return;
  if (isSelfProfile.value) {
    reviewEligibility.value = { can_review: false, has_reviewed: false, is_self: true };
    return;
  }
  reviewCheckLoading.value = true;
  try {
    reviewEligibility.value = await checkReviewEligibility(props.id);
    if (reviewEligibility.value?.can_review && !reviewEligibility.value?.has_reviewed) {
      reviewRating.value = 5;
      reviewTitle.value = '';
      reviewComment.value = '';
    }
  } catch (e) {
    if (redirectToLoginIfSessionStale(e, { always: true })) {
      reviewEligibility.value = null;
      return;
    }
    reviewCheckError.value = e?.message ?? 'Could not verify review eligibility.';
    reviewEligibility.value = null;
  } finally {
    reviewCheckLoading.value = false;
  }
}

function beginEditReview() {
  const ex = reviewEligibility.value?.existing_review;
  if (!ex) return;
  reviewRating.value = Math.min(5, Math.max(1, Number(ex.rating) || 5));
  reviewTitle.value = ex.title ?? '';
  reviewComment.value = ex.comment ?? '';
  reviewModalOpen.value = true;
}

function openNewReviewModal() {
  const el = reviewEligibility.value;
  if (!el?.can_review || el.has_reviewed) return;
  reviewRating.value = 5;
  reviewTitle.value = '';
  reviewComment.value = '';
  reviewModalOpen.value = true;
}

function closeReviewModal() {
  reviewModalOpen.value = false;
}

async function submitReviewForm() {
  if (!authUserIdNumber.value || isSelfProfile.value) return;
  const r = Number(reviewRating.value);
  if (!Number.isFinite(r) || r < 1 || r > 5) {
    alertError('Please choose a rating from 1 to 5.', 'Review');
    return;
  }
  const el = reviewEligibility.value;
  const existing = el?.existing_review;
  const usePatch = !!(existing?.id && el?.has_reviewed);
  reviewSubmitting.value = true;
  try {
    if (usePatch) {
      await updateTeacherReview(existing.id, {
        rating: r,
        title: reviewTitle.value.trim(),
        comment: reviewComment.value.trim(),
      });
      alertSuccess('Review updated.', 'Review');
    } else {
      await submitTeacherReview(props.id, {
        rating: r,
        title: reviewTitle.value.trim(),
        comment: reviewComment.value.trim(),
      });
      alertSuccess('Thanks! Your review was submitted.', 'Review');
    }
    reviewModalOpen.value = false;
    reviewRating.value = 5;
    reviewTitle.value = '';
    reviewComment.value = '';
    await loadReviewData();
    await loadReviewEligibility();
  } catch (err) {
    if (redirectToLoginIfSessionStale(err, { always: true })) return;
    alertError(parseReviewApiError(err), 'Review');
  } finally {
    reviewSubmitting.value = false;
  }
}

function openLeadModal() {
  if (!isLoggedIn.value) return;
  leadModalOpen.value = true;
}

function closeLeadModal() {
  leadModalOpen.value = false;
}

function onLeadCreatedFromProfile() {
  closeLeadModal();
}

const displayAverageRating = computed(() => {
  if (reviewsFetchState.value === 'ok' && reviewStats.value?.average_rating != null) {
    return Number(reviewStats.value.average_rating);
  }
  return 0;
});

const displayTotalReviews = computed(() => {
  if (reviewsFetchState.value === 'ok' && reviewStats.value?.total_reviews != null) {
    return Number(reviewStats.value.total_reviews);
  }
  return 0;
});

const showRatingBadge = computed(
  () => displayAverageRating.value > 0 || displayTotalReviews.value > 0,
);

const displayRatingLabel = computed(() => {
  const n = displayAverageRating.value;
  if (!Number.isFinite(n) || n <= 0) {
    return displayTotalReviews.value > 0 ? '—' : '0';
  }
  return n.toFixed(1).replace(/\.0$/, '');
});

const distributionRows = computed(() => {
  const d = reviewStats.value?.distribution;
  return Array.isArray(d) ? [...d].sort((a, b) => (b.rating ?? 0) - (a.rating ?? 0)) : [];
});

const canonicalPath = computed(() => (data.value ? instituteProfilePath(data.value) : null));
const canonicalUrl = computed(() => {
  if (typeof window === 'undefined' || !canonicalPath.value) return '';
  return `${window.location.origin}${canonicalPath.value}`;
});

const metaTitle = computed(() => {
  if (!name.value) return 'Institute profile | SuGanta';
  const loc = cityState.value ? ` in ${cityState.value}` : '';
  const type = instituteTypeLabel.value ? ` — ${instituteTypeLabel.value}` : '';
  return `${name.value}${type}${loc} | SuGanta`;
});

const metaDescription = computed(() => {
  if (!name.value) {
    return 'Discover schools and institutes on SuGanta — programs, facilities, and contact options.';
  }
  const raw = metaAboutSnippet.value;
  const one = raw
    ? raw.replace(/\s+/g, ' ').trim().slice(0, 140)
    : `${name.value} is listed on SuGanta with programs and contact details.`;
  return one.length > 160 ? `${one.slice(0, 157)}…` : one;
});

async function loadInstitute() {
  loading.value = true;
  error.value = null;
  errorCode.value = null;
  try {
    payload.value = await getInstitute(Number(props.id));
    loadReviewData();
  } catch (e) {
    error.value = e?.message ?? 'Failed to load institute profile';
    errorCode.value = e?.status ?? null;
  } finally {
    loading.value = false;
  }
}

async function loadReviewData() {
  reviewsFetchState.value = 'loading';
  reviewsError.value = null;
  reviewStats.value = null;
  reviewList.value = [];
  reviewPagination.value = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
  try {
    const [stats, listPayload] = await Promise.all([
      getTeacherReviewStats(props.id),
      listTeacherReviews(props.id, { page: 1, per_page: 10, sort: 'latest' }),
    ]);
    reviewStats.value = stats;
    reviewList.value = listPayload.items;
    reviewPagination.value = listPayload.pagination;
    reviewsFetchState.value = 'ok';
  } catch (e) {
    const code = e?.code ?? e?.status;
    if (code === 401) {
      if (redirectToLoginIfSessionStale(e)) return;
      reviewsFetchState.value = 'unauthorized';
    } else {
      reviewsFetchState.value = 'error';
      reviewsError.value = e?.message ?? 'Could not load reviews.';
    }
  }
}

async function loadMoreReviews() {
  const p = reviewPagination.value;
  if (reviewsLoading.value || p.current_page >= p.last_page || reviewsFetchState.value !== 'ok') return;
  reviewsLoading.value = true;
  try {
    const next = await listTeacherReviews(props.id, {
      page: p.current_page + 1,
      per_page: p.per_page,
      sort: 'latest',
    });
    reviewList.value = [...reviewList.value, ...next.items];
    reviewPagination.value = next.pagination;
  } catch (e) {
    if (redirectToLoginIfSessionStale(e)) return;
  } finally {
    reviewsLoading.value = false;
  }
}

function navigateToInstitute(row) {
  const path = instituteProfilePath(row);
  if (path) router.visit(path);
}

watch(
  data,
  (val) => {
    if (!val || typeof window === 'undefined') return;
    const path = instituteProfilePath(val);
    if (!path || window.location.pathname === path) return;
    router.replace(path, { preserveState: true, preserveScroll: true });
  },
  { flush: 'post' },
);

watch([data, isLoggedIn], () => {
  loadReviewEligibility();
});

onMounted(loadInstitute);
</script>

<template>
  <Head>
    <title>{{ metaTitle }}</title>
    <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
    <meta name="description" :content="metaDescription" />
    <meta property="og:title" :content="metaTitle" />
    <meta property="og:description" :content="metaDescription" />
    <meta property="og:image" :content="logoUrl || 'https://app.suganta.com/logo/Su250.png'" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="metaTitle" />
    <meta name="twitter:description" :content="metaDescription" />
  </Head>

  <AppLayout>
    <div v-if="loading" class="relative mx-auto max-w-6xl animate-pulse px-1">
      <div class="mb-8 h-10 w-40 rounded-2xl bg-slate-200"></div>
      <div class="mb-6 h-48 rounded-3xl bg-slate-200"></div>
      <div class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm">
        <div class="flex flex-col gap-6 sm:flex-row">
          <div class="mx-auto h-36 w-36 rounded-2xl bg-slate-200 sm:mx-0"></div>
          <div class="flex-1 space-y-4">
            <div class="h-9 w-2/3 max-w-md rounded-xl bg-slate-200"></div>
            <div class="h-4 w-1/2 rounded-lg bg-slate-100"></div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="errorCode === 404" class="mx-auto max-w-6xl px-4 py-20 text-center">
      <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      </div>
      <p class="mb-2 text-xl font-semibold text-slate-800">Institute not found</p>
      <p class="mb-6 text-sm text-slate-500">This profile may have been removed or the link is incorrect.</p>
      <Link href="/institutes" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:bg-slate-800">Back to institutes</Link>
    </div>

    <div v-else-if="error" class="mx-auto max-w-6xl px-4 py-12">
      <div class="mb-5 rounded-2xl border border-red-200/80 bg-red-50/90 p-5 text-red-900 shadow-sm">{{ error }}</div>
      <button type="button" class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:from-indigo-500 hover:to-violet-500" @click="loadInstitute">Try again</button>
    </div>

    <div v-else-if="data" class="relative mx-auto max-w-7xl px-1">
      <div class="pointer-events-none absolute -right-20 -top-10 -z-10 h-64 w-64 rounded-full bg-violet-200/35 blur-3xl"></div>
      <div class="pointer-events-none absolute -left-16 top-32 -z-10 h-56 w-56 rounded-full bg-indigo-200/30 blur-3xl"></div>

      <Link
        href="/institutes"
        class="group mb-8 inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-sm transition hover:border-indigo-200 hover:text-indigo-700"
      >
        <svg class="h-4 w-4 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        All institutes
      </Link>

      <div
        class="relative mb-8 min-h-[280px] overflow-hidden rounded-3xl border border-slate-200/70 shadow-[0_20px_50px_-24px_rgba(79,70,229,0.25)] sm:min-h-[300px]"
      >
        <!-- Full-bleed background: cover or gradient, always readable via bottom scrim -->
        <div class="absolute inset-0">
          <img
            v-if="coverUrl && !coverError"
            :src="coverUrl"
            alt=""
            class="h-full w-full object-cover"
            @error="coverError = true"
          />
          <div
            v-else
            class="h-full w-full bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-950"
          ></div>
          <div
            class="absolute inset-0 bg-gradient-to-t from-slate-950/92 via-slate-900/55 to-slate-900/25"
            aria-hidden="true"
          ></div>
        </div>

        <div
          class="relative z-10 flex flex-col gap-6 px-5 pb-8 pt-10 sm:flex-row sm:items-end sm:gap-8 sm:px-10 sm:pb-10 sm:pt-12"
        >
          <div class="flex shrink-0 justify-center sm:justify-start">
            <div class="relative">
              <div class="absolute -inset-1 rounded-2xl bg-gradient-to-br from-white/40 to-indigo-300/30 opacity-90 blur-md"></div>
              <img
                v-if="logoUrl && !logoError"
                :src="logoUrl"
                :alt="name"
                class="relative h-28 w-28 rounded-2xl object-cover shadow-2xl ring-4 ring-white/95 sm:h-32 sm:w-32"
                @error="logoError = true"
              />
              <div
                v-else
                class="relative flex h-28 w-28 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-2xl font-bold text-white shadow-2xl ring-4 ring-white/95 sm:h-32 sm:w-32 sm:text-3xl"
              >
                {{ initials }}
              </div>
            </div>
          </div>

          <div class="min-w-0 flex-1 space-y-3 sm:space-y-4">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-2">
              <h1
                class="text-balance text-3xl font-bold tracking-tight text-white [text-shadow:0_2px_24px_rgba(0,0,0,0.35)] sm:text-4xl"
              >
                {{ name }}
              </h1>
              <span
                v-if="data.verified"
                class="rounded-full bg-sky-400 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-950 shadow-sm ring-1 ring-white/30"
              >
                Verified
              </span>
              <span
                v-if="data.is_featured"
                class="rounded-full bg-amber-400 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950 shadow-sm ring-1 ring-white/30"
              >
                Featured
              </span>
            </div>

            <div v-if="cityState || area" class="flex flex-wrap gap-2">
              <span
                class="inline-flex max-w-full items-start gap-2 rounded-xl bg-white px-3.5 py-2 text-sm font-medium leading-snug text-slate-800 shadow-md ring-1 ring-slate-200/90"
              >
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="min-w-0">
                  <span class="text-slate-900">{{ cityState }}</span>
                  <template v-if="area">
                    <span class="text-slate-400"> · </span>
                    <span class="text-slate-700">{{ area }}</span>
                  </template>
                </span>
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
              <div
                v-if="showRatingBadge"
                class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-slate-900 shadow-md ring-1 ring-slate-200/90"
              >
                <div class="flex" aria-hidden="true">
                  <svg
                    v-for="i in 5"
                    :key="i"
                    class="h-4 w-4 sm:h-5 sm:w-5"
                    :class="i <= Math.round(displayAverageRating) ? 'text-amber-400' : 'text-slate-200'"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                  </svg>
                </div>
                <span class="text-sm font-bold tabular-nums text-slate-900">{{ displayRatingLabel }}</span>
                <span class="text-sm text-slate-600">({{ displayTotalReviews }} {{ displayTotalReviews === 1 ? 'review' : 'reviews' }})</span>
              </div>
              <span
                v-if="instituteTypeLabel"
                class="inline-flex items-center rounded-lg bg-indigo-100 px-3 py-1.5 text-sm font-semibold text-indigo-950 shadow-sm ring-1 ring-indigo-200/90"
              >
                {{ instituteTypeLabel }}
              </span>
              <span
                v-if="instituteCategoryLabel"
                class="inline-flex items-center rounded-lg bg-violet-100 px-3 py-1.5 text-sm font-semibold text-violet-950 shadow-sm ring-1 ring-violet-200/90"
              >
                {{ instituteCategoryLabel }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="establishmentLabel || counts.total_students || counts.total_teachers || counts.total_branches != null"
        class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4"
      >
        <div
          v-if="establishmentLabel"
          class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm backdrop-blur-sm"
        >
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Established</p>
          <p class="mt-1 text-lg font-bold text-slate-900">{{ establishmentLabel }}</p>
        </div>
        <div
          v-if="counts.total_students"
          class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm backdrop-blur-sm"
        >
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Students</p>
          <p class="mt-1 text-lg font-bold text-slate-900">{{ counts.total_students }}</p>
        </div>
        <div
          v-if="counts.total_teachers"
          class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm backdrop-blur-sm"
        >
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Teaching staff</p>
          <p class="mt-1 text-lg font-bold text-slate-900">{{ counts.total_teachers }}</p>
        </div>
        <div
          v-if="counts.total_branches != null"
          class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm backdrop-blur-sm"
        >
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Branches</p>
          <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ counts.total_branches }}</p>
        </div>
      </div>

      <div
        class="flex flex-col-reverse gap-8 lg:grid lg:grid-cols-[minmax(0,1fr)_18.5rem] xl:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
      >
        <div class="min-w-0 space-y-8">
          <div
            v-if="descriptionPlain"
            class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div class="pointer-events-none absolute -right-16 top-0 h-40 w-40 rounded-full bg-indigo-100/50 blur-3xl"></div>
            <h2 class="relative mb-3 text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Description</h2>
            <p class="relative max-w-3xl break-words whitespace-pre-line text-base leading-[1.7] text-slate-700 sm:text-lg">
              {{ descriptionPlain }}
            </p>
          </div>

          <div
            v-if="showBioSection"
            class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div class="pointer-events-none absolute -right-16 top-0 h-40 w-40 rounded-full bg-violet-100/50 blur-3xl"></div>
            <h2 class="relative mb-3 text-xs font-bold uppercase tracking-[0.18em] text-violet-600">Bio</h2>
            <p class="relative max-w-3xl break-words whitespace-pre-line text-base leading-[1.7] text-slate-700 sm:text-lg">
              {{ bioPlain }}
            </p>
          </div>

          <div
            v-if="!descriptionPlain && !bioPlain"
            class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div class="pointer-events-none absolute -right-16 top-0 h-40 w-40 rounded-full bg-indigo-100/50 blur-3xl"></div>
            <h2 class="relative mb-3 text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">About</h2>
            <p class="relative max-w-3xl text-base leading-[1.7] text-slate-500 sm:text-lg">
              {{ DEFAULT_ABOUT }}
            </p>
          </div>

          <div
            v-if="hasPortfolioContent"
            class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-[0_12px_40px_-16px_rgba(79,70,229,0.2)]"
          >
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-violet-500/[0.04] via-transparent to-indigo-600/[0.06]"></div>
            <div class="relative p-6 sm:p-8">
              <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-violet-600">Portfolio &amp; showcase</p>
                  <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ portfolioRecord?.title || 'Programs & highlights' }}
                  </h2>
                  <p v-if="portfolioRecord?.order != null" class="mt-1 text-xs text-slate-400">Display order {{ portfolioRecord.order }}</p>
                </div>
                <span
                  v-if="portfolioRecord?.is_featured"
                  class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200/80"
                >
                  Featured portfolio
                </span>
              </div>

              <div v-if="portfolioCategoryLabels.length" class="mb-4 flex flex-wrap gap-2">
                <span
                  v-for="(cat, ci) in portfolioCategoryLabels"
                  :key="`cat-${ci}`"
                  class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white"
                >
                  {{ cat }}
                </span>
              </div>

              <div v-if="portfolioTagList.length" class="mb-6 flex flex-wrap gap-1.5">
                <span
                  v-for="(tag, ti) in portfolioTagList"
                  :key="`tag-${ti}`"
                  class="rounded-lg border border-indigo-100/80 bg-indigo-50/80 px-2.5 py-1 text-xs font-medium text-indigo-900"
                >
                  {{ tag }}
                </span>
              </div>

              <div
                v-if="portfolioRecord?.description"
                class="institute-portfolio-prose prose prose-slate max-w-none text-slate-700 prose-headings:font-bold prose-headings:text-slate-900 prose-a:text-indigo-600 prose-strong:text-slate-900"
                v-html="portfolioRecord.description"
              ></div>

              <div v-if="portfolioImages.length" class="mt-8">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-400">Photos</h3>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                  <button
                    v-for="(purl, pi) in portfolioImages"
                    :key="pi"
                    type="button"
                    class="group relative block overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-100 text-left shadow-md ring-1 ring-slate-900/5 transition hover:border-indigo-300 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    @click="galleryLightboxUrl = purl"
                  >
                    <img
                      :src="purl"
                      :alt="portfolioRecord?.title ? `${portfolioRecord.title} — ${pi + 1}` : `Portfolio ${pi + 1}`"
                      class="h-36 w-full object-cover transition duration-500 group-hover:scale-[1.04] md:h-44"
                    />
                    <span class="sr-only">View full size</span>
                  </button>
                </div>
              </div>

              <div v-if="portfolioFiles.length" class="mt-8 border-t border-slate-100 pt-8">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-400">Brochures &amp; documents</h3>
                <ul class="space-y-3">
                  <li
                    v-for="(f, fi) in portfolioFiles"
                    :key="fi"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 transition hover:border-indigo-200 hover:bg-white"
                  >
                    <span class="min-w-0 truncate text-sm font-semibold text-slate-800" :title="f.name || f.path">{{ f.name || f.path || 'Download' }}</span>
                    <a
                      :href="f.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="shrink-0 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                      Open
                    </a>
                  </li>
                </ul>
              </div>

              <div v-if="publicWebsiteUrl && !website" class="mt-6 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-3 text-sm text-indigo-950">
                <span class="font-semibold">Official site:</span>
                <a :href="publicWebsiteUrl" target="_blank" rel="noopener noreferrer" class="ml-1 break-all font-medium underline-offset-2 hover:underline">
                  {{ publicWebsiteUrl.replace(/^https?:\/\//, '') }}
                </a>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
              </span>
              <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Institute details</h2>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div v-if="establishmentLabel" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Established</span>
                <p class="font-semibold text-slate-900">{{ establishmentLabel }}</p>
              </div>
              <div v-if="counts.total_students" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Students</span>
                <p class="font-semibold text-slate-900">{{ counts.total_students }}</p>
              </div>
              <div v-if="counts.total_teachers" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Teachers</span>
                <p class="font-semibold text-slate-900">{{ counts.total_teachers }}</p>
              </div>
              <div v-if="counts.total_branches != null" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Branches</span>
                <p class="font-semibold text-slate-900">{{ counts.total_branches }}</p>
              </div>
              <div v-if="profile.affiliation_number" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100 sm:col-span-2">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Affiliation no.</span>
                <p class="font-semibold text-slate-900">{{ profile.affiliation_number }}</p>
              </div>
              <div v-if="profile.registration_number" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100 sm:col-span-2">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Registration</span>
                <p class="font-semibold text-slate-900">{{ profile.registration_number }}</p>
              </div>
              <div v-if="profile.udise_code" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">UDISE</span>
                <p class="font-semibold text-slate-900">{{ profile.udise_code }}</p>
              </div>
              <div v-if="profile.aicte_code" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">AICTE</span>
                <p class="font-semibold text-slate-900">{{ profile.aicte_code }}</p>
              </div>
              <div v-if="profile.ugc_code" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">UGC</span>
                <p class="font-semibold text-slate-900">{{ profile.ugc_code }}</p>
              </div>
            </div>
          </div>

          <div v-if="principalName || principalPhone || principalEmail" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Leadership</h2>
            <div class="space-y-3 text-sm">
              <p v-if="principalName"><span class="font-semibold text-slate-800">Principal:</span> {{ principalName }}</p>
              <p v-if="principalPhone"><span class="font-semibold text-slate-800">Phone:</span> {{ principalPhone }}</p>
              <p v-if="principalEmail" class="break-all"><span class="font-semibold text-slate-800">Email:</span> {{ principalEmail }}</p>
            </div>
          </div>

          <div v-if="address || pincode || (profile.latitude != null && profile.longitude != null)" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Location</h2>
            <p v-if="address" class="font-medium leading-relaxed text-slate-800">{{ address }}</p>
            <p v-if="pincode" class="mt-2 text-sm text-slate-600">PIN: {{ pincode }}</p>
            <a
              v-if="profile.latitude != null && profile.longitude != null"
              :href="`https://www.google.com/maps?q=${profile.latitude},${profile.longitude}`"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 transition hover:border-indigo-200 hover:bg-white"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
              Open in Maps
            </a>
          </div>

          <div v-if="coursesOffered.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Courses offered</h2>
            <div class="flex flex-wrap gap-2">
              <span v-for="(c, i) in coursesOffered" :key="i" class="rounded-xl border border-indigo-100/80 bg-indigo-50/90 px-3 py-1.5 text-sm font-semibold text-indigo-900">{{ c }}</span>
            </div>
          </div>

          <div v-if="specializations.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Specializations</h2>
            <div class="flex flex-wrap gap-2">
              <span v-for="(c, i) in specializations" :key="i" class="rounded-xl bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-900 ring-1 ring-violet-100">{{ c }}</span>
            </div>
          </div>

          <div v-if="facilities.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Facilities</h2>
            <div class="flex flex-wrap gap-2">
              <span v-for="(c, i) in facilities" :key="i" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-800">{{ c }}</span>
            </div>
          </div>

          <div v-if="affiliations.length || accreditations.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Affiliations &amp; accreditations</h2>
            <div v-if="affiliations.length" class="mb-4">
              <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Affiliations</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="(c, i) in affiliations" :key="i" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-800">{{ c }}</span>
              </div>
            </div>
            <div v-if="accreditations.length">
              <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Accreditations</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="(c, i) in accreditations" :key="i" class="rounded-lg border border-emerald-100 bg-emerald-50/80 px-3 py-1.5 text-sm font-semibold text-emerald-900">{{ c }}</span>
              </div>
            </div>
          </div>

          <div v-if="galleryUrls.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Gallery</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
              <button
                v-for="(url, i) in galleryUrls"
                :key="i"
                type="button"
                class="group relative block overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-100 text-left shadow-md transition hover:border-indigo-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                @click="galleryLightboxUrl = url"
              >
                <img :src="url" :alt="`Gallery ${i + 1}`" class="h-32 w-full object-cover transition duration-300 group-hover:scale-[1.03] md:h-40" />
              </button>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Reviews</h2>
                <p class="mt-1 text-sm text-slate-500">Feedback from the SuGanta community (same review system as tutor profiles).</p>
              </div>
              <button
                v-if="reviewsFetchState === 'error'"
                type="button"
                class="shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-white"
                @click="loadReviewData"
              >
                Retry reviews
              </button>
            </div>

            <div v-if="data && !isSelfProfile" class="mb-6 space-y-4">
              <p v-if="!isLoggedIn" class="rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm text-slate-600">
                <Link href="/login" class="font-semibold text-indigo-600 hover:text-violet-600">Sign in</Link>
                to leave a review for {{ name.split(' ')[0] || 'this institute' }}.
              </p>
              <template v-else>
                <div v-if="reviewCheckLoading" class="text-sm text-slate-500">Checking whether you can review…</div>
                <p v-else-if="reviewCheckError" class="text-sm text-red-700">{{ reviewCheckError }}</p>
                <template v-else-if="reviewEligibility">
                  <div
                    v-if="reviewEligibility.has_reviewed && reviewEligibility.existing_review"
                    class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 ring-1 ring-emerald-100/80"
                  >
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Your review</p>
                    <div class="mt-2 flex gap-0.5">
                      <svg
                        v-for="i in 5"
                        :key="i"
                        class="h-4 w-4"
                        :class="i <= (reviewEligibility.existing_review.rating || 0) ? 'text-amber-400' : 'text-slate-200'"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                    </div>
                    <h3 v-if="reviewEligibility.existing_review.title" class="mt-2 font-semibold text-slate-900">
                      {{ reviewEligibility.existing_review.title }}
                    </h3>
                    <p v-if="reviewEligibility.existing_review.comment" class="mt-1 whitespace-pre-line text-sm text-slate-700">
                      {{ reviewEligibility.existing_review.comment }}
                    </p>
                    <button type="button" class="mt-3 text-sm font-semibold text-indigo-600 hover:text-violet-600" @click="beginEditReview">
                      Edit your review
                    </button>
                  </div>
                  <div
                    v-if="reviewEligibility.can_review && !reviewEligibility.has_reviewed"
                    class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4 ring-1 ring-indigo-100/80 sm:p-5"
                  >
                    <p class="text-sm text-slate-700">Share your experience with {{ name.split(' ')[0] || 'this institute' }}.</p>
                    <button
                      type="button"
                      class="mt-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500"
                      @click="openNewReviewModal"
                    >
                      Write a review
                    </button>
                  </div>
                </template>
              </template>
            </div>

            <div v-if="reviewsFetchState === 'idle' || reviewsFetchState === 'loading'" class="animate-pulse space-y-4 py-2">
              <div class="h-4 w-48 rounded-lg bg-slate-200"></div>
              <div class="h-24 rounded-2xl bg-slate-100"></div>
            </div>

            <template v-else-if="reviewsFetchState === 'unauthorized'">
              <p v-if="!isLoggedIn" class="text-sm leading-relaxed text-slate-600">
                <Link href="/login" class="font-semibold text-indigo-600 hover:text-violet-600">Sign in</Link>
                to load full review statistics (Review API uses your session).
              </p>
              <p v-else class="text-sm leading-relaxed text-slate-600">Reviews could not be loaded. Try refreshing the page.</p>
            </template>

            <p v-else-if="reviewsFetchState === 'error' && reviewsError" class="text-sm text-red-700">{{ reviewsError }}</p>

            <template v-else-if="reviewsFetchState === 'ok'">
              <div v-if="reviewStats" class="mb-8 grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl bg-slate-50/90 p-5 ring-1 ring-slate-100">
                  <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Summary</p>
                  <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">
                    {{ Number(reviewStats.average_rating ?? 0).toFixed(1).replace(/\.0$/, '') }}
                    <span class="text-lg font-semibold text-amber-500">★</span>
                  </p>
                  <p class="mt-1 text-sm text-slate-600">{{ reviewStats.total_reviews ?? 0 }} reviews</p>
                </div>
                <div v-if="distributionRows.length" class="rounded-2xl bg-slate-50/90 p-5 ring-1 ring-slate-100">
                  <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Rating breakdown</p>
                  <div class="space-y-2">
                    <div v-for="row in distributionRows" :key="row.rating" class="flex items-center gap-3 text-sm">
                      <span class="w-9 shrink-0 font-semibold tabular-nums text-slate-700">{{ row.rating }}★</span>
                      <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-amber-400 transition-[width]" :style="{ width: `${Math.min(100, Number(row.percentage) || 0)}%` }"></div>
                      </div>
                      <span class="w-10 shrink-0 text-right tabular-nums text-slate-500">{{ row.count }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!reviewList.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-12 text-center text-sm text-slate-500">
                No published reviews yet.
              </div>
              <ul v-else class="flex flex-col gap-4">
                <PublicReviewCard
                  v-for="rev in reviewList"
                  :key="rev.id"
                  :review="rev"
                  reply-label="Response from institute"
                />
              </ul>

              <div v-if="reviewPagination.last_page > reviewPagination.current_page" class="mt-6 flex justify-center">
                <button
                  type="button"
                  class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
                  :disabled="reviewsLoading"
                  @click="loadMoreReviews"
                >
                  {{ reviewsLoading ? 'Loading…' : 'Load more reviews' }}
                </button>
              </div>
            </template>
          </div>

          <div v-if="relatedInstitutes.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <h2 class="mb-6 text-lg font-bold text-slate-900 sm:text-xl">Related institutes</h2>
            <div class="flex flex-col gap-4">
              <InstituteCard
                v-for="rel in relatedInstitutes"
                :key="rel.id"
                layout="row"
                :institute="rel"
                @click="navigateToInstitute"
                @contact="navigateToInstitute"
              />
            </div>
          </div>
        </div>

        <aside class="w-full shrink-0 space-y-6 lg:sticky lg:top-24 lg:z-10 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:overscroll-contain lg:pr-1" aria-label="Contact and links">
          <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-800 p-6 text-white shadow-2xl shadow-indigo-900/25 ring-1 ring-white/10 sm:p-7">
            <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
            <h3 class="relative text-lg font-bold sm:text-xl">Contact</h3>
            <p class="relative mt-2 text-sm leading-relaxed text-indigo-100/95">Reach {{ name.split(' ')[0] || 'this institute' }} for admissions and enquiries.</p>
            <div v-if="phonePrimary || whatsapp" class="relative mb-5 mt-5 space-y-3 rounded-2xl bg-white/10 p-4 text-sm backdrop-blur-sm">
              <p v-if="phonePrimary"><span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-200">Phone</span>{{ phonePrimary }}</p>
              <p v-if="whatsapp"><span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-200">WhatsApp</span>{{ whatsapp }}</p>
            </div>
            <p v-if="publicWebsiteUrl" class="relative mb-4 text-sm">
              <a :href="publicWebsiteUrl" target="_blank" rel="noopener noreferrer" class="break-all font-semibold underline-offset-2 hover:underline">
                {{ publicWebsiteUrl.replace(/^https?:\/\//, '') }}
              </a>
            </p>
            <button
              v-if="isLoggedIn"
              type="button"
              class="relative mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3 font-bold text-indigo-600 shadow-lg transition hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-700"
              @click="openLeadModal"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              Request contact
            </button>
            <Link
              v-else
              href="/login"
              class="relative mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3 font-bold text-indigo-600 shadow-lg transition hover:bg-indigo-50"
            >
              Sign in to contact
            </Link>
          </div>

          <div v-if="socialLinks.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-7">
            <span class="mb-3 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Social &amp; web</span>
            <ul class="flex flex-col gap-2">
              <li v-for="([label, url], i) in socialLinks" :key="i">
                <a :href="url" target="_blank" rel="noopener noreferrer" class="inline-flex break-all text-sm font-semibold text-indigo-600 transition hover:text-violet-600">{{ label }}</a>
              </li>
            </ul>
          </div>
        </aside>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="reviewModalOpen && reviewEligibility && !isSelfProfile"
        class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
        role="dialog"
        aria-modal="true"
        @click.self="closeReviewModal"
      >
        <div class="flex max-h-[min(90vh,640px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">{{ reviewEligibility.has_reviewed ? 'Update your review' : 'Write a review' }}</h2>
            <button type="button" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50" aria-label="Close" @click="closeReviewModal">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form class="min-h-0 flex-1 overflow-y-auto px-5 py-4" @submit.prevent="submitReviewForm">
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Rating</span>
              <div class="flex gap-1">
                <button
                  v-for="star in 5"
                  :key="star"
                  type="button"
                  class="rounded-lg p-1 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                  @click="reviewRating = star"
                >
                  <svg class="h-8 w-8" :class="star <= reviewRating ? 'text-amber-400' : 'text-slate-300'" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </button>
              </div>
            </div>
            <label class="mt-4 block">
              <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Title (optional)</span>
              <input v-model="reviewTitle" type="text" maxlength="255" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            </label>
            <label class="mt-3 block">
              <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Comment (optional)</span>
              <textarea v-model="reviewComment" rows="3" maxlength="5000" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            </label>
            <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
              <button type="submit" class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md disabled:opacity-50" :disabled="reviewSubmitting">
                {{ reviewSubmitting ? 'Saving…' : reviewEligibility.has_reviewed ? 'Update review' : 'Submit review' }}
              </button>
              <button type="button" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeReviewModal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="leadModalOpen && data"
        class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
        role="dialog"
        aria-modal="true"
        @click.self="closeLeadModal"
      >
        <div class="flex max-h-[min(92vh,820px)] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-indigo-50/30 px-5 py-4">
            <h2 class="text-lg font-bold tracking-tight text-slate-900">Contact {{ name.split(' ')[0] || 'institute' }}</h2>
            <button type="button" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50" aria-label="Close" @click="closeLeadModal">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-5">
            <CreateLeadForm
              compact
              :owner-user-id="leadOwnerUserId"
              :auth-user-id="authUserIdNumber"
              :teacher-name="name"
              :viewer-name="viewerLeadName"
              :viewer-email="viewerLeadEmail"
              :viewer-phone="viewerLeadPhone"
              :default-location="cityState"
              :default-subject="defaultLeadSubject"
              @created="onLeadCreatedFromProfile"
            />
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="galleryLightboxUrl"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        @click.self="galleryLightboxUrl = ''"
      >
        <button type="button" class="absolute right-4 top-4 z-10 rounded-full bg-white/15 p-2.5 text-white ring-1 ring-white/20 hover:bg-white/25" aria-label="Close" @click="galleryLightboxUrl = ''">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img :src="galleryLightboxUrl" alt="" class="max-h-[min(92vh,900px)] max-w-full rounded-lg object-contain shadow-2xl ring-1 ring-white/10" />
      </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.institute-portfolio-prose :deep(p) {
  margin-top: 0.75em;
  margin-bottom: 0.75em;
}
.institute-portfolio-prose :deep(p:first-child) {
  margin-top: 0;
}
.institute-portfolio-prose :deep(ul),
.institute-portfolio-prose :deep(ol) {
  margin: 0.75em 0;
  padding-left: 1.25rem;
}
.institute-portfolio-prose :deep(li) {
  margin: 0.35em 0;
}
.institute-portfolio-prose :deep(a) {
  word-break: break-word;
}
</style>
