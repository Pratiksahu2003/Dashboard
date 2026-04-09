<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { isEmailVerified, useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';
import { initWebPush, teardownWebPush } from '@/services/firebaseWebPush';
import { connectEcho, subscribeToChatConversation } from '@/services/chatEcho';

const user = ref(null);
const sidebarOpen = ref(false);
const { getUser, getToken, isAuthenticated, clearSession } = useAuth();
const { error: showError, info: showInfo } = useAlerts();

const notifications = ref([]);
const unreadCount = ref(0);
const chatUnreadCount = ref(0);
const isNotificationLoading = ref(false);
const isNotificationOpen = ref(false);
const notificationMenuRef = ref(null);
const navbarAvatarError = ref(false);
const notificationFilter = ref('all');
const notificationPerPage = ref(5);
const notificationPage = ref(1);
const notificationMeta = ref(null);
const chatChannelUnsubs = new Map();
const activePlans = ref([]);
const activePlansLoading = ref(false);
let chatUnreadRefreshTimer = null;
let emailGateRedirectScheduled = false;

const enforceEmailVerifiedOrLeave = () => {
    if (!isAuthenticated()) {
        clearSession();
        router.replace(route('login'));
        return false;
    }
    const stored = getUser();
    if (stored && !isEmailVerified(stored)) {
        if (emailGateRedirectScheduled) return false;
        emailGateRedirectScheduled = true;
        router.replace(route('auth.verify.email'), {
            preserveState: false,
            preserveScroll: false,
        });
        setTimeout(() => {
            emailGateRedirectScheduled = false;
        }, 2000);
        return false;
    }
    user.value = stored;
    return true;
};

const onInertiaFinish = () => {
    if (!enforceEmailVerifiedOrLeave()) return;
    user.value = getUser();
};

onMounted(() => {
    if (!enforceEmailVerifiedOrLeave()) return;
    initWebPush().catch(() => {});
    window.addEventListener('app:push-message', onForegroundPush);
    document.addEventListener('inertia:finish', onInertiaFinish);
    loadNotificationSummary();
    connectEcho(() => getToken());
    loadChatUnreadSummary();
    loadActivePlans();
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('inertia:finish', onInertiaFinish);
    for (const unsub of chatChannelUnsubs.values()) unsub();
    chatChannelUnsubs.clear();
    clearTimeout(chatUnreadRefreshTimer);
    window.removeEventListener('app:push-message', onForegroundPush);
    document.removeEventListener('click', onDocumentClick);
});

const onForegroundPush = event => {
    const detail = event?.detail || {};
    const title = detail?.title || 'Notification';
    const body = detail?.body || 'You have a new update.';
    showInfo(body, title);
    scheduleChatUnreadRefresh();
};

const initials = computed(() => {
    const u = user.value;
    if (!u) return '?';
    const first = (u.first_name || u.name || '').trim();
    const last = (u.last_name || '').trim();
    const a = first ? first[0] : '';
    const b = last ? last[0] : '';
    return (a + b).toUpperCase() || '?';
});

const displayName = computed(() => {
    const u = user.value;
    if (!u) return 'Account';
    if (u.first_name || u.last_name) return `${u.first_name || ''} ${u.last_name || ''}`.trim();
    return u.name || u.email || 'Account';
});
const profilePicUrl = computed(() => (user.value?.profile_pic || '').trim());
const normalizedRole = computed(() => String(user.value?.role || '').trim().toLowerCase());
const instituteMenuLabel = computed(() => (normalizedRole.value === 'institute' ? 'Institute' : 'University'));

const currentRoute = computed(() => {
    try {
        return route().current();
    } catch {
        return '';
    }
});

const logout = async () => {
    await teardownWebPush().catch(() => {});
    api.post('/auth/logout')
        .catch(() => {})
        .finally(() => {
            clearSession();
            router.visit(route('login'));
        });
};

const navItems = computed(() => ([
    { id: 'dashboard', label: 'Dashboard', icon: 'M3 12h18M3 6h18M3 18h18', href: 'dashboard' },
    { id: 'ai-adviser', label: 'AI Adviser', icon: 'M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 16.8 7.1 19.2 8 13.7 4 9.8l5.5-.8L12 3zm7 14h2m-1-1v2M3 17h2m-1-1v2', href: 'ai-adviser' },
    { id: 'leads', label: 'Leads', icon: 'M17 20h5V4H2v16h5m10 0V10H7v10m10 0H7', href: 'leads' },
    { id: 'portfolio', label: 'Portfolio', icon: 'M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm3 4h8m-8 4h8m-8 4h5', href: 'portfolio' },
    { id: 'study-requirements', label: 'Study Requirements', icon: 'M4 6h16M4 12h10M4 18h7m11-2l2 2 4-4', href: 'study-requirements' },
    { id: 'notes', label: 'Notes', icon: 'M5 5h14v14H5zM8 8h8M8 12h8M8 16h6', href: 'notes' },
    { id: 'marketplace', label: 'Marketplace', icon: 'M3 7h18M5 7l1 12h12l1-12M9 11v4M15 11v4', href: 'marketplace' },
    { id: 'subscriptions', label: 'Subscriptions', icon: 'M4 7h16M4 12h16M4 17h10M17 17l2 2 4-4', href: 'subscriptions' },
    { id: 'payments', label: 'Payments', icon: 'M12 8c-3.314 0-6 1.343-6 3s2.686 3 6 3 6-1.343 6-3-2.686-3-6-3zm0 0V6m0 8v2m-6-5v5c0 1.657 2.686 3 6 3s6-1.343 6-3v-5', href: 'payments' },
    { id: 'google-workspace', label: 'Google Workspace', icon: 'M21.35 11.1H12v2.92h5.35c-.57 3.03-3.25 4.67-5.35 4.67a6 6 0 010-12c1.7 0 3.04.73 3.9 1.53l2.13-2.06A8.88 8.88 0 0012 3a9 9 0 100 18c5.2 0 8.63-3.65 8.63-8.8 0-.59-.06-1.03-.14-1.1z', href: 'google-workspace' },
    { id: 'chat', label: 'Chat', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', href: 'chat' },
    { id: 'profile', label: 'Profile', icon: 'M5.121 17.804A8.962 8.962 0 0112 15c2.347 0 4.483.902 6.08 2.38M15 11a3 3 0 11-6 0 3 3 0 016 0z', href: 'profile' },
    { id: 'support-tickets', label: 'Support Tickets', icon: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', href: 'support-tickets' },
    { id: 'notifications', label: 'Notifications', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', href: 'notifications' },
]));

const isItemActive = item => {
    if (!item.href) return false;
    if (item.href === 'support-tickets') {
        return currentRoute.value === 'support-tickets' || currentRoute.value === 'support-ticket-details' || currentRoute.value === 'support-tickets-create';
    }
    return currentRoute.value === item.href;
};

const formatDateTime = value => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString();
};

const formatDateOnly = value => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString();
};

const loadNotificationSummary = async () => {
    isNotificationLoading.value = true;

    try {
        const [allResponse, unreadResponse] = await Promise.all([
            api.get('/notifications', {
                params: {
                    per_page: notificationPerPage.value,
                    page: notificationPage.value,
                    filter: notificationFilter.value,
                },
            }),
            api.get('/notifications', {
                params: { per_page: 1, page: 1, filter: 'unread' },
            }),
        ]);

        notifications.value = allResponse?.data?.data || [];
        notificationMeta.value = allResponse?.data?.meta || null;
        unreadCount.value = unreadResponse?.data?.meta?.total ?? 0;
    } catch (error) {
        showError(error?.message || 'Unable to load notifications.', 'Notification Error');
    } finally {
        isNotificationLoading.value = false;
    }
};

const scheduleChatUnreadRefresh = () => {
    clearTimeout(chatUnreadRefreshTimer);
    chatUnreadRefreshTimer = setTimeout(() => {
        loadChatUnreadSummary();
    }, 250);
};

const syncChatRealtimeSubscriptions = rows => {
    const echo = connectEcho(() => getToken());
    if (!echo || !Array.isArray(rows)) return;

    const ids = new Set(rows.map(r => Number(r?.id)).filter(n => Number.isFinite(n) && n > 0));

    for (const [id, unsub] of chatChannelUnsubs.entries()) {
        if (!ids.has(id)) {
            unsub();
            chatChannelUnsubs.delete(id);
        }
    }

    for (const id of ids) {
        if (chatChannelUnsubs.has(id)) continue;
        const unsub = subscribeToChatConversation(echo, id, {
            onMessageSent: scheduleChatUnreadRefresh,
            onMessageRead: scheduleChatUnreadRefresh,
            onReadState: scheduleChatUnreadRefresh,
            onSubscriptionError: () => {},
        });
        chatChannelUnsubs.set(id, unsub);
    }
};

const loadChatUnreadSummary = async () => {
    try {
        const response = await api.get('https://www.suganta.in/api/v3/chat/conversations', {
            params: { folder: 'inbox', page: 1 },
        });
        const rows = response?.data?.data || [];
        chatUnreadCount.value = rows.reduce((sum, row) => sum + Number(row?.unread_count || 0), 0);
        syncChatRealtimeSubscriptions(rows);
    } catch {
        chatUnreadCount.value = 0;
    }
};

const onDocumentClick = event => {
    const root = notificationMenuRef.value;
    if (!root) return;
    if (!root.contains(event.target)) {
        isNotificationOpen.value = false;
    }
};

const toggleNotifications = () => {
    isNotificationOpen.value = !isNotificationOpen.value;
    if (isNotificationOpen.value && notifications.value.length === 0) {
        loadNotificationSummary();
    }
};

const applyNotificationFilter = () => {
    notificationPage.value = 1;
    loadNotificationSummary();
};

const nextNotificationPage = () => {
    const current = notificationMeta.value?.current_page || 1;
    const last = notificationMeta.value?.last_page || 1;
    if (current >= last) return;
    notificationPage.value = current + 1;
    loadNotificationSummary();
};

const prevNotificationPage = () => {
    const current = notificationMeta.value?.current_page || 1;
    if (current <= 1) return;
    notificationPage.value = current - 1;
    loadNotificationSummary();
};

const onNavbarAvatarError = () => {
    navbarAvatarError.value = true;
};

const loadActivePlans = async () => {
    activePlansLoading.value = true;
    try {
        const response = await api.get('/subscriptions/my-subscriptions', {
            params: {
                status: 'active',
                per_page: 50,
                page: 1,
            },
        });
        const rows = response?.data?.data || [];
        activePlans.value = rows
            .map(row => ({
                id: row?.id,
                planName: row?.plan?.name || 'Active Plan',
                expiresAt: row?.expires_at || null,
            }))
            .filter(row => row.id);
    } catch {
        activePlans.value = [];
    } finally {
        activePlansLoading.value = false;
    }
};
</script>

<template>
    <div class="h-screen overflow-hidden bg-slate-100">
        <div class="flex h-full">
            <aside class="hidden lg:flex lg:w-72 bg-white border-r border-slate-200 text-slate-900 flex-col p-5 h-full overflow-y-auto">
                <Link :href="route('dashboard')" class="flex items-center mb-8">
                    <img src="/logo/Su250.png" alt="SuGanta" class="h-10 w-auto" />
                </Link>

                <nav class="space-y-2">
                    <component
                        :is="item.href ? Link : 'button'"
                        v-for="item in navItems"
                        :key="item.id"
                        :href="item.href ? route(item.href) : undefined"
                        type="button"
                        :class="[
                            'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-sm font-bold transition',
                            isItemActive(item)
                                ? 'bg-slate-900 text-white shadow-sm'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                        ]"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.id === 'notifications' && unreadCount > 0"
                            class="ml-auto text-[10px] font-black rounded-md px-1.5 py-0.5 bg-rose-100 text-rose-700 border border-rose-200"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </component>
                </nav>

                <Link :href="route('subscriptions')" class="mt-auto block rounded-2xl bg-slate-50 p-4 border border-slate-200 hover:bg-slate-100 transition">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs font-black text-slate-500 uppercase tracking-widest">Your Plans</div>
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-wide">Manage</span>
                    </div>
                    <div v-if="activePlansLoading" class="mt-2 space-y-1.5">
                        <div class="h-4 rounded bg-slate-200 animate-pulse"></div>
                        <div class="h-4 w-3/4 rounded bg-slate-200 animate-pulse"></div>
                    </div>
                    <div v-else-if="activePlans.length" class="mt-2 space-y-2">
                        <div
                            v-for="plan in activePlans.slice(0, 3)"
                            :key="`active-plan-${plan.id}`"
                            class="min-w-0"
                        >
                            <p class="text-sm font-black text-slate-900 truncate">{{ plan.planName }}</p>
                            <p class="text-[11px] font-semibold text-slate-600">
                                Expires: {{ formatDateOnly(plan.expiresAt) }}
                            </p>
                        </div>
                        <p v-if="activePlans.length > 3" class="text-xs font-semibold text-slate-600">
                            +{{ activePlans.length - 3 }} more active plans
                        </p>
                    </div>
                    <p v-else class="mt-2 text-xs font-medium text-slate-600">
                        No active subscription plans. Open Subscriptions to choose a plan.
                    </p>
                </Link>
                <button
                    type="button"
                    @click="logout"
                    class="mt-3 w-full px-3 py-2.5 rounded-xl text-sm font-black text-slate-700 hover:text-slate-900 hover:bg-slate-100 border border-slate-200 transition"
                >
                    Log out
                </button>
            </aside>

            <div class="flex-1 min-w-0 h-full overflow-hidden flex flex-col">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div class="h-16 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="lg:hidden h-9 w-9 rounded-lg border border-slate-200 text-slate-700"
                                @click="sidebarOpen = true"
                                aria-label="Open navigation menu"
                            >
                                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                            <span class="text-sm font-black text-slate-900"><slot name="breadcrumb">Dashboard</slot></span>
                        </div>

                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('chat')"
                                class="relative h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition"
                                aria-label="Open chat"
                            >
                                <svg class="w-4 h-4 mx-auto mt-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <span
                                    v-if="chatUnreadCount > 0"
                                    class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-emerald-600 text-white text-[10px] leading-5 font-black border-2 border-white"
                                >
                                    {{ chatUnreadCount > 99 ? '99+' : chatUnreadCount }}
                                </span>
                            </Link>

                            <div ref="notificationMenuRef" class="relative">
                                <button
                                    type="button"
                                    class="relative h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition"
                                    @click="toggleNotifications"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <span
                                        v-if="unreadCount > 0"
                                        class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-rose-600 text-white text-[10px] leading-5 font-black border-2 border-white"
                                    >
                                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                                    </span>
                                </button>

                                <div
                                    v-if="isNotificationOpen"
                                    class="absolute right-0 mt-2 w-80 rounded-2xl border border-slate-200 bg-white shadow-lg p-3 z-40"
                                >
                                    <div class="mb-2 flex items-center justify-between">
                                        <h3 class="text-sm font-black text-slate-900">Notifications</h3>
                                        <button
                                            type="button"
                                            class="text-xs font-bold text-slate-500 hover:text-slate-700"
                                            @click="loadNotificationSummary"
                                        >
                                            Refresh
                                        </button>
                                    </div>

                                    <div class="mb-2 grid grid-cols-3 gap-2">
                                        <select v-model="notificationFilter" class="col-span-2 rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold text-slate-700 bg-white">
                                            <option value="all">All</option>
                                            <option value="read">Read</option>
                                            <option value="unread">Unread</option>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition"
                                            @click="applyNotificationFilter"
                                        >
                                            Apply
                                        </button>
                                    </div>

                                    <div v-if="isNotificationLoading" class="space-y-2">
                                        <div v-for="i in 4" :key="`notif-skeleton-${i}`" class="h-14 rounded-lg bg-slate-100 animate-pulse"></div>
                                    </div>

                                    <div v-else-if="notifications.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-xs font-semibold text-slate-600 text-center">
                                        No notifications found.
                                    </div>

                                    <div v-else class="space-y-2 max-h-80 overflow-auto">
                                        <article v-for="item in notifications" :key="item.id" class="rounded-lg border border-slate-200 p-2.5">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="text-xs font-black text-slate-900">{{ item.title || 'Notification' }}</h4>
                                                <span
                                                    class="text-[10px] font-black rounded px-1.5 py-0.5 border uppercase"
                                                    :class="item.read_at ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-blue-50 text-blue-700 border-blue-200'"
                                                >
                                                    {{ item.read_at ? 'Read' : 'Unread' }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs font-medium text-slate-600 line-clamp-2">{{ item.message || '-' }}</p>
                                            <p class="mt-1 text-[11px] font-bold text-slate-500">{{ formatDateTime(item.created_at) }}</p>
                                        </article>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between">
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                                            :disabled="(notificationMeta?.current_page || 1) <= 1 || isNotificationLoading"
                                            @click="prevNotificationPage"
                                        >
                                            Prev
                                        </button>
                                        <span class="text-[11px] font-bold text-slate-500">
                                            Page {{ notificationMeta?.current_page || 1 }}/{{ notificationMeta?.last_page || 1 }}
                                        </span>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                                            :disabled="(notificationMeta?.current_page || 1) >= (notificationMeta?.last_page || 1) || isNotificationLoading"
                                            @click="nextNotificationPage"
                                        >
                                            Next
                                        </button>
                                    </div>

                                    <Link
                                        :href="route('notifications')"
                                        class="mt-3 block text-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50 transition"
                                        @click="isNotificationOpen = false"
                                    >
                                        View all notifications
                                    </Link>
                                </div>
                            </div>

                            <Link
                                :href="route('ai-adviser')"
                                class="hidden sm:inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-blue-50 px-3 py-2 text-xs font-black text-indigo-700 hover:from-indigo-100 hover:to-blue-100 transition"
                            >
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-white text-[10px] font-black">AI</span>
                                <span>AI Adviser</span>
                            </Link>

                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>

        <div v-if="sidebarOpen" class="lg:hidden fixed inset-0 z-50">
            <div class="absolute inset-0 bg-slate-950/60" @click="sidebarOpen = false"></div>
            <aside class="absolute left-0 top-0 h-full w-72 bg-white text-slate-900 p-5 border-r border-slate-200">
                <div class="flex items-center justify-between mb-6">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <img src="/logo/Su250.png" alt="SuGanta" class="h-9 w-auto" />
                    </Link>
                    <button class="h-8 w-8 rounded-lg border border-slate-300 text-slate-600" @click="sidebarOpen = false">x</button>
                </div>
                <nav class="space-y-2">
                    <component
                        :is="item.href ? Link : 'button'"
                        v-for="item in navItems"
                        :key="`mobile-${item.id}`"
                        :href="item.href ? route(item.href) : undefined"
                        type="button"
                        :class="[
                            'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-sm font-bold transition',
                            isItemActive(item)
                                ? 'bg-slate-900 text-white shadow-sm'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.id === 'notifications' && unreadCount > 0"
                            class="ml-auto text-[10px] font-black rounded-md px-1.5 py-0.5 bg-rose-100 text-rose-700 border border-rose-200"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </component>
                </nav>
                <button
                    type="button"
                    @click="logout"
                    class="mt-4 w-full px-3 py-2.5 rounded-xl text-sm font-black text-slate-700 hover:text-slate-900 hover:bg-slate-100 border border-slate-200 transition"
                >
                    Log out
                </button>
            </aside>
        </div>
    </div>
</template>
