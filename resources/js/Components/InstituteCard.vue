<template>
  <div
    class="group relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.06),0_12px_32px_-12px_rgba(79,70,229,0.1)] transition-all duration-300 hover:border-indigo-200/60 hover:shadow-[0_20px_48px_-16px_rgba(79,70,229,0.18)]"
    data-testid="institute-card"
  >
    <div class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-indigo-500 via-violet-500 to-indigo-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
    <div
      class="flex gap-0"
      :class="layout === 'row' ? 'flex-col sm:flex-row sm:items-stretch' : 'flex-col sm:flex-row'"
    >
      <div
        class="flex flex-shrink-0 flex-col items-center justify-start border-b border-slate-100/90 bg-gradient-to-b from-indigo-50/40 via-white to-violet-50/20 sm:border-b-0 sm:border-r sm:border-slate-100/90"
        :class="layout === 'row' ? 'px-5 pb-4 pt-6 sm:w-44 sm:py-7' : 'w-full px-4 pb-4 pt-5 sm:w-32'"
      >
        <div class="relative">
          <img
            v-if="institute.logo_url && !logoError"
            :src="institute.logo_url"
            :alt="institute.name"
            :class="[
              layout === 'row' ? 'h-24 w-24 sm:h-28 sm:w-28' : 'h-20 w-20',
              'rounded-2xl object-cover shadow-lg shadow-slate-900/10 ring-4 ring-white',
            ]"
            data-testid="institute-logo"
            @error="logoError = true"
          />
          <div
            v-else
            :class="layout === 'row' ? 'h-24 w-24 sm:h-28 sm:w-28 text-2xl sm:text-3xl' : 'h-20 w-20 text-xl'"
            class="flex select-none items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 font-bold text-white shadow-lg shadow-indigo-500/30"
            data-testid="institute-initials"
          >
            {{ initials }}
          </div>
          <span
            v-if="institute.is_featured"
            class="absolute -bottom-1 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-full border-2 border-white bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm"
          >
            Featured
          </span>
        </div>
        <div v-if="typeLabel" class="mt-3 hidden text-center sm:block">
          <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Type</span>
          <span class="text-xs font-semibold leading-tight text-slate-800">{{ typeLabel }}</span>
        </div>
      </div>

      <div class="flex min-w-0 flex-1 flex-col" :class="layout === 'row' ? 'p-5 sm:p-6 sm:pl-6' : 'pb-4 pl-1 pr-4 pt-4'">
        <div class="flex flex-1 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 flex-1">
            <div class="mb-1 flex flex-wrap items-center gap-2">
              <h3
                class="text-lg font-bold leading-tight tracking-tight text-slate-900 transition-colors group-hover:text-indigo-950 sm:text-xl"
                data-testid="institute-name"
              >
                {{ institute.name }}
              </h3>
              <span
                v-if="institute.verified"
                class="inline-flex items-center gap-0.5 rounded-full border border-sky-100/80 bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-800"
                data-testid="institute-verified"
              >
                <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Verified
              </span>
            </div>

            <p v-if="typeLabel" class="mb-2 text-xs font-semibold text-indigo-600 sm:hidden">{{ typeLabel }}</p>

            <div v-if="cityState" class="mb-3 flex items-center gap-1.5 text-sm text-slate-600" data-testid="institute-location">
              <svg class="h-4 w-4 flex-shrink-0 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <span class="truncate">{{ cityState }}</span>
            </div>

            <p
              class="mb-3 line-clamp-3 text-sm leading-relaxed"
              :class="hasDescription ? 'text-slate-600' : 'text-slate-500'"
              data-testid="institute-description"
            >
              {{ displayDescription }}
            </p>

            <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600">
              <span v-if="institute.institute_category" class="font-medium">{{ institute.institute_category }}</span>
              <span v-if="institute.establishment_year" class="flex items-center gap-1">
                <span class="text-slate-400">Est.</span>
                <span class="font-semibold text-slate-800">{{ institute.establishment_year }}</span>
              </span>
 </div>

            <div v-if="chipList.length" class="flex flex-wrap gap-1.5">
              <span
                v-for="(c, i) in chipList"
                :key="`${c}-${i}`"
                class="rounded-lg border border-indigo-100/60 bg-indigo-50/90 px-2.5 py-1 text-xs font-semibold text-indigo-900"
              >
                {{ c }}
              </span>
              <span v-if="chipOverflow > 0" class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                +{{ chipOverflow }} more
              </span>
            </div>
          </div>

          <div
            class="flex flex-row gap-3 border-t border-slate-100 pt-4 sm:min-w-[10rem] sm:flex-col sm:border-t-0 sm:pt-0 sm:pl-2 sm:text-right"
          >
            <div class="flex-1 space-y-2 text-left sm:flex-none sm:text-right">
              <div v-if="institute.total_students">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Students</div>
                <div class="text-sm font-bold tabular-nums text-slate-900">{{ institute.total_students }}</div>
              </div>
              <div v-if="institute.total_teachers">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Teachers</div>
                <div class="text-sm font-semibold text-slate-800">{{ institute.total_teachers }}</div>
              </div>
              <div v-if="institute.total_branches != null && institute.total_branches > 0">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Branches</div>
                <div class="text-sm font-semibold text-slate-800">{{ institute.total_branches }}</div>
              </div>
            </div>

            <div class="flex w-full gap-2 sm:w-auto sm:flex-col">
              <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 sm:w-full sm:min-w-[140px]"
                @click.stop="$emit('click', institute)"
              >
                View profile
              </button>
              <button
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 sm:w-full sm:min-w-[140px]"
                @click.stop="$emit('contact', institute)"
              >
                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
  institute: { type: Object, required: true },
  layout: { type: String, default: 'default' },
});

defineEmits(['click', 'contact']);

const logoError = ref(false);

const initials = computed(() => {
  if (!props.institute.name) return '?';
  return props.institute.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
});

const cityState = computed(() => {
  const parts = [props.institute.city, props.institute.state].filter(Boolean);
  return parts.join(', ');
});

const typeLabel = computed(() => props.institute.institute_type ?? '');

const hasDescription = computed(() => {
  const d = props.institute.description;
  return d != null && String(d).trim() !== '';
});

const DEFAULT_DESC =
  'Discover programs, facilities, and contact options on SuGanta. Open the full profile for complete details.';

const displayDescription = computed(() =>
  hasDescription.value ? String(props.institute.description).trim() : DEFAULT_DESC,
);

const allChips = computed(() => {
  const courses = Array.isArray(props.institute.courses_offered) ? props.institute.courses_offered : [];
  const fac = Array.isArray(props.institute.facilities) ? props.institute.facilities : [];
  const spec = Array.isArray(props.institute.specializations) ? props.institute.specializations : [];
  return [...courses.slice(0, 2), ...spec.slice(0, 2), ...fac.slice(0, 2)].filter(Boolean);
});

const chipList = computed(() => allChips.value.slice(0, 5));

const chipOverflow = computed(() => {
  const t = allChips.value.length;
  return t > 5 ? t - 5 : 0;
});
</script>
