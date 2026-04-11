<template>
  <div
    class="group relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.06),0_12px_32px_-12px_rgba(79,70,229,0.1)] transition-all duration-300 hover:border-indigo-200/60 hover:shadow-[0_20px_48px_-16px_rgba(79,70,229,0.18)]"
    data-testid="teacher-card"
  >
    <div class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-indigo-500 via-violet-500 to-indigo-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
    <div
      class="flex gap-0"
      :class="layout === 'row' ? 'flex-col sm:flex-row sm:items-stretch' : 'flex-col sm:flex-row'"
    >
      <!-- Avatar column -->
      <div
        class="flex flex-col items-center justify-start flex-shrink-0 border-b border-slate-100/90 bg-gradient-to-b from-indigo-50/40 via-white to-violet-50/20 sm:border-b-0 sm:border-r sm:border-slate-100/90"
        :class="layout === 'row' ? 'pt-6 pb-4 px-5 sm:py-7 sm:w-44' : 'pt-5 px-4 pb-4 w-full sm:w-28'"
      >
        <div class="relative">
          <img
            v-if="teacher.avatar_url && !avatarError"
            :src="teacher.avatar_url"
            :alt="teacher.name"
            :class="[
              layout === 'row' ? 'w-24 h-24 sm:w-28 sm:h-28' : 'w-20 h-20',
                           'rounded-full object-cover shadow-lg shadow-slate-900/10 ring-4 ring-white',
            ]"
            data-testid="avatar-image"
            @error="avatarError = true"
          />
          <div
            v-else
            :class="layout === 'row' ? 'w-24 h-24 sm:w-28 sm:h-28 text-2xl sm:text-3xl' : 'w-20 h-20 text-xl'"
            class="rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold select-none shadow-lg shadow-indigo-500/30"
            data-testid="avatar-initials"
          >
            {{ initials }}
          </div>

          <span
            class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[10px] font-bold px-2.5 py-0.5 rounded-full border-2 border-white whitespace-nowrap shadow-sm"
            :class="isAvailable
              ? 'bg-emerald-500 text-white'
              : 'bg-gray-200 text-gray-700'"
          >
            {{ availabilityShort }}
          </span>
        </div>

        <div v-if="qualificationLabel" class="mt-3 text-center hidden sm:block">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Qualification</span>
          <span class="text-xs font-semibold text-slate-800 leading-tight">{{ qualificationLabel }}</span>
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0 flex flex-col" :class="layout === 'row' ? 'p-5 sm:p-6 sm:pl-6' : 'pt-4 pr-4 pb-4 pl-1'">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between flex-1">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <h3
                class="font-bold text-slate-900 text-lg sm:text-xl tracking-tight leading-tight group-hover:text-indigo-950 transition-colors"
                data-testid="teacher-name"
              >
                {{ teacher.name }}
              </h3>
              <span
                v-if="teacher.verified"
                class="inline-flex items-center gap-0.5 text-[10px] font-bold text-sky-800 bg-sky-50 border border-sky-100/80 px-2 py-0.5 rounded-full"
                data-testid="verified-badge"
              >
                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Verified
              </span>
            </div>

            <p v-if="qualificationLabel" class="text-xs font-semibold text-indigo-600 sm:hidden mb-2">
              {{ qualificationLabel }}
            </p>

            <div v-if="cityState" class="flex items-center gap-1.5 text-sm text-slate-600 mb-3" data-testid="city-state">
              <svg class="w-4 h-4 flex-shrink-0 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <span class="truncate">{{ cityState }}</span>
            </div>

            <!-- Bio: real snippet or platform default on every card -->
            <p
              class="text-sm leading-relaxed mb-3 line-clamp-3"
              :class="bioSnippet ? 'text-slate-600' : 'text-slate-500'"
              data-testid="teacher-bio"
            >
              {{ displayBio }}
            </p>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600 mb-3">
              <span v-if="teacher.experience_years?.label" class="flex items-center gap-1.5" data-testid="experience-years">
                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="font-semibold text-slate-800">{{ teacher.experience_years.label }}</span>
              </span>

              <span v-if="teacher.teaching_mode?.label" class="flex items-center gap-1.5" data-testid="teaching-mode">
                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="font-medium">{{ teacher.teaching_mode.label }}</span>
              </span>

              <span v-if="teacher.availability_status?.label" class="flex items-center gap-1.5" data-testid="availability-status">
                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium">{{ teacher.availability_status.label }}</span>
              </span>
            </div>

            <div v-if="visibleSubjects.length" class="flex flex-wrap gap-1.5">
              <span
                v-for="subject in visibleSubjects"
                :key="subject.id"
                class="text-xs font-semibold bg-indigo-50/90 text-indigo-900 border border-indigo-100/60 px-2.5 py-1 rounded-lg"
                data-testid="subject-tag"
              >
                {{ subject.name }}
              </span>
              <span
                v-if="subjectOverflow > 0"
                class="text-xs font-semibold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg"
                data-testid="subject-overflow"
              >
                +{{ subjectOverflow }} more
              </span>
            </div>
          </div>

          <!-- Rate + actions (right stack on row layout) -->
          <div
            class="flex flex-row sm:flex-col gap-3 sm:items-end sm:text-right sm:min-w-[10rem] border-t sm:border-t-0 border-slate-100 pt-4 sm:pt-0 sm:pl-2"
          >
            <div v-if="hourlyRateDisplay" class="flex-1 sm:flex-none text-left sm:text-right" data-testid="hourly-rate">
              <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">
                {{ hourlyRateDisplay.kind === 'monthly_range' ? 'Monthly' : 'Hourly' }}
              </div>
              <div class="text-lg font-bold leading-tight text-indigo-600 sm:text-xl">
                <template v-if="hourlyRateDisplay.kind === 'hourly_fixed'">
                  <span class="tabular-nums">Rs. {{ hourlyRateDisplay.value }}</span>
                </template>
                <template v-else>
                  {{ hourlyRateDisplay.value }}
                </template>
              </div>
              <div v-if="hourlyRateDisplay.kind === 'hourly_fixed'" class="text-xs font-medium text-slate-400">per hour</div>
            </div>
            <div v-else class="flex-1 sm:flex-none text-left sm:text-right text-sm text-slate-400 italic">
              Rate on request
            </div>

            <div class="flex sm:flex-col gap-2 w-full sm:w-auto">
              <button
                type="button"
                class="flex-1 sm:w-full sm:min-w-[140px] rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                @click.stop="$emit('click', teacher)"
              >
                View profile
              </button>
              <button
                type="button"
                class="flex-1 sm:w-full sm:min-w-[140px] flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500"
                @click.stop="$emit('click', teacher)"
              >
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Contact
              </button>
            </div>
          </div>
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
  /** 'row' = full-width listing card; default compact */
  layout: {
    type: String,
    default: 'default',
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

const qualificationLabel = computed(() => props.teacher.qualification?.label ?? '');

const bioSnippet = computed(() => {
  const raw = props.teacher.bio ?? props.teacher.profile?.bio;
  if (raw == null || String(raw).trim() === '') return null;
  const text = String(raw)
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return text || null;
});

/** Shown when the API sends no bio so every card still has helpful copy */
const DEFAULT_CARD_BIO =
  'Professional educator on SuGanta offering personalized support. Open the full profile for subjects, experience, rates, and contact details.';

const displayBio = computed(() => bioSnippet.value || DEFAULT_CARD_BIO);

const hourlyRateDisplay = computed(() => {
  const t = props.teacher;
  const fixed = t.hourly_rate;
  if (fixed != null && fixed !== '') {
    return { kind: 'hourly_fixed', value: fixed };
  }
  const hourlyRange =
    t.hourly_rate_range?.label
    ?? t.teaching?.hourly_rate_range?.label
    ?? null;
  if (hourlyRange) {
    return { kind: 'hourly_range', value: hourlyRange };
  }
  const monthlyRange =
    t.monthly_rate_range?.label
    ?? t.teaching?.monthly_rate_range?.label
    ?? null;
  if (monthlyRange) {
    return { kind: 'monthly_range', value: monthlyRange };
  }
  return null;
});

const visibleSubjects = computed(() => (props.teacher.subjects ?? []).slice(0, 4));

const subjectOverflow = computed(() => {
  const total = (props.teacher.subjects ?? []).length;
  return total > 4 ? total - 4 : 0;
});

const isAvailable = computed(() =>
  props.teacher.availability_status?.label?.toLowerCase().includes('available') ?? false
);

const availabilityShort = computed(() => {
  const label = props.teacher.availability_status?.label ?? '';
  if (isAvailable.value) return 'Available';
  if (label) return label.split(' ')[0] || 'Status';
  return '—';
});
</script>
