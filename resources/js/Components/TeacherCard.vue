<template>
  <div
    class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 cursor-pointer hover:shadow-md transition-shadow"
    data-testid="teacher-card"
    @click="$emit('click', teacher.id)"
  >
    <!-- Header: avatar + name + verified badge -->
    <div class="flex items-start gap-3 mb-3">
      <!-- Avatar -->
      <div class="flex-shrink-0">
        <img
          v-if="teacher.avatar_url && !avatarError"
          :src="teacher.avatar_url"
          :alt="teacher.name"
          class="w-14 h-14 rounded-full object-cover"
          data-testid="avatar-image"
          @error="avatarError = true"
        />
        <div
          v-else
          class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-semibold text-lg select-none"
          data-testid="avatar-initials"
        >
          {{ initials }}
        </div>
      </div>

      <!-- Name + location + verified -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1 flex-wrap">
          <span class="font-semibold text-gray-900 truncate" data-testid="teacher-name">{{ teacher.name }}</span>
          <span
            v-if="teacher.verified"
            class="inline-flex items-center gap-0.5 text-xs font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full"
            data-testid="verified-badge"
          >
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            Verified
          </span>
        </div>

        <div v-if="cityState" class="text-sm text-gray-500 mt-0.5" data-testid="city-state">{{ cityState }}</div>
      </div>
    </div>

    <!-- Subjects -->
    <div class="flex flex-wrap gap-1 mb-3" data-testid="subjects-container">
      <span
        v-for="subject in visibleSubjects"
        :key="subject.id"
        class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full"
        data-testid="subject-tag"
      >
        {{ subject.name }}
      </span>
      <span
        v-if="subjectOverflow > 0"
        class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
        data-testid="subject-overflow"
      >
        +{{ subjectOverflow }} more
      </span>
    </div>

    <!-- Rating + reviews -->
    <div class="flex items-center gap-2 mb-2 text-sm">
      <span class="text-amber-500 font-medium" data-testid="rating">{{ teacher.rating }} ★</span>
      <span class="text-gray-500" data-testid="total-reviews">({{ teacher.total_reviews }} reviews)</span>
    </div>

    <!-- Rate + teaching mode + availability -->
    <div class="flex flex-wrap items-center gap-2 text-sm mb-2">
      <span v-if="teacher.hourly_rate != null" class="font-semibold text-gray-800" data-testid="hourly-rate">
        ₹{{ teacher.hourly_rate }}/hr
      </span>
      <span
        v-if="teacher.teaching_mode?.label"
        class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full"
        data-testid="teaching-mode"
      >
        {{ teacher.teaching_mode.label }}
      </span>
      <span
        v-if="teacher.availability_status?.label"
        class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full"
        data-testid="availability-status"
      >
        {{ teacher.availability_status.label }}
      </span>
    </div>

    <!-- Qualification + experience -->
    <div class="flex flex-wrap gap-2 text-xs text-gray-500">
      <span v-if="teacher.qualification?.label" data-testid="qualification">
        {{ teacher.qualification.label }}
      </span>
      <span v-if="teacher.experience_years?.label" data-testid="experience-years">
        {{ teacher.experience_years.label }}
      </span>
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

const visibleSubjects = computed(() => {
  return (props.teacher.subjects ?? []).slice(0, 3);
});

const subjectOverflow = computed(() => {
  const total = (props.teacher.subjects ?? []).length;
  return total > 3 ? total - 3 : 0;
});
</script>
