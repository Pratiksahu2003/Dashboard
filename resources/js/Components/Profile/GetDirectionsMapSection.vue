<script setup>
import { computed } from 'vue';

const props = defineProps({
  profileName: { type: String, required: true },
  /** Prefer GPS when both are valid numbers. */
  latitude: { type: [Number, String], default: null },
  longitude: { type: [Number, String], default: null },
  /**
   * When lat/lng are missing, map uses this search query (address, city, etc.).
   * Google will geocode the string in the embed.
   */
  placeQuery: { type: String, default: '' },
  phone: { type: String, default: '' },
  contactPerson: { type: String, default: '' },
  address: { type: String, default: '' },
});

const latNum = computed(() => Number(props.latitude));
const lngNum = computed(() => Number(props.longitude));

const validCoords = computed(() => {
  const la = latNum.value;
  const lo = lngNum.value;
  if (!Number.isFinite(la) || !Number.isFinite(lo)) return false;
  return la >= -90 && la <= 90 && lo >= -180 && lo <= 180;
});

const trimmedPlace = computed(() => String(props.placeQuery ?? '').trim());

const hasPlaceQuery = computed(() => trimmedPlace.value.length >= 3);

/** Show section when we can build an embed from coords or location text. */
const canShowMap = computed(() => validCoords.value || hasPlaceQuery.value);

/** Google Maps embed (no API key). */
const embedUrl = computed(() => {
  if (validCoords.value) {
    const q = `${latNum.value},${lngNum.value}`;
    return `https://maps.google.com/maps?q=${encodeURIComponent(q)}&z=15&output=embed`;
  }
  if (hasPlaceQuery.value) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(trimmedPlace.value)}&z=15&output=embed`;
  }
  return '';
});

const openMapsUrl = computed(() => {
  if (validCoords.value) {
    return `https://www.google.com/maps?q=${latNum.value},${lngNum.value}`;
  }
  if (hasPlaceQuery.value) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(trimmedPlace.value)}`;
  }
  return '';
});

const headingName = computed(() => (props.profileName?.trim() ? props.profileName.trim() : 'this place'));

const showPhone = computed(() => !!props.phone?.trim());
const showPerson = computed(() => !!props.contactPerson?.trim());
const showAddress = computed(() => !!props.address?.trim());
</script>

<template>
  <section
    v-if="canShowMap && embedUrl"
    class="mb-8"
    aria-labelledby="get-directions-heading"
  >
    <h2
      id="get-directions-heading"
      class="mb-4 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
    >
      Get directions to {{ headingName }}
    </h2>

    <div class="flex flex-col gap-4 lg:relative lg:min-h-[420px]">
      <div
        class="relative order-1 min-h-[280px] w-full overflow-hidden rounded-xl bg-slate-200 shadow-md ring-1 ring-slate-200/80 sm:min-h-[360px] lg:order-none lg:min-h-[420px]"
      >
        <iframe
          :src="embedUrl"
          class="absolute inset-0 h-full w-full border-0"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Map location"
          allowfullscreen
        />
      </div>

      <div
        class="order-2 w-full shrink-0 rounded-xl border-2 border-[#d3833b] bg-[#2c3e50] p-6 shadow-xl lg:absolute lg:right-[4%] lg:top-[8%] lg:z-10 lg:max-w-sm lg:p-7"
      >
        <h3 class="mb-5 text-lg font-bold text-[#d3833b]">
          Contact Information
        </h3>
        <ul class="space-y-5 text-left">
          <li v-if="showPhone" class="flex gap-3">
            <span class="mt-0.5 shrink-0 text-[#d3833b]" aria-hidden="true">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold uppercase tracking-wide text-[#d3833b]">Contact No</p>
              <p class="mt-0.5 text-sm font-medium text-white">{{ phone }}</p>
            </div>
          </li>
          <li v-if="showPerson" class="flex gap-3">
            <span class="mt-0.5 shrink-0 text-[#d3833b]" aria-hidden="true">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold uppercase tracking-wide text-[#d3833b]">Contact Person</p>
              <p class="mt-0.5 text-sm font-medium text-white">{{ contactPerson }}</p>
            </div>
          </li>
          <li v-if="showAddress" class="flex gap-3">
            <span class="mt-0.5 shrink-0 text-[#d3833b]" aria-hidden="true">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold uppercase tracking-wide text-[#d3833b]">Address</p>
              <p class="mt-0.5 text-sm font-medium leading-relaxed text-white">{{ address }}</p>
            </div>
          </li>
        </ul>

        <a
          v-if="openMapsUrl"
          :href="openMapsUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#d3833b] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#c4732f]"
        >
          Open in Google Maps
        </a>
      </div>
    </div>
  </section>
</template>
