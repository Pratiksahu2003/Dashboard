<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import { getTeacher } from '@/services/teacherApi';

const props = defineProps({
  id: { type: Number, required: true },
  slug: { type: String, default: '' },
});

const teacher = ref(null);
const loading = ref(false);
const error = ref(null);
const errorCode = ref(null);
const avatarError = ref(false);

const t = computed(() => teacher.value);
const profile = computed(() => t.value?.profile ?? {});
const name = computed(() => t.value?.user?.name ?? '');
const initials = computed(() => name.value.split(' ').filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('') || '?');
const avatarUrl = computed(() => t.value?.profile_image_url ?? profile.value?.profile_image_url ?? null);
/** Plain bio text; null if empty — UI can show a default line */
const bioPlain = computed(() => {
  const raw = profile.value?.bio;
  if (raw == null || String(raw).trim() === '') return null;
  return String(raw).replace(/\r\n/g, '\n').trim();
});

const DEFAULT_PROFILE_BIO =
  'Professional educator on SuGanta offering personalized support. Explore subjects, qualifications, rates, and contact options on this page.';

const profileBioDisplay = computed(() => bioPlain.value || DEFAULT_PROFILE_BIO);
const city = computed(() => t.value?.location?.city ?? '');
const state = computed(() => t.value?.location?.state ?? '');
const area = computed(() => t.value?.location?.area ?? '');
const pincode = computed(() => t.value?.location?.pincode ?? '');
const addressLine1 = computed(() => t.value?.location?.address_line_1 ?? '');
const addressLine2 = computed(() => t.value?.location?.address_line_2 ?? '');
const countryLabel = computed(() => t.value?.location?.country?.label ?? '');
const cityState = computed(() => [city.value, state.value].filter(Boolean).join(', '));
const fullAddressLines = computed(() => {
  const lines = [
    [addressLine1.value, addressLine2.value].filter(Boolean).join(', '),
    [area.value, city.value, state.value].filter(Boolean).join(', '),
    [pincode.value, countryLabel.value].filter(Boolean).join(' ').trim(),
  ].filter(Boolean);
  return lines;
});

const teaching = computed(() => t.value?.teaching ?? {});
const qualification = computed(() => teaching.value?.qualification?.label ?? '');
const experience = computed(() => teaching.value?.teaching_experience_years?.label ?? '');
const hourlyRateRange = computed(() => teaching.value?.hourly_rate_range?.label ?? '');
const monthlyRateRange = computed(() => teaching.value?.monthly_rate_range?.label ?? '');
const teachingMode = computed(() => teaching.value?.teaching_mode?.label ?? '');
const availability = computed(() => teaching.value?.availability_status?.label ?? '');
const teachingPhilosophy = computed(() => teaching.value?.teaching_philosophy ?? '');
const institutionName = computed(() => teaching.value?.institution_name ?? '');
const fieldOfStudy = computed(() => teaching.value?.field_of_study ?? '');
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
const whatsappNumber = computed(() => profile.value?.whatsapp ?? '');

const social = computed(() => t.value?.social ?? {});
const socialLinks = computed(() => {
  const s = social.value;
  const pairs = [
    ['Instagram', s.instagram_url],
    ['LinkedIn', s.linkedin_url],
    ['Facebook', s.facebook_url],
    ['YouTube', s.youtube_url],
    ['Twitter / X', s.twitter_url],
    ['GitHub', s.github_url],
    ['Portfolio', s.portfolio_url],
    ['Blog', s.blog_url],
  ];
  return pairs.filter(([, url]) => url && String(url).trim());
});

const completionPct = computed(() =>
  t.value?.completion_percentage ?? t.value?.profile_completion_percentage ?? profile.value?.profile_completion_percentage ?? null
);

function digitsOnly(s) {
  return String(s ?? '').replace(/\D/g, '');
}

const callHref = computed(() => {
  const d = digitsOnly(phonePrimary.value);
  return d ? `tel:${d}` : null;
});

const whatsappHref = computed(() => {
  let d = digitsOnly(whatsappNumber.value || phonePrimary.value);
  if (d.length === 10) d = `91${d}`;
  return d ? `https://wa.me/${d}` : null;
});

const subjects = computed(() => t.value?.subjects ?? []);
const relatedTeachers = computed(() => t.value?.related_teachers ?? []);
const rating = computed(() => t.value?.rating ?? 0);
const totalReviews = computed(() => t.value?.total_reviews ?? 0);
const portfolio = computed(() => t.value?.portfolio ?? null);

const heroRateLabel = computed(() => hourlyRateRange.value || monthlyRateRange.value || '');
const heroRateCaption = computed(() => (hourlyRateRange.value ? 'Hourly range' : monthlyRateRange.value ? 'Monthly range' : ''));

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
  try {
    teacher.value = await getTeacher(Number(props.id));
  } catch (e) {
    error.value = e?.message ?? 'Failed to load teacher profile';
    errorCode.value = e?.status ?? null;
  } finally {
    loading.value = false;
  }
}

function navigateToTeacher(t) {
  const path = teacherProfilePath(t);
  if (path) router.visit(path);
}

onMounted(loadTeacher);
</script>

<template>
  <Head>
    <title>{{ metaTitle }}</title>
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
    <div v-else-if="teacher" class="relative max-w-6xl mx-auto px-1">
      <div class="pointer-events-none absolute -right-20 -top-10 h-64 w-64 rounded-full bg-violet-200/35 blur-3xl -z-10"></div>
      <div class="pointer-events-none absolute -left-16 top-32 h-56 w-56 rounded-full bg-indigo-200/30 blur-3xl -z-10"></div>

      <Link
        href="/teachers"
        class="group mb-8 inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-sm transition hover:border-indigo-200 hover:text-indigo-700"
      >
        <svg class="h-4 w-4 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        All teachers
      </Link>

      <!-- Hero Card -->
      <div class="relative mb-8 overflow-hidden rounded-3xl border border-slate-200/70 bg-gradient-to-br from-white via-indigo-50/30 to-violet-50/40 p-8 shadow-[0_20px_50px_-24px_rgba(79,70,229,0.25)] sm:p-10">
        <div class="absolute right-0 top-0 h-40 w-40 translate-x-10 -translate-y-10 rounded-full bg-indigo-400/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-8 md:flex-row md:items-start">
          <!-- Avatar -->
          <div class="flex shrink-0 justify-center md:justify-start">
            <div class="relative">
              <div class="absolute -inset-1 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 opacity-60 blur-md"></div>
              <img v-if="avatarUrl && !avatarError" :src="avatarUrl" :alt="name" class="relative h-36 w-36 rounded-full object-cover ring-4 ring-white shadow-xl shadow-slate-900/15" @error="avatarError = true" />
              <div v-else class="relative flex h-36 w-36 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-3xl font-bold text-white shadow-xl shadow-indigo-500/30 ring-4 ring-white">{{ initials }}</div>
              <span v-if="teacher.verified" class="absolute -bottom-1 left-1/2 z-10 -translate-x-1/2 rounded-full border-2 border-white bg-sky-500 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-md">Verified</span>
            </div>
          </div>

          <!-- Info -->
          <div class="min-w-0 flex-1">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ name }}</h1>
                <div v-if="cityState" class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-slate-600">
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200/80">
                    <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    {{ cityState }}<template v-if="area"><span class="text-slate-400">·</span>{{ area }}</template>
                  </span>
                </div>
              </div>
              <div v-if="heroRateLabel" class="shrink-0 rounded-2xl border border-indigo-100/80 bg-white/90 px-5 py-4 text-right shadow-md shadow-indigo-500/5 backdrop-blur-sm">
                <div class="text-2xl font-bold tabular-nums text-indigo-600 sm:text-3xl">{{ heroRateLabel }}</div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ heroRateCaption }}</div>
              </div>
            </div>

            <!-- Stats (ratings only when there are reviews — avoids empty star rows) -->
            <div class="mb-6 flex flex-wrap gap-2">
              <div
                v-if="Number(rating) > 0 || Number(totalReviews) > 0"
                class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 shadow-sm ring-1 ring-slate-200/80"
              >
                <div class="flex">
                  <svg v-for="i in 5" :key="i" class="h-4 w-4 sm:h-5 sm:w-5" :class="i <= Math.round(rating) ? 'text-amber-400' : 'text-slate-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <span class="text-sm font-bold text-slate-900">{{ rating }}</span>
                <span class="text-sm text-slate-500">({{ totalReviews }} reviews)</span>
              </div>
              <span v-if="experience" class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-800">{{ experience }}</span>
              <span v-if="teachingMode" class="rounded-full bg-violet-100/80 px-3 py-1.5 text-sm font-semibold text-violet-900">{{ teachingMode }}</span>
              <span v-if="availability" class="rounded-full bg-emerald-100/80 px-3 py-1.5 text-sm font-semibold text-emerald-900">{{ availability }}</span>
            </div>

            <!-- Bio -->
            <div class="mb-6 rounded-2xl border border-slate-200/60 bg-white/60 p-5 backdrop-blur-sm">
              <h2 class="mb-2 text-xs font-bold uppercase tracking-[0.15em] text-slate-400">About</h2>
              <p
                class="leading-relaxed whitespace-pre-line"
                :class="bioPlain ? 'text-slate-700' : 'text-slate-500'"
              >{{ profileBioDisplay }}</p>
            </div>

            <!-- Subjects -->
            <div v-if="subjects.length" class="flex flex-wrap gap-2">
              <span v-for="subject in subjects" :key="subject.id" class="rounded-xl border border-indigo-100/80 bg-indigo-50/90 px-3 py-1.5 text-sm font-semibold text-indigo-900">{{ subject.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8 mb-8">
        <!-- Teaching + profile details -->
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              </span>
              <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Teaching</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-x-8 sm:gap-y-5">
              <div v-if="qualification" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Qualification</span><p class="text-slate-900 font-semibold">{{ qualification }}</p></div>
              <div v-if="highestQual" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Highest qualification</span><p class="text-slate-900 font-semibold">{{ highestQual }}</p></div>
              <div v-if="experience" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Experience</span><p class="text-slate-900 font-semibold">{{ experience }}</p></div>
              <div v-if="institutionName" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Institution</span><p class="text-slate-900 font-semibold">{{ institutionName }}</p></div>
              <div v-if="fieldOfStudy" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Field of study</span><p class="text-slate-900 font-semibold">{{ fieldOfStudy }}</p></div>
              <div v-if="graduationYear" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Graduation year</span><p class="text-slate-900 font-semibold">{{ graduationYear }}</p></div>
              <div v-if="specialization" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Specialization</span><p class="text-slate-900 font-semibold">{{ specialization }}</p></div>
              <div v-if="hourlyRateRange" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Hourly rate</span><p class="text-slate-900 font-semibold">{{ hourlyRateRange }}</p></div>
              <div v-if="monthlyRateRange" class="rounded-2xl bg-slate-50/80 px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Monthly rate</span><p class="text-slate-900 font-semibold">{{ monthlyRateRange }}</p></div>
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

          <div v-if="fullAddressLines.length || socialLinks.length || genderLabel || dateOfBirth || nationalityLabel || completionPct != null" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </span>
              <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Profile &amp; location</h2>
            </div>
            <div v-if="fullAddressLines.length" class="mb-6 rounded-2xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Address</span>
              <p v-for="(line, i) in fullAddressLines" :key="i" class="text-slate-800 font-medium leading-relaxed">{{ line }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
              <div v-if="genderLabel" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Gender</span><p class="text-slate-900 font-semibold">{{ genderLabel }}</p></div>
              <div v-if="dateOfBirth" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Date of birth</span><p class="text-slate-900 font-semibold">{{ dateOfBirth }}</p></div>
              <div v-if="nationalityLabel" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Nationality</span><p class="text-slate-900 font-semibold">{{ nationalityLabel }}</p></div>
              <div v-if="completionPct != null" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-100"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Profile completion</span><p class="text-slate-900 font-semibold">{{ completionPct }}%</p></div>
            </div>
            <div v-if="socialLinks.length">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Social &amp; web</span>
              <ul class="flex flex-col gap-2">
                <li v-for="([label, url], i) in socialLinks" :key="i">
                  <a :href="url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-violet-600 transition break-all">{{ label }}</a>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Contact Card -->
        <div class="relative h-fit overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-800 p-6 text-white shadow-2xl shadow-indigo-900/25 ring-1 ring-white/10 lg:sticky lg:top-24">
          <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
          <h3 class="relative text-lg font-bold sm:text-xl">Contact</h3>
          <p class="relative mt-2 text-sm text-indigo-100/95 leading-relaxed">Reach out to {{ name.split(' ')[0] || 'this teacher' }} for classes.</p>
          <div v-if="phonePrimary || phoneSecondary" class="relative mb-5 mt-5 space-y-3 rounded-2xl bg-white/10 p-4 text-sm backdrop-blur-sm">
            <p v-if="phonePrimary" class="text-white"><span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-200">Primary</span>{{ phonePrimary }}</p>
            <p v-if="phoneSecondary" class="text-white"><span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-200">Secondary</span>{{ phoneSecondary }}</p>
          </div>
          <a
            v-if="callHref"
            :href="callHref"
            class="relative mb-3 flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3 font-bold text-indigo-600 shadow-lg transition hover:bg-indigo-50"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            Call
          </a>
          <a
            v-if="whatsappHref"
            :href="whatsappHref"
            target="_blank"
            rel="noopener noreferrer"
            class="relative flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-400 py-3 font-bold text-emerald-950 shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-300"
          >
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp
          </a>
          <p v-if="!callHref && !whatsappHref" class="relative text-sm text-indigo-100/90">Phone number not shared on the public profile.</p>
        </div>
      </div>

      <!-- Portfolio -->
      <div v-if="portfolio" class="mb-8 rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
        <h2 class="mb-4 text-lg font-bold text-slate-900 sm:text-xl">Portfolio</h2>
        <h3 class="mb-3 font-semibold text-slate-800">{{ portfolio.title }}</h3>
        <div v-if="portfolio.description" class="prose prose-sm max-w-none text-slate-700 prose-headings:text-slate-900" v-html="portfolio.description"></div>
        <div v-if="portfolio.images?.length" class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
          <img v-for="(img, i) in portfolio.images" :key="i" :src="img.url" class="h-32 w-full rounded-2xl border border-slate-200/80 object-cover shadow-md transition hover:shadow-lg" />
        </div>
      </div>

      <!-- Related Teachers -->
      <div v-if="relatedTeachers.length" class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8">
        <h2 class="mb-6 text-lg font-bold text-slate-900 sm:text-xl">Related teachers</h2>
        <div class="flex flex-col gap-4">
          <TeacherCard
            v-for="related in relatedTeachers"
            :key="related.id"
            layout="row"
            :teacher="related"
            @click="navigateToTeacher"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
