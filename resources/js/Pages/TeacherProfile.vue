<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, router, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import PublicReviewCard from '@/Components/PublicReviewCard.vue';
import CreateLeadForm from '@/Components/CreateLeadForm.vue';
import WhyChooseUsSection from '@/Components/Profile/WhyChooseUsSection.vue';
import GetDirectionsMapSection from '@/Components/Profile/GetDirectionsMapSection.vue';
import { useAlerts } from '@/composables/useAlerts';
import { getTeacher, teacherProfilePath, resolveTeacherUserId } from '@/services/teacherApi';
import {
  getTeacherReviewStats,
  listTeacherReviews,
  checkReviewEligibility,
  submitTeacherReview,
  updateTeacherReview,
} from '@/services/reviewApi';

const page = usePage();
const { success: alertSuccess, error: alertError } = useAlerts();

const props = defineProps({
  id: { type: Number, required: true },
  /** From `/teachers/{id}/{slug}`; null when served from legacy `/teachers/{id}`. */
  slug: { type: String, default: null },
});

const teacher = ref(null);
const loading = ref(false);
const error = ref(null);
const errorCode = ref(null);
const avatarError = ref(false);

const t = computed(() => teacher.value);
const profile = computed(() => t.value?.profile ?? {});
const teaching = computed(() => t.value?.teaching ?? profile.value?.teaching ?? {});
const name = computed(
  () =>
    t.value?.user?.name
    ?? profile.value?.display_name
    ?? [profile.value?.first_name, profile.value?.last_name].filter(Boolean).join(' ')
    ?? '',
);
const initials = computed(() => name.value.split(' ').filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('') || '?');
const avatarUrl = computed(
  () =>
    profile.value?.profile_image_url
    ?? t.value?.profile_image_url
    ?? null,
);

const avatarLightboxOpen = ref(false);

function openAvatarLightbox() {
  if (avatarUrl.value && !avatarError.value) avatarLightboxOpen.value = true;
}

function closeAvatarLightbox() {
  avatarLightboxOpen.value = false;
}

const portfolioLightboxUrl = ref('');

function openPortfolioLightbox(url) {
  const u = typeof url === 'string' ? url.trim() : '';
  if (!u) return;
  portfolioLightboxUrl.value = u;
}

function closePortfolioLightbox() {
  portfolioLightboxUrl.value = '';
}

const reviewModalOpen = ref(false);
const leadModalOpen = ref(false);

const bodyScrollLocked = computed(
  () =>
    avatarLightboxOpen.value
    || !!portfolioLightboxUrl.value
    || reviewModalOpen.value
    || leadModalOpen.value,
);

function onOverlayEscape(e) {
  if (e.key !== 'Escape') return;
  if (avatarLightboxOpen.value) {
    closeAvatarLightbox();
    return;
  }
  if (portfolioLightboxUrl.value) {
    closePortfolioLightbox();
    return;
  }
  if (reviewModalOpen.value) {
    reviewModalOpen.value = false;
    return;
  }
  if (leadModalOpen.value) {
    leadModalOpen.value = false;
  }
}

watch(bodyScrollLocked, (locked) => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = locked ? 'hidden' : '';
  if (locked) {
    document.addEventListener('keydown', onOverlayEscape);
  } else {
    document.removeEventListener('keydown', onOverlayEscape);
  }
});

onUnmounted(() => {
  if (typeof document === 'undefined') return;
  document.removeEventListener('keydown', onOverlayEscape);
  document.body.style.overflow = '';
});

/** Plain bio text; null if empty — UI can show a default line */
const bioPlain = computed(() => {
  const raw = profile.value?.bio;
  if (raw == null || String(raw).trim() === '') return null;
  return String(raw).replace(/\r\n/g, '\n').trim();
});

const DEFAULT_PROFILE_BIO =
  'Professional educator on SuGanta offering personalized support. Explore subjects, qualifications, rates, and contact options on this page.';

const profileBioDisplay = computed(() => bioPlain.value || DEFAULT_PROFILE_BIO);
const location = computed(() => t.value?.location ?? profile.value?.location ?? {});
const city = computed(() => location.value?.city ?? '');
const state = computed(() => location.value?.state ?? '');
const area = computed(() => location.value?.area ?? '');
const pincode = computed(() => location.value?.pincode ?? '');
const addressLine1 = computed(() => location.value?.address_line_1 ?? '');
const addressLine2 = computed(() => location.value?.address_line_2 ?? '');
const countryLabel = computed(() => location.value?.country?.label ?? '');
const cityState = computed(() => [city.value, state.value].filter(Boolean).join(', '));
const fullAddressLines = computed(() => {
  const lines = [
    [addressLine1.value, addressLine2.value].filter(Boolean).join(', '),
    [area.value, city.value, state.value].filter(Boolean).join(', '),
    [pincode.value, countryLabel.value].filter(Boolean).join(' ').trim(),
  ].filter(Boolean);
  return lines;
});

const mapLatitude = computed(() => {
  const loc = location.value;
  const p = profile.value;
  return loc?.latitude ?? loc?.lat ?? p?.latitude ?? p?.lat ?? null;
});

const mapLongitude = computed(() => {
  const loc = location.value;
  const p = profile.value;
  return loc?.longitude ?? loc?.lng ?? p?.longitude ?? p?.lng ?? null;
});

const hasMapCoords = computed(() => {
  const la = Number(mapLatitude.value);
  const lo = Number(mapLongitude.value);
  return (
    Number.isFinite(la)
    && Number.isFinite(lo)
    && la >= -90
    && la <= 90
    && lo >= -180
    && lo <= 180
  );
});

/** Geocoding fallback: structured address lines, then area/city/state. */
const teacherPlaceQuery = computed(() => {
  const joined = fullAddressLines.value.join(', ').trim();
  if (joined.length >= 3) return joined;
  return [area.value, city.value, state.value, pincode.value, countryLabel.value].filter(Boolean).join(', ').trim();
});

const teacherShowDirectionsMap = computed(
  () => !!String(name.value || '').trim() && (hasMapCoords.value || teacherPlaceQuery.value.length >= 3),
);

const teacherDirectionsAddress = computed(() => fullAddressLines.value.join(', '));

const teacherDirectionsPhone = computed(() =>
  String(phonePrimary.value || phoneSecondary.value || '').trim(),
);

const qualification = computed(() => teaching.value?.qualification?.label ?? '');
const qualificationText = computed(() => {
  const v = teaching.value?.qualification_text;
  if (v == null || v === '') return '';
  const s = String(v).trim();
  if (/^\d+$/.test(s)) return '';
  return s;
});
const experience = computed(() => teaching.value?.teaching_experience_years?.label ?? '');
const hourlyRateFixed = computed(() => teaching.value?.hourly_rate ?? null);
const monthlyRateFixed = computed(() => teaching.value?.monthly_rate ?? null);
const hourlyRateRange = computed(() => teaching.value?.hourly_rate_range?.label ?? '');
const monthlyRateRange = computed(() => teaching.value?.monthly_rate_range?.label ?? '');
const teachingMode = computed(() => teaching.value?.teaching_mode?.label ?? '');
const availability = computed(() => teaching.value?.availability_status?.label ?? '');
const teachingPhilosophy = computed(() => teaching.value?.teaching_philosophy ?? '');
const institutionName = computed(() => teaching.value?.institution_name ?? '');
const fieldOfStudy = computed(() => {
  const v = teaching.value?.field_of_study;
  if (v == null || v === '') return '';
  const s = String(v).trim();
  if (/^\d+$/.test(s)) return '';
  return s;
});
const graduationYear = computed(() => teaching.value?.graduation_year ?? null);
const specialization = computed(() => teaching.value?.specialization ?? '');
const travelRadius = computed(() => teaching.value?.travel_radius_km?.label ?? '');
const languages = computed(() => {
  const list = teaching.value?.languages;
  if (!Array.isArray(list) || !list.length) return [];
  return list.map((x) => (typeof x === 'string' ? x : x?.label ?? x?.name ?? '')).filter(Boolean);
});

const genderLabel = computed(() => profile.value?.gender?.label ?? '');
const nationalityLabel = computed(() => {
  const n = profile.value?.nationality;
  if (n == null || n === '') return '';
  if (typeof n === 'object') return n.label ?? '';
  return String(n);
});
const highestQual = computed(() => profile.value?.highest_qualification?.label ?? '');
const dateOfBirth = computed(() => profile.value?.date_of_birth ?? '');
const phonePrimary = computed(() => profile.value?.phone_primary ?? '');
const phoneSecondary = computed(() => profile.value?.phone_secondary ?? '');
const social = computed(() => t.value?.social ?? profile.value?.social ?? {});
const discordUsername = computed(() => {
  const d = social.value?.discord_username;
  return d && String(d).trim() ? String(d).trim() : '';
});

const socialLinks = computed(() => {
  const s = social.value;
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
  ];
  return pairs.filter(([, url]) => url && String(url).trim());
});

const completionPct = computed(() =>
  t.value?.completion_percentage ?? t.value?.profile_completion_percentage ?? profile.value?.profile_completion_percentage ?? null
);

const subjects = computed(() => t.value?.subjects ?? teaching.value?.subjects ?? []);
const relatedTeachers = computed(() => t.value?.related_teachers ?? []);
const publicRating = computed(() => Number(t.value?.rating) || 0);
const publicTotalReviews = computed(() => Number(t.value?.total_reviews) || 0);

/** Review API V2 — @see docs/ReviewApiV2.md (requires Sanctum session for full data). */
const reviewsFetchState = ref('idle');
const reviewStats = ref(null);
const reviewList = ref([]);
const reviewPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const reviewsLoading = ref(false);
const reviewsError = ref(null);

/** Logged-in user: write / edit review (Review API V2). */
const reviewEligibility = ref(null);
const reviewCheckLoading = ref(false);
const reviewCheckError = ref(null);
const reviewRating = ref(5);
const reviewTitle = ref('');
const reviewComment = ref('');
const reviewSubmitting = ref(false);

/** At most one client-side canonical URL replace per payload load (avoids Inertia remount/replace loops). */
const slugRedirectAttempted = ref(false);

const isLoggedIn = computed(() => page.props?.auth?.user != null);

/**
 * Force login via global handler. Use `always: true` for mutations (e.g. submit review) even if
 * Inertia `auth.user` was empty; use default on public review GETs so guests are not redirected.
 */
function normalizePathname(path) {
  if (!path) return '';
  try {
    return decodeURI(String(path)).replace(/\/+$/, '').toLowerCase();
  } catch {
    return String(path).replace(/\/+$/, '').toLowerCase();
  }
}

function pathsMatch(a, b) {
  return normalizePathname(a) === normalizePathname(b);
}

/**
 * Same idea as institutes: avoid slug-only `router.replace` when `/teacher/{id}/…` already matches.
 */
function shouldSkipCanonicalTeacherReplace(row) {
  if (typeof window === 'undefined' || !row) return true;
  const wantId = resolveTeacherUserId(row);
  if (!wantId) return false;
  const cur = window.location.pathname || '';
  const m = cur.match(/^\/teacher\/(\d+)(?:\/[^/]*)?\/?$/i);
  if (!m) return false;
  return Number(m[1]) === Number(wantId);
}

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

const authUser = computed(() => page.props?.auth?.user ?? null);
const authUserIdNumber = computed(() => {
  const id = authUser.value?.id;
  const n = Number(id);
  return Number.isFinite(n) && n > 0 ? n : null;
});
/** Tutor user id for `lead_owner_id` (see docs/LeadCreateApi.md). */
const leadOwnerUserId = computed(() => resolveTeacherUserId(teacher.value) ?? props.id);
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
  const first = subjects.value[0];
  return first?.name ? String(first.name) : '';
});

const isSelfProfile = computed(
  () =>
    authUserIdNumber.value != null
    && leadOwnerUserId.value != null
    && Number(authUserIdNumber.value) === Number(leadOwnerUserId.value),
);

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
  if (!teacher.value || !isLoggedIn.value) return;
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
  return publicRating.value;
});

const displayTotalReviews = computed(() => {
  if (reviewsFetchState.value === 'ok' && reviewStats.value?.total_reviews != null) {
    return Number(reviewStats.value.total_reviews);
  }
  return publicTotalReviews.value;
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
const portfolio = computed(() => t.value?.portfolio ?? profile.value?.portfolio ?? null);
const profileEmail = computed(() => profile.value?.email ?? '');
const profileWebsite = computed(() => profile.value?.website ?? '');

function formatInrAmount(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value ?? '');
  return n.toLocaleString('en-IN');
}

const heroHourlyLine = computed(() => {
  if (hourlyRateFixed.value != null && hourlyRateFixed.value !== '') {
    return `\u20B9${formatInrAmount(hourlyRateFixed.value)} / hr`;
  }
  return hourlyRateRange.value || '';
});

const heroMonthlyLine = computed(() => {
  if (monthlyRateFixed.value != null && monthlyRateFixed.value !== '') {
    return `\u20B9${formatInrAmount(monthlyRateFixed.value)} / mo`;
  }
  return monthlyRateRange.value || '';
});

const showHeroHourlyRate = computed(() => !!heroHourlyLine.value);
const showHeroMonthlyRate = computed(() => !!heroMonthlyLine.value);
const hasHeroRates = computed(() => showHeroHourlyRate.value || showHeroMonthlyRate.value);
const showBothHeroRates = computed(() => showHeroHourlyRate.value && showHeroMonthlyRate.value);

const canonicalPath = computed(() => (t.value ? teacherProfilePath(t.value) : null));
const canonicalUrl = computed(() => {
  if (typeof window === 'undefined' || !canonicalPath.value) return '';
  return `${window.location.origin}${canonicalPath.value}`;
});

const metaTitle = computed(() => {
  if (!name.value) return 'Teacher profile | SuGanta';
  const loc = cityState.value ? ` in ${cityState.value}` : '';
  const qual = qualification.value ? ` — ${qualification.value}` : '';
  return `${name.value}${qual} | Tutor${loc} | SuGanta`;
});

const metaDescription = computed(() => {
  if (!name.value) {
    return 'Discover qualified tutors on SuGanta. Compare subjects, experience, teaching mode, and rates, then connect in a few clicks.';
  }
  const subjectLine = subjects.value.length
    ? `Teaches ${subjects.value.slice(0, 4).map((s) => s.name).join(', ')}${subjects.value.length > 4 ? '…' : ''}.`
    : '';
  const creds = [qualification.value, experience.value, teachingMode.value].filter(Boolean).join('. ');
  const credLine = creds ? `${creds}.` : '';
  const locLine = cityState.value ? `Based in ${cityState.value}.` : '';

  let bioLine = '';
  if (bioPlain.value) {
    const oneLine = bioPlain.value.replace(/\s+/g, ' ').trim();
    bioLine = oneLine.length > 100 ? `${oneLine.slice(0, 97)}…` : oneLine;
  } else {
    bioLine = `${name.value} offers tutoring on SuGanta. View the profile for qualifications, availability, rates, and contact options.`;
  }

  const pieces = [subjectLine, credLine, locLine, bioLine].filter(Boolean);
  let desc = pieces.join(' ');
  if (desc.length > 160) desc = `${desc.slice(0, 157)}…`;
  return desc;
});

async function loadTeacher() {
  loading.value = true;
  error.value = null;
  errorCode.value = null;
  slugRedirectAttempted.value = false;
  try {
    teacher.value = await getTeacher(Number(props.id));
    await loadReviewData();
    await loadReviewEligibility();
  } catch (e) {
    error.value = e?.message ?? 'Failed to load teacher profile';
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

function navigateToTeacher(t) {
  const path = teacherProfilePath(t);
  if (path) router.visit(path);
}

watch(
  teacher,
  (val) => {
    if (!val || typeof window === 'undefined') return;
    if (shouldSkipCanonicalTeacherReplace(val)) return;
    const path = teacherProfilePath(val);
    if (!path) return;
    if (pathsMatch(window.location.pathname, path)) return;
    if (slugRedirectAttempted.value) return;
    slugRedirectAttempted.value = true;
    router.replace(path, { preserveState: true, preserveScroll: true });
  },
  { flush: 'post' },
);

watch(isLoggedIn, (logged, wasLogged) => {
  if (!logged) {
    reviewEligibility.value = null;
    return;
  }
  if (wasLogged === false && teacher.value) {
    void loadReviewEligibility();
  }
});

watch(
  () => props.id,
  (id, prevId) => {
    if (prevId === undefined) return;
    if (Number(id) === Number(prevId)) return;
    void loadTeacher();
  },
);

const teacherNavTab = ref('overview');

function teacherScrollTo(sectionId, tab) {
  teacherNavTab.value = tab;
  if (typeof document === 'undefined') return;
  document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const hasTeacherPortfolio = computed(() => !!portfolio.value);

/** Hero category pill (qualification, teaching mode, or fallback). */
const teacherHeroCategory = computed(() => qualification.value || teachingMode.value || 'Tutor');

onMounted(loadTeacher);
</script>

<template>
  <Head>
    <title>{{ metaTitle }}</title>
    <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
    <meta name="description" :content="metaDescription" />
    <meta property="og:title" :content="metaTitle" />
    <meta property="og:description" :content="metaDescription" />
    <meta property="og:image" :content="avatarUrl || 'https://app.suganta.com/logo/Su250.png'" />
    <meta property="og:type" content="profile" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="metaTitle" />
    <meta name="twitter:description" :content="metaDescription" />
  </Head>

  <AppLayout>
    <!-- Loading -->
    <div v-if="loading" class="relative max-w-6xl mx-auto animate-pulse px-1">
      <div class="h-10 bg-slate-200 rounded-2xl w-40 mb-8"></div>
      <div class="rounded-3xl border border-slate-200/80 bg-white p-8 mb-6 shadow-sm">
        <div class="flex flex-col gap-6 sm:flex-row">
          <div class="w-36 h-36 rounded-full bg-slate-200 mx-auto sm:mx-0"></div>
          <div class="flex-1 space-y-4">
            <div class="h-9 bg-slate-200 rounded-xl w-2/3 max-w-md"></div>
            <div class="h-4 bg-slate-100 rounded-lg w-1/2"></div>
            <div class="h-4 bg-slate-100 rounded-lg w-3/4"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 404 -->
    <div v-else-if="errorCode === 404" class="max-w-6xl mx-auto text-center py-20 px-4">
      <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </div>
      <p class="text-xl font-semibold text-slate-800 mb-2">Teacher not found</p>
      <p class="text-slate-500 text-sm mb-6">This profile may have been removed or the link is incorrect.</p>
      <Link href="/teachers" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:bg-slate-800">Back to teachers</Link>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="max-w-6xl mx-auto py-12 px-4">
      <div class="rounded-2xl border border-red-200/80 bg-red-50/90 p-5 text-red-900 shadow-sm mb-5">{{ error }}</div>
      <button type="button" class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:from-indigo-500 hover:to-violet-500" @click="loadTeacher">Try again</button>
    </div>

    <!-- Profile -->
    <div v-else-if="teacher" class="relative mx-auto max-w-7xl px-3 sm:px-4 lg:px-6">
      <div class="pointer-events-none absolute -right-20 -top-10 -z-10 h-64 w-64 rounded-full bg-violet-200/35 blur-3xl"></div>
      <div class="pointer-events-none absolute -left-16 top-32 -z-10 h-56 w-56 rounded-full bg-indigo-200/30 blur-3xl"></div>

      <!-- Split hero: dark banner + white body (same pattern as institute profile) -->
      <div class="relative mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_20px_50px_-28px_rgba(15,23,42,0.2)] sm:rounded-3xl">
        <div class="relative h-[7.5rem] overflow-hidden sm:h-36">
          <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900" />
          <div
            class="absolute inset-0 opacity-[0.22] [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.2)_1px,transparent_0)] [background-size:14px_14px] sm:[background-size:16px_16px]"
            aria-hidden="true"
          />
        </div>

        <div class="relative px-4 pb-0 sm:px-8">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
              <div class="-mt-12 shrink-0 sm:-mt-14">
                <div class="relative mx-auto w-fit sm:mx-0">
                  <button
                    v-if="avatarUrl && !avatarError"
                    type="button"
                    class="relative block rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    title="View full size"
                    @click="openAvatarLightbox"
                  >
                    <img
                      :src="avatarUrl"
                      :alt="name"
                      class="h-24 w-24 rounded-full border-[3px] border-white object-cover shadow-xl ring-1 ring-slate-200/80 sm:h-[6.75rem] sm:w-[6.75rem]"
                      @error="avatarError = true"
                    />
                  </button>
                  <div
                    v-else
                    class="flex h-24 w-24 items-center justify-center rounded-full border-[3px] border-white bg-gradient-to-br from-indigo-600 to-violet-700 text-xl font-bold text-white shadow-xl ring-1 ring-slate-200/80 sm:h-[6.75rem] sm:w-[6.75rem] sm:text-2xl"
                  >
                    {{ initials }}
                  </div>
                  <span
                    v-if="teacher.verified"
                    class="absolute bottom-0.5 right-0.5 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-white shadow-md"
                    title="Verified"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </span>
                </div>
              </div>

              <div class="min-w-0 pb-1 text-center sm:pb-5 sm:text-left">
                <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                  <h1 class="text-balance text-lg font-bold uppercase leading-snug tracking-tight text-slate-900 sm:text-xl md:text-2xl">
                    {{ name }}
                  </h1>
                  <span
                    v-if="teacher.is_featured"
                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200/80"
                  >
                    Featured
                  </span>
                </div>

                <div class="mt-2.5 flex flex-wrap items-center justify-center gap-x-2 gap-y-2 text-sm text-slate-600 sm:justify-start">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-900 ring-1 ring-indigo-100 sm:text-sm"
                  >
                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    {{ teacherHeroCategory }}
                  </span>
                  <span v-if="teacherHeroCategory && experience" class="hidden text-slate-300 sm:inline" aria-hidden="true">·</span>
                  <span v-if="experience" class="inline-flex items-center gap-1.5 text-xs sm:text-sm">
                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ experience }}
                  </span>
                  <span v-if="experience && (cityState || area)" class="hidden text-slate-300 sm:inline" aria-hidden="true">·</span>
                  <span v-if="cityState || area" class="inline-flex max-w-full items-center gap-1.5 text-xs sm:text-sm">
                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="min-w-0">
                      <template v-if="cityState">{{ cityState }}</template>
                      <template v-if="cityState && area"><span class="text-slate-400">, </span></template>
                      <template v-if="area">{{ area }}</template>
                    </span>
                  </span>
                </div>

                <div v-if="showRatingBadge" class="mt-2 flex justify-center sm:justify-start">
                  <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs ring-1 ring-slate-200/90 sm:text-sm">
                    <div class="flex" aria-hidden="true">
                      <svg
                        v-for="i in 5"
                        :key="i"
                        class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                        :class="i <= Math.round(displayAverageRating) ? 'text-amber-400' : 'text-slate-200'"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                    </div>
                    <span class="font-bold tabular-nums text-slate-900">{{ displayRatingLabel }}</span>
                    <span class="text-slate-500">({{ displayTotalReviews }})</span>
                  </div>
                </div>

                <div v-if="teachingMode || availability" class="mt-2 flex flex-wrap justify-center gap-2 sm:justify-start">
                  <span v-if="teachingMode" class="rounded-full bg-violet-50 px-2.5 py-0.5 text-[11px] font-semibold text-violet-900 ring-1 ring-violet-100 sm:text-xs">{{ teachingMode }}</span>
                  <span v-if="availability" class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-900 ring-1 ring-emerald-100 sm:text-xs">{{ availability }}</span>
                </div>
              </div>
            </div>

            <div class="flex shrink-0 flex-col gap-2 pb-4 sm:flex-row sm:items-center sm:pb-5 lg:flex-col lg:items-stretch">
              <button
                v-if="isLoggedIn"
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-600/25 transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 sm:w-auto lg:w-full"
                @click="openLeadModal"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                Contact now
              </button>
              <Link
                v-else
                href="/login"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-600/25 transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 sm:w-auto lg:w-full"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                Contact now
              </Link>
            </div>
          </div>

          <nav
            class="sticky top-0 z-20 -mx-4 flex flex-wrap justify-center border-t border-slate-100 bg-white/95 px-1 backdrop-blur-md sm:-mx-8 sm:px-2"
            aria-label="Profile sections"
          >
            <button
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'overview' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-overview', 'overview')"
            >
              Overview
            </button>
            <button
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'about' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-about', 'about')"
            >
              About me
            </button>
            <button
              v-if="hasTeacherPortfolio"
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'portfolio' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-portfolio', 'portfolio')"
            >
              Portfolio
            </button>
            <button
              v-if="name"
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'why' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-why', 'why')"
            >
              Why choose us
            </button>
            <button
              v-if="teacherShowDirectionsMap"
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'map' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-map', 'map')"
            >
              Map
            </button>
            <button
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'reviews' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-reviews', 'reviews')"
            >
              Reviews
            </button>
            <button
              v-if="relatedTeachers.length"
              type="button"
              class="-mb-px border-b-2 border-transparent px-3 py-3 text-xs font-semibold transition sm:px-4 sm:text-sm"
              :class="teacherNavTab === 'similar' ? 'border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800'"
              @click="teacherScrollTo('teacher-section-similar', 'similar')"
            >
              Similar tutors
            </button>
          </nav>
        </div>
      </div>

      <Link
        href="/teachers"
        class="group mb-6 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-indigo-700"
      >
        <svg class="h-4 w-4 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        All teachers
      </Link>

      <div
        class="flex flex-col-reverse gap-8 lg:grid lg:grid-cols-[minmax(0,1fr)_18.5rem] xl:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
      >
        <!-- Main column: scrolls; aside stays sticky on large screens -->
        <div class="min-w-0 space-y-8">
          <div id="teacher-section-overview" class="scroll-mt-28 space-y-6">
          <div class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              </span>
              <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Teaching</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-x-8 sm:gap-y-5">
              <div v-if="qualification" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Qualification</span><p class="text-slate-900 font-semibold">{{ qualification }}</p></div>
              <div v-if="qualificationText" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Qualification detail</span><p class="text-slate-900 font-semibold">{{ qualificationText }}</p></div>
              <div v-if="highestQual" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Highest qualification</span><p class="text-slate-900 font-semibold">{{ highestQual }}</p></div>
              <div v-if="experience" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Experience</span><p class="text-slate-900 font-semibold">{{ experience }}</p></div>
              <div v-if="institutionName" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Institution</span><p class="text-slate-900 font-semibold">{{ institutionName }}</p></div>
              <div v-if="fieldOfStudy" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Field of study</span><p class="text-slate-900 font-semibold">{{ fieldOfStudy }}</p></div>
              <div v-if="graduationYear" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Graduation year</span><p class="text-slate-900 font-semibold">{{ graduationYear }}</p></div>
              <div v-if="specialization" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Specialization</span><p class="text-slate-900 font-semibold">{{ specialization }}</p></div>
              <div v-if="showHeroHourlyRate" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Hourly rate</span><p class="font-semibold tabular-nums text-indigo-700">{{ heroHourlyLine }}</p></div>
              <div v-if="showHeroMonthlyRate" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Monthly rate</span><p class="font-semibold tabular-nums text-violet-800">{{ heroMonthlyLine }}</p></div>
              <div v-if="teachingMode" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Teaching mode</span><p class="text-slate-900 font-semibold">{{ teachingMode }}</p></div>
              <div v-if="availability" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Availability</span><p class="text-slate-900 font-semibold">{{ availability }}</p></div>
              <div v-if="travelRadius" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Travel radius</span><p class="text-slate-900 font-semibold">{{ travelRadius }}</p></div>
              <div v-if="teaching.online_classes !== undefined" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Online classes</span><p class="font-semibold" :class="teaching.online_classes ? 'text-emerald-600' : 'text-slate-400'">{{ teaching.online_classes ? 'Yes' : 'No' }}</p></div>
              <div v-if="teaching.home_tuition !== undefined" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Home tuition</span><p class="font-semibold" :class="teaching.home_tuition ? 'text-emerald-600' : 'text-slate-400'">{{ teaching.home_tuition ? 'Yes' : 'No' }}</p></div>
              <div v-if="teaching.institute_classes !== undefined" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Institute classes</span><p class="font-semibold" :class="teaching.institute_classes ? 'text-emerald-600' : 'text-slate-400'">{{ teaching.institute_classes ? 'Yes' : 'No' }}</p></div>
            </div>
            <div v-if="languages.length" class="mt-6 border-t border-slate-100 pt-6">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Languages</span>
              <div class="flex flex-wrap gap-2">
                <span v-for="(lang, i) in languages" :key="i" class="rounded-lg bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-900 ring-1 ring-violet-100">{{ lang }}</span>
              </div>
            </div>
            <div v-if="teachingPhilosophy" class="mt-6 border-t border-slate-100 pt-6">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Teaching philosophy</span>
              <p class="text-slate-700 leading-relaxed whitespace-pre-line">{{ teachingPhilosophy }}</p>
            </div>
          </div>

          <div v-if="fullAddressLines.length || profileEmail || profileWebsite || genderLabel || dateOfBirth || nationalityLabel || completionPct != null || teacherShowDirectionsMap" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </span>
              <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Profile &amp; location</h2>
            </div>

            <div v-if="teacherShowDirectionsMap" id="teacher-section-map" class="scroll-mt-28">
              <GetDirectionsMapSection
                :profile-name="name"
                :latitude="mapLatitude"
                :longitude="mapLongitude"
                :place-query="teacherPlaceQuery"
                :phone="teacherDirectionsPhone"
                :contact-person="name"
                :address="teacherDirectionsAddress"
              />
            </div>

            <div v-if="fullAddressLines.length" class="mb-6 rounded-2xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Address</span>
              <p v-for="(line, i) in fullAddressLines" :key="i" class="text-slate-800 font-medium leading-relaxed">{{ line }}</p>
            </div>
            <div v-if="profileEmail" class="mb-6 rounded-2xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Email</span>
              <p class="text-slate-800 font-medium break-all">{{ profileEmail }}</p>
            </div>
            <div v-if="profileWebsite" class="mb-6">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Website</span>
              <a :href="profileWebsite" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-indigo-600 hover:text-violet-600 break-all">{{ profileWebsite }}</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
              <div v-if="genderLabel" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Gender</span><p class="text-slate-900 font-semibold">{{ genderLabel }}</p></div>
              <div v-if="dateOfBirth" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Date of birth</span><p class="text-slate-900 font-semibold">{{ dateOfBirth }}</p></div>
              <div v-if="nationalityLabel" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Nationality</span><p class="text-slate-900 font-semibold">{{ nationalityLabel }}</p></div>
              <div v-if="completionPct != null" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Profile completion</span><p class="text-slate-900 font-semibold">{{ completionPct }}%</p></div>
            </div>
          </div>

          <div v-if="name" id="teacher-section-why" class="scroll-mt-28">
            <WhyChooseUsSection :profile-name="name" variant="teacher" />
          </div>
          </div>

          <div id="teacher-section-about" class="scroll-mt-28 space-y-6">
            <div class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
              <div class="pointer-events-none absolute -right-16 top-0 h-40 w-40 rounded-full bg-indigo-100/50 blur-3xl"></div>
              <h2 class="relative mb-3 text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">About me</h2>
              <p
                class="relative max-w-3xl whitespace-pre-line text-base leading-[1.7] sm:text-lg"
                :class="bioPlain ? 'text-slate-700' : 'text-slate-500'"
              >
                {{ profileBioDisplay }}
              </p>
            </div>
            <div v-if="subjects.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
              <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-400">Subjects</h2>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="subject in subjects"
                  :key="subject.id"
                  class="rounded-xl border border-indigo-100/80 bg-indigo-50/90 px-3 py-1.5 text-sm font-semibold text-indigo-900"
                >
                  {{ subject.name }}
                </span>
              </div>
            </div>
          </div>

          <!-- Portfolio -->
          <div
            v-if="portfolio"
            id="teacher-section-portfolio"
            class="scroll-mt-28 rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Portfolio</h2>
            <h3 class="mb-3 font-semibold text-slate-800">{{ portfolio.title }}</h3>
            <div v-if="portfolio.description" class="prose prose-sm max-w-none text-slate-700 prose-headings:text-slate-900" v-html="portfolio.description"></div>
            <div v-if="portfolio.images?.length" class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
              <button
                v-for="(img, i) in portfolio.images"
                :key="i"
                type="button"
                class="group relative block w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-100 text-left shadow-md transition hover:border-indigo-200 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                @click="openPortfolioLightbox(img.url)"
              >
                <img
                  :src="img.url"
                  :alt="portfolio.title ? `${portfolio.title} — image ${i + 1}` : `Portfolio image ${i + 1}`"
                  class="h-32 w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                />
                <span class="sr-only">View full screen</span>
              </button>
            </div>
          </div>

          <!-- Reviews (API V2 — docs/ReviewApiV2.md) -->
          <div id="teacher-section-reviews" class="scroll-mt-28 rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Reviews</h2>
            <p class="mt-1 text-sm text-slate-500">Student feedback from SuGanta reviews.</p>
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

        <!-- Write / edit review (logged-in visitors — Review API V2) -->
        <div v-if="teacher && !isSelfProfile" class="mb-6 space-y-4">
          <p v-if="!isLoggedIn" class="rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm text-slate-600">
            <Link href="/login" class="font-semibold text-indigo-600 hover:text-violet-600">Sign in</Link>
            to leave a review for {{ name.split(' ')[0] || 'this tutor' }}.
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
                <button
                  type="button"
                  class="mt-3 text-sm font-semibold text-indigo-600 hover:text-violet-600"
                  @click="beginEditReview"
                >
                  Edit your review
                </button>
              </div>
              <div
                v-if="reviewEligibility.can_review && !reviewEligibility.has_reviewed"
                class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4 ring-1 ring-indigo-100/80 sm:p-5"
              >
                <p class="text-sm text-slate-700">
                  Share your experience with {{ name.split(' ')[0] || 'this tutor' }}.
                </p>
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
          <div class="h-24 rounded-2xl bg-slate-100"></div>
        </div>

        <template v-else-if="reviewsFetchState === 'unauthorized'">
          <p v-if="!isLoggedIn" class="text-sm leading-relaxed text-slate-600">
            <Link href="/login" class="font-semibold text-indigo-600 hover:text-violet-600">Sign in</Link>
            to load full review statistics and comments (Review API requires a session).
          </p>
          <p v-else class="text-sm leading-relaxed text-slate-600">
            Reviews could not be loaded with your current session. Try refreshing the page.
          </p>
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
              <p class="mt-1 text-sm text-slate-600">
                {{ reviewStats.total_reviews ?? 0 }} reviews
                <template v-if="reviewStats.verified_count != null">
                  · {{ reviewStats.verified_count }} verified
                </template>
                <template v-if="reviewStats.total_helpful != null">
                  · {{ reviewStats.total_helpful }} helpful votes
                </template>
              </p>
            </div>
            <div v-if="distributionRows.length" class="rounded-2xl bg-slate-50/90 p-5 ring-1 ring-slate-100">
              <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Rating breakdown</p>
              <div class="space-y-2">
                <div
                  v-for="row in distributionRows"
                  :key="row.rating"
                  class="flex items-center gap-3 text-sm"
                >
                  <span class="w-9 shrink-0 font-semibold tabular-nums text-slate-700">{{ row.rating }}★</span>
                  <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-200">
                    <div
                      class="h-full rounded-full bg-amber-400 transition-[width]"
                      :style="{ width: `${Math.min(100, Number(row.percentage) || 0)}%` }"
                    ></div>
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
              reply-label="Response from tutor"
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



          <div
            v-if="relatedTeachers.length"
            id="teacher-section-similar"
            class="scroll-mt-28 rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <h2 class="mb-6 text-lg font-bold text-slate-900 sm:text-xl">Related teachers</h2>
            <div class="flex flex-col gap-4">
              <TeacherCard
                v-for="related in relatedTeachers"
                :key="related.id"
                layout="row"
                :teacher="related"
                @click="navigateToTeacher"
                @contact="navigateToTeacher"
              />
            </div>
          </div>
        </div>

        <!-- Sticky sidebar: scrollport is AppLayout main — top aligns to main viewport, not document -->
        <aside
          class="w-full shrink-0 space-y-4 self-start lg:sticky lg:top-0 lg:z-10 lg:max-h-screen lg:overflow-y-auto lg:overscroll-contain lg:pr-1"
          aria-label="Contact and social links"
        >
          <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-800 p-6 text-white shadow-2xl shadow-indigo-900/25 ring-1 ring-white/10 sm:p-7">
            <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
            <h3 class="relative text-lg font-bold sm:text-xl">Contact</h3>
            <p class="relative mt-2 text-sm text-indigo-100/95 leading-relaxed">Reach out to {{ name.split(' ')[0] || 'this teacher' }} for classes.</p>
            <div v-if="phonePrimary || phoneSecondary" class="relative mb-5 mt-5 space-y-3 rounded-2xl bg-white/10 p-4 text-sm backdrop-blur-sm">
              <p v-if="phonePrimary" class="text-white"><span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-200">Primary</span>{{ phonePrimary }}</p>
              <p v-if="phoneSecondary" class="text-white"><span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-200">Secondary</span>{{ phoneSecondary }}</p>
            </div>
            <p v-else class="relative mt-4 text-sm text-indigo-100/90">Phone numbers may not be shown on the public profile.</p>
            <button
              v-if="isLoggedIn"
              type="button"
              class="relative mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3 font-bold text-indigo-600 shadow-lg transition hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-700"
              @click="openLeadModal"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              Contact Now
            </button>
            <Link
              v-else
              href="/login"
              class="relative mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3 font-bold text-indigo-600 shadow-lg transition hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-700"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
              Sign in to contact
            </Link>
          </div>

          <div
            v-if="socialLinks.length || discordUsername"
            class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-7"
          >
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Social &amp; web</span>
            <ul class="flex flex-col gap-2">
              <li v-for="([label, url], i) in socialLinks" :key="i">
                <a :href="url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-violet-600 transition break-all">{{ label }}</a>
              </li>
              <li v-if="discordUsername">
                <span class="text-sm font-semibold text-slate-700">Discord: {{ discordUsername }}</span>
              </li>
            </ul>
          </div>
        </aside>
      </div>

      <Teleport to="body">
        <div
          v-if="reviewModalOpen && reviewEligibility && !isSelfProfile"
          class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
          role="dialog"
          aria-modal="true"
          aria-labelledby="review-modal-title"
          @click.self="closeReviewModal"
        >
          <div class="flex max-h-[min(90vh,640px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
              <h2 id="review-modal-title" class="text-lg font-bold text-slate-900">
                {{ reviewEligibility.has_reviewed ? 'Update your review' : 'Write a review' }}
              </h2>
              <button
                type="button"
                class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-slate-50"
                aria-label="Close"
                @click="closeReviewModal"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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
                    class="rounded-lg p-1 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    :aria-pressed="reviewRating >= star"
                    :aria-label="`Rate ${star} out of 5`"
                    @click="reviewRating = star"
                  >
                    <svg
                      class="h-8 w-8"
                      :class="star <= reviewRating ? 'text-amber-400' : 'text-slate-300'"
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
                  v-model="reviewTitle"
                  type="text"
                  maxlength="255"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </label>
              <label class="mt-3 block">
                <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Comment (optional)</span>
                <textarea
                  v-model="reviewComment"
                  rows="3"
                  maxlength="5000"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </label>
              <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                <button
                  type="submit"
                  class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50"
                  :disabled="reviewSubmitting"
                >
                  {{ reviewSubmitting ? 'Saving…' : reviewEligibility.has_reviewed ? 'Update review' : 'Submit review' }}
                </button>
                <button
                  type="button"
                  class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                  @click="closeReviewModal"
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
          v-if="leadModalOpen && teacher"
          class="fixed inset-0 z-[190] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:items-center"
          role="dialog"
          aria-modal="true"
          aria-labelledby="lead-modal-title"
          @click.self="closeLeadModal"
        >
          <div class="flex max-h-[min(92vh,820px)] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-indigo-50/30 px-5 py-4">
              <h2 id="lead-modal-title" class="text-lg font-bold tracking-tight text-slate-900">
                Contact {{ name.split(' ')[0] || 'tutor' }}
              </h2>
              <button
                type="button"
                class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-slate-50"
                aria-label="Close"
                @click="closeLeadModal"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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
          v-if="avatarLightboxOpen && avatarUrl"
          class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          :aria-label="`Profile photo — ${name}`"
          @click.self="closeAvatarLightbox"
        >
          <button
            type="button"
            class="absolute right-4 top-4 z-10 rounded-full bg-white/15 p-2.5 text-white outline-none ring-1 ring-white/20 transition hover:bg-white/25 focus-visible:ring-2 focus-visible:ring-white"
            aria-label="Close full screen photo"
            @click="closeAvatarLightbox"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <img
            :src="avatarUrl"
            :alt="name"
            class="max-h-[min(92vh,900px)] max-w-full rounded-lg object-contain shadow-2xl ring-1 ring-white/10"
          />
        </div>
      </Teleport>

      <Teleport to="body">
        <div
          v-if="portfolioLightboxUrl"
          class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-label="Portfolio image full screen"
          @click.self="closePortfolioLightbox"
        >
          <button
            type="button"
            class="absolute right-4 top-4 z-10 rounded-full bg-white/15 p-2.5 text-white outline-none ring-1 ring-white/20 transition hover:bg-white/25 focus-visible:ring-2 focus-visible:ring-white"
            aria-label="Close full screen image"
            @click="closePortfolioLightbox"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <img
            :src="portfolioLightboxUrl"
            alt=""
            class="max-h-[min(92vh,900px)] max-w-full rounded-lg object-contain shadow-2xl ring-1 ring-white/10"
          />
        </div>
      </Teleport>
    </div>
  </AppLayout>
</template>
