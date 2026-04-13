<script setup>
import { ref, onMounted, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import { listTeachers, getOptions, teacherProfilePath } from '@/services/teacherApi';

// ─── Reactive state ───────────────────────────────────────────────────────────

const teachers = ref([]);
const pagination = ref({ current_page: 1, per_page: 12, total: 0, last_page: 1 });
const options = ref({});
const filters = ref({
  location: '',
  subject_id: null,
  teaching_mode: null,
  availability: null,
  hourly_rate_range: null,
  experience: null,
});
const search = ref('');
const sort = ref('created_at');
const page = ref(1);
const loading = ref(false);
const optionsLoading = ref(false);
const error = ref(null);
const optionsError = ref(null);

// ─── fetchTeachers ────────────────────────────────────────────────────────────

async function fetchTeachers(overrides = {}) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      per_page: 12,
      sort: sort.value,
      order: 'desc',
      page: page.value,
      search: search.value || undefined,
      ...filters.value,
      ...overrides,
    };
    // Remove null/undefined/empty values
    Object.keys(params).forEach(k => {
      if (params[k] === null || params[k] === undefined || params[k] === '') delete params[k];
    });
    const result = await listTeachers(params);
    teachers.value = result.teachers ?? [];
    pagination.value = result.pagination ?? { current_page: 1, per_page: 12, total: 0, last_page: 1 };
  } catch (e) {
    error.value = e.message || 'Failed to load teachers.';
    // Do NOT clear teachers.value — preserve existing list on error
  } finally {
    loading.value = false;
  }
}

// ─── Options loading ──────────────────────────────────────────────────────────

async function loadOptions() {
  optionsLoading.value = true;
  optionsError.value = null;
  try {
    options.value = await getOptions();
  } catch (e) {
    optionsError.value = e.message || 'Failed to load filter options.';
    options.value = {};
  } finally {
    optionsLoading.value = false;
  }
}

// ─── Filter handlers ──────────────────────────────────────────────────────────

function onApplyFilters() {
  page.value = 1;
  fetchTeachers({ ...filters.value, page: 1 });
}

function onClearFilters() {
  filters.value = {
    location: '',
    subject_id: null,
    teaching_mode: null,
    availability: null,
    hourly_rate_range: null,
    experience: null,
  };
  search.value = '';
  sort.value = 'created_at';
  page.value = 1;
  fetchTeachers({ page: 1 });
}

// ─── Search handler ───────────────────────────────────────────────────────────

function onSearchSubmit() {
  page.value = 1;
  fetchTeachers({ search: search.value, page: 1 });
}

// ─── Sort handler ─────────────────────────────────────────────────────────────

function onSortChange() {
  page.value = 1;
  fetchTeachers({ sort: sort.value, page: 1 });
}

// ─── Pagination handler ───────────────────────────────────────────────────────

function goToPage(n) {
  page.value = n;
  fetchTeachers({ page: n });
}

// ─── Card click ───────────────────────────────────────────────────────────────

function onTeacherClick(teacher) {
  const path = teacherProfilePath(teacher);
  if (path) router.visit(path);
}

// ─── Mount ────────────────────────────────────────────────────────────────────

onMounted(() => {
  Promise.allSettled([
    loadOptions(),
    fetchTeachers({ per_page: 12, sort: 'created_at', order: 'desc' }),
  ]);
});

const teachersMetaTitle = computed(() => {
  const n = pagination.value?.total;
  if (typeof n === 'number' && n > 0) return `Find ${n.toLocaleString()} teachers | SuGanta`;
  return 'Find teachers | SuGanta';
});

const teachersMetaDescription = computed(() => {
  const n = pagination.value?.total;
  const countBit =
    typeof n === 'number' && n > 0
      ? `${n.toLocaleString()} tutors listed. `
      : '';
  return `${countBit}Search SuGanta by subject, place, teaching mode, availability, experience, and hourly budget. Open a profile to see full details and get in touch.`;
});
</script>

<template>
  <Head>
    <title>{{ teachersMetaTitle }}</title>
    <meta name="description" :content="teachersMetaDescription" />
    <meta property="og:title" :content="teachersMetaTitle" />
    <meta property="og:description" :content="teachersMetaDescription" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="teachersMetaTitle" />
    <meta name="twitter:description" :content="teachersMetaDescription" />
  </Head>

  <AppLayout>
    <div class="relative max-w-7xl mx-auto">
      <!-- subtle page backdrop -->
      <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-[2rem]">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-violet-200/40 blur-3xl"></div>
        <div class="absolute -left-20 top-40 h-64 w-64 rounded-full bg-indigo-200/35 blur-3xl"></div>
      </div>

      <!-- Page header -->
      <header class="mb-8 sm:mb-10">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600 mb-2">Tutors</p>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight">
              Find teachers
            </h1>
            <p class="mt-2 text-slate-600 max-w-xl text-sm sm:text-base leading-relaxed">
              Browse verified educators, compare experience and rates, and open a full profile in one click.
            </p>
          </div>
          <div
            v-if="pagination.total > 0 || (!loading && pagination.total === 0)"
            class="flex items-center gap-2 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-2.5 shadow-sm backdrop-blur-sm"
          >
            <span class="text-2xl font-bold tabular-nums text-slate-900">{{ pagination.total }}</span>
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500 leading-tight">listed<br>tutors</span>
          </div>
        </div>
      </header>

      <!-- Options error -->
      <div
        v-if="optionsError"
        class="mb-5 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 shadow-sm"
      >
        {{ optionsError }}
      </div>

      <!-- Filters + list: sidebar + single-column cards -->
      <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
        <!-- Left sidebar: FilterPanel -->
        <aside class="w-full lg:w-72 flex-shrink-0">
          <FilterPanel
            v-model:modelValue="filters"
            :options="options"
            :loading="optionsLoading"
            @apply="onApplyFilters"
            @clear="onClearFilters"
          />
        </aside>

        <!-- Right main area -->
        <div class="flex-1 min-w-0 space-y-5">
          <!-- Search + Sort bar -->
          <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm sm:p-2.5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch">
              <div class="flex flex-1 gap-2 min-w-0">
                <div class="relative flex-1">
                  <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                  </span>
                  <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, subject, or keyword..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    data-testid="search-input"
                    @keydown.enter="onSearchSubmit"
                  />
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500"
                  data-testid="search-submit"
                  @click="onSearchSubmit"
                >
                  Search
                </button>
              </div>

              <select
                v-model="sort"
                class="w-full sm:w-52 shrink-0 rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-8 text-sm font-medium text-slate-800 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                data-testid="sort-select"
                @change="onSortChange"
              >
              <option value="created_at">Newest</option>
              <option value="rating">Top Rated</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
              <option value="name">Name A-Z</option>
              </select>
            </div>
          </div>

          <!-- Error alert (shown with or without existing teachers) -->
          <div
            v-if="error"
            class="mb-4 rounded-2xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-sm text-red-900 shadow-sm"
            data-testid="error-alert"
          >
            {{ error }}
          </div>

          <!-- Loading skeletons (one full-width card per row) -->
          <div v-if="loading" class="flex flex-col gap-4">
            <div
              v-for="i in 12"
              :key="i"
              class="rounded-3xl border border-slate-200/60 bg-white/80 p-5 sm:p-6 animate-pulse flex flex-col sm:flex-row gap-5 shadow-sm"
              data-testid="teacher-skeleton"
            >
              <div class="w-24 h-24 rounded-full bg-slate-200 flex-shrink-0 mx-auto sm:mx-0"></div>
              <div class="flex-1 space-y-3 min-w-0">
                <div class="h-5 bg-slate-200 rounded-xl w-2/3 max-w-md mx-auto sm:mx-0"></div>
                <div class="h-3 bg-slate-100 rounded-lg w-1/3 max-w-xs mx-auto sm:mx-0"></div>
                <div class="h-3 bg-slate-100 rounded-lg w-full"></div>
                <div class="h-3 bg-slate-100 rounded-lg w-5/6"></div>
                <div class="flex gap-2 pt-2 max-w-md">
                  <div class="h-11 bg-slate-100 rounded-xl flex-1"></div>
                  <div class="h-11 bg-slate-100 rounded-xl flex-1"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div
            v-else-if="!error && teachers.length === 0"
            class="rounded-3xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-16 text-center"
            data-testid="empty-state"
          >
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-md shadow-slate-900/5 ring-1 ring-slate-200/80">
              <svg class="h-7 w-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <p class="text-lg font-semibold text-slate-800">No teachers match</p>
            <p class="text-sm mt-2 text-slate-500 max-w-sm mx-auto leading-relaxed">Try clearing filters or using a broader search — new tutors join often.</p>
          </div>

          <!-- Teacher cards: one full-width card per row -->
          <div v-else class="flex flex-col gap-5">
            <TeacherCard
              v-for="teacher in teachers"
              :key="teacher.id"
              :teacher="teacher"
              :data-testid="`teacher-card-${teacher.id}`"
              layout="row"
              @click="onTeacherClick"
            />
          </div>

          <!-- Pagination -->
          <div
            v-if="pagination.last_page > 1"
            class="mt-8 flex flex-wrap items-center justify-center gap-2"
            data-testid="pagination"
          >
            <button
              type="button"
              class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="pagination.current_page <= 1"
              @click="goToPage(pagination.current_page - 1)"
            >
              Previous
            </button>

            <button
              v-for="n in pagination.last_page"
              :key="n"
              type="button"
              class="min-w-[2.5rem] rounded-full border px-3 py-2 text-sm font-semibold transition"
              :class="n === pagination.current_page
                ? 'border-transparent bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/25'
                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
              @click="goToPage(n)"
            >
              {{ n }}
            </button>

            <button
              type="button"
              class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="pagination.current_page >= pagination.last_page"
              @click="goToPage(pagination.current_page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
