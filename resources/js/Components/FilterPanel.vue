<template>
  <!-- Skeleton loading state -->
  <div v-if="loading" data-testid="filter-skeleton" class="space-y-4">
    <div v-for="i in 6" :key="i" class="animate-pulse">
      <div class="h-4 bg-gray-200 rounded w-1/3 mb-1"></div>
      <div class="h-9 bg-gray-200 rounded w-full"></div>
    </div>
    <div class="flex gap-2 pt-2">
      <div class="h-9 bg-gray-200 rounded flex-1 animate-pulse"></div>
      <div class="h-9 bg-gray-200 rounded flex-1 animate-pulse"></div>
    </div>
  </div>

  <!-- Filter controls -->
  <div v-else class="space-y-4">
    <!-- Location -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
      <input
        type="text"
        :value="filters.location"
        placeholder="City or area..."
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="filter-location"
        @input="update('location', $event.target.value)"
      />
    </div>

    <!-- Subject -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
      <select
        :value="filters.subject_id"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="filter-subject"
        @change="update('subject_id', $event.target.value ? Number($event.target.value) : null)"
      >
        <option value="">All Subjects</option>
        <option
          v-for="subject in options?.subjects ?? []"
          :key="subject.id"
          :value="subject.id"
        >
          {{ subject.name }}
        </option>
      </select>
    </div>

    <!-- Teaching Mode -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Teaching Mode</label>
      <select
        :value="filters.teaching_mode"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="filter-teaching-mode"
        @change="update('teaching_mode', $event.target.value ? Number($event.target.value) : null)"
      >
        <option value="">Any Mode</option>
        <option
          v-for="item in options?.options?.teaching_mode ?? []"
          :key="item.id"
          :value="item.id"
        >
          {{ item.label }}
        </option>
      </select>
    </div>

    <!-- Availability -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Availability</label>
      <select
        :value="filters.availability"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="filter-availability"
        @change="update('availability', $event.target.value ? Number($event.target.value) : null)"
      >
        <option value="">Any Availability</option>
        <option
          v-for="item in options?.options?.availability_status ?? []"
          :key="item.id"
          :value="item.id"
        >
          {{ item.label }}
        </option>
      </select>
    </div>

    <!-- Hourly Rate Range -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate</label>
      <select
        :value="filters.hourly_rate_range"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="filter-hourly-rate"
        @change="update('hourly_rate_range', $event.target.value ? Number($event.target.value) : null)"
      >
        <option value="">Any Rate</option>
        <option
          v-for="item in options?.options?.hourly_rate_range ?? []"
          :key="item.id"
          :value="item.id"
        >
          {{ item.label }}
        </option>
      </select>
    </div>

    <!-- Experience -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Experience</label>
      <select
        :value="filters.experience"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="filter-experience"
        @change="update('experience', $event.target.value ? Number($event.target.value) : null)"
      >
        <option value="">Any Experience</option>
        <option
          v-for="item in options?.options?.teaching_experience_years ?? []"
          :key="item.id"
          :value="item.id"
        >
          {{ item.label }}
        </option>
      </select>
    </div>

    <!-- Action buttons -->
    <div class="flex gap-2 pt-2">
      <button
        type="button"
        class="flex-1 bg-indigo-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="apply-filters-btn"
        @click="$emit('apply')"
      >
        Apply Filters
      </button>
      <button
        type="button"
        class="flex-1 bg-white text-gray-700 text-sm font-medium py-2 px-4 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400"
        data-testid="clear-filters-btn"
        @click="$emit('clear')"
      >
        Clear Filters
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  options: {
    type: Object,
    default: () => ({}),
  },
  modelValue: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'apply', 'clear']);

// Computed getter/setter for clean v-model handling
const filters = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>
