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
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold leading-tight text-slate-900">Refine results</h2>
        <p class="text-xs text-slate-500">Institutes, schools &amp; coaching centres</p>
      </div>
      <span
        v-if="activeFilterCount > 0"
        class="shrink-0 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-indigo-800"
        data-testid="institute-active-filter-count"
      >
        {{ activeFilterCount }} active
      </span>
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

    <div v-else class="space-y-6">
      <!-- Location -->
      <section class="space-y-3">
        <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-indigo-600">Location</h3>

        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Broad search</label>
          <input
            type="text"
            :value="filters.location"
            placeholder="City, area, or state in one field…"
            class="field-input"
            data-testid="institute-filter-location"
            @input="onLocationInput($event.target.value)"
          />
          <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">
            When this is filled, the API uses it instead of separate city and state below.
          </p>
        </div>

        <div ref="cityDropdownRef" class="relative">
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">City (directory)</label>
          <button
            type="button"
            class="field-input flex w-full items-center justify-between gap-2 text-left"
            data-testid="institute-filter-city-trigger"
            aria-haspopup="listbox"
            :aria-expanded="cityMenuOpen"
            :disabled="!!String(filters.location || '').trim()"
            @click.stop="toggleCityMenu"
          >
            <span class="min-w-0 truncate font-medium text-slate-800">{{ selectedCityLabel }}</span>
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <p v-if="String(filters.location || '').trim()" class="mt-1 text-[11px] text-amber-700/90">
            Clear broad search above to use the city directory.
          </p>
          <div
            v-show="cityMenuOpen"
            class="absolute left-0 right-0 z-30 mt-1 flex max-h-64 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
            data-testid="institute-filter-city-dropdown"
            role="listbox"
            @click.stop
          >
            <div class="border-b border-slate-100 p-2">
              <input
                v-model="citySearch"
                type="search"
                autocomplete="off"
                placeholder="Search cities…"
                class="w-full rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                data-testid="institute-filter-city-search"
                @keydown.escape.prevent="cityMenuOpen = false"
              />
            </div>
            <ul class="max-h-48 overflow-y-auto py-1 text-sm" role="presentation">
              <li
                role="option"
                :aria-selected="!filters.city"
                class="cursor-pointer px-3 py-2 text-slate-700 hover:bg-indigo-50"
                data-testid="institute-filter-city-all"
                @mousedown.prevent="selectCity(null)"
              >
                Any city
              </li>
              <li
                v-for="row in filteredCities"
                :key="row.value"
                role="option"
                :aria-selected="filters.city === row.value"
                class="cursor-pointer px-3 py-2 text-slate-800 hover:bg-indigo-50"
                :data-testid="`institute-filter-city-${row.value}`"
                @mousedown.prevent="selectCity(row.value)"
              >
                <span class="font-medium">{{ row.value }}</span>
                <span v-if="row.count != null" class="ml-2 text-xs tabular-nums text-slate-400">({{ row.count }})</span>
              </li>
              <li
                v-if="citySearch.trim() && filteredCities.length === 0"
                class="px-3 py-4 text-center text-xs text-slate-500"
              >
                No cities match “{{ citySearch.trim() }}”
              </li>
            </ul>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
          <input
            type="text"
            :value="filters.state"
            placeholder="Ignored if broad search is set"
            class="field-input"
            :class="locationLocksDetail ? 'opacity-60' : ''"
            data-testid="institute-filter-state"
            @input="update('state', $event.target.value)"
          />
        </div>

        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pincode</label>
          <input
            type="text"
            :value="filters.pincode"
            placeholder="Exact PIN / postal code"
            maxlength="20"
            inputmode="numeric"
            autocomplete="postal-code"
            class="field-input"
            data-testid="institute-filter-pincode"
            @input="onPincodeInput($event)"
          />
          <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">
            Exact match on <span class="font-mono text-[10px] text-slate-600">profile.pincode</span>. Independent of broad search and city directory above.
          </p>
        </div>
      </section>

      <div class="border-t border-slate-100 pt-2"></div>

      <!-- Institute profile -->
      <section class="space-y-3">
        <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-indigo-600">Institute</h3>

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
      </section>

      <div class="border-t border-slate-100 pt-2"></div>

      <!-- Scale -->
      <section class="space-y-3">
        <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-indigo-600">Scale</h3>

        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Student body</label>
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
      </section>

      <div class="border-t border-slate-100 pt-2"></div>

      <!-- Quality -->
      <section class="space-y-3">
        <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-indigo-600">Listing quality</h3>
        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3 space-y-3">
          <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
            <input
              type="checkbox"
              :checked="!!filters.verified"
              class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              data-testid="institute-filter-verified"
              @change="onVerifiedChange($event.target.checked)"
            />
            <span>
              <span class="font-semibold">Verified only</span>
              <span class="mt-0.5 block text-xs font-normal text-slate-500">Profile verification from SuGanta</span>
            </span>
          </label>
          <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
            <input
              type="checkbox"
              :checked="!!filters.featured"
              class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              data-testid="institute-filter-featured"
              @change="onFeaturedChange($event.target.checked)"
            />
            <span>
              <span class="font-semibold">Featured only</span>
              <span class="mt-0.5 block text-xs font-normal text-slate-500">Highlighted institutes</span>
            </span>
          </label>
        </div>
      </section>

      <div class="flex gap-2 pt-1">
        <button
          type="button"
          class="flex-1 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
          data-testid="institute-apply-filters"
          @click="$emit('apply')"
        >
          Apply filters
        </button>
        <button
          type="button"
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 py-2.5 px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300/50"
          data-testid="institute-clear-filters"
          @click="$emit('clear')"
        >
          Clear all
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  options: { type: Object, default: () => ({}) },
  modelValue: { type: Object, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'apply', 'clear']);

const filters = computed(() => props.modelValue);

const locationLocksDetail = computed(() => !!String(props.modelValue?.location ?? '').trim());

const sortedCities = computed(() => {
  const raw = props.options?.cities;
  if (!Array.isArray(raw)) return [];
  return [...raw].sort((a, b) => {
    const ca = Number(b?.count) || 0;
    const cb = Number(a?.count) || 0;
    if (ca !== cb) return ca - cb;
    return String(a?.value ?? '').localeCompare(String(b?.value ?? ''));
  });
});

const cityDropdownRef = ref(null);
const cityMenuOpen = ref(false);
const citySearch = ref('');

const filteredCities = computed(() => {
  const list = sortedCities.value;
  const q = citySearch.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter((c) => String(c?.value ?? '').toLowerCase().includes(q));
});

const selectedCityLabel = computed(() => {
  const c = props.modelValue.city;
  if (c == null || c === '') return 'Any city';
  return String(c);
});

const activeFilterCount = computed(() => {
  const f = props.modelValue;
  let n = 0;
  if (String(f.location ?? '').trim()) n++;
  if (String(f.city ?? '').trim()) n++;
  if (String(f.state ?? '').trim()) n++;
  if (String(f.pincode ?? '').trim()) n++;
  if (f.institute_type != null) n++;
  if (f.institute_category != null) n++;
  if (f.establishment_year_range != null) n++;
  if (f.total_students_range != null) n++;
  if (f.total_teachers_range != null) n++;
  if (f.verified) n++;
  if (f.featured) n++;
  return n;
});

function emitPatch(partial) {
  const next = { ...props.modelValue, ...partial };
  if (next.verified !== true) delete next.verified;
  if (next.featured !== true) delete next.featured;
  Object.keys(next).forEach((k) => {
    if (next[k] === null || next[k] === undefined) delete next[k];
    if (next[k] === '' && !['location', 'city', 'state', 'pincode'].includes(k)) delete next[k];
  });
  emit('update:modelValue', next);
}

function update(key, value) {
  const partial = { [key]: value };
  if (key === 'city' && value) partial.location = '';
  emitPatch(partial);
}

function onPincodeInput(e) {
  const raw = e?.target?.value ?? '';
  const v = String(raw).trim().replace(/\s+/g, '').slice(0, 20);
  update('pincode', v);
}

function onLocationInput(val) {
  const partial = { location: val };
  if (String(val).trim()) {
    partial.city = '';
    partial.state = '';
  }
  emitPatch(partial);
}

function selectCity(val) {
  if (val == null || val === '') {
    emitPatch({ city: '' });
  } else {
    emitPatch({ city: val, location: '' });
  }
  cityMenuOpen.value = false;
  citySearch.value = '';
}

function toggleCityMenu() {
  if (locationLocksDetail.value) return;
  cityMenuOpen.value = !cityMenuOpen.value;
  if (cityMenuOpen.value) citySearch.value = '';
}

function onVerifiedChange(checked) {
  emitPatch({ verified: checked ? true : null });
}

function onFeaturedChange(checked) {
  emitPatch({ featured: checked ? true : null });
}

function onDocumentClick(e) {
  const el = cityDropdownRef.value;
  if (el && !el.contains(e.target)) cityMenuOpen.value = false;
}

function onDocumentKeydown(e) {
  if (e.key === 'Escape' && cityMenuOpen.value) cityMenuOpen.value = false;
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
.field-input:disabled {
  @apply cursor-not-allowed bg-slate-50 text-slate-500;
}
</style>
