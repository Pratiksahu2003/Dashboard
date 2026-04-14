<script setup>
import { computed } from 'vue';

const props = defineProps({
  /** Institute or teacher display name (heading only). */
  profileName: { type: String, required: true },
  variant: {
    type: String,
    default: 'institute',
    validator: (v) => v === 'institute' || v === 'teacher',
  },
});

const INSTITUTE_ITEMS = [
  {
    icon: 'thumbs',
    title: 'Expert Faculty',
    description: 'Our experienced faculty provides in-depth knowledge and support.',
  },
  {
    icon: 'shield',
    title: 'Safe Environment',
    description: 'We ensure a safe and conducive learning environment for all students.',
  },
  {
    icon: 'track',
    title: 'Proven Track Record',
    description: 'High success rates and numerous student achievements.',
  },
  {
    icon: 'rupee',
    title: 'Affordable Fees',
    description: 'We offer competitive pricing to ensure education is accessible.',
  },
  {
    icon: 'coaching',
    title: 'Comprehensive Coaching',
    description: 'From foundation building to advanced strategies, we cover it all.',
  },
];

const TEACHER_ITEMS = [
  {
    icon: 'thumbs',
    title: 'Subject Expertise',
    description: 'Deep knowledge in your subjects with clear explanations and structured learning.',
  },
  {
    icon: 'shield',
    title: 'Personalized Attention',
    description: 'Support tailored to your pace, goals, and learning style.',
  },
  {
    icon: 'track',
    title: 'Proven Results',
    description: 'A track record of helping students build confidence and improve outcomes.',
  },
  {
    icon: 'rupee',
    title: 'Fair, Transparent Rates',
    description: 'Competitive pricing so quality tutoring stays within reach.',
  },
  {
    icon: 'coaching',
    title: 'Complete Support',
    description: 'From fundamentals to exam strategies—guidance at every step.',
  },
];

const items = computed(() => (props.variant === 'teacher' ? TEACHER_ITEMS : INSTITUTE_ITEMS));

const headingName = computed(() => (props.profileName?.trim() ? props.profileName.trim() : 'Us'));
</script>

<template>
  <section class="mb-8" aria-labelledby="why-choose-heading">
    <h2
      id="why-choose-heading"
      class="mb-5 text-left text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
    >
      Why Choose {{ headingName }}?
    </h2>

    <div
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-5"
    >
      <article
        v-for="(item, index) in items"
        :key="index"
        class="flex flex-col items-center rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm"
      >
        <div class="mb-3 flex h-10 w-10 shrink-0 items-center justify-center text-amber-500" aria-hidden="true">
          <!-- thumbs up -->
          <svg
            v-if="item.icon === 'thumbs'"
            class="h-9 w-9"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"
            />
          </svg>
          <!-- shield check -->
          <svg
            v-else-if="item.icon === 'shield'"
            class="h-9 w-9"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
            />
          </svg>
          <!-- users + achievement -->
          <svg
            v-else-if="item.icon === 'track'"
            class="h-9 w-9"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            />
          </svg>
          <!-- INR -->
          <span
            v-else-if="item.icon === 'rupee'"
            class="text-3xl font-bold leading-none tracking-tight"
            aria-hidden="true"
          >&#8377;</span>
          <!-- lightbulb -->
          <svg
            v-else
            class="h-9 w-9"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
            />
          </svg>
        </div>
        <h3 class="mb-2 text-base font-bold text-slate-900">
          {{ item.title }}
        </h3>
        <p class="text-sm leading-relaxed text-slate-600">
          {{ item.description }}
        </p>
      </article>
    </div>
  </section>
</template>
