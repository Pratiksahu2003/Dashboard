<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useLeadApi } from '@/composables/useLeadApi';
import { useAlerts } from '@/composables/useAlerts';

const props = defineProps({
  /** Profile owner (tutor) user id → sent as `lead_owner_id` per LeadCreateApi.md */
  ownerUserId: { type: Number, required: true },
  /** Signed-in viewer id (creator). Sent as `user_id` in the JSON body when set; API also records the session user. */
  authUserId: { type: Number, default: null },
  teacherName: { type: String, default: '' },
  viewerName: { type: String, default: '' },
  viewerEmail: { type: String, default: '' },
  defaultLocation: { type: String, default: '' },
  defaultSubject: { type: String, default: '' },
});

const emit = defineEmits(['created']);

const { createLead } = useLeadApi();
const { success: showSuccess, error: showError } = useAlerts();

const isSubmitting = ref(false);

const typeOptions = ['student', 'parent', 'institute', 'teacher'];
const sourceOptions = ['website', 'social_media', 'referral', 'advertisement', 'direct'];
const priorityOptions = ['low', 'medium', 'high', 'urgent'];

const form = ref({
  name: '',
  phone: '',
  email: '',
  type: 'student',
  source: 'website',
  subject_interest: '',
  grade_level: '',
  location: '',
  message: '',
  priority: 'medium',
});

const isSelfLead = computed(
  () => props.authUserId != null && Number(props.authUserId) === Number(props.ownerUserId),
);

function applyPrefills() {
  if (props.viewerName) form.value.name = props.viewerName;
  if (props.viewerEmail) form.value.email = props.viewerEmail;
  if (props.defaultLocation) form.value.location = props.defaultLocation;
  if (props.defaultSubject) form.value.subject_interest = props.defaultSubject;
}

function resetForm() {
  form.value = {
    name: '',
    phone: '',
    email: '',
    type: 'student',
    source: 'website',
    subject_interest: '',
    grade_level: '',
    location: '',
    message: '',
    priority: 'medium',
  };
  applyPrefills();
}

watch(
  () => props.ownerUserId,
  () => {
    resetForm();
  },
  { immediate: true },
);

watch(
  () => [props.viewerName, props.viewerEmail, props.defaultLocation, props.defaultSubject],
  () => applyPrefills(),
);

function parseApiError(err) {
  const errors = err?.errors;
  if (errors && typeof errors === 'object') {
    const k = Object.keys(errors)[0];
    if (k && Array.isArray(errors[k]) && errors[k][0]) return errors[k][0];
  }
  return err?.message || 'Could not create lead.';
}

function unwrapLead(raw) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  if (raw.id != null) return raw;
  const inner = raw.data;
  if (inner && typeof inner === 'object' && inner.id != null) return inner;
  return null;
}

async function submit() {
  if (!props.authUserId) {
    showError('Please sign in to create a lead.', 'Lead');
    return;
  }
  if (isSelfLead.value) {
    showError('You cannot create a lead for your own profile.', 'Lead');
    return;
  }
  const name = form.value.name?.trim();
  const phone = form.value.phone?.trim();
  if (!name || !phone) {
    showError('Name and phone are required.', 'Lead');
    return;
  }

  const ownerId = Number(props.ownerUserId);
  if (!Number.isFinite(ownerId) || ownerId <= 0) {
    showError('Invalid tutor reference.', 'Lead');
    return;
  }

  const creatorId = Number(props.authUserId);
  const payload = {
    name,
    phone,
    lead_owner_id: ownerId,
    ...(Number.isFinite(creatorId) && creatorId > 0 ? { user_id: creatorId } : {}),
    email: form.value.email?.trim() || undefined,
    type: form.value.type || undefined,
    source: form.value.source || undefined,
    subject_interest: form.value.subject_interest?.trim() || undefined,
    grade_level: form.value.grade_level?.trim() || undefined,
    location: form.value.location?.trim() || undefined,
    message: form.value.message?.trim() || undefined,
    status: 'new',
    priority: form.value.priority || undefined,
  };

  Object.keys(payload).forEach((k) => {
    if (payload[k] === '' || payload[k] === null || payload[k] === undefined) delete payload[k];
  });

  isSubmitting.value = true;
  try {
    const response = await createLead(payload);
    const created = unwrapLead(response);
    showSuccess(
      created?.lead_id ? `Lead ${created.lead_id} submitted.` : 'Lead created successfully.',
      'Lead',
    );
    emit('created', created);
    resetForm();
  } catch (err) {
    showError(parseApiError(err), 'Lead');
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <section
    class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.08)] sm:p-8"
    data-testid="create-lead-form"
  >
    <div class="mb-6">
      <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Request contact</h2>
      <p class="mt-1 text-sm text-slate-500">
        Send a lead to
        <span class="font-semibold text-slate-700">{{ teacherName || 'this tutor' }}</span>
        <template v-if="authUserId"> — signed in as user #{{ authUserId }}</template>.
        <span class="mt-0.5 block text-xs text-slate-400">Lead owner (this tutor) user id: {{ ownerUserId }}</span>
      </p>
    </div>

    <div v-if="!authUserId" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-6 text-center text-sm text-slate-600">
      <Link href="/login" class="font-semibold text-indigo-600 hover:text-violet-600">Sign in</Link>
      to submit your details and create a lead for this teacher.
    </div>

    <div v-else-if="isSelfLead" class="rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-4 text-sm text-amber-900">
      You are viewing your own profile — leads are for students contacting tutors.
    </div>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Your name *</span>
          <input
            v-model="form.name"
            type="text"
            required
            maxlength="255"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            autocomplete="name"
          />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Phone *</span>
          <input
            v-model="form.phone"
            type="tel"
            required
            maxlength="30"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            autocomplete="tel"
          />
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Email</span>
          <input
            v-model="form.email"
            type="email"
            maxlength="255"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            autocomplete="email"
          />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Type</span>
          <select
            v-model="form.type"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Source</span>
          <select
            v-model="form.source"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option v-for="s in sourceOptions" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Subject interest</span>
          <input
            v-model="form.subject_interest"
            type="text"
            maxlength="255"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Grade / level</span>
          <input
            v-model="form.grade_level"
            type="text"
            maxlength="100"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Location</span>
          <input
            v-model="form.location"
            type="text"
            maxlength="255"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Message</span>
          <textarea
            v-model="form.message"
            rows="3"
            maxlength="5000"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            placeholder="What would you like to study, timing, mode, etc."
          />
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Priority</span>
          <select
            v-model="form.priority"
            class="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option v-for="p in priorityOptions" :key="p" :value="p">{{ p }}</option>
          </select>
        </label>
      </div>

      <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
          type="submit"
          class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="isSubmitting"
        >
          {{ isSubmitting ? 'Submitting…' : 'Submit lead' }}
        </button>
        <Link
          href="/leads"
          class="text-sm font-semibold text-indigo-600 hover:text-violet-600"
        >
          View my leads
        </Link>
      </div>
    </form>
  </section>
</template>
