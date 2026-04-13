<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InstituteCard from '@/Components/InstituteCard.vue';
import InstituteFilterPanel from '@/Components/InstituteFilterPanel.vue';
import CreateLeadForm from '@/Components/CreateLeadForm.vue';
import {
  listInstitutes,
  getInstituteOptions,
  instituteProfilePath,
  resolveInstituteUserId,
} from '@/services/instituteApi';

const inertiaPage = usePage();

const institutes = ref([]);
const pagination = ref({ current_page: 1, per_page: 12, total: 0, last_page: 1 });
const options = ref({});
const filters = ref({
  location: '',
  city: '',
  state: '',
  pincode: '',
  institute_type: null,
  institute_category: null,
  establishment_year_range: null,
  total_students_range: null,
  total_teachers_range: null,
  verified: null,
  featured: null,
});
const search = ref('');
const orderBy = ref('recent');
const page = ref(1);
const loading = ref(false);
const optionsLoading = ref(false);
const error = ref(null);
const optionsError = ref(null);

const leadModalInstitute = ref(null);

const authUser = computed(() => inertiaPage.props?.auth?.user ?? null);
const authUserIdNumber = computed(() => {
  const id = authUser.value?.id;
  const n = Number(id);
  return Number.isFinite(n) && n > 0 ? n : null;
});
const viewerLeadName = computed(() => {
  const u = authUser.value;
  if (!u) return '';
  const fn = u.first_name ?? '';
  const ln = u.last_name ?? '';
  const joined = `${fn} ${ln}`.trim();
  if (joined) return joined;
  return String(u.name ?? '').trim();
});
const viewerLeadEmail = computed(() => String(authUser.value?.email ?? '').trim());
const viewerLeadPhone = computed(() => String(authUser.value?.phone ?? '').trim());

const leadModalOwnerId = computed(() => {
  if (!leadModalInstitute.value) return null;
  const resolved = resolveInstituteUserId(leadModalInstitute.value);
  if (resolved != null) return resolved;
  const n = Number(leadModalInstitute.value.id);
  return Number.isFinite(n) && n > 0 ? n : null;
});
const leadModalInstituteName = computed(() => String(leadModalInstitute.value?.name ?? ''));
const leadModalLocation = computed(() => {
  const x = leadModalInstitute.value;
  if (!x) return '';
  return [x.city, x.state].filter(Boolean).join(', ');
});
const leadModalSubject = computed(() => {
  const x = leadModalInstitute.value;
  const courses = x?.courses_offered;
  if (Array.isArray(courses) && courses[0]) return String(courses[0]);
  return '';
});

function openLeadModal(institute) {
  leadModalInstitute.value = institute;
}

function closeLeadModal() {
  leadModalInstitute.value = null;
}

function onLeadModalKeydown(e) {
  if (e.key === 'Escape') closeLeadModal();
}

watch(leadModalInstitute, (t) => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = t ? 'hidden' : '';
  if (t) {
    document.addEventListener('keydown', onLeadModalKeydown);
  } else {
    document.removeEventListener('keydown', onLeadModalKeydown);
  }
});

onUnmounted(() => {
  if (typeof document === 'undefined') return;
  document.removeEventListener('keydown', onLeadModalKeydown);
  document.body.style.overflow = '';
});

async function fetchInstitutes(overrides = {}) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      per_page: 12,
      order_by: orderBy.value,
      page: page.value,
      search: search.value || undefined,
      ...filters.value,
      ...overrides,
    };
    Object.keys(params).forEach((k) => {
      if (params[k] === null || params[k] === undefined || params[k] === '') delete params[k];
    });
    const result = await listInstitutes(params);
    institutes.value = result.institutes ?? [];
    pagination.value = result.pagination ?? { current_page: 1, per_page: 12, total: 0, last_page: 1 };
  } catch (e) {
    error.value = e.message || 'Failed to load institutes.';
  } finally {
    loading.value = false;
  }
}

async function loadOptions() {
  optionsLoading.value = true;
  optionsError.value = null;
  try {
    options.value = await getInstituteOptions();
  } catch (e) {
    optionsError.value = e.message || 'Failed to load filter options.';
    options.value = {};
  } finally {
    optionsLoading.value = false;
  }
}

function onApplyFilters() {
  page.value = 1;
  fetchInstitutes({ page: 1 });
}

function onClearFilters() {
  filters.value = {
    location: '',
    city: '',
    state: '',
    pincode: '',
    institute_type: null,
    institute_category: null,
    establishment_year_range: null,
    total_students_range: null,
    total_teachers_range: null,
    verified: null,
    featured: null,
  };
  search.value = '';
  orderBy.value = 'recent';
  page.value = 1;
  fetchInstitutes({ page: 1 });
}

function onSearchSubmit() {
  page.value = 1;
  fetchInstitutes({ search: search.value, page: 1 });
}

function onSortChange() {
  page.value = 1;
  fetchInstitutes({ order_by: orderBy.value, page: 1 });
}

function goToPage(n) {
  page.value = n;
  fetchInstitutes({ page: n });
}

function onInstituteClick(institute) {
  const path = instituteProfilePath(institute);
  if (path) router.visit(path);
}

onMounted(() => {
  Promise.allSettled([loadOptions(), fetchInstitutes({ per_page: 12, order_by: 'recent' })]);
});

const institutesMetaTitle = computed(() => {
  const n = pagination.value?.total;
  if (typeof n === 'number' && n > 0) return `Find ${n.toLocaleString()} institutes | SuGanta`;
  return 'Find institutes | SuGanta';
});

const institutesMetaDescription = computed(() => {
  const n = pagination.value?.total;
  const countBit =
    typeof n === 'number' && n > 0 ? `${n.toLocaleString()} institutes listed. ` : '';
  return `${countBit}Search SuGanta for schools, colleges, and coaching centres by location, type, size, and more.`;
});
</script>

<template>
  <Head>
    <title>{{ institutesMetaTitle }}</title>
    <meta name="description" :content="institutesMetaDescription" />
    <meta property="og:title" :content="institutesMetaTitle" />
    <meta property="og:description" :content="institutesMetaDescription" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="institutesMetaTitle" />
    <meta name="twitter:description" :content="institutesMetaDescription" />
  </Head>

  <AppLayout>
    <div class="relative mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col">
      <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-[2rem]">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-violet-200/40 blur-3xl"></div>
        <div class="absolute -left-20 top-40 h-64 w-64 rounded-full bg-indigo-200/35 blur-3xl"></div>
      </div>

      <header class="mb-8 shrink-0 sm:mb-10">
        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Directory</p>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
              Find institutes
            </h1>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 sm:text-base">
              Browse verified schools, colleges, and learning centres — compare programs and reach out in one click.
            </p>
          </div>
          <div
            v-if="pagination.total > 0 || (!loading && pagination.total === 0)"
            class="flex items-center gap-2 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-2.5 shadow-sm backdrop-blur-sm"
          >
            <span class="text-2xl font-bold tabular-nums text-slate-900">{{ pagination.total }}</span>
            <span class="text-xs font-medium uppercase leading-tight tracking-wide text-slate-500">listed<br>institutes</span>
          </div>
        </div>
      </header>

      <div
        v-if="optionsError"
        class="mb-5 shrink-0 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 shadow-sm"
      >
        {{ optionsError }}
      </div>

      <div class="flex min-h-0 flex-col gap-6 lg:flex-1 lg:flex-row lg:gap-8">
        <aside class="flex w-full shrink-0 flex-col lg:w-72 lg:min-h-0 lg:overflow-hidden">
          <InstituteFilterPanel
            v-model="filters"
            :options="options"
            :loading="optionsLoading"
            @apply="onApplyFilters"
            @clear="onClearFilters"
          />
        </aside>

        <div class="scrollbar-none min-h-0 min-w-0 flex-1 space-y-5 lg:overflow-y-auto lg:overscroll-y-contain">
          <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm sm:p-2.5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch">
              <div class="flex min-w-0 flex-1 gap-2">
                <div class="relative flex-1">
                  <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                  </span>
                  <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or description…"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    data-testid="institutes-search-input"
                    @keydown.enter="onSearchSubmit"
                  />
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500"
                  data-testid="institutes-search-submit"
                  @click="onSearchSubmit"
                >
                  Search
                </button>
              </div>

              <select
                v-model="orderBy"
                class="w-full shrink-0 rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-8 text-sm font-medium text-slate-800 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 sm:w-56"
                data-testid="institutes-sort-select"
                @change="onSortChange"
              >
                <option value="recent">Recently joined</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
                <option value="established_asc">Oldest first</option>
                <option value="established_desc">Newest established</option>
                <option value="students_asc">Fewest students</option>
                <option value="students_desc">Most students</option>
              </select>
            </div>
          </div>

          <div
            v-if="error"
            class="mb-4 rounded-2xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-sm text-red-900 shadow-sm"
            data-testid="institutes-error-alert"
          >
            {{ error }}
          </div>

          <div v-if="loading" class="flex flex-col gap-4">
            <div
              v-for="i in 12"
              :key="i"
              class="flex animate-pulse flex-col gap-5 rounded-3xl border border-slate-200/60 bg-white/80 p-5 shadow-sm sm:flex-row sm:p-6"
              data-testid="institute-skeleton"
            >
              <div class="mx-auto h-24 w-24 flex-shrink-0 rounded-2xl bg-slate-200 sm:mx-0"></div>
              <div class="min-w-0 flex-1 space-y-3">
                <div class="mx-auto h-5 w-2/3 max-w-md rounded-xl bg-slate-200 sm:mx-0"></div>
                <div class="mx-auto h-3 w-1/3 max-w-xs rounded-lg bg-slate-100 sm:mx-0"></div>
                <div class="h-3 w-full rounded-lg bg-slate-100"></div>
                <div class="flex max-w-md gap-2 pt-2">
                  <div class="h-11 flex-1 rounded-xl bg-slate-100"></div>
                  <div class="h-11 flex-1 rounded-xl bg-slate-100"></div>
                </div>
              </div>
            </div>
          </div>

          <div
            v-else-if="!error && institutes.length === 0"
            class="rounded-3xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-16 text-center"
            data-testid="institutes-empty-state"
          >
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-md shadow-slate-900/5 ring-1 ring-slate-200/80">
              <svg class="h-7 w-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <p class="text-lg font-semibold text-slate-800">No institutes match</p>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">Try clearing filters or broadening your search.</p>
          </div>

          <div v-else class="flex flex-col gap-5">
            <InstituteCard
              v-for="inst in institutes"
              :key="inst.id"
              :institute="inst"
              layout="row"
              :data-testid="`institute-card-${inst.id}`"
              @click="onInstituteClick"
              @contact="openLeadModal"
            />
          </div>

          <div
            v-if="pagination.last_page > 1"
            class="mt-8 flex flex-wrap items-center justify-center gap-2"
            data-testid="institutes-pagination"
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

    <Teleport to="body">
      <div
        v-if="leadModalInstitute"
        class="fixed inset-0 z-[180] flex items-end justify-center bg-slate-950/55 p-0 backdrop-blur-[2px] sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Request contact with institute"
        @click.self="closeLeadModal"
      >
        <div
          class="flex max-h-[min(92vh,880px)] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl ring-1 ring-slate-200/80 sm:max-h-[90vh] sm:rounded-3xl"
          @click.stop
        >
          <div class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-gradient-to-r from-slate-50 to-indigo-50/30 px-4 py-3 sm:px-5">
            <p class="min-w-0 truncate text-base font-bold tracking-tight text-slate-900">
              Contact {{ leadModalInstituteName || 'institute' }}
            </p>
            <button
              type="button"
              class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
              aria-label="Close"
              @click="closeLeadModal"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-5">
            <p
              v-if="!leadModalOwnerId"
              class="rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-900"
            >
              This listing cannot receive a lead right now. Try opening the full profile instead.
            </p>
            <CreateLeadForm
              v-else
              :key="leadModalOwnerId"
              compact
              :owner-user-id="leadModalOwnerId"
              :auth-user-id="authUserIdNumber"
              :teacher-name="leadModalInstituteName"
              :viewer-name="viewerLeadName"
              :viewer-email="viewerLeadEmail"
              :viewer-phone="viewerLeadPhone"
              :default-location="leadModalLocation"
              :default-subject="leadModalSubject"
              @created="closeLeadModal"
            />
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
