<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';

const props = defineProps({
    supportTicketId: { type: Number, required: true },
});

const { requireAuth, getUser } = useAuth();
const { success: showSuccess, error: showError } = useAlerts();

const authUser = ref(null);
const currentUserId = computed(() => Number(authUser.value?.id || 0));
const isAdmin = computed(() => ['admin', 'super_admin'].includes(String(authUser.value?.role || '').toLowerCase()));

const detailLoading = ref(false);
const replyLoading = ref(false);

const options = ref({ priorities: {} });
const selectedTicket = ref(null);
const validationErrors = ref({ reply: '' });

const replyForm = ref({
    message: '',
    attachment: null,
});
const replyFileInputKey = ref(0);
const quillToolbar = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const isOwnerOfSelectedTicket = computed(() => Number(selectedTicket.value?.user_id || 0) === currentUserId.value);
const canManageSelectedTicket = computed(() => !!selectedTicket.value && (isAdmin.value || isOwnerOfSelectedTicket.value));
const canReplySelectedTicket = computed(() => canManageSelectedTicket.value);

const formatDateTime = value => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString();
};

const decodeHtmlEntities = value => {
    const text = String(value || '');
    if (!text) return '';
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
};

const renderRichContent = value => {
    const decoded = decodeHtmlEntities(value);
    if (!decoded.trim()) return '';

    const parser = new DOMParser();
    const doc = parser.parseFromString(decoded, 'text/html');
    const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'A']);
    const allowedAttrs = { A: new Set(['href', 'target', 'rel']) };

    const walk = node => {
        Array.from(node.childNodes).forEach(child => {
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tag = child.tagName.toUpperCase();
                if (!allowedTags.has(tag)) {
                    const fragment = document.createDocumentFragment();
                    while (child.firstChild) fragment.appendChild(child.firstChild);
                    child.replaceWith(fragment);
                    return;
                }
                Array.from(child.attributes).forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const allowed = allowedAttrs[tag] || new Set();
                    if (!allowed.has(name)) child.removeAttribute(attr.name);
                });
                if (tag === 'A') {
                    const href = child.getAttribute('href') || '';
                    if (!/^(https?:|mailto:|tel:|#)/i.test(href)) {
                        child.removeAttribute('href');
                    } else {
                        child.setAttribute('target', '_blank');
                        child.setAttribute('rel', 'noopener noreferrer');
                    }
                }
                walk(child);
            } else if (child.nodeType === Node.COMMENT_NODE) {
                child.remove();
            }
        });
    };

    walk(doc.body);
    return doc.body.innerHTML.trim();
};

const sanitizeEditorHtml = value => {
    const raw = String(value || '');
    if (!raw.trim()) return '';

    const parser = new DOMParser();
    const doc = parser.parseFromString(raw, 'text/html');
    const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'A']);
    const allowedAttrs = { A: new Set(['href', 'target', 'rel']) };

    const walk = node => {
        Array.from(node.childNodes).forEach(child => {
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tag = child.tagName.toUpperCase();
                if (!allowedTags.has(tag)) {
                    const fragment = document.createDocumentFragment();
                    while (child.firstChild) fragment.appendChild(child.firstChild);
                    child.replaceWith(fragment);
                    return;
                }
                Array.from(child.attributes).forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const allowed = allowedAttrs[tag] || new Set();
                    if (!allowed.has(name)) child.removeAttribute(attr.name);
                });
                if (tag === 'A') {
                    const href = child.getAttribute('href') || '';
                    if (!/^(https?:|mailto:|tel:|#)/i.test(href)) {
                        child.removeAttribute('href');
                    } else {
                        child.setAttribute('target', '_blank');
                        child.setAttribute('rel', 'noopener noreferrer');
                    }
                }
                walk(child);
            } else if (child.nodeType === Node.COMMENT_NODE) {
                child.remove();
            }
        });
    };

    walk(doc.body);
    return doc.body.innerHTML.trim();
};

const badgeClass = (type, value) => {
    const key = String(value || '').toLowerCase();
    if (type === 'priority') {
        if (key === 'urgent') return 'bg-rose-50 text-rose-700 border-rose-200';
        if (key === 'high') return 'bg-amber-50 text-amber-700 border-amber-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
};

const fetchOptions = async () => {
    try {
        const response = await api.get('/support-tickets/options');
        options.value = {
            priorities: response?.data?.priorities || {},
        };
    } catch {
        options.value = { priorities: {} };
    }
};

const openTicket = async () => {
    detailLoading.value = true;
    try {
        const response = await api.get(`/support-tickets/${props.supportTicketId}`);
        selectedTicket.value = response?.data || null;
    } catch (error) {
        selectedTicket.value = null;
        showError(error?.message || 'Unable to load ticket details.');
    } finally {
        detailLoading.value = false;
    }
};

const submitReply = async () => {
    if (!selectedTicket.value?.id || !canReplySelectedTicket.value) return;
    validationErrors.value.reply = '';
    const cleanedHtml = sanitizeEditorHtml(replyForm.value.message);
    const plainMessage = String(cleanedHtml || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (!plainMessage) {
        validationErrors.value.reply = 'Reply message is required.';
        return;
    }

    replyLoading.value = true;
    try {
        const form = new FormData();
        form.append('message', cleanedHtml);
        if (replyForm.value.attachment) form.append('attachment', replyForm.value.attachment);
        await api.post(`/support-tickets/${selectedTicket.value.id}/reply`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        showSuccess('Reply added successfully.');
        replyForm.value = { message: '', attachment: null };
        replyFileInputKey.value += 1;
        await openTicket();
    } catch (error) {
        const firstError = error?.errors && typeof error.errors === 'object'
            ? Object.values(error.errors)?.[0]?.[0]
            : '';
        validationErrors.value.reply = firstError || '';
        showError(firstError || error?.message || 'Unable to add reply.');
    } finally {
        replyLoading.value = false;
    }
};

const downloadBlob = async (url, fallbackName) => {
    try {
        const response = await fetch(`${api.defaults.baseURL}${url}`, {
            method: 'GET',
            credentials: 'include',
        });
        if (response.status === 401) {
            document.dispatchEvent(new CustomEvent('app:unauthorized'));
            throw new Error('Unauthorized');
        }
        if (!response.ok) throw new Error('Download failed');
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = fallbackName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(downloadUrl);
    } catch {
        showError('Unable to download attachment.');
    }
};

const onReplyFileChange = event => {
    replyForm.value.attachment = event?.target?.files?.[0] || null;
};

onMounted(async () => {
    if (!requireAuth()) return;
    authUser.value = getUser();
    await fetchOptions();
    await openTicket();
});
</script>

<template>
    <Head title="Ticket Details" />
    <AppLayout>
        <template #breadcrumb>Ticket Details</template>

        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-black text-slate-900">Support Ticket Details</h1>
                <Link :href="route('support-tickets')" class="btn-soft">Back to Existing Tickets</Link>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5">
                <div v-if="detailLoading" class="space-y-2">
                    <div v-for="i in 5" :key="`detail-loading-${i}`" class="h-16 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>
                <div v-else-if="!selectedTicket" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-600">Ticket not found or access denied.</div>
                <div v-else class="space-y-4">
                    <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-black text-slate-900">{{ selectedTicket.subject }}</h3>
                            <div class="flex items-center gap-1">
                                <span class="badge" :class="badgeClass('priority', selectedTicket.priority)">{{ options.priorities?.[selectedTicket.priority] || selectedTicket.priority }}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ selectedTicket.ticket_number }} | Created {{ formatDateTime(selectedTicket.created_at) }}</p>
                        <div class="mt-2 prose prose-sm max-w-none" v-html="renderRichContent(selectedTicket.message)"></div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" class="btn-soft" :disabled="!canManageSelectedTicket" @click="downloadBlob(`/support-tickets/${selectedTicket.id}/attachment`, `${selectedTicket.ticket_number || 'ticket'}-attachment`)">Download Ticket Attachment</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="space-y-3">
                            <h4 class="text-sm font-black text-slate-900">Replies</h4>
                            <div class="rounded-xl border border-slate-200 p-3 max-h-[360px] overflow-auto bg-white space-y-2">
                                <article v-for="reply in selectedTicket.replies || []" :key="reply.id" class="rounded-lg border border-slate-200 p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-black text-slate-800">{{ reply.user?.name || 'Unknown' }}</p>
                                        <span class="badge" :class="reply.is_admin_reply ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-700 border-slate-200'">{{ reply.is_admin_reply ? 'Admin Reply' : 'User Reply' }}</span>
                                    </div>
                                    <div class="mt-1 prose prose-sm max-w-none" v-html="renderRichContent(reply.message)"></div>
                                    <p class="mt-1 text-[11px] font-bold text-slate-500">{{ formatDateTime(reply.created_at) }}</p>
                                    <button type="button" class="btn-soft mt-2" :disabled="!canManageSelectedTicket" @click="downloadBlob(`/support-tickets/${selectedTicket.id}/replies/${reply.id}/attachment`, `reply-${reply.id}-attachment`)">Download Reply Attachment</button>
                                </article>
                                <p v-if="(selectedTicket.replies || []).length === 0" class="text-xs font-semibold text-slate-500">No replies yet.</p>
                            </div>

                            <div v-if="canReplySelectedTicket" class="rounded-xl border border-slate-200 p-3 bg-slate-50 space-y-3">
                                <h5 class="text-xs font-black text-slate-800 uppercase tracking-[0.05em]">Add Reply</h5>
                                <label class="field-wrap">
                                    <span class="field-title">Message *</span>
                                    <QuillEditor
                                        v-model:content="replyForm.message"
                                        content-type="html"
                                        theme="snow"
                                        :toolbar="quillToolbar"
                                        placeholder="Write your reply"
                                        class="quill-editor"
                                    />
                                </label>
                                <label class="field-wrap"><span class="field-title">Attachment (Optional)</span><input :key="replyFileInputKey" type="file" class="field-input" @change="onReplyFileChange" /></label>
                                <p v-if="validationErrors.reply" class="text-xs font-semibold text-rose-600">{{ validationErrors.reply }}</p>
                                <button type="button" class="btn-dark" :disabled="replyLoading" @click="submitReply">{{ replyLoading ? 'Posting...' : 'Post Reply' }}</button>
                            </div>
                        </div>
                    </div>
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
.badge { font-size: 11px; font-weight: 900; border-width: 1px; border-style: solid; border-radius: 0.5rem; padding: 0.15rem 0.5rem; text-transform: uppercase; }
.btn-dark, .btn-soft { border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900; padding: 0.45rem 0.9rem; transition: 150ms ease; }
.btn-dark { color: #fff; background: rgb(15 23 42); border: 1px solid rgb(15 23 42); }
.btn-dark:hover { background: rgb(30 41 59); }
.btn-soft { color: rgb(51 65 85); background: #fff; border: 1px solid rgb(203 213 225); }
.btn-soft:hover { background: rgb(248 250 252); }
button:disabled { opacity: 0.65; cursor: not-allowed; }
.quill-editor :deep(.ql-container) { min-height: 150px; font-size: 0.875rem; background: white; }
.quill-editor :deep(.ql-toolbar) { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; background: white; }
.quill-editor :deep(.ql-container) { border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; }
</style>
