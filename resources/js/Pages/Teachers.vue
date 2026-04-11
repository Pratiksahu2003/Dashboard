<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import { listTeachers, getOptions } from '@/services/teacherApi';

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
  verified: false,
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
    teachers.value = result.teachers;
    pagination.value = result.pagination;
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
    verified: false,
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

function onTeacherClick(id) {
  router.visit(`/teachers/${id}`);
}

// ─── Mount ────────────────────────────────────────────────────────────────────

onMounted(() => {
  Promise.allSettled([
    loadOptions(),
    fetchTeachers({ per_page: 12, sort: 'created_at', order: 'desc' }),
  ]);
});
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto">
      <!-- Page header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
          Find Teachers
          <span v-if="pagination.total > 0" class="text-base font-normal text-gray-500 ml-2">
            ({{ pagination.total }} total)
          </span>
          <span v-else-if="!loading" class="text-base font-normal text-gray-500 ml-2">
            ({{ pagination.total }} total)
          </span>
        </h1>
      </div>

      <!-- Options error -->
      <div v-if="optionsError" class="mb-4 rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-800">
        {{ optionsError }}
      </div>

      <!-- Two-column layout -->
      <div class="flex gap-6">
        <!-- Left sidebar: FilterPanel -->
        <aside class="w-64 flex-shrink-0">
          <FilterPanel
            v-model:modelValue="filters"
            :options="options"
            :loading="optionsLoading"
            @apply="onApplyFilters"
            @clear="onClearFilters"
          />
        </aside>

        <!-- Right main area -->
        <div class="flex-1 min-w-0">
          <!-- Search + Sort bar -->
          <div class="flex gap-3 mb-5">
            <div class="flex flex-1 gap-2">
              <input
                v-model="search"
                type="text"
                placeholder="Search teachers..."
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                data-testid="search-input"
                @keydown.enter="onSearchSubmit"
              />
              <button
                type="button"
                class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                data-testid="search-submit"
                @click="onSearchSubmit"
              >
                Search
              </button>
            </div>

            <select
              v-model="sort"
              class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
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

          <!-- Error alert (shown with or without existing teachers) -->
          <div
            v-if="error"
            class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800"
            data-testid="error-alert"
          >
            {{ error }}
          </div>

          <!-- Loading skeletons -->
          <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="i in 12"
              :key="i"
              class="bg-white rounded-xl border border-gray-100 p-4 animate-pulse"
              data-testid="teacher-skeleton"
            >
              <div class="flex gap-3 mb-3">
                <div class="w-14 h-14 rounded-full bg-gray-200"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                  <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
              </div>
              <div class="space-y-2">
                <div class="h-3 bg-gray-200 rounded"></div>
                <div class="h-3 bg-gray-200 rounded w-5/6"></div>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div
            v-else-if="!error && teachers.length === 0"
            class="text-center py-16 text-gray-500"
            data-testid="empty-state"
          >
            <p class="text-lg font-medium">No teachers found</p>
            <p class="text-sm mt-1">Try adjusting your search or filters.</p>
          </div>

          <!-- Teacher cards -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <TeacherCard
              v-for="teacher in teachers"
              :key="teacher.id"
              :teacher="teacher"
              :data-testid="`teacher-card-${teacher.id}`"
              @click="onTeacherClick"
            />
          </div>

          <!-- Pagination -->
          <div
            v-if="pagination.last_page > 1"
            class="mt-6 flex items-center justify-center gap-2"
            data-testid="pagination"
          >
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="pagination.current_page <= 1"
              @click="goToPage(pagination.current_page - 1)"
            >
              Prev
            </button>

            <button
              v-for="n in pagination.last_page"
              :key="n"
              type="button"
              class="px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors"
              :class="n === pagination.current_page
                ? 'bg-indigo-600 text-white border-indigo-600'
                : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
              @click="goToPage(n)"
            >
              {{ n }}
            </button>

            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
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
