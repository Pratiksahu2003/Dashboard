<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';

const { requireAuth } = useAuth();
const { success: showSuccess, error: showError } = useAlerts();

const optionsLoading = ref(false);
const createLoading = ref(false);
const options = ref({
    priorities: {},
    categories: {},
});
const validationError = ref('');

const createForm = ref({
    subject: '',
    message: '',
    priority: '',
    category: '',
    attachment: null,
    user_notes: '',
});

const priorityEntries = computed(() => Object.entries(options.value.priorities || {}));
const categoryEntries = computed(() => Object.entries(options.value.categories || {}));
const quillToolbar = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const fetchOptions = async () => {
    optionsLoading.value = true;
    try {
        const response = await api.get('/support-tickets/options');
        const data = response?.data || {};
        options.value = {
            priorities: data.priorities || {},
            categories: data.categories || {},
        };

        if (!createForm.value.priority) createForm.value.priority = Object.keys(options.value.priorities)[0] || '';
        if (!createForm.value.category) createForm.value.category = Object.keys(options.value.categories)[0] || '';
    } catch (error) {
        showError(error?.message || 'Unable to load ticket options.');
    } finally {
        optionsLoading.value = false;
    }
};

const onCreateFileChange = event => {
    createForm.value.attachment = event?.target?.files?.[0] || null;
};

const createPayload = () => {
    const hasAttachment = !!createForm.value.attachment;
    if (!hasAttachment) {
        return {
            data: {
                subject: createForm.value.subject,
                message: createForm.value.message,
                priority: createForm.value.priority,
                category: createForm.value.category,
                user_notes: createForm.value.user_notes || undefined,
            },
            headers: undefined,
        };
    }

    const formData = new FormData();
    formData.append('subject', createForm.value.subject);
    formData.append('message', createForm.value.message);
    formData.append('priority', createForm.value.priority);
    formData.append('category', createForm.value.category);
    if (createForm.value.user_notes) formData.append('user_notes', createForm.value.user_notes);
    formData.append('attachment', createForm.value.attachment);
    return {
        data: formData,
        headers: { 'Content-Type': 'multipart/form-data' },
    };
};

const createTicket = async () => {
    validationError.value = '';

    const subject = String(createForm.value.subject || '').trim();
    const message = String(createForm.value.message || '').replace(/<[^>]*>/g, '').trim();

    if (!subject) {
        validationError.value = 'Subject is required.';
        return;
    }
    if (!message) {
        validationError.value = 'Message is required.';
        return;
    }
    if (!createForm.value.priority || !createForm.value.category) {
        validationError.value = 'Priority and category are required.';
        return;
    }

    createLoading.value = true;
    try {
        const payload = createPayload();
        const response = await api.post('/support-tickets', payload.data, payload.headers ? { headers: payload.headers } : undefined);
        const createdId = response?.data?.id;
        showSuccess('Support ticket created successfully.');

        createForm.value = {
            subject: '',
            message: '',
            priority: createForm.value.priority || '',
            category: createForm.value.category || '',
            attachment: null,
            user_notes: '',
        };

        if (createdId) {
            window.location.href = route('support-ticket-details', { supportTicket: createdId });
        }
    } catch (error) {
        showError(error?.message || 'Unable to create support ticket.');
    } finally {
        createLoading.value = false;
    }
};

onMounted(async () => {
    if (!requireAuth()) return;
    await fetchOptions();
});
</script>

<template>
    <Head title="Create Ticket" />
    <AppLayout>
        <template #breadcrumb>Create Ticket</template>

        <div class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-xl font-black text-slate-900">Create Support Ticket</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">Submit a new support issue with optional attachment.</p>
                    </div>
                    <Link :href="route('support-tickets')" class="btn-soft">View Existing Tickets</Link>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="max-w-3xl space-y-3">
                    <label class="field-wrap">
                        <span class="field-title">Subject *</span>
                        <input v-model="createForm.subject" type="text" maxlength="255" class="field-input" placeholder="Enter subject" />
                    </label>
                    <label class="field-wrap">
                        <span class="field-title">Message *</span>
                        <QuillEditor
                            v-model:content="createForm.message"
                            content-type="html"
                            theme="snow"
                            :toolbar="quillToolbar"
                            placeholder="Describe your issue"
                            class="quill-editor"
                        />
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="field-wrap">
                            <span class="field-title">Priority *</span>
                            <select v-model="createForm.priority" class="field-input bg-white">
                                <option value="" disabled>Select priority</option>
                                <option v-for="[value, label] in priorityEntries" :key="`priority-create-${value}`" :value="value">{{ label }}</option>
                            </select>
                        </label>
                        <label class="field-wrap">
                            <span class="field-title">Category *</span>
                            <select v-model="createForm.category" class="field-input bg-white">
                                <option value="" disabled>Select category</option>
                                <option v-for="[value, label] in categoryEntries" :key="`category-create-${value}`" :value="value">{{ label }}</option>
                            </select>
                        </label>
                    </div>
                    <label class="field-wrap">
                        <span class="field-title">Attachment (Optional)</span>
                        <input type="file" class="field-input" @change="onCreateFileChange" />
                        <p class="text-[11px] font-semibold text-slate-500">Allowed: jpg, jpeg, png, pdf, doc, docx, txt, zip (max 10MB).</p>
                    </label>
                    <label class="field-wrap">
                        <span class="field-title">User Notes (Optional)</span>
                        <textarea v-model="createForm.user_notes" rows="3" maxlength="1000" class="field-input" placeholder="Additional notes"></textarea>
                    </label>

                    <p v-if="validationError" class="text-xs font-semibold text-rose-600">{{ validationError }}</p>
                    <button type="button" class="btn-dark" :disabled="createLoading || optionsLoading" @click="createTicket">
                        {{ createLoading ? 'Creating...' : 'Create Ticket' }}
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.field-wrap { display: flex; flex-direction: column; gap: 0.35rem; }
.field-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: rgb(71 85 105); }
.field-input { border-radius: 0.5rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 600; color: rgb(51 65 85); }
.field-input:focus { outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); border-color: rgb(59 130 246); }
.btn-dark, .btn-soft { border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900; padding: 0.45rem 0.9rem; transition: 150ms ease; }
.btn-dark { color: #fff; background: rgb(15 23 42); border: 1px solid rgb(15 23 42); }
.btn-dark:hover { background: rgb(30 41 59); }
.btn-soft { color: rgb(51 65 85); background: #fff; border: 1px solid rgb(203 213 225); }
.btn-soft:hover { background: rgb(248 250 252); }
button:disabled { opacity: 0.65; cursor: not-allowed; }
.quill-editor :deep(.ql-container) { min-height: 150px; font-size: 0.875rem; }
.quill-editor :deep(.ql-toolbar) { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; }
.quill-editor :deep(.ql-container) { border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; }
</style>
