<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';

const { requireAuth } = useAuth();
const { error: showError } = useAlerts();

const isLoading = ref(false);
const items = ref([]);
const meta = ref(null);

const filter = ref('all');
const perPage = ref(15);
const page = ref(1);
const tabCounts = ref({
    all: 0,
    read: 0,
    unread: 0,
});
const tabs = [
    { key: 'all', label: 'All' },
    { key: 'read', label: 'Read' },
    { key: 'unread', label: 'Unread' },
];

const hasPrev = computed(() => (meta.value?.current_page || 1) > 1);
const hasNext = computed(() => (meta.value?.current_page || 1) < (meta.value?.last_page || 1));

const formatDateTime = value => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString();
};

const priorityClass = priority => {
    const value = String(priority || '').toLowerCase();
    if (value === 'high') return 'bg-rose-50 text-rose-700 border-rose-200';
    if (value === 'low') return 'bg-sky-50 text-sky-700 border-sky-200';
    return 'bg-slate-100 text-slate-700 border-slate-200';
};

const loadNotifications = async () => {
    isLoading.value = true;

    try {
        const response = await api.get('/notifications', {
            params: {
                filter: filter.value,
                per_page: perPage.value,
                page: page.value,
            },
        });
        items.value = response?.data?.data || [];
        meta.value = response?.data?.meta || null;
    } catch (error) {
        showError(error?.message || 'Unable to load notifications.', 'Notification Error');
    } finally {
        isLoading.value = false;
    }
};

const loadTabCounts = async () => {
    try {
        const [allRes, readRes, unreadRes] = await Promise.all([
            api.get('/notifications', { params: { filter: 'all', per_page: 1, page: 1 } }),
            api.get('/notifications', { params: { filter: 'read', per_page: 1, page: 1 } }),
            api.get('/notifications', { params: { filter: 'unread', per_page: 1, page: 1 } }),
        ]);

        tabCounts.value = {
            all: allRes?.data?.meta?.total ?? 0,
            read: readRes?.data?.meta?.total ?? 0,
            unread: unreadRes?.data?.meta?.total ?? 0,
        };
    } catch {
        tabCounts.value = {
            all: tabCounts.value.all || 0,
            read: tabCounts.value.read || 0,
            unread: tabCounts.value.unread || 0,
        };
    }
};

const applyFilters = () => {
    page.value = 1;
    loadNotifications();
};

const setFilterTab = value => {
    if (filter.value === value) return;
    filter.value = value;
    page.value = 1;
    loadNotifications();
};

const nextPage = () => {
    if (!hasNext.value) return;
    page.value += 1;
    loadNotifications();
};

const prevPage = () => {
    if (!hasPrev.value) return;
    page.value -= 1;
    loadNotifications();
};

onMounted(() => {
    if (!requireAuth()) return;
    loadNotifications();
    loadTabCounts();
});
</script>

<template>
    <Head title="Notifications" />

    <AppLayout>
        <template #breadcrumb>Notifications</template>

        <div class="space-y-5">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-xl font-black text-slate-900">Notifications</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">Filter and browse all your account notifications.</p>

                        <div class="mt-4 inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="px-3 py-1.5 text-xs font-black rounded-lg transition"
                                :class="filter === tab.key ? 'bg-white text-slate-900 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'"
                                :disabled="isLoading"
                                @click="setFilterTab(tab.key)"
                            >
                                {{ tab.label }}
                                <span class="ml-1 text-[11px] opacity-80">({{ tabCounts[tab.key] ?? 0 }})</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                            <div class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 mb-2">Per Page</div>
                            <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-1">
                                <button
                                    v-for="size in [10, 15, 20, 50]"
                                    :key="`per-page-${size}`"
                                    type="button"
                                    class="px-2.5 py-1.5 text-xs font-black rounded-md transition"
                                    :class="perPage === size ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                                    :disabled="isLoading"
                                    @click="perPage = size; applyFilters()"
                                >
                                    {{ size }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5">
                <div v-if="isLoading" class="space-y-3">
                    <div v-for="i in 8" :key="`notif-loading-${i}`" class="h-20 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>

                <div v-else-if="items.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-600">No notifications for this filter.</p>
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="notification in items"
                        :key="notification.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-black text-slate-900">{{ notification.title || 'Notification' }}</h3>
                                <p class="mt-1 text-sm font-medium text-slate-600 break-words">{{ notification.message || '-' }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase"
                                    :class="priorityClass(notification.priority)"
                                >
                                    {{ notification.priority || 'normal' }}
                                </span>
                                <span
                                    class="text-[11px] font-black rounded-md border px-2 py-0.5 uppercase"
                                    :class="notification.read_at ? 'text-slate-600 bg-slate-100 border-slate-200' : 'text-blue-700 bg-blue-50 border-blue-200'"
                                >
                                    {{ notification.read_at ? 'Read' : 'Unread' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-2 text-[11px] font-bold text-slate-500">
                            {{ formatDateTime(notification.created_at) }}
                        </div>
                    </article>
                </div>
            </section>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs font-bold text-slate-500">
                    Showing {{ meta?.from || 0 }}-{{ meta?.to || 0 }} of {{ meta?.total || 0 }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                        :disabled="!hasPrev || isLoading"
                        @click="prevPage"
                    >
                        Previous
                    </button>
                    <span class="text-xs font-black text-slate-700">
                        Page {{ meta?.current_page || 1 }} / {{ meta?.last_page || 1 }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-50"
                        :disabled="!hasNext || isLoading"
                        @click="nextPage"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
