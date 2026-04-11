<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import { getTeacher } from '@/services/teacherApi';

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps({
  id: {
    type: Number,
    required: true,
  },
});

// ─── Reactive state ───────────────────────────────────────────────────────────

const teacher = ref(null);
const loading = ref(false);
const error = ref(null);
const errorCode = ref(null);
const avatarError = ref(false);

// ─── Computed ─────────────────────────────────────────────────────────────────

const initials = computed(() => {
  const name = teacher.value?.user?.name;
  if (!name) return '?';
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('');
});

// ─── Data fetching ────────────────────────────────────────────────────────────

async function loadTeacher() {
  loading.value = true;
  error.value = null;
  errorCode.value = null;
  avatarError.value = false;
  try {
    teacher.value = await getTeacher(props.id);
  } catch (e) {
    error.value = e.message || 'Failed to load teacher profile.';
    errorCode.value = e.status || null;
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadTeacher();
});
</script>

<template>
  <AppLayout>
    <!-- Loading skeleton -->
    <div v-if="loading" class="max-w-4xl mx-auto animate-pulse" data-testid="profile-skeleton">
      <div class="h-8 bg-gray-200 rounded w-1/4 mb-6"></div>
      <div class="bg-white rounded-xl border border-gray-100 p-6">
        <div class="flex gap-4 mb-6">
          <div class="w-24 h-24 rounded-full bg-gray-200"></div>
          <div class="flex-1 space-y-3">
            <div class="h-6 bg-gray-200 rounded w-1/3"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
          </div>
        </div>
        <div class="space-y-3">
          <div class="h-4 bg-gray-200 rounded"></div>
          <div class="h-4 bg-gray-200 rounded w-5/6"></div>
          <div class="h-4 bg-gray-200 rounded w-4/6"></div>
        </div>
      </div>
    </div>

    <!-- 404 not found -->
    <div v-else-if="errorCode === 404" class="max-w-4xl mx-auto text-center py-16" data-testid="not-found">
      <p class="text-xl font-semibold text-gray-700 mb-4">Teacher not found</p>
      <Link href="/teachers" class="text-indigo-600 hover:underline font-medium">
        ← Back to Teachers
      </Link>
    </div>

    <!-- Non-404 error -->
    <div v-else-if="error" class="max-w-4xl mx-auto py-8" data-testid="error-message">
      <p class="text-red-700 mb-4">{{ error }}</p>
      <button
        type="button"
        class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
        data-testid="retry-btn"
        @click="loadTeacher"
      >
        Retry
      </button>
    </div>

    <!-- Profile content -->
    <div v-else-if="teacher" class="max-w-4xl mx-auto">
      <!-- Back button -->
      <Link
        href="/teachers"
        class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline mb-6"
        data-testid="back-btn"
      >
        ← Back to Teachers
      </Link>

      <!-- Profile card -->
      <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
        <!-- Header: avatar + name + verified -->
        <div class="flex items-start gap-5 mb-6">
          <!-- Avatar -->
          <div class="flex-shrink-0">
            <img
              v-if="teacher.profile?.profile_image_url && !avatarError"
              :src="teacher.profile.profile_image_url"
              :alt="teacher.user?.name"
              class="w-24 h-24 rounded-full object-cover"
              data-testid="profile-avatar"
              @error="avatarError = true"
            />
            <div
              v-else
              class="w-24 h-24 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-2xl select-none"
              data-testid="profile-avatar-initials"
            >
              {{ initials }}
            </div>
          </div>

          <!-- Name + verified + location -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h1 class="text-2xl font-bold text-gray-900" data-testid="profile-name">
                {{ teacher.user?.name }}
              </h1>
              <span
                v-if="teacher.verified"
                class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full"
                data-testid="profile-verified-badge"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Verified
              </span>
            </div>
            <p v-if="teacher.profile?.city" class="text-sm text-gray-500" data-testid="profile-city">
              {{ teacher.profile.city }}
            </p>
            <p v-if="teacher.profile?.state" class="text-sm text-gray-500" data-testid="profile-state">
              {{ teacher.profile.state }}
            </p>
          </div>
        </div>

        <!-- Bio -->
        <p v-if="teacher.profile?.bio" class="text-gray-700 mb-6" data-testid="profile-bio">
          {{ teacher.profile.bio }}
        </p>

        <!-- Teaching details grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
          <div v-if="teacher.teaching?.qualification?.label">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Qualification</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-qualification">
              {{ teacher.teaching.qualification.label }}
            </p>
          </div>
          <div v-if="teacher.teaching?.experience_years?.label">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Experience</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-experience">
              {{ teacher.teaching.experience_years.label }}
            </p>
          </div>
          <div v-if="teacher.teaching?.specialization">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Specialization</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-specialization">
              {{ teacher.teaching.specialization }}
            </p>
          </div>
          <div v-if="teacher.teaching?.languages?.length">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Languages</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-languages">
              {{ teacher.teaching.languages.join(', ') }}
            </p>
          </div>
          <div v-if="teacher.teaching?.hourly_rate != null">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Hourly Rate</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-hourly-rate">
              ₹{{ teacher.teaching.hourly_rate }}/hr
            </p>
          </div>
          <div v-if="teacher.teaching?.monthly_rate != null">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Monthly Rate</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-monthly-rate">
              ₹{{ teacher.teaching.monthly_rate }}/mo
            </p>
          </div>
          <div v-if="teacher.teaching?.teaching_mode?.label">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Teaching Mode</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-teaching-mode">
              {{ teacher.teaching.teaching_mode.label }}
            </p>
          </div>
          <div v-if="teacher.teaching?.availability_status?.label">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Availability</span>
            <p class="text-sm text-gray-800 mt-0.5" data-testid="profile-availability">
              {{ teacher.teaching.availability_status.label }}
            </p>
          </div>
        </div>

        <!-- Class type flags -->
        <div class="flex flex-wrap gap-3 mb-6">
          <span
            class="text-xs font-medium px-3 py-1 rounded-full"
            :class="teacher.teaching?.online_classes ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
            data-testid="profile-online-classes"
          >
            Online Classes: {{ teacher.teaching?.online_classes ? 'Yes' : 'No' }}
          </span>
          <span
            class="text-xs font-medium px-3 py-1 rounded-full"
            :class="teacher.teaching?.home_tuition ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
            data-testid="profile-home-tuition"
          >
            Home Tuition: {{ teacher.teaching?.home_tuition ? 'Yes' : 'No' }}
          </span>
          <span
            class="text-xs font-medium px-3 py-1 rounded-full"
            :class="teacher.teaching?.institute_classes ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
            data-testid="profile-institute-classes"
          >
            Institute Classes: {{ teacher.teaching?.institute_classes ? 'Yes' : 'No' }}
          </span>
        </div>

        <!-- Subjects -->
        <div v-if="teacher.subjects?.length" class="flex flex-wrap gap-2">
          <span
            v-for="subject in teacher.subjects"
            :key="subject.id"
            class="text-sm bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full"
            data-testid="profile-subject-tag"
          >
            {{ subject.name }}
          </span>
        </div>
      </div>

      <!-- Reviews section -->
      <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Reviews</h2>

        <div v-if="!teacher.reviews_sample?.length" class="text-gray-500 text-sm" data-testid="no-reviews">
          No reviews yet
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="review in teacher.reviews_sample.slice(0, 5)"
            :key="review.id"
            class="border-b border-gray-100 pb-4 last:border-0 last:pb-0"
            data-testid="review-item"
          >
            <div class="flex items-center gap-2 mb-1">
              <span class="text-amber-500 font-medium">{{ review.rating }} ★</span>
              <span class="text-xs text-gray-400">{{ review.created_at }}</span>
            </div>
            <p class="text-sm text-gray-700">{{ review.comment }}</p>
          </div>
        </div>
      </div>

      <!-- Related teachers -->
      <div
        v-if="teacher.related_teachers?.length"
        class="bg-white rounded-xl border border-gray-100 p-6"
        data-testid="related-teachers"
      >
        <h2 class="text-lg font-bold text-gray-900 mb-4">Related Teachers</h2>
        <div class="flex gap-4 overflow-x-auto pb-2">
          <div
            v-for="related in teacher.related_teachers"
            :key="related.id"
            class="flex-shrink-0 w-64"
          >
            <TeacherCard :teacher="related" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
