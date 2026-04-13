<script setup>
import { computed } from 'vue';

const props = defineProps({
  review: { type: Object, required: true },
  /** e.g. "Response from tutor" / "Response from institute" */
  replyLabel: { type: String, default: 'Response' },
});

const rev = computed(() => props.review ?? {});

const displayComment = computed(() => {
  const raw = rev.value.comment;
  if (raw == null || typeof raw !== 'string') return '';
  let s = raw.trim();
  if (s.length >= 2 && ((s.startsWith('"') && s.endsWith('"')) || (s.startsWith("'") && s.endsWith("'")))) {
    s = s.slice(1, -1);
  }
  return s;
});

function formatTs(iso) {
  if (!iso || typeof iso !== 'string') return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

const repliedAtDisplay = computed(() => formatTs(rev.value.replied_at));
const hasAvatar = computed(() => {
  const a = rev.value.reviewer?.avatar;
  return typeof a === 'string' && a.trim().length > 0;
});
</script>

<template>
  <li
    v-show="!rev.status || rev.status === 'published'"
    class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100/80"
  >
    <div class="flex flex-wrap items-start gap-3">
      <img
        v-if="hasAvatar"
        :src="rev.reviewer.avatar"
        alt=""
        class="h-11 w-11 shrink-0 rounded-full object-cover ring-2 ring-slate-100"
      />
      <div
        v-else
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 text-sm font-bold text-white ring-2 ring-slate-100"
      >
        {{ (rev.reviewer?.name || '?').slice(0, 1).toUpperCase() }}
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-semibold text-slate-900">{{ rev.reviewer?.name ?? 'Reviewer' }}</span>
          <span v-if="rev.reviewer?.id != null" class="text-xs tabular-nums text-slate-400">#{{ rev.reviewer.id }}</span>
          <span v-if="rev.is_verified" class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-800">Verified</span>
          <span class="text-xs text-slate-400">{{ rev.time_ago || rev.reviewed_at || '' }}</span>
        </div>
        <div class="mt-1 flex items-center gap-0.5">
          <svg
            v-for="i in 5"
            :key="i"
            class="h-4 w-4"
            :class="i <= (rev.rating || 0) ? 'text-amber-400' : 'text-slate-200'"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        </div>
        <h3 v-if="rev.title" class="mt-2 font-semibold text-slate-800">{{ rev.title }}</h3>
        <p v-if="displayComment" class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ displayComment }}</p>
        <div v-if="rev.tags?.length" class="mt-3 flex flex-wrap gap-1.5">
          <span
            v-for="(tag, ti) in rev.tags"
            :key="ti"
            class="rounded-lg bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800"
          >{{ tag }}</span>
        </div>
        <p v-if="rev.helpful_count != null && rev.helpful_count > 0" class="mt-2 text-xs font-medium text-slate-500">
          {{ rev.helpful_count }} found this helpful
        </p>
        <div
          v-if="rev.reply"
          class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-slate-700"
        >
          <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-indigo-600">{{ replyLabel }}</span>
          <p class="whitespace-pre-line">{{ rev.reply }}</p>
          <p v-if="repliedAtDisplay" class="mt-1 text-xs text-slate-500">{{ repliedAtDisplay }}</p>
        </div>
      </div>
    </div>
  </li>
</template>
