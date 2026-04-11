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
const name = computed(() => t.value?.user?.name ?? '');
const initials = computed(() => name.value.split(' ').filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('') || '?');
const avatarUrl = computed(() => t.value?.profile_image_url ?? null);
const bio = computed(() => t.value?.profile?.bio ?? '');
const city = computed(() => t.value?.location?.city ?? '');
const state = computed(() => t.value?.location?.state ?? '');
const area = computed(() => t.value?.location?.area ?? '');
const cityState = computed(() => [city.value, state.value].filter(Boolean).join(', '));

const teaching = computed(() => t.value?.teaching ?? {});
const qualification = computed(() => teaching.value?.qualification?.label ?? '');
const experience = computed(() => teaching.value?.teaching_experience_years?.label ?? '');
const hourlyRateRange = computed(() => teaching.value?.hourly_rate_range?.label ?? '');
const monthlyRateRange = computed(() => teaching.value?.monthly_rate_range?.label ?? '');
const teachingMode = computed(() => teaching.value?.teaching_mode?.label ?? '');
const availability = computed(() => teaching.value?.availability_status?.label ?? '');
const teachingPhilosophy = computed(() => teaching.value?.teaching_philosophy ?? '');
const institutionName = computed(() => teaching.value?.institution_name ?? '');

const subjects = computed(() => t.value?.subjects ?? []);
const relatedTeachers = computed(() => t.value?.related_teachers ?? []);
const rating = computed(() => t.value?.rating ?? 0);
const totalReviews = computed(() => t.value?.total_reviews ?? 0);
const portfolio = computed(() => t.value?.portfolio ?? null);

const metaTitle = computed(() => name.value ? `${name.value} - Teacher Profile | SuGanta` : 'Teacher Profile | SuGanta');
const metaDescription = computed(() => {
  const parts = [];
  if (name.value) parts.push(name.value);
  if (qualification.value) parts.push(qualification.value);
  if (experience.value) parts.push(experience.value);
  if (cityState.value) parts.push(cityState.value);
  if (subjects.value.length) parts.push(`Teaches ${subjects.value.slice(0, 3).map(s => s.name).join(', ')}`);
  return parts.join(' • ') || 'Find the best teachers on SuGanta';
});

async function loadTeacher() {
  loading.value = true;
  error.value = null;
  errorCode.value = null;
  try {
    teacher.value = await getTeacher(props.id);
  } catch (e) {
    error.value = e?.message ?? 'Failed to load teacher profile';
    errorCode.value = e?.status ?? null;
  } finally {
    loading.value = false;
  }
}

function navigateToTeacher(t) {
  const slug = (t.name ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  router.visit(`/teachers/${slug}-${t.id}`);
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
    <div v-if="loading" class="max-w-6xl mx-auto animate-pulse">
      <div class="h-8 bg-gray-200 rounded w-32 mb-6"></div>
      <div class="bg-white rounded-2xl border p-6 mb-6">
        <div class="flex gap-6 mb-6">
          <div class="w-32 h-32 rounded-full bg-gray-200"></div>
          <div class="flex-1 space-y-3">
            <div class="h-8 bg-gray-200 rounded w-1/3"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
            <div class="h-4 bg-gray-200 rounded w-2/3"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 404 -->
    <div v-else-if="errorCode === 404" class="max-w-6xl mx-auto text-center py-16">
      <p class="text-xl font-semibold text-gray-700 mb-4">Teacher not found</p>
      <Link href="/teachers" class="text-indigo-600 hover:underline font-medium">← Back to Teachers</Link>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="max-w-6xl mx-auto py-8">
      <p class="text-red-700 mb-4">{{ error }}</p>
      <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700" @click="loadTeacher">Retry</button>
    </div>

    <!-- Profile -->
    <div v-else-if="teacher" class="max-w-6xl mx-auto">
      <Link href="/teachers" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline mb-6">← Back to Teachers</Link>

      <!-- Hero Card -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">
        <div class="flex flex-col md:flex-row gap-8">
          <!-- Avatar -->
          <div class="flex-shrink-0">
            <div class="relative">
              <img v-if="avatarUrl && !avatarError" :src="avatarUrl" :alt="name" class="w-32 h-32 rounded-full object-cover ring-4 ring-white shadow-lg" @error="avatarError = true" />
              <div v-else class="w-32 h-32 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 flex items-center justify-center text-white font-bold text-3xl shadow-lg">{{ initials }}</div>
              <span v-if="teacher.verified" class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full border-4 border-white shadow">✓ Verified</span>
            </div>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4 mb-3">
              <div>
                <h1 class="text-3xl font-black text-gray-900 mb-2">{{ name }}</h1>
                <div v-if="cityState" class="flex items-center gap-2 text-gray-600 mb-2">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                  <span>{{ cityState }}</span>
                  <span v-if="area" class="text-gray-400">• {{ area }}</span>
                </div>
              </div>
              <div v-if="hourlyRateRange" class="text-right">
                <div class="text-3xl font-black text-indigo-600">{{ hourlyRateRange }}</div>
                <div class="text-sm text-gray-500">Hourly Rate</div>
              </div>
            </div>

            <!-- Stats -->
            <div class="flex flex-wrap gap-4 mb-4">
              <div class="flex items-center gap-2">
                <div class="flex">
                  <svg v-for="i in 5" :key="i" class="w-5 h-5" :class="i <= Math.round(rating) ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <span class="font-bold text-gray-900">{{ rating }}</span>
                <span class="text-gray-500">({{ totalReviews }} reviews)</span>
              </div>
              <span v-if="experience" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-semibold">{{ experience }}</span>
              <span v-if="teachingMode" class="px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-sm font-semibold">{{ teachingMode }}</span>
              <span v-if="availability" class="px-3 py-1 bg-green-50 text-green-700 rounded-full text-sm font-semibold">{{ availability }}</span>
            </div>

            <!-- Bio -->
            <p v-if="bio" class="text-gray-700 leading-relaxed mb-4">{{ bio }}</p>

            <!-- Subjects -->
            <div v-if="subjects.length" class="flex flex-wrap gap-2">
              <span v-for="subject in subjects" :key="subject.id" class="px-3 py-1 bg-indigo-50 text-indigo-700 border border-indigo-100 rounded-full text-sm font-semibold">{{ subject.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Teaching Info -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="text-xl font-black text-gray-900 mb-4">Teaching Information</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-if="qualification"><span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Qualification</span><p class="text-gray-900 font-semibold">{{ qualification }}</p></div>
            <div v-if="experience"><span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Experience</span><p class="text-gray-900 font-semibold">{{ experience }}</p></div>
            <div v-if="institutionName"><span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Institution</span><p class="text-gray-900 font-semibold">{{ institutionName }}</p></div>
            <div v-if="monthlyRateRange"><span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Monthly Rate</span><p class="text-gray-900 font-semibold">{{ monthlyRateRange }}</p></div>
            <div v-if="teaching.online_classes !== undefined"><span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Online Classes</span><p class="font-semibold" :class="teaching.online_classes ? 'text-green-600' : 'text-gray-400'">{{ teaching.online_classes ? 'Yes' : 'No' }}</p></div>
            <div v-if="teaching.home_tuition !== undefined"><span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Home Tuition</span><p class="font-semibold" :class="teaching.home_tuition ? 'text-green-600' : 'text-gray-400'">{{ teaching.home_tuition ? 'Yes' : 'No' }}</p></div>
          </div>
          <div v-if="teachingPhilosophy" class="mt-4 pt-4 border-t"><span class="text-xs font-semibold text-gray-500 uppercase block mb-2">Teaching Philosophy</span><p class="text-gray-700 leading-relaxed">{{ teachingPhilosophy }}</p></div>
        </div>

        <!-- Contact Card -->
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white">
          <h3 class="text-xl font-black mb-4">Contact Teacher</h3>
          <p class="text-indigo-100 text-sm mb-6">Interested in learning? Get in touch with {{ name.split(' ')[0] }} today!</p>
          <button type="button" class="w-full bg-white text-indigo-600 font-bold py-3 rounded-xl hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            Call Now
          </button>
          <button type="button" class="w-full bg-green-500 text-white font-bold py-3 rounded-xl hover:bg-green-600 transition-colors flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp
          </button>
        </div>
      </div>

      <!-- Portfolio -->
      <div v-if="portfolio" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="text-xl font-black text-gray-900 mb-4">Portfolio</h2>
        <h3 class="font-bold text-gray-900 mb-2">{{ portfolio.title }}</h3>
        <div v-if="portfolio.description" class="text-gray-700 prose prose-sm max-w-none" v-html="portfolio.description"></div>
        <div v-if="portfolio.images?.length" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
          <img v-for="(img, i) in portfolio.images" :key="i" :src="img.url" class="rounded-lg border border-gray-200 w-full h-32 object-cover" />
        </div>
      </div>

      <!-- Related Teachers -->
      <div v-if="relatedTeachers.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-xl font-black text-gray-900 mb-4">Related Teachers</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <TeacherCard v-for="related in relatedTeachers" :key="related.id" :teacher="related" @click="navigateToTeacher" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
