<template>
  <div
    class="rounded-3xl border border-slate-200/80 bg-white/95 p-5 sm:p-6 shadow-[0_8px_30px_-8px_rgba(15,23,42,0.08)] backdrop-blur-sm lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto"
  >
    <div class="mb-5 flex items-center gap-2 border-b border-slate-100 pb-4">
      <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/25">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
      </span>
      <div>
        <h2 class="text-sm font-semibold leading-tight text-slate-900">Refine results</h2>
        <p class="text-xs text-slate-500">Institutes &amp; schools</p>
      </div>
    </div>

    <div v-if="loading" data-testid="institute-filter-skeleton" class="space-y-4">
      <div v-for="i in 6" :key="i" class="animate-pulse">
        <div class="mb-2 h-3 w-1/3 rounded-lg bg-slate-200"></div>
        <div class="h-10 w-full rounded-xl bg-slate-100"></div>
      </div>
      <div class="flex gap-2 pt-2">
        <div class="h-11 flex-1 animate-pulse rounded-xl bg-slate-100"></div>
        <div class="h-11 flex-1 animate-pulse rounded-xl bg-slate-100"></div>
      </div>
    </div>

    <div v-else class="space-y-4">
      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Location search</label>
        <input
          type="text"
          :value="filters.location"
          placeholder="City, area, or state…"
          class="field-input"
          data-testid="institute-filter-location"
          @input="update('location', $event.target.value)"
        />
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">City</label>
        <input
          type="text"
          :value="filters.city"
          placeholder="When location is empty"
          class="field-input"
          data-testid="institute-filter-city"
          @input="update('city', $event.target.value)"
        />
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
        <input
          type="text"
          :value="filters.state"
          class="field-input"
          data-testid="institute-filter-state"
          @input="update('state', $event.target.value)"
        />
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pincode</label>
        <input
          type="text"
          :value="filters.pincode"
          class="field-input"
          data-testid="institute-filter-pincode"
          @input="update('pincode', $event.target.value)"
        />
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Type</label>
        <select
          :value="filters.institute_type ?? ''"
          class="field-input"
          data-testid="institute-filter-type"
          @change="update('institute_type', $event.target.value ? Number($event.target.value) : null)"
        >
          <option value="">Any type</option>
          <option
            v-for="item in options?.options?.institute_type ?? []"
            :key="item.id"
            :value="item.id"
          >
            {{ item.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Category</label>
        <select
          :value="filters.institute_category ?? ''"
          class="field-input"
          data-testid="institute-filter-category"
          @change="update('institute_category', $event.target.value ? Number($event.target.value) : null)"
        >
          <option value="">Any category</option>
          <option
            v-for="item in options?.options?.institute_category ?? []"
            :key="item.id"
            :value="item.id"
          >
            {{ item.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Established</label>
        <select
          :value="filters.establishment_year_range ?? ''"
          class="field-input"
          data-testid="institute-filter-established"
          @change="update('establishment_year_range', $event.target.value ? Number($event.target.value) : null)"
        >
          <option value="">Any period</option>
          <option
            v-for="item in options?.options?.establishment_year_range ?? []"
            :key="item.id"
            :value="item.id"
          >
            {{ item.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Students</label>
        <select
          :value="filters.total_students_range ?? ''"
          class="field-input"
          data-testid="institute-filter-students"
          @change="update('total_students_range', $event.target.value ? Number($event.target.value) : null)"
        >
          <option value="">Any size</option>
          <option
            v-for="item in options?.options?.total_students_range ?? []"
            :key="item.id"
            :value="item.id"
          >
            {{ item.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Teaching staff</label>
        <select
          :value="filters.total_teachers_range ?? ''"
          class="field-input"
          data-testid="institute-filter-teachers-range"
          @change="update('total_teachers_range', $event.target.value ? Number($event.target.value) : null)"
        >
          <option value="">Any</option>
          <option
            v-for="item in options?.options?.total_teachers_range ?? []"
            :key="item.id"
            :value="item.id"
          >
            {{ item.label }}
          </option>
        </select>
      </div>

      <div class="flex flex-wrap gap-4">
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            :checked="!!filters.verified"
            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            data-testid="institute-filter-verified"
            @change="update('verified', $event.target.checked || null)"
          />
          Verified only
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            :checked="!!filters.featured"
            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            data-testid="institute-filter-featured"
            @change="update('featured', $event.target.checked || null)"
          />
          Featured only
        </label>
      </div>

      <div class="flex gap-2 pt-3">
        <button
          type="button"
          class="flex-1 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
          data-testid="institute-apply-filters"
          @click="$emit('apply')"
        >
          Apply
        </button>
        <button
          type="button"
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 py-2.5 px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300/50"
          data-testid="institute-clear-filters"
          @click="$emit('clear')"
        >
          Clear
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  options: { type: Object, default: () => ({}) },
  modelValue: { type: Object, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'apply', 'clear']);

function update(key, value) {
  const next = { ...props.modelValue, [key]: value };
  if (!value && (key === 'verified' || key === 'featured')) {
    delete next[key];
  }
  emit('update:modelValue', next);
}
</script>

<style scoped>
.field-input {
  @apply w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm;
  @apply placeholder:text-slate-400 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20;
}
</style>
