<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import * as chatApi from '@/services/chatApi';
import {
    connectEcho,
    disconnectEcho,
    isReverbConfigured,
    subscribeToChatConversation,
} from '@/services/chatEcho';

const props = defineProps({
    initialConversationId: { type: Number, default: null },
});

const { requireAuth, getUser, getToken } = useAuth();
const { error: showError, success: showSuccess, confirmDanger } = useAlerts();

const folder = ref('inbox');
const conversations = ref([]);
const convMeta = ref({ current_page: 1, last_page: 1, loading: false });
const selectedConversationId = ref(null);
const thread = ref(null);
const participants = ref([]);
const myMembership = ref(null);

const messages = ref([]);
const messagesLoading = ref(false);
const olderLoading = ref(false);
const hasMoreOlder = ref(true);
const sendLoading = ref(false);

const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
let searchTimer = null;

const showNewChatModal = ref(false);
const newChatStep = ref('pick');
const modalPrivateQuery = ref('');
const modalPrivateResults = ref([]);
const modalPrivateLoading = ref(false);
let modalSearchTimer = null;
const groupTitle = ref('');
const groupMemberQuery = ref('');
const groupSearchResults = ref([]);
const groupSearchLoading = ref(false);
let groupSearchTimer = null;
const groupMembers = ref([]);
const creatingGroup = ref(false);

const composerText = ref('');
const replyTo = ref(null);
const editingMessage = ref(null);
const editDraft = ref('');
const reactionPickerMessage = ref(null);
const reactionPickerOpen = ref(false);
const REACTION_RECENT_KEY = 'chat_reaction_recent';
const reactionCategories = [
    { id: 'all', label: 'All' },
    { id: 'smileys', label: 'Smileys' },
    { id: 'gestures', label: 'Gestures' },
    { id: 'hearts', label: 'Hearts' },
    { id: 'celebration', label: 'Fun' },
];
const reactionGroups = {
    smileys: ['😀', '😁', '😂', '🤣', '😃', '😄', '😅', '😊', '😉', '😍', '🥰', '😘', '😗', '😎', '🤩', '🤗', '🤔', '🫡', '😴', '😌', '😇', '🙃', '😐', '😑', '🙄', '😬', '😭', '😢', '😤', '😡', '🤯', '😱', '🤭', '🤫'],
    gestures: ['👍', '👎', '👌', '👏', '🙌', '🙏', '🤝', '💪', '✌️', '🤟', '👀', '💯', '✅', '❌', '⭐', '🌟', '🔥', '💥'],
    hearts: ['❤️', '🩷', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❤️‍🔥', '💖', '💘', '💝', '💕', '💞', '💓'],
    celebration: ['🎉', '🎊', '🥳', '🎁', '🏆', '🥇', '⚽', '🏀', '🎯', '🎮', '🎵', '🎶', '🎬', '📚', '✈️', '🌍', '🌈', '☀️', '🌙', '⭐️', '⚡', '☕', '🍕', '🍔', '🍟', '🍿', '🍩', '🍫', '🍎', '🍓', '🥑', '🍇', '🐶', '🐱', '🦁', '🐼'],
};
const reactionCategory = ref('all');
const reactionSearch = ref('');
const reactionRecent = ref([]);
const allReactionChoices = computed(() => [...reactionGroups.smileys, ...reactionGroups.gestures, ...reactionGroups.hearts, ...reactionGroups.celebration]);
const filteredReactionChoices = computed(() => {
    const source = reactionCategory.value === 'all' ? allReactionChoices.value : (reactionGroups[reactionCategory.value] || []);
    const q = reactionSearch.value.trim();
    if (!q) return source;
    return source.filter(em => em.includes(q));
});

const isMobile = ref(false);
let pollTimer = null;
let typingTimer = null;
let typingStopTimer = null;
let echoUnsubscribe = null;
let typingClearTimer = null;

const typingPeerId = ref(null);
const typingActive = ref(false);

const currentUser = ref(null);
const messagesScrollRef = ref(null);

const updateViewport = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.matchMedia('(max-width: 1023px)').matches;
};

const formatShortTime = value => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const peerTitle = computed(() => {
    const c = thread.value;
    if (!c) return 'Chat';
    if (c.type === 'private' && c.peer) return c.peer.name || c.display_title || c.title;
    return c.display_title || c.title || 'Conversation';
});

const peerAvatar = computed(() => {
    const c = thread.value;
    if (c?.type === 'private' && c.peer?.profile_image) return c.peer.profile_image;
    return '';
});

const typingLabel = computed(() => {
    if (!typingActive.value) return '';
    const uid = typingPeerId.value;
    const fromParticipant = participants.value.find(p => Number(p.user_id) === Number(uid));
    const name = fromParticipant?.user?.name;
    if (name) return `${name} is typing…`;
    if (thread.value?.type === 'private' && Number(thread.value?.peer?.id) === Number(uid)) {
        return `${thread.value.peer.name} is typing…`;
    }
    return 'Someone is typing…';
});

const initialsFromName = name => {
    if (!name || typeof name !== 'string') return '?';
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    return name.slice(0, 2).toUpperCase();
};

const isMine = message => {
    const uid = currentUser.value?.id;
    if (!uid || !message?.sender_id) return false;
    return Number(message.sender_id) === Number(uid);
};

const scrollThreadToBottom = () => {
    nextTick(() => {
        const el = messagesScrollRef.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
};

const mergeIncomingMessages = incoming => {
    const ids = new Set(messages.value.map(m => m.id));
    const added = [];
    for (const m of incoming) {
        if (m?.id && !ids.has(m.id)) added.push(m);
    }
    if (!added.length) return;
    messages.value = [...messages.value, ...added].sort((a, b) => a.id - b.id);
    scrollThreadToBottom();
};

const stopEchoSubscription = () => {
    if (echoUnsubscribe) {
        echoUnsubscribe();
        echoUnsubscribe = null;
    }
};

const stopPoll = () => {
    clearInterval(pollTimer);
    pollTimer = null;
};

const startPoll = () => {
    stopPoll();
    if (isReverbConfigured()) return;
    pollTimer = setInterval(async () => {
        if (!selectedConversationId.value) return;
        try {
            const page = await chatApi.listMessages(selectedConversationId.value, { page: 1, per_page: 40 });
            mergeIncomingMessages(page?.data || []);
        } catch {
            /* ignore */
        }
    }, 12000);
};

const bindRealtime = conversationId => {
    stopEchoSubscription();
    stopPoll();
    typingActive.value = false;
    typingPeerId.value = null;
    clearTimeout(typingClearTimer);

    if (!conversationId) return;

    const echo = connectEcho(() => getToken());
    if (!echo) {
        startPoll();
        return;
    }

    echoUnsubscribe = subscribeToChatConversation(echo, conversationId, {
        onSubscribed: () => {
            // no-op; but ensures subscription is established before relying on events
        },
        onSubscriptionError: () => {
            stopEchoSubscription();
            startPoll();
            showError('Realtime not available for this chat (403). Falling back to refresh mode.', 'Chat realtime');
        },
        onMessageSent: async payload => {
            const cid = selectedConversationId.value;
            if (!cid || Number(payload?.conversation_id) !== Number(cid)) return;
            if (Number(payload?.sender_id) === Number(currentUser.value?.id)) return;
            try {
                const page = await chatApi.listMessages(cid, { page: 1, per_page: 15 });
                mergeIncomingMessages(page?.data || []);
                await loadConversations(1, false);
            } catch {
                /* ignore */
            }
        },
        onMessageRead: () => {
            loadConversations(1, false).catch(() => {});
        },
        onReadState: () => {
            loadConversations(1, false).catch(() => {});
        },
        onReaction: async payload => {
            if (Number(payload?.conversation_id) !== Number(selectedConversationId.value)) return;
            const mid = payload?.message_id;
            if (!mid) return;
            try {
                const page = await chatApi.listMessages(selectedConversationId.value, { page: 1, per_page: 100 });
                const map = new Map((page?.data || []).map(m => [m.id, m]));
                const updated = map.get(mid);
                if (updated) {
                    messages.value = messages.value.map(m => (m.id === mid ? updated : m));
                }
            } catch {
                /* ignore */
            }
        },
        onTyping: payload => {
            if (Number(payload?.conversation_id) !== Number(selectedConversationId.value)) return;
            if (Number(payload?.user_id) === Number(currentUser.value?.id)) return;
            typingPeerId.value = payload.user_id;
            typingActive.value = !!payload.is_typing;
            clearTimeout(typingClearTimer);
            if (payload.is_typing) {
                typingClearTimer = setTimeout(() => {
                    typingActive.value = false;
                }, 3500);
            }
        },
    });
};

const loadConversations = async (page = 1, append = false) => {
    convMeta.value.loading = true;
    try {
        const data = await chatApi.listConversations({ folder: folder.value, page });
        const rows = data?.data || [];
        conversations.value = append ? [...conversations.value, ...rows] : rows;
        convMeta.value = {
            current_page: data?.current_page || 1,
            last_page: data?.last_page || 1,
            loading: false,
        };
    } catch (e) {
        showError(e?.message || 'Could not load conversations.', 'Chat');
        convMeta.value.loading = false;
    }
};

const loadMoreConversations = async () => {
    if (convMeta.value.loading) return;
    const next = (convMeta.value.current_page || 1) + 1;
    if (next > (convMeta.value.last_page || 1)) return;
    convMeta.value.loading = true;
    try {
        const data = await chatApi.listConversations({ folder: folder.value, page: next });
        const rows = data?.data || [];
        conversations.value = [...conversations.value, ...rows];
        convMeta.value.current_page = data?.current_page || next;
        convMeta.value.last_page = data?.last_page || convMeta.value.last_page;
    } catch (e) {
        showError(e?.message || 'Could not load more chats.', 'Chat');
    } finally {
        convMeta.value.loading = false;
    }
};

const replaceChatUrl = conversationId => {
    try {
        if (typeof window === 'undefined') return;
        const next = conversationId ? `/chat/${conversationId}` : '/chat';
        if (window.location.pathname === next) return;
        window.history.replaceState({}, '', next);
    } catch {
        /* ignore */
    }
};

const openConversation = async (id, { updateRoute = true } = {}) => {
    if (!id) return;
    selectedConversationId.value = id;
    if (updateRoute) {
        replaceChatUrl(id);
    }
    messages.value = [];
    hasMoreOlder.value = true;
    messagesLoading.value = true;
    replyTo.value = null;
    composerText.value = '';
    editingMessage.value = null;

    try {
        const detail = await chatApi.getConversation(id);
        thread.value = detail?.conversation || null;
        participants.value = Array.isArray(detail?.participants) ? detail.participants : [];
        myMembership.value = detail?.my_membership || null;

        const page = await chatApi.listMessages(id, { per_page: 50, page: 1 });
        const raw = page?.data || [];
        messages.value = [...raw].reverse();
        hasMoreOlder.value = raw.length >= 50;

        await chatApi.markConversationRead(id, {}).catch(() => {});
        await loadConversations(convMeta.value.current_page || 1, false);
    } catch (e) {
        showError(e?.message || 'Could not open conversation.', 'Chat');
    } finally {
        messagesLoading.value = false;
        scrollThreadToBottom();
    }
};

const loadOlderMessages = async () => {
    if (!selectedConversationId.value || olderLoading.value || !hasMoreOlder.value) return;
    if (!messages.value.length) return;
    const minId = Math.min(...messages.value.map(m => m.id));
    if (!Number.isFinite(minId)) return;
    olderLoading.value = true;
    const el = messagesScrollRef.value;
    const prevHeight = el?.scrollHeight || 0;
    try {
        const page = await chatApi.listMessages(selectedConversationId.value, {
            before_id: minId,
            per_page: 50,
        });
        const batch = page?.data || [];
        if (!batch.length) {
            hasMoreOlder.value = false;
            return;
        }
        const chronological = [...batch].reverse();
        messages.value = [...chronological, ...messages.value];
        hasMoreOlder.value = batch.length >= 50;
        await nextTick();
        if (el) el.scrollTop = el.scrollHeight - prevHeight;
    } catch (e) {
        showError(e?.message || 'Could not load older messages.', 'Chat');
    } finally {
        olderLoading.value = false;
    }
};

const onMessagesScroll = e => {
    const t = e.target;
    if (t.scrollTop < 80) loadOlderMessages();
};

const sendTypingSignal = typing => {
    if (!selectedConversationId.value) return;
    chatApi.sendTyping(selectedConversationId.value, { is_typing: typing }).catch(() => {});
};

const onComposerInput = () => {
    clearTimeout(typingTimer);
    clearTimeout(typingStopTimer);
    typingTimer = setTimeout(() => sendTypingSignal(true), 400);
    typingStopTimer = setTimeout(() => sendTypingSignal(false), 2500);
};

const sendMessage = async () => {
    const text = composerText.value.trim();
    if (!text || !selectedConversationId.value || sendLoading.value) return;
    sendLoading.value = true;
    clearTimeout(typingTimer);
    clearTimeout(typingStopTimer);
    sendTypingSignal(false);
    try {
        const payload = { message: text };
        if (replyTo.value?.id) payload.reply_to = replyTo.value.id;
        const data = await chatApi.sendMessage(selectedConversationId.value, payload);
        const msg = data?.message;
        if (msg?.id) {
            messages.value = [...messages.value, msg].sort((a, b) => a.id - b.id);
        }
        composerText.value = '';
        replyTo.value = null;
        scrollThreadToBottom();
        await loadConversations(1, false);
    } catch (e) {
        showError(e?.message || 'Message not sent.', 'Chat');
    } finally {
        sendLoading.value = false;
    }
};

const setReply = message => {
    replyTo.value = message;
};

const clearReply = () => {
    replyTo.value = null;
};

const beginEdit = message => {
    if (!isMine(message) || message.deleted_at) return;
    editingMessage.value = message;
    editDraft.value = message.message || '';
};

const saveEdit = async () => {
    if (!editingMessage.value) return;
    const text = editDraft.value.trim();
    if (!text) {
        showError('Message cannot be empty.');
        return;
    }
    try {
        const data = await chatApi.editMessage(editingMessage.value.id, { message: text });
        const updated = data?.message;
        if (updated?.id) {
            messages.value = messages.value.map(m => (m.id === updated.id ? updated : m));
        }
        editingMessage.value = null;
    } catch (e) {
        showError(e?.message || 'Could not edit message.', 'Chat');
    }
};

const removeMessage = async message => {
    if (!isMine(message)) return;
    const ok = await confirmDanger({
        title: 'Delete this message?',
        text: 'It will be removed for everyone in this chat.',
        confirmText: 'Delete',
    });
    if (!ok) return;
    try {
        await chatApi.deleteMessage(message.id);
        messages.value = messages.value.map(m =>
            m.id === message.id ? { ...m, deleted_at: new Date().toISOString(), message: '' } : m,
        );
    } catch (e) {
        showError(e?.message || 'Could not delete message.', 'Chat');
    }
};

const toggleReaction = async (message, emoji) => {
    try {
        if (message.my_reaction === emoji) {
            await chatApi.removeReaction(message.id);
        } else {
            await chatApi.addReaction(message.id, { reaction: emoji });
        }
        const page = await chatApi.listMessages(selectedConversationId.value, { page: 1, per_page: 100 });
        const map = new Map((page?.data || []).map(m => [m.id, m]));
        messages.value = messages.value.map(m => map.get(m.id) || m);
    } catch (e) {
        showError(e?.message || 'Could not update reaction.', 'Chat');
    }
};

const openReactionPicker = message => {
    reactionPickerMessage.value = message;
    reactionCategory.value = 'all';
    reactionSearch.value = '';
    reactionPickerOpen.value = true;
};

const closeReactionPicker = () => {
    reactionPickerOpen.value = false;
    reactionPickerMessage.value = null;
};

const chooseReaction = async emoji => {
    if (!reactionPickerMessage.value) return;
    const target = reactionPickerMessage.value;
    await toggleReaction(target, emoji);
    if (!reactionRecent.value.includes(emoji)) {
        reactionRecent.value = [emoji, ...reactionRecent.value].slice(0, 18);
    } else {
        reactionRecent.value = [emoji, ...reactionRecent.value.filter(v => v !== emoji)].slice(0, 18);
    }
    try {
        localStorage.setItem(REACTION_RECENT_KEY, JSON.stringify(reactionRecent.value));
    } catch {
        /* ignore */
    }
    closeReactionPicker();
};

const toggleMute = async () => {
    if (!thread.value?.id) return;
    const next = !myMembership.value?.muted;
    try {
        const data = await chatApi.patchConversation(thread.value.id, { muted: next });
        myMembership.value = data?.my_membership || { ...myMembership.value, muted: next };
        showSuccess(next ? 'Conversation muted.' : 'Conversation unmuted.');
        await loadConversations(1, false);
    } catch (e) {
        showError(e?.message || 'Could not update mute.', 'Chat');
    }
};

const toggleArchive = async () => {
    if (!thread.value?.id) return;
    const next = !myMembership.value?.archived;
    try {
        const data = await chatApi.patchConversation(thread.value.id, { archived: next });
        myMembership.value = data?.my_membership || { ...myMembership.value, archived: next };
        showSuccess(next ? 'Archived.' : 'Moved to inbox.');
        selectedConversationId.value = null;
        thread.value = null;
        messages.value = [];
        replaceChatUrl(null);
        await loadConversations(1, false);
    } catch (e) {
        showError(e?.message || 'Could not update archive.', 'Chat');
    }
};

const runSearch = () => {
    const q = searchQuery.value.trim();
    if (q.length < 2) {
        searchResults.value = [];
        return;
    }
    searchLoading.value = true;
    chatApi
        .searchUsers({ q, limit: 20 })
        .then(data => {
            searchResults.value = data?.users || [];
        })
        .catch(e => showError(e?.message || 'Search failed.', 'Chat'))
        .finally(() => {
            searchLoading.value = false;
        });
};

watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 320);
});

watch(modalPrivateQuery, () => {
    clearTimeout(modalSearchTimer);
    modalSearchTimer = setTimeout(runModalPrivateSearch, 320);
});

watch(groupMemberQuery, () => {
    clearTimeout(groupSearchTimer);
    groupSearchTimer = setTimeout(runGroupMemberSearch, 320);
});

const startDm = async (row, { clearSidebarSearch = true } = {}) => {
    try {
        if (row.has_private_conversation && row.private_conversation_id) {
            await openConversation(row.private_conversation_id);
            if (clearSidebarSearch) {
                searchQuery.value = '';
                searchResults.value = [];
            }
            return true;
        }
        const data = await chatApi.createConversation({
            type: 'private',
            participants: [row.id],
        });
        const conv = data?.conversation;
        if (conv?.id) {
            await openConversation(conv.id);
            if (clearSidebarSearch) {
                searchQuery.value = '';
                searchResults.value = [];
            }
            return true;
        }
    } catch (e) {
        showError(e?.message || 'Could not start chat.', 'Chat');
    }
    return false;
};

const runModalPrivateSearch = () => {
    const q = modalPrivateQuery.value.trim();
    if (q.length < 2) {
        modalPrivateResults.value = [];
        return;
    }
    modalPrivateLoading.value = true;
    chatApi
        .searchUsers({ q, limit: 25 })
        .then(data => {
            modalPrivateResults.value = data?.users || [];
        })
        .catch(e => showError(e?.message || 'Search failed.', 'Chat'))
        .finally(() => {
            modalPrivateLoading.value = false;
        });
};

const runGroupMemberSearch = () => {
    const q = groupMemberQuery.value.trim();
    if (q.length < 2) {
        groupSearchResults.value = [];
        return;
    }
    groupSearchLoading.value = true;
    chatApi
        .searchUsers({ q, limit: 25 })
        .then(data => {
            groupSearchResults.value = data?.users || [];
        })
        .catch(e => showError(e?.message || 'Search failed.', 'Chat'))
        .finally(() => {
            groupSearchLoading.value = false;
        });
};

const addGroupMember = row => {
    const myId = currentUser.value?.id;
    if (row?.id == null || Number(row.id) === Number(myId)) return;
    if (groupMembers.value.some(m => Number(m.id) === Number(row.id))) return;
    groupMembers.value = [
        ...groupMembers.value,
        { id: row.id, name: row.name, profile_image: row.profile_image },
    ];
    groupMemberQuery.value = '';
    groupSearchResults.value = [];
};

const removeGroupMember = id => {
    groupMembers.value = groupMembers.value.filter(m => Number(m.id) !== Number(id));
};

const resetNewChatModal = () => {
    newChatStep.value = 'pick';
    modalPrivateQuery.value = '';
    modalPrivateResults.value = [];
    groupTitle.value = '';
    groupMemberQuery.value = '';
    groupSearchResults.value = [];
    groupMembers.value = [];
};

const openNewChatModal = () => {
    resetNewChatModal();
    showNewChatModal.value = true;
};

const closeNewChatModal = () => {
    showNewChatModal.value = false;
    resetNewChatModal();
};

const pickNewChatType = step => {
    newChatStep.value = step;
};

const startDmFromModal = async row => {
    const ok = await startDm(row, { clearSidebarSearch: false });
    if (ok) {
        modalPrivateQuery.value = '';
        modalPrivateResults.value = [];
        closeNewChatModal();
    }
};

const createGroupConversation = async () => {
    const myId = currentUser.value?.id;
    const ids = [...new Set(groupMembers.value.map(m => m.id))].filter(id => Number(id) !== Number(myId));
    if (ids.length < 2) {
        showError('Add at least two people for a group. (The API requires two other participants.)', 'New group');
        return;
    }
    creatingGroup.value = true;
    try {
        const data = await chatApi.createConversation({
            type: 'group',
            title: groupTitle.value.trim() || 'Group chat',
            participants: ids,
        });
        const conv = data?.conversation;
        if (conv?.id) {
            showSuccess('Group chat created.');
            closeNewChatModal();
            await openConversation(conv.id);
        }
    } catch (e) {
        showError(e?.message || 'Could not create group.', 'Chat');
    } finally {
        creatingGroup.value = false;
    }
};

const backToList = () => {
    selectedConversationId.value = null;
    thread.value = null;
    messages.value = [];
    stopEchoSubscription();
    stopPoll();
    replaceChatUrl(null);
};

watch(selectedConversationId, id => {
    bindRealtime(id);
});

watch(folder, () => {
    loadConversations(1, false);
});

onMounted(() => {
    if (!requireAuth()) return;
    currentUser.value = getUser();
    try {
        const saved = JSON.parse(localStorage.getItem(REACTION_RECENT_KEY) || '[]');
        if (Array.isArray(saved)) {
            reactionRecent.value = saved.filter(v => typeof v === 'string').slice(0, 18);
        }
    } catch {
        reactionRecent.value = [];
    }
    connectEcho(() => getToken());
    updateViewport();
    window.addEventListener('resize', updateViewport);
    loadConversations(1, false);
    if (props.initialConversationId) {
        openConversation(props.initialConversationId, { updateRoute: false });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewport);
    stopEchoSubscription();
    stopPoll();
    disconnectEcho();
    clearTimeout(searchTimer);
    clearTimeout(typingTimer);
    clearTimeout(typingStopTimer);
    clearTimeout(typingClearTimer);
    clearTimeout(modalSearchTimer);
    clearTimeout(groupSearchTimer);
    sendTypingSignal(false);
});

const showConversationList = computed(() => !isMobile.value || !selectedConversationId.value);
const showThreadPanel = computed(() => !isMobile.value || !!selectedConversationId.value);
</script>

<template>
    <Head title="Chat" />

    <AppLayout>
        <template #breadcrumb>Messages</template>

        <div
            class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden h-[min(900px,calc(100dvh-7.5rem))] lg:h-[calc(100dvh-8.25rem)]"
        >
            <div class="flex flex-1 min-h-0 flex-col lg:flex-row">
                <!-- Conversations -->
                <aside
                    v-show="showConversationList"
                    class="flex flex-col border-b lg:border-b-0 lg:border-r border-slate-200 w-full lg:w-[min(100%,380px)] lg:max-w-[40%] shrink-0 min-h-0 bg-slate-50/80"
                >
                    <div class="p-3 sm:p-4 border-b border-slate-200 bg-white/90 space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center justify-between gap-2">
                                <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Messages</h1>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-black text-white shadow-sm hover:bg-slate-800 transition w-full sm:w-auto"
                                @click="openNewChatModal"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Start new chat
                            </button>
                        </div>
                        <div class="flex rounded-xl bg-slate-100 p-1 gap-1">
                            <button
                                v-for="tab in [
                                    { id: 'inbox', label: 'Inbox' },
                                    { id: 'archived', label: 'Archived' },
                                    { id: 'all', label: 'All' },
                                ]"
                                :key="tab.id"
                                type="button"
                                class="flex-1 rounded-lg px-2 py-1.5 text-xs font-black transition"
                                :class="folder === tab.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                @click="folder = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                        <div>
                            <label class="sr-only" for="chat-search">Find people</label>
                            <div class="relative">
                                <input
                                    id="chat-search"
                                    v-model="searchQuery"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="Search people (min 2 characters)…"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 pr-10 text-sm font-semibold text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                                <span
                                    v-if="searchLoading"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 rounded-full border-2 border-slate-200 border-t-slate-700 animate-spin"
                                />
                            </div>
                            <div
                                v-if="searchResults.length"
                                class="mt-2 max-h-48 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100"
                            >
                                <button
                                    v-for="u in searchResults"
                                    :key="u.id"
                                    type="button"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-slate-50 transition"
                                    @click="startDm(u)"
                                >
                                    <img
                                        :src="u.profile_image"
                                        alt=""
                                        class="h-10 w-10 rounded-xl object-cover bg-slate-200 shrink-0"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-black text-slate-900 truncate">{{ u.name }}</div>
                                        <div class="text-[11px] font-semibold text-slate-500 truncate">
                                            {{ u.phone || 'Phone hidden' }}
                                            <span v-if="u.has_private_conversation" class="ml-1 text-emerald-600">· Existing chat</span>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto">
                        <div v-if="convMeta.loading && conversations.length === 0" class="p-4 space-y-2">
                            <div v-for="i in 6" :key="`sk-${i}`" class="h-16 rounded-xl bg-slate-100 animate-pulse" />
                        </div>
                        <div v-else-if="conversations.length === 0" class="p-6 text-center text-sm font-semibold text-slate-500">
                            No conversations in this folder.
                        </div>
                        <ul v-else class="divide-y divide-slate-100">
                            <li v-for="c in conversations" :key="c.id">
                                <button
                                    type="button"
                                    class="w-full flex items-start gap-3 px-3 sm:px-4 py-3 text-left transition hover:bg-white"
                                    :class="selectedConversationId === c.id ? 'bg-white shadow-inner border-l-4 border-l-slate-900' : ''"
                                    @click="openConversation(c.id)"
                                >
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="c.type === 'private' && c.peer?.profile_image"
                                            :src="c.peer.profile_image"
                                            alt=""
                                            class="h-12 w-12 rounded-2xl object-cover bg-slate-200"
                                        />
                                        <div
                                            v-else
                                            class="h-12 w-12 rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 text-white flex items-center justify-center text-sm font-black"
                                        >
                                            {{ initialsFromName(c.display_title || c.title) }}
                                        </div>
                                        <span
                                            v-if="c.unread_count > 0"
                                            class="absolute -top-1 -right-1 min-w-[1.25rem] h-5 px-1 rounded-full bg-rose-600 text-white text-[10px] font-black flex items-center justify-center border-2 border-white"
                                        >
                                            {{ c.unread_count > 99 ? '99+' : c.unread_count }}
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-black text-slate-900 truncate flex items-center gap-1.5 min-w-0">
                                                <span
                                                    v-if="c.type === 'group'"
                                                    class="shrink-0 text-[9px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded-md bg-indigo-100 text-indigo-800 border border-indigo-200"
                                                    >Group</span
                                                >
                                                {{ c.display_title || c.title }}
                                            </span>
                                            <span class="text-[10px] font-bold text-slate-400 shrink-0">{{
                                                formatShortTime(c.last_message_at || c.updated_at)
                                            }}</span>
                                        </div>
                                        <p class="mt-0.5 text-xs font-semibold text-slate-500 line-clamp-2">
                                            <template v-if="c.last_message">
                                                <span v-if="!c.last_message.is_mine" class="text-slate-600">{{ c.last_message.sender_name }}: </span>
                                                <span>{{ c.last_message.text }}</span>
                                            </template>
                                            <template v-else>No messages yet</template>
                                        </p>
                                    </div>
                                </button>
                            </li>
                        </ul>
                        <div v-if="conversations.length" class="p-3">
                            <button
                                v-if="convMeta.current_page < convMeta.last_page"
                                type="button"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                                :disabled="convMeta.loading"
                                @click="loadMoreConversations"
                            >
                                {{ convMeta.loading ? 'Loading…' : 'Load more' }}
                            </button>
                        </div>
                    </div>
                </aside>

                <!-- Thread -->
                <section v-show="showThreadPanel" class="flex flex-col flex-1 min-w-0 min-h-0 bg-white">
                    <template v-if="!selectedConversationId">
                        <div class="flex flex-1 flex-col items-center justify-center text-center px-6 py-16 text-slate-500">
                            <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                    />
                                </svg>
                            </div>
                            <p class="text-sm font-black text-slate-800">Select a conversation</p>
                            <p class="mt-1 text-xs font-semibold max-w-sm">Search for someone to start a private chat.</p>
                        </div>
                    </template>

                    <template v-else>
                        <header class="flex items-center gap-3 px-3 sm:px-4 py-3 border-b border-slate-200 bg-white shrink-0">
                            <button
                                type="button"
                                class="lg:hidden h-10 w-10 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 shrink-0"
                                aria-label="Back to conversations"
                                @click="backToList"
                            >
                                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <img
                                v-if="peerAvatar"
                                :src="peerAvatar"
                                alt=""
                                class="h-11 w-11 rounded-2xl object-cover bg-slate-200 shrink-0 hidden sm:block"
                            />
                            <div
                                v-else
                                class="h-11 w-11 rounded-2xl shrink-0 hidden sm:flex items-center justify-center text-sm font-black text-white bg-gradient-to-br"
                                :class="thread?.type === 'group' ? 'from-indigo-600 to-violet-800' : 'from-slate-700 to-slate-900'"
                            >
                                {{ initialsFromName(peerTitle) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-sm sm:text-base font-black text-slate-900 truncate">{{ peerTitle }}</h2>
                                <p class="text-[11px] font-bold text-slate-500 truncate">
                                    <template v-if="thread?.type === 'group'"> {{ participants.length }} members · Group </template>
                                    <template v-else> Private · SuGanta Chat </template>
                                    <span v-if="myMembership?.muted" class="text-amber-700"> · Muted</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 px-2.5 py-1.5 text-[11px] font-black text-slate-700 hover:bg-slate-50"
                                    @click="toggleMute"
                                >
                                    {{ myMembership?.muted ? 'Unmute' : 'Mute' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 px-2.5 py-1.5 text-[11px] font-black text-slate-700 hover:bg-slate-50"
                                    @click="toggleArchive"
                                >
                                    {{ myMembership?.archived ? 'Unarchive' : 'Archive' }}
                                </button>
                            </div>
                        </header>

                        <div
                            v-if="typingLabel"
                            class="shrink-0 px-3 sm:px-4 py-1.5 text-xs font-bold text-slate-500 italic border-b border-slate-100 bg-slate-50/90"
                        >
                            {{ typingLabel }}
                        </div>

                        <div
                            ref="messagesScrollRef"
                            class="flex-1 min-h-0 overflow-y-auto px-3 sm:px-5 py-4 space-y-3 bg-gradient-to-b from-slate-50 to-white"
                            @scroll.passive="onMessagesScroll"
                        >
                            <div v-if="messagesLoading" class="space-y-3">
                                <div v-for="i in 5" :key="`m-sk-${i}`" class="h-16 rounded-2xl bg-slate-100 animate-pulse" />
                            </div>
                            <template v-else>
                                <div v-if="olderLoading" class="text-center text-[11px] font-bold text-slate-400">Loading older…</div>
                                <article
                                    v-for="msg in messages"
                                    :key="msg.id"
                                    class="flex"
                                    :class="isMine(msg) ? 'justify-end' : 'justify-start'"
                                >
                                    <div
                                        class="max-w-[min(100%,520px)] rounded-2xl px-3.5 py-2.5 shadow-sm border text-sm"
                                        :class="
                                            isMine(msg)
                                                ? 'bg-slate-900 text-white border-slate-800'
                                                : 'bg-white text-slate-900 border-slate-200'
                                        "
                                    >
                                        <div v-if="msg.reply_to_message" class="mb-2 rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-[11px]">
                                            <span class="font-black opacity-80">Reply</span>
                                            <span class="block opacity-90 line-clamp-2">{{ msg.reply_to_message.message || 'Unavailable' }}</span>
                                        </div>
                                        <p v-if="msg.deleted_at" class="text-xs font-semibold italic opacity-80">This message was deleted.</p>
                                        <p v-else class="whitespace-pre-wrap break-words font-medium leading-relaxed">{{ msg.message }}</p>
                                        <div class="mt-1 flex items-center justify-between gap-2 text-[10px] font-bold opacity-70">
                                            <span>{{ formatShortTime(msg.created_at) }}</span>
                                            <span v-if="msg.is_edited" class="uppercase tracking-wide">Edited</span>
                                        </div>
                                        <div v-if="!msg.deleted_at" class="mt-2 flex flex-wrap items-center gap-1.5">
                                            <button
                                                type="button"
                                                class="h-7 min-w-[3.5rem] rounded-lg border border-white/10 bg-white/5 text-[11px] font-black leading-none hover:bg-white/10 px-2"
                                                :class="!isMine(msg) ? 'border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700' : ''"
                                                @click="openReactionPicker(msg)"
                                            >
                                                React
                                            </button>
                                            <span
                                                v-for="(r, idx) in msg.reaction_summary || []"
                                                :key="`rs-${msg.id}-${idx}`"
                                                class="text-[11px] font-black rounded-full px-2 py-0.5"
                                                :class="isMine(msg) ? 'bg-white/10' : 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ r.emoji }} {{ r.count }}
                                            </span>
                                            <button
                                                v-if="isMine(msg)"
                                                type="button"
                                                class="ml-auto text-[10px] font-black uppercase tracking-wide opacity-80 hover:opacity-100"
                                                @click="beginEdit(msg)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="isMine(msg)"
                                                type="button"
                                                class="text-[10px] font-black uppercase tracking-wide opacity-80 hover:opacity-100"
                                                @click="removeMessage(msg)"
                                            >
                                                Delete
                                            </button>
                                            <button
                                                v-if="!isMine(msg)"
                                                type="button"
                                                class="text-[10px] font-black uppercase tracking-wide opacity-80 hover:opacity-100"
                                                @click="setReply(msg)"
                                            >
                                                Reply
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>

                        <footer class="border-t border-slate-200 bg-white p-3 sm:p-4 shrink-0">
                            <div v-if="replyTo" class="mb-2 flex items-start justify-between gap-2 rounded-xl bg-slate-50 border border-slate-200 px-3 py-2">
                                <div class="min-w-0">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500">Replying to</div>
                                    <p class="text-xs font-semibold text-slate-700 line-clamp-2">{{ replyTo.message }}</p>
                                </div>
                                <button type="button" class="text-xs font-black text-slate-500 hover:text-slate-800" @click="clearReply">Cancel</button>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
                                <label class="sr-only" for="chat-composer">Message</label>
                                <textarea
                                    id="chat-composer"
                                    v-model="composerText"
                                    rows="2"
                                    maxlength="10000"
                                    placeholder="Write a message…"
                                    class="flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 resize-y min-h-[44px] max-h-40"
                                    @keydown.enter.exact.prevent="sendMessage"
                                    @input="onComposerInput"
                                />
                                <button
                                    type="button"
                                    class="rounded-2xl bg-slate-900 text-white px-5 py-3 text-sm font-black hover:bg-slate-800 transition disabled:opacity-50 shrink-0"
                                    :disabled="sendLoading || !composerText.trim()"
                                    @click="sendMessage"
                                >
                                    {{ sendLoading ? 'Sending…' : 'Send' }}
                                </button>
                            </div>
                            <p class="mt-2 text-[10px] font-bold text-slate-400 text-right">{{ composerText.length }}/10000</p>
                        </footer>
                    </template>
                </section>
            </div>
        </div>

        <Modal :show="showNewChatModal" max-width="xl" @close="closeNewChatModal">
            <div class="px-5 py-5 sm:px-7 sm:py-6 max-h-[min(90vh,720px)] overflow-y-auto">
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Start new chat</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Private: one person. Group: at least two others.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                        aria-label="Close"
                        @click="closeNewChatModal"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="newChatStep === 'pick'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border-2 border-slate-200 bg-white p-5 text-left transition hover:border-slate-900 hover:shadow-md"
                        @click="pickNewChatType('private')"
                    >
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                            </span>
                            <span class="text-base font-black text-slate-900">Private chat</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-600 leading-relaxed">Message one person. Opens an existing DM if you already have one.</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border-2 border-slate-200 bg-white p-5 text-left transition hover:border-indigo-600 hover:shadow-md"
                        @click="pickNewChatType('group')"
                    >
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                    />
                                </svg>
                            </span>
                            <span class="text-base font-black text-slate-900">Group chat</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-600 leading-relaxed">Add a title and at least two people.</p>
                    </button>
                </div>

                <div v-else-if="newChatStep === 'private'" class="space-y-4">
                    <button
                        type="button"
                        class="text-xs font-black text-slate-600 hover:text-slate-900 flex items-center gap-1"
                        @click="pickNewChatType('pick')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500" for="modal-private-search">Find someone</label>
                    <div class="relative">
                        <input
                            id="modal-private-search"
                            v-model="modalPrivateQuery"
                            type="search"
                            autocomplete="off"
                            placeholder="Type at least 2 characters…"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 pr-10 text-sm font-semibold text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        />
                        <span
                            v-if="modalPrivateLoading"
                            class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 rounded-full border-2 border-slate-200 border-t-slate-700 animate-spin"
                        />
                    </div>
                    <div
                        v-if="modalPrivateResults.length"
                        class="max-h-56 overflow-auto rounded-xl border border-slate-200 divide-y divide-slate-100"
                    >
                        <button
                            v-for="u in modalPrivateResults"
                            :key="`modal-p-${u.id}`"
                            type="button"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-slate-50 transition"
                            @click="startDmFromModal(u)"
                        >
                            <img :src="u.profile_image" alt="" class="h-10 w-10 rounded-xl object-cover bg-slate-200 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-black text-slate-900 truncate">{{ u.name }}</div>
                                <div class="text-[11px] font-semibold text-slate-500 truncate">
                                    {{ u.phone || 'Phone hidden' }}
                                    <span v-if="u.has_private_conversation" class="text-emerald-600"> · Open existing</span>
                                </div>
                            </div>
                        </button>
                    </div>
                    <p v-else-if="modalPrivateQuery.trim().length >= 2 && !modalPrivateLoading" class="text-xs font-semibold text-slate-500">No users found.</p>
                </div>

                <div v-else-if="newChatStep === 'group'" class="space-y-4">
                    <button
                        type="button"
                        class="text-xs font-black text-slate-600 hover:text-slate-900 flex items-center gap-1"
                        @click="pickNewChatType('pick')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500 mb-1" for="group-title">Group name (optional)</label>
                        <input
                            id="group-title"
                            v-model="groupTitle"
                            type="text"
                            maxlength="255"
                            placeholder="e.g. Study group"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500 mb-1" for="group-member-search">Add people (min. 2)</label>
                        <div class="relative">
                            <input
                                id="group-member-search"
                                v-model="groupMemberQuery"
                                type="search"
                                autocomplete="off"
                                placeholder="Search by name — 2+ characters…"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 pr-10 text-sm font-semibold text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            />
                            <span
                                v-if="groupSearchLoading"
                                class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 rounded-full border-2 border-slate-200 border-t-indigo-600 animate-spin"
                            />
                        </div>
                    </div>
                    <div v-if="groupMembers.length" class="flex flex-wrap gap-2">
                        <span
                            v-for="m in groupMembers"
                            :key="`gm-${m.id}`"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 pl-2 pr-1 py-1 text-xs font-bold text-slate-800"
                        >
                            <img :src="m.profile_image" alt="" class="h-6 w-6 rounded-full object-cover bg-slate-200" />
                            {{ m.name }}
                            <button
                                type="button"
                                class="rounded-full p-0.5 text-slate-500 hover:bg-slate-200 hover:text-slate-900"
                                aria-label="Remove"
                                @click="removeGroupMember(m.id)"
                            >
                                ×
                            </button>
                        </span>
                    </div>
                    <div
                        v-if="groupSearchResults.filter(u => !groupMembers.some(g => Number(g.id) === Number(u.id))).length"
                        class="max-h-40 overflow-auto rounded-xl border border-slate-200 divide-y divide-slate-100"
                    >
                        <button
                            v-for="u in groupSearchResults.filter(k => !groupMembers.some(g => Number(g.id) === Number(k.id)))"
                            :key="`g-add-${u.id}`"
                            type="button"
                            class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-indigo-50/80 transition"
                            :disabled="Number(u.id) === Number(currentUser?.id)"
                            @click="addGroupMember(u)"
                        >
                            <img :src="u.profile_image" alt="" class="h-9 w-9 rounded-xl object-cover bg-slate-200 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-black text-slate-900 truncate">{{ u.name }}</div>
                                <div class="text-[11px] font-semibold text-slate-500">Tap to add</div>
                            </div>
                            <span class="text-[11px] font-black text-indigo-600 shrink-0">+ Add</span>
                        </button>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-500">
                        Selected: {{ groupMembers.length }} · Need at least 2 others besides you.
                    </p>
                    <button
                        type="button"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white shadow hover:bg-indigo-700 transition disabled:opacity-50"
                        :disabled="groupMembers.length < 2 || creatingGroup"
                        @click="createGroupConversation"
                    >
                        {{ creatingGroup ? 'Creating…' : 'Create group chat' }}
                    </button>
                </div>
            </div>
        </Modal>

        <div
            v-if="reactionPickerOpen && reactionPickerMessage"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-950/50 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl border border-slate-200 p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-black text-slate-900">Choose reaction</h3>
                    <button type="button" class="text-xs font-black text-slate-500 hover:text-slate-800" @click="closeReactionPicker">
                        Close
                    </button>
                </div>

                <input
                    v-model="reactionSearch"
                    type="text"
                    placeholder="Search emoji…"
                    class="w-full mb-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />

                <div class="mb-3 flex flex-wrap gap-1.5">
                    <button
                        v-for="cat in reactionCategories"
                        :key="`cat-${cat.id}`"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-[11px] font-black transition"
                        :class="reactionCategory === cat.id ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                        @click="reactionCategory = cat.id"
                    >
                        {{ cat.label }}
                    </button>
                </div>

                <div v-if="reactionRecent.length && !reactionSearch" class="mb-3">
                    <div class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Recent</div>
                    <div class="grid grid-cols-9 gap-1.5">
                        <button
                            v-for="em in reactionRecent"
                            :key="`recent-${reactionPickerMessage.id}-${em}`"
                            type="button"
                            class="h-9 rounded-lg border border-slate-200 bg-slate-50 text-lg hover:bg-slate-100"
                            @click="chooseReaction(em)"
                        >
                            {{ em }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-9 gap-1.5 max-h-64 overflow-y-auto pr-1">
                    <button
                        v-for="em in filteredReactionChoices"
                        :key="`picker-${reactionPickerMessage.id}-${em}`"
                        type="button"
                        class="h-9 rounded-lg border border-slate-200 bg-slate-50 text-lg hover:bg-slate-100"
                        @click="chooseReaction(em)"
                    >
                        {{ em }}
                    </button>
                </div>
                <p v-if="filteredReactionChoices.length === 0" class="mt-3 text-xs font-semibold text-slate-500">No emoji found for this search.</p>
            </div>
        </div>

        <div
            v-if="editingMessage"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-950/50 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl border border-slate-200 p-4 sm:p-5">
                <h3 class="text-sm font-black text-slate-900">Edit message</h3>
                <textarea
                    v-model="editDraft"
                    rows="4"
                    maxlength="10000"
                    class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50"
                        @click="editingMessage = null"
                    >
                        Cancel
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800" @click="saveEdit">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
