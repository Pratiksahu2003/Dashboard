<template>
  <div
    class="rounded-3xl border border-slate-200/80 bg-white/95 p-5 sm:p-6 shadow-[0_8px_30px_-8px_rgba(15,23,42,0.08)] backdrop-blur-sm lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto"
  >
    <div class="flex items-center gap-2 mb-5 pb-4 border-b border-slate-100">
      <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/25">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-900 leading-tight">Refine results</h2>
        <p class="text-xs text-slate-500">Narrow down your search</p>
      </div>
    </div>

    <!-- Skeleton loading state -->
    <div v-if="loading" data-testid="filter-skeleton" class="space-y-4">
      <div v-for="i in 6" :key="i" class="animate-pulse">
        <div class="h-3 bg-slate-200 rounded-lg w-1/3 mb-2"></div>
        <div class="h-10 bg-slate-100 rounded-xl w-full"></div>
      </div>
      <div class="flex gap-2 pt-2">
        <div class="h-11 bg-slate-100 rounded-xl flex-1 animate-pulse"></div>
        <div class="h-11 bg-slate-100 rounded-xl flex-1 animate-pulse"></div>
      </div>
    </div>

    <!-- Filter controls -->
    <div v-else class="space-y-4">
      <!-- Location -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Location</label>
        <input
          type="text"
          :value="filters.location"
          placeholder="City or area..."
          class="field-input"
          data-testid="filter-location"
          @input="update('location', $event.target.value)"
        />
      </div>

      <!-- Subject (searchable) -->
      <div ref="subjectDropdownRef" class="relative">
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Subject</label>
        <button
          type="button"
          class="field-input flex w-full items-center justify-between gap-2 text-left"
          data-testid="filter-subject-trigger"
          aria-haspopup="listbox"
          :aria-expanded="subjectMenuOpen"
          @click.stop="toggleSubjectMenu"
        >
          <span class="min-w-0 truncate font-medium text-slate-800">{{ selectedSubjectLabel }}</span>
          <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div
          v-show="subjectMenuOpen"
          class="absolute left-0 right-0 z-30 mt-1 flex max-h-64 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
          data-testid="filter-subject-dropdown"
          role="listbox"
          @click.stop
        >
          <div class="border-b border-slate-100 p-2">
            <input
              v-model="subjectSearch"
              type="search"
              autocomplete="off"
              placeholder="Search subjects..."
              class="w-full rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              data-testid="filter-subject-search"
              @keydown.escape.prevent="subjectMenuOpen = false"
            />
          </div>
          <ul class="max-h-48 overflow-y-auto py-1 text-sm" role="presentation">
            <li
              role="option"
              :aria-selected="filters.subject_id == null"
              class="cursor-pointer px-3 py-2 text-slate-700 hover:bg-indigo-50"
              data-testid="filter-subject-all"
              @mousedown.prevent="selectSubjectId(null)"
            >
              All subjects
            </li>
            <li
              v-for="subject in filteredSubjects"
              :key="subject.id"
              role="option"
              :aria-selected="Number(filters.subject_id) === Number(subject.id)"
              class="cursor-pointer px-3 py-2 text-slate-800 hover:bg-indigo-50"
              :data-testid="`filter-subject-option-${subject.id}`"
              @mousedown.prevent="selectSubjectId(Number(subject.id))"
            >
              {{ subject.name }}
            </li>
            <li
              v-if="subjectSearch.trim() && filteredSubjects.length === 0"
              class="px-3 py-4 text-center text-xs text-slate-500"
            >
              No subjects match “{{ subjectSearch.trim() }}”
            </li>
          </ul>
        </div>
      </div>

      <!-- Teaching Mode -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Teaching Mode</label>
        <select
          :value="filters.teaching_mode"
          class="field-input"
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
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Availability</label>
        <select
          :value="filters.availability"
          class="field-input"
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
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Hourly Rate</label>
        <select
          :value="filters.hourly_rate_range"
          class="field-input"
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
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Experience</label>
        <select
          :value="filters.experience"
          class="field-input"
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
      <div class="flex gap-2 pt-3">
        <button
          type="button"
          class="flex-1 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
          data-testid="apply-filters-btn"
          @click="$emit('apply')"
        >
          Apply
        </button>
        <button
          type="button"
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 py-2.5 px-4 text-sm font-semibold text-slate-700 transition hover:bg-white hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300/50"
          data-testid="clear-filters-btn"
          @click="$emit('clear')"
        >
          Clear
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

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

const filters = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value });
}

const subjectDropdownRef = ref(null);
const subjectMenuOpen = ref(false);
const subjectSearch = ref('');

const filteredSubjects = computed(() => {
  const list = props.options?.subjects ?? [];
  const q = subjectSearch.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter((s) => String(s.name ?? '').toLowerCase().includes(q));
});

const selectedSubjectLabel = computed(() => {
  const id = props.modelValue.subject_id;
  if (id == null || id === '') return 'All subjects';
  const match = (props.options?.subjects ?? []).find((s) => Number(s.id) === Number(id));
  return match?.name ?? 'All subjects';
});

function toggleSubjectMenu() {
  subjectMenuOpen.value = !subjectMenuOpen.value;
  if (subjectMenuOpen.value) subjectSearch.value = '';
}

function selectSubjectId(id) {
  update('subject_id', id);
  subjectMenuOpen.value = false;
  subjectSearch.value = '';
}

function onDocumentClick(e) {
  const el = subjectDropdownRef.value;
  if (el && !el.contains(e.target)) subjectMenuOpen.value = false;
}

function onDocumentKeydown(e) {
  if (e.key === 'Escape' && subjectMenuOpen.value) subjectMenuOpen.value = false;
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<style scoped>
.field-input {
  @apply w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm;
  @apply placeholder:text-slate-400 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20;
}
</style>
