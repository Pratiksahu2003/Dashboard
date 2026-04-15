<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useLeadApi } from '@/composables/useLeadApi';
import { useAlerts } from '@/composables/useAlerts';
import { getLoginUrl } from '@/utils/authRedirect';

const props = defineProps({
  /** Profile owner (tutor) user id → sent as `lead_owner_id` per LeadCreateApi.md */
  ownerUserId: { type: Number, required: true },
  /** Signed-in viewer id (creator). Sent as `user_id` in the JSON body when set; API also records the session user. */
  authUserId: { type: Number, default: null },
  teacherName: { type: String, default: '' },
  /** From account — used as lead `name` (no manual field). */
  viewerName: { type: String, default: '' },
  viewerEmail: { type: String, default: '' },
  /** From account — required by API for `phone`. */
  viewerPhone: { type: String, default: '' },
  defaultLocation: { type: String, default: '' },
  defaultSubject: { type: String, default: '' },
  /** When true, minimal chrome for use inside a dialog (no large title / hairline). */
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['created']);

const { createLead } = useLeadApi();
const { success: showSuccess, error: showError } = useAlerts();
const loginUrl = getLoginUrl();

const isSubmitting = ref(false);

const typeOptions = ['student', 'parent', 'institute', 'teacher'];
const sourceOptions = ['website', 'social_media', 'referral', 'advertisement', 'direct'];
const priorityOptions = ['low', 'medium', 'high', 'urgent'];

const form = ref({
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

const resolvedName = computed(() => props.viewerName?.trim() || '');
const resolvedPhone = computed(() => props.viewerPhone?.trim() || '');
const resolvedEmail = computed(() => props.viewerEmail?.trim() || '');

const profileReadyForLead = computed(() => resolvedName.value.length > 0 && resolvedPhone.value.length > 0);

function applyPrefills() {
  if (props.defaultLocation) form.value.location = props.defaultLocation;
  if (props.defaultSubject) form.value.subject_interest = props.defaultSubject;
}

function resetForm() {
  form.value = {
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
  () => [props.defaultLocation, props.defaultSubject],
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
  if (!resolvedName.value) {
    showError('Your account needs a display name. Update your profile and try again.', 'Lead');
    return;
  }
  if (!resolvedPhone.value) {
    showError('Add a phone number to your profile to request contact.', 'Lead');
    return;
  }

  const ownerId = Number(props.ownerUserId);
  if (!Number.isFinite(ownerId) || ownerId <= 0) {
    showError('Invalid tutor reference.', 'Lead');
    return;
  }

  const creatorId = Number(props.authUserId);
  const payload = {
    name: resolvedName.value,
    phone: resolvedPhone.value,
    lead_owner_id: ownerId,
    ...(Number.isFinite(creatorId) && creatorId > 0 ? { user_id: creatorId } : {}),
    ...(resolvedEmail.value ? { email: resolvedEmail.value } : {}),
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
    :class="[
      'relative overflow-hidden bg-white',
      compact
        ? 'rounded-none border-0 shadow-none'
        : 'rounded-2xl border border-slate-200/90 shadow-[0_20px_50px_-24px_rgba(15,23,42,0.25)] sm:rounded-3xl',
    ]"
    data-testid="create-lead-form"
  >
    <div
      v-if="!compact"
      class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-fuchsia-500"
      aria-hidden="true"
    />
    <div :class="compact ? 'relative' : 'relative p-6 sm:p-8'">
      <div v-if="!compact" class="mb-8">
        <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Request contact</h2>
        <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
          Your name, phone, and email are taken from your signed-in account and shared with
          <span class="font-semibold text-slate-800">{{ teacherName || 'this tutor' }}</span>
          when you send this request.
        </p>
      </div>
      <p v-else class="mb-6 text-sm leading-relaxed text-slate-600">
        We'll use your account <span class="font-medium text-slate-800">name, phone, and email</span> for this request to
        <span class="font-semibold text-slate-800">{{ teacherName || 'this tutor' }}</span>.
      </p>

      <div
        v-if="!authUserId"
        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/90 px-5 py-8 text-center"
      >
        <p class="text-sm text-slate-600">
          <Link :href="loginUrl" class="font-semibold text-indigo-600 underline-offset-2 hover:text-violet-600 hover:underline">
            Sign in
          </Link>
          to send a contact request using your profile details.
        </p>
      </div>

      <div
        v-else-if="isSelfLead"
        class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50 to-orange-50/50 px-5 py-4 text-sm text-amber-950"
      >
        You're on your own profile — leads are for learners contacting tutors.
      </div>

      <template v-else>
        <div
          v-if="!profileReadyForLead"
          class="mb-6 rounded-2xl border border-rose-100 bg-rose-50/80 px-5 py-4 text-sm text-rose-900"
        >
                   <p class="font-medium">Complete your profile first</p>
          <ul class="mt-2 list-inside list-disc space-y-1 text-rose-800/90">
            <li v-if="!resolvedName">Add your name.</li>
            <li v-if="!resolvedPhone">Add a phone number.</li>
          </ul>
          <p class="mt-2 text-sm text-rose-800/80">Both are required for the tutor to reach you.</p>
          <Link
            href="/profile"
            class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-rose-800 underline-offset-2 hover:underline"
          >
            Open profile settings
            <span aria-hidden="true">→</span>
          </Link>
        </div>

        <form v-else class="space-y-6" @submit.prevent="submit">
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="block">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">I am a</span>
              <select
                v-model="form.type"
                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              >
                <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">How you found them</span>
              <select
                v-model="form.source"
                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              >
                <option v-for="s in sourceOptions" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">Subject interest</span>
              <input
                v-model="form.subject_interest"
                type="text"
                maxlength="255"
                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                placeholder="e.g. Mathematics"
              />
            </label>
            <label class="block">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">Grade / level</span>
              <input
                v-model="form.grade_level"
                type="text"
                maxlength="100"
                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                placeholder="Optional"
              />
            </label>
            <label class="block sm:col-span-2">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">Location</span>
              <input
                v-model="form.location"
                type="text"
                maxlength="255"
                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                placeholder="City or area"
              />
            </label>
            <label class="block sm:col-span-2">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">Message</span>
              <textarea
                v-model="form.message"
                rows="4"
                maxlength="5000"
                class="w-full resize-y rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                placeholder="Goals, schedule, online or in-person — anything that helps the tutor respond."
              />
            </label>
            <label class="block sm:col-span-2">
              <span class="mb-1.5 block text-sm font-medium text-slate-700">Priority</span>
              <select
                v-model="form.priority"
                class="w-full max-w-xs rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              >
                <option v-for="p in priorityOptions" :key="p" :value="p">{{ p }}</option>
              </select>
            </label>
          </div>

          <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <button
              type="submit"
              class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:min-w-[200px]"
              :disabled="isSubmitting"
            >
              {{ isSubmitting ? 'Sending…' : 'Send request' }}
            </button>
            <Link
              href="/leads"
              class="text-center text-sm font-medium text-indigo-600 underline-offset-2 hover:text-violet-600 hover:underline sm:text-left"
            >
              View my leads
            </Link>
          </div>
        </form>
      </template>
    </div>
  </section>
</template>
