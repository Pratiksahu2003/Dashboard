<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import * as aiAdviserApi from '@/services/aiAdviserApi';

const { requireAuth } = useAuth();
const { error: showError } = useAlerts();

const conversations = ref([]);
const convLoading = ref(false);
const convMeta = ref({ current_page: 1, last_page: 1, has_more: false });
const selectedConversationId = ref(null);

const conversationLoading = ref(false);
const activeConversation = ref(null);
const messages = ref([]);
const usage = ref(null);
const usageLoading = ref(false);

const composerText = ref('');
const creatingConversation = ref(false);
const sending = ref(false);
const assistantTyping = ref(false);
const messagesScrollRef = ref(null);
const isMobile = ref(false);
const quickPrompts = [
    'Create a daily study timetable for me.',
    'Help me choose a career path based on my skills.',
    'Review my learning goals and improve them.',
    'Give me a 7-day revision strategy.',
];

const selectedConversation = computed(() => conversations.value.find(c => Number(c.id) === Number(selectedConversationId.value)) || null);
const usagePercent = computed(() => Math.max(0, Math.min(100, Number(usage.value?.usage_percentage || 0))));
const usageLabel = computed(() => {
    const u = usage.value;
    if (!u) return 'Token usage unavailable';
    return `${u.tokens_used || 0} / ${u.tokens_limit || 0} tokens used`;
});
const showConversationList = computed(() => !isMobile.value || !selectedConversationId.value);
const showThreadPanel = computed(() => !isMobile.value || !!selectedConversationId.value);

const formatDateTime = value => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString();
};

const formatRelative = value => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const scrollToBottom = () => {
    nextTick(() => {
        const el = messagesScrollRef.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
};

const normalizeMessageSections = message => {
    if (Array.isArray(message?.content_sections) && message.content_sections.length) return message.content_sections;
    return [{ type: 'paragraph', body: message?.content || '' }];
};

const loadUsage = async () => {
    usageLoading.value = true;
    try {
        const data = await aiAdviserApi.getUsage();
        usage.value = data?.token_usage || null;
    } catch {
        usage.value = null;
    } finally {
        usageLoading.value = false;
    }
};

const loadConversations = async (page = 1) => {
    convLoading.value = true;
    try {
        const data = await aiAdviserApi.listConversations({ page });
        const rows = data?.conversations || [];
        conversations.value = page === 1 ? rows : [...conversations.value, ...rows];
        convMeta.value = {
            current_page: Number(data?.pagination?.current_page || 1),
            last_page: Number(data?.pagination?.last_page || 1),
            has_more: !!data?.pagination?.has_more,
        };
    } catch (e) {
        showError(e?.message || 'Unable to load AI conversations.', 'AI Adviser');
    } finally {
        convLoading.value = false;
    }
};

const openConversation = async conversationId => {
    if (!conversationId) return;
    selectedConversationId.value = conversationId;
    conversationLoading.value = true;
    try {
        const data = await aiAdviserApi.getConversation(conversationId);
        activeConversation.value = data?.conversation || null;
        messages.value = Array.isArray(data?.messages) ? data.messages : [];
        scrollToBottom();
    } catch (e) {
        showError(e?.message || 'Unable to load conversation.', 'AI Adviser');
    } finally {
        conversationLoading.value = false;
    }
};

const startNewConversation = async firstMessage => {
    creatingConversation.value = true;
    try {
        const subject = firstMessage.trim().slice(0, 70);
        const data = await aiAdviserApi.startConversation({
            message: firstMessage,
            subject: subject || undefined,
        });

        const conv = data?.conversation || null;
        messages.value = Array.isArray(data?.messages) ? data.messages : [];
        activeConversation.value = conv;
        if (data?.token_usage) usage.value = data.token_usage;
        if (conv?.id) selectedConversationId.value = conv.id;

        await loadConversations(1);
        scrollToBottom();
    } finally {
        creatingConversation.value = false;
    }
};

const sendCurrentMessage = async () => {
    const text = composerText.value.trim();
    if (!text || sending.value || creatingConversation.value) return;
    sending.value = true;
    assistantTyping.value = true;
    try {
        if (!selectedConversationId.value) {
            await startNewConversation(text);
            composerText.value = '';
            return;
        }

        const data = await aiAdviserApi.sendMessage(selectedConversationId.value, { message: text });
        if (data?.user_message) messages.value.push(data.user_message);
        if (data?.assistant_message) messages.value.push(data.assistant_message);
        if (data?.conversation) activeConversation.value = data.conversation;
        if (data?.token_usage) usage.value = data.token_usage;
        composerText.value = '';
        scrollToBottom();
        await loadConversations(1);
    } catch (e) {
        if (Number(e?.code) === 402) {
            const rem = e?.errors?.tokens_remaining;
            showError(`Token limit reached. Remaining tokens: ${rem ?? 0}. Please upgrade your AI plan.`, 'AI Limit');
        } else {
            showError(e?.message || 'Unable to send message.', 'AI Adviser');
        }
    } finally {
        assistantTyping.value = false;
        sending.value = false;
    }
};

const onComposerEnter = event => {
    if (event.shiftKey) return;
    event.preventDefault();
    sendCurrentMessage();
};

const useQuickPrompt = async prompt => {
    composerText.value = prompt;
    await nextTick();
    sendCurrentMessage();
};

const goToAiPlanUpgrade = () => {
    try {
        window.location.assign(`${route('subscriptions')}?s_type=3`);
    } catch {
        window.location.assign('/subscriptions?s_type=3');
    }
};

const updateViewport = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.matchMedia('(max-width: 1023px)').matches;
};

const backToList = () => {
    if (!isMobile.value) return;
    selectedConversationId.value = null;
    activeConversation.value = null;
    messages.value = [];
};

onMounted(async () => {
    if (!requireAuth()) return;
    updateViewport();
    window.addEventListener('resize', updateViewport);
    await Promise.all([loadConversations(1), loadUsage()]);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewport);
});
</script>

<template>
    <Head title="AI Adviser" />

    <AppLayout>
        <template #breadcrumb>AI Adviser</template>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden h-[min(920px,calc(100dvh-7.25rem))] lg:h-[calc(100dvh-8rem)]">
            <div class="flex h-full">
                <aside v-show="showConversationList" class="w-full lg:w-[360px] shrink-0 border-r border-slate-200 bg-[#f8f9ff] flex flex-col min-h-0">
                    <div class="p-4 border-b border-slate-200 bg-gradient-to-r from-violet-50 to-blue-50">
                        <h1 class="text-xl font-black text-slate-900">AI Adviser</h1>
                        <p class="mt-1 text-xs font-semibold text-slate-600">Ask, plan, and get guided answers.</p>
                    </div>

                    <div class="p-3 border-b border-slate-200 bg-white">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-600 mb-2">
                            <span>Token usage</span>
                            <span v-if="usageLoading">Loading...</span>
                            <span v-else>{{ usageLabel }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-slate-900 transition-all" :style="{ width: `${usagePercent}%` }"></div>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-2">
                        <button
                            v-for="conv in conversations"
                            :key="conv.id"
                            type="button"
                            class="w-full text-left rounded-2xl p-3 mb-2 border transition shadow-sm"
                            :class="selectedConversationId === conv.id ? 'border-indigo-300 bg-white ring-2 ring-indigo-100' : 'border-transparent bg-white/80 hover:border-slate-200 hover:bg-white'"
                            @click="openConversation(conv.id)"
                        >
                            <p class="text-sm font-black text-slate-900 truncate">{{ conv.subject || 'Untitled conversation' }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-600 line-clamp-2">{{ conv.last_message_preview || 'No messages yet' }}</p>
                            <p class="mt-1 text-[10px] font-bold text-slate-400">{{ formatRelative(conv.last_active_at || conv.started_at) }}</p>
                        </button>

                        <div v-if="!convLoading && conversations.length === 0" class="p-4 text-center text-sm font-semibold text-slate-500">
                            No AI conversations yet.
                        </div>

                        <button
                            v-if="convMeta.has_more"
                            type="button"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50 transition"
                            :disabled="convLoading"
                            @click="loadConversations(convMeta.current_page + 1)"
                        >
                            {{ convLoading ? 'Loading...' : 'Load more' }}
                        </button>
                    </div>
                </aside>

                <section v-show="showThreadPanel" class="flex flex-1 flex-col min-w-0 min-h-0 bg-white">
                    <header class="px-5 py-4 border-b border-slate-200 bg-gradient-to-r from-[#eef2ff] via-white to-[#eaf3ff]">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="lg:hidden h-8 w-8 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50"
                                aria-label="Back to conversations"
                                @click="backToList"
                            >
                                <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <h2 class="text-base font-black text-slate-900 truncate">
                                {{ selectedConversation?.subject || activeConversation?.subject || 'New AI conversation' }}
                            </h2>
                        </div>
                        <p class="text-[11px] font-semibold text-slate-500">
                            {{ selectedConversationId ? 'Conversation mode' : 'New conversation mode' }}
                        </p>
                    </header>

                    <div ref="messagesScrollRef" class="flex-1 min-h-0 overflow-y-auto px-6 py-5 bg-gradient-to-b from-[#fbfcff] to-[#f4f7ff] space-y-4">
                        <div v-if="conversationLoading" class="space-y-3">
                            <div v-for="i in 4" :key="`ai-sk-${i}`" class="h-20 rounded-2xl bg-slate-100 animate-pulse"></div>
                        </div>

                        <template v-else>
                            <div v-if="messages.length === 0" class="h-full flex items-center justify-center text-center">
                                <div class="max-w-xl">
                                    <h3 class="text-2xl font-black text-slate-900">How can I help today?</h3>
                                    <p class="mt-2 text-sm font-semibold text-slate-500">
                                        Ask study, career, planning, or business questions. Responses are structured like Gemini sections.
                                    </p>
                                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                                        <button
                                            v-for="prompt in quickPrompts"
                                            :key="prompt"
                                            type="button"
                                            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100 transition"
                                            @click="useQuickPrompt(prompt)"
                                        >
                                            {{ prompt }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <article v-for="msg in messages" :key="msg.id" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                <div
                                    class="max-w-[min(100%,800px)] rounded-2xl border p-4"
                                    :class="msg.role === 'user' ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white border-indigo-600 shadow-md' : 'bg-white text-slate-900 border-slate-200 shadow-sm'"
                                >
                                    <template v-if="msg.role === 'assistant'">
                                        <template v-for="(section, idx) in normalizeMessageSections(msg)" :key="`${msg.id}-s-${idx}`">
                                            <h4 v-if="section.type === 'heading'" class="text-base font-black mb-2">{{ section.heading }}</h4>
                                            <p v-else-if="section.type === 'paragraph'" class="text-sm font-medium leading-relaxed mb-2">{{ section.body }}</p>
                                            <ul v-else-if="section.type === 'list'" class="list-disc pl-5 space-y-1 mb-2">
                                                <li v-for="(item, liIdx) in section.items || []" :key="`${msg.id}-li-${liIdx}`" class="text-sm font-medium leading-relaxed">
                                                    {{ item }}
                                                </li>
                                            </ul>
                                            <div v-else-if="section.type === 'note'" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 mb-2">
                                                {{ section.body }}
                                            </div>
                                            <p v-else class="text-sm font-medium leading-relaxed mb-2">{{ msg.content }}</p>
                                        </template>
                                    </template>
                                    <p v-else class="text-sm font-semibold whitespace-pre-wrap break-words">{{ msg.content }}</p>
                                    <p class="mt-2 text-[10px] font-bold opacity-70">{{ formatDateTime(msg.sent_at) }}</p>
                                </div>
                            </article>

                            <article v-if="assistantTyping" class="flex justify-start">
                                <div class="max-w-[min(100%,320px)] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full bg-slate-400 animate-bounce"></span>
                                        <span class="h-2 w-2 rounded-full bg-slate-400 animate-bounce [animation-delay:120ms]"></span>
                                        <span class="h-2 w-2 rounded-full bg-slate-400 animate-bounce [animation-delay:240ms]"></span>
                                    </div>
                                    <p class="mt-2 text-[11px] font-semibold text-slate-500">AI Adviser is thinking...</p>
                                </div>
                            </article>
                        </template>
                    </div>

                    <footer class="border-t border-slate-200 bg-white p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="prompt in quickPrompts.slice(0, 3)"
                                    :key="`footer-${prompt}`"
                                    type="button"
                                    class="rounded-full border border-indigo-200 bg-white px-2.5 py-1 text-[10px] font-black text-indigo-700 hover:bg-indigo-50 transition"
                                    @click="useQuickPrompt(prompt)"
                                >
                                    {{ prompt }}
                                </button>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[10px] font-black text-amber-800 hover:bg-amber-100 transition"
                                @click="goToAiPlanUpgrade"
                            >
                                Upgrade AI Adviser Plan
                            </button>
                        </div>
                        <div class="rounded-3xl border border-indigo-100 bg-gradient-to-r from-slate-50 to-indigo-50 p-2.5 flex items-end gap-2 shadow-[0_10px_28px_rgba(15,23,42,0.1)]">
                            <textarea
                                v-model="composerText"
                                rows="2"
                                maxlength="6000"
                                placeholder="Ask AI Adviser anything..."
                                class="flex-1 bg-transparent px-3 py-2 text-sm font-semibold text-slate-900 placeholder:text-slate-400 border-0 outline-none ring-0 focus:ring-0 focus:outline-none resize-none"
                                @keydown.enter="onComposerEnter"
                            />
                            <button
                                type="button"
                                class="rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 text-white px-5 py-2.5 text-sm font-black hover:from-indigo-700 hover:to-blue-700 transition disabled:opacity-50 shadow-sm"
                                :disabled="sending || creatingConversation || !composerText.trim()"
                                @click="sendCurrentMessage"
                            >
                                {{ sending || creatingConversation ? 'Sending...' : 'Send' }}
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

