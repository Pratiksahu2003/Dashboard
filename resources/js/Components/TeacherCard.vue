<template>
  <div
    class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden"
    data-testid="teacher-card"
  >
    <div class="flex gap-0">

      <!-- Left: Avatar column -->
      <div class="flex flex-col items-center pt-5 px-4 pb-4 w-28 flex-shrink-0">
        <div class="relative">
          <img
            v-if="teacher.avatar_url && !avatarError"
            :src="teacher.avatar_url"
            :alt="teacher.name"
            class="w-20 h-20 rounded-full object-cover ring-2 ring-white shadow"
            data-testid="avatar-image"
            @error="avatarError = true"
          />
          <div
            v-else
            class="w-20 h-20 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 flex items-center justify-center text-white font-bold text-xl select-none shadow"
            data-testid="avatar-initials"
          >
            {{ initials }}
          </div>

          <!-- Online / availability dot -->
          <span
            class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[10px] font-bold px-2 py-0.5 rounded-full border-2 border-white whitespace-nowrap"
            :class="isAvailable
              ? 'bg-emerald-500 text-white'
              : 'bg-gray-300 text-gray-600'"
          >
            {{ isAvailable ? '● ONLINE' : '○ OFFLINE' }}
          </span>
        </div>
      </div>

      <!-- Right: Content -->
      <div class="flex-1 min-w-0 pt-4 pr-4 pb-4 pl-1">

        <!-- Top row: name + rate -->
        <div class="flex items-start justify-between gap-2 mb-1">
          <div class="min-w-0">
            <h3
              class="font-black text-gray-900 text-base uppercase tracking-wide leading-tight truncate"
              data-testid="teacher-name"
            >
              {{ teacher.name }}
            </h3>
            <!-- Verified badge inline -->
            <span
              v-if="teacher.verified"
              class="inline-flex items-center gap-0.5 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 px-1.5 py-0.5 rounded-full mt-0.5"
              data-testid="verified-badge"
            >
              <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Verified
            </span>
          </div>

          <!-- Hourly rate top-right -->
          <div v-if="teacher.hourly_rate != null" class="text-right flex-shrink-0" data-testid="hourly-rate">
            <div class="text-indigo-600 font-black text-base leading-none">₹{{ teacher.hourly_rate }}</div>
            <div class="text-gray-400 text-[11px] font-medium">/hour</div>
          </div>
        </div>

        <!-- Location -->
        <div v-if="cityState" class="flex items-center gap-1 text-xs text-gray-500 mb-2" data-testid="city-state">
          <svg class="w-3 h-3 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          <span class="truncate">{{ cityState }}</span>
        </div>

        <!-- Bio snippet -->
        <p v-if="teacher.bio" class="text-xs text-gray-500 leading-relaxed mb-2 line-clamp-2">
          {{ teacher.bio }}
        </p>

        <!-- Stats row: experience · monthly rate · area -->
        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600 mb-3">
          <span v-if="teacher.experience_years?.label" class="flex items-center gap-1" data-testid="experience-years">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span class="font-semibold">{{ teacher.experience_years.label }}</span>
          </span>

          <span v-if="teacher.teaching_mode?.label" class="flex items-center gap-1" data-testid="teaching-mode">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span class="font-semibold">{{ teacher.teaching_mode.label }}</span>
          </span>

          <span v-if="teacher.availability_status?.label" class="flex items-center gap-1" data-testid="availability-status">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            </svg>
            <span class="font-semibold">{{ teacher.availability_status.label }}</span>
          </span>
        </div>

        <!-- Subjects -->
        <div v-if="visibleSubjects.length" class="flex flex-wrap gap-1 mb-3">
          <span
            v-for="subject in visibleSubjects"
            :key="subject.id"
            class="text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-full"
            data-testid="subject-tag"
          >
            {{ subject.name }}
          </span>
          <span
            v-if="subjectOverflow > 0"
            class="text-[11px] font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full"
            data-testid="subject-overflow"
          >
            +{{ subjectOverflow }} more
          </span>
        </div>

        <!-- Rating -->
        <div class="flex items-center gap-1.5 mb-3">
          <div class="flex">
            <svg v-for="i in 5" :key="i" class="w-3.5 h-3.5" :class="i <= Math.round(teacher.rating) ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
          </div>
          <span class="text-xs font-bold text-gray-700" data-testid="rating">{{ teacher.rating }}</span>
          <span class="text-xs text-gray-400" data-testid="total-reviews">({{ teacher.total_reviews }} reviews)</span>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-2">
          <button
            type="button"
            class="flex-1 border border-gray-300 text-gray-700 text-sm font-semibold py-2 rounded-xl hover:bg-gray-50 transition-colors"
            @click.stop="$emit('click', teacher)"
          >
            Profile
          </button>
          <button
            type="button"
            class="flex-1 bg-indigo-600 text-white text-sm font-semibold py-2 rounded-xl hover:bg-indigo-700 transition-colors flex items-center justify-center gap-1.5"
            @click.stop="$emit('click', teacher)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            Contact Now
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  teacher: {
    type: Object,
    required: true,
  },
});

defineEmits(['click']);

const avatarError = ref(false);

const initials = computed(() => {
  if (!props.teacher.name) return '?';
  return props.teacher.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('');
});

const cityState = computed(() => {
  const parts = [props.teacher.city, props.teacher.state].filter(Boolean);
  return parts.join(', ');
});

const slug = computed(() => {
  if (!props.teacher.name) return 'teacher';
  return props.teacher.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

const profileUrl = computed(() => `/teachers/${slug.value}-${props.teacher.id}`);

const visibleSubjects = computed(() => (props.teacher.subjects ?? []).slice(0, 3));

const subjectOverflow = computed(() => {
  const total = (props.teacher.subjects ?? []).length;
  return total > 3 ? total - 3 : 0;
});

const isAvailable = computed(() =>
  props.teacher.availability_status?.label?.toLowerCase().includes('available') ?? false
);
</script>
