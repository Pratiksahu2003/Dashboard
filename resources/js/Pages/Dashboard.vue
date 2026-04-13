<script setup>
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useGoogleWorkspaceApi } from '@/composables/useGoogleWorkspaceApi';
import GoogleConnectModal from '@/Components/GoogleConnectModal.vue';
import api from '@/api';

const { requireAuth } = useAuth();
const { error: showError } = useAlerts();
const { getCalendarEvents } = useGoogleWorkspaceApi();
const dashboardData = ref(null);
const latestLeadsPercent = ref(10);
const isLoading = ref(false);
const isLeadLoading = ref(false);
const lastLoadedAt = ref('');
const dashboardAvatarError = ref(false);
const calendarData = ref(null);
const calendarLoading = ref(false);
const dashboardCalendarMonth = ref(new Date());
const isGoogleConnectModalOpen = ref(false);

const dashboardCalendarItems = computed(() => (Array.isArray(calendarData.value?.items) ? calendarData.value.items : []));
const dashboardCalendarMonthLabel = computed(() => dashboardCalendarMonth.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const dashboardWeekDayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const dashboardSelectedEvent = ref(null);

const counts = computed(() => dashboardData.value?.counts || {});
const latestLeads = computed(() => {
    const data = dashboardData.value;
    if (Array.isArray(data?.recent_leads)) return data.recent_leads;
    if (Array.isArray(data?.latest_leads?.data)) return data.latest_leads.data;
    return [];
});
const leadMeta = computed(() => {
    const data = dashboardData.value;
    if (data?.latest_leads?.meta) return data.latest_leads.meta;
    const total = counts.value.leads ?? latestLeads.value.length;
    return {
        total,
        returned: latestLeads.value.length,
    };
});
const recentPayments = computed(() => dashboardData.value?.recent_payments || []);
const latestNotifications = computed(() => dashboardData.value?.latest_notifications || []);
const user = computed(() => dashboardData.value?.user || null);
const dashboardProfilePicUrl = computed(() => (user.value?.profile_pic || '').trim());

const countCards = computed(() => ([
    {
        key: 'support_tickets',
        label: 'Support Tickets',
        value: counts.value.support_tickets ?? 0,
        accent: 'text-blue-700 bg-blue-50 border-blue-100',
    },
    {
        key: 'payments',
        label: 'Payments',
        value: counts.value.payments ?? 0,
        accent: 'text-emerald-700 bg-emerald-50 border-emerald-100',
    },
    {
        key: 'leads',
        label: 'Leads',
        value: counts.value.leads ?? 0,
        accent: 'text-violet-700 bg-violet-50 border-violet-100',
    },
    {
        key: 'study_requirements',
        label: 'Study Requirements',
        value: counts.value.study_requirements ?? 0,
        accent: 'text-amber-700 bg-amber-50 border-amber-100',
    },
]));

const getInitials = value => {
    if (!value) return '?';
    const first = (value.first_name || '').trim();
    const last = (value.last_name || '').trim();
    if (first || last) return `${first[0] || ''}${last[0] || ''}`.toUpperCase();
    const email = (value.email || '').trim();
    return email ? email[0].toUpperCase() : '?';
};

const formatDateTime = value => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString();
};

const formatDate = value => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString();
};

const formatMoney = (amount, currency) => {
    const numericAmount = Number(amount ?? 0);
    if (Number.isNaN(numericAmount)) return '-';
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency || 'INR',
            maximumFractionDigits: 2,
        }).format(numericAmount);
    } catch {
        return `${currency || 'INR'} ${numericAmount.toFixed(2)}`;
    }
};

const statusBadgeClass = status => {
    const value = String(status || '').toLowerCase();
    if (value === 'success') return 'text-emerald-700 bg-emerald-50 border-emerald-200';
    if (value === 'pending') return 'text-amber-700 bg-amber-50 border-amber-200';
    if (value === 'failed') return 'text-rose-700 bg-rose-50 border-rose-200';
    return 'text-slate-700 bg-slate-100 border-slate-200';
};

const notificationPriorityClass = priority => {
    const value = String(priority || '').toLowerCase();
    if (value === 'high') return 'text-rose-700 bg-rose-50 border-rose-200';
    if (value === 'low') return 'text-sky-700 bg-sky-50 border-sky-200';
    return 'text-slate-700 bg-slate-100 border-slate-200';
};

const leadStatusClass = status => {
    const value = String(status || '').toLowerCase();
    if (['won', 'converted', 'qualified'].includes(value)) return 'text-emerald-700 bg-emerald-50 border-emerald-200';
    if (['new', 'open'].includes(value)) return 'text-blue-700 bg-blue-50 border-blue-200';
    if (['lost', 'rejected', 'closed'].includes(value)) return 'text-rose-700 bg-rose-50 border-rose-200';
    return 'text-slate-700 bg-slate-100 border-slate-200';
};

const onDashboardAvatarError = () => {
    dashboardAvatarError.value = true;
};

const normalizeDateTime = value => {
    if (!value) return null;
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return null;
    return parsed;
};

const calendarDateKey = value => {
    const parsed = normalizeDateTime(value);
    if (!parsed) return '';
    const year = parsed.getFullYear();
    const month = `${parsed.getMonth() + 1}`.padStart(2, '0');
    const day = `${parsed.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const dashboardCalendarEventsByDate = computed(() => {
    const map = new Map();
    dashboardCalendarItems.value.forEach(event => {
        const key = calendarDateKey(event?.start?.dateTime || event?.start?.date);
        if (!key) return;
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(event);
    });
    return map;
});

const dashboardCalendarGridStart = computed(() => {
    const value = new Date(dashboardCalendarMonth.value.getFullYear(), dashboardCalendarMonth.value.getMonth(), 1);
    value.setDate(value.getDate() - value.getDay());
    return value;
});

const dashboardMonthCells = computed(() => {
    const result = [];
    const start = new Date(dashboardCalendarGridStart.value);
    const month = dashboardCalendarMonth.value.getMonth();
    const todayKey = calendarDateKey(new Date());

    for (let i = 0; i < 42; i++) {
        const cellDate = new Date(start);
        cellDate.setDate(start.getDate() + i);
        const key = calendarDateKey(cellDate);
        result.push({
            key: `${key}-${i}`,
            day: cellDate.getDate(),
            isCurrentMonth: cellDate.getMonth() === month,
            isToday: key === todayKey,
            events: dashboardCalendarEventsByDate.value.get(key) || [],
        });
    }
    return result;
});

const dashboardCalendarEventList = computed(() => {
    return [...dashboardCalendarItems.value].sort((a, b) => {
        const aTime = normalizeDateTime(a?.start?.dateTime || a?.start?.date)?.getTime() || 0;
        const bTime = normalizeDateTime(b?.start?.dateTime || b?.start?.date)?.getTime() || 0;
        return aTime - bTime;
    });
});
const dashboardCalendarEventListPreview = computed(() => dashboardCalendarEventList.value.slice(0, 8));

const dashboardChangeMonth = step => {
    const next = new Date(dashboardCalendarMonth.value);
    next.setMonth(next.getMonth() + step);
    dashboardCalendarMonth.value = next;
};

const dashboardGoToToday = () => {
    dashboardCalendarMonth.value = new Date();
};

const loadDashboardCalendar = async () => {
    calendarLoading.value = true;
    try {
        calendarData.value = await getCalendarEvents({
            calendar: { max_results: 60 },
        });
    } catch (error) {
        const message = error?.response?.data?.message || error?.message || '';
        if (message.includes('not connected') || message.includes('refresh token')) {
            isGoogleConnectModalOpen.value = true;
        } else {
            showError('Unable to load dashboard calendar events.', 'Dashboard Calendar');
        }
    } finally {
        calendarLoading.value = false;
    }
};

const fetchDashboard = async () => {
    const percent = Number(latestLeadsPercent.value);
    latestLeadsPercent.value = Number.isFinite(percent) ? Math.min(100, Math.max(1, Math.round(percent))) : 10;

    isLoading.value = true;
    isLeadLoading.value = true;

    try {
        const response = await api.get('/dashboard', {
            params: {
                latest_leads_percent: latestLeadsPercent.value,
            },
        });
        dashboardData.value = response?.data || null;
        dashboardAvatarError.value = false;
        lastLoadedAt.value = new Date().toISOString();
    } catch (error) {
        showError(error?.message || 'Unable to load dashboard data.', 'Dashboard Error');
    } finally {
        isLoading.value = false;
        isLeadLoading.value = false;
    }
};

onMounted(() => {
    fetchDashboard();
    loadDashboardCalendar();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #breadcrumb>Dashboard</template>

        <div class="space-y-5">
            <div class="rounded-3xl bg-gradient-to-br from-blue-600 via-indigo-700 to-indigo-900 p-6 text-white shadow-sm relative overflow-hidden">
                <div class="absolute inset-0 opacity-35 mesh-pattern"></div>
                <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xs font-black uppercase tracking-[0.24em] text-white/70">Dashboard Overview</div>
                        <h1 class="mt-1 text-2xl md:text-3xl font-black tracking-tight">
                            Welcome{{ user?.first_name ? `, ${user.first_name}` : '' }}
                        </h1>
                        <p class="mt-2 text-sm font-medium text-white/90">
                            Monitor your latest leads, payments, tickets, and notifications from one place.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 rounded-2xl bg-white/10 backdrop-blur px-4 py-3 border border-white/20">
                        <div class="h-10 w-10 rounded-xl overflow-hidden bg-white/20 flex items-center justify-center text-sm font-black">
                            <img
                                v-if="dashboardProfilePicUrl && !dashboardAvatarError"
                                :src="dashboardProfilePicUrl"
                                alt="Profile"
                                class="h-full w-full object-cover"
                                @error="onDashboardAvatarError"
                            />
                            <span v-else>{{ getInitials(user) }}</span>
                        </div>
                        <div class="leading-tight">
                            <div class="text-sm font-black">{{ user?.first_name }} {{ user?.last_name }}</div>
                            <div class="text-xs font-semibold text-white/75">{{ user?.email || 'No email available' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <section
                class="relative overflow-hidden rounded-3xl border border-indigo-200/60 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-5 shadow-[0_12px_40px_-12px_rgba(79,70,229,0.25)] sm:p-6"
                aria-label="Featured platform areas"
            >
                <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-violet-400/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-indigo-400/15 blur-3xl"></div>
                <div class="relative">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-indigo-600">Explore the platform</p>
                            <h2 class="mt-1 text-lg font-black tracking-tight text-slate-900 sm:text-xl">
                                Teachers, institutes, AI adviser, and marketplace
                            </h2>
                            <p class="mt-1 max-w-2xl text-sm font-semibold text-slate-600">
                                Jump into the experiences that matter most: public directories, intelligent guidance, and resources.
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Link
                            :href="route('teachers')"
                            class="dash-spotlight-card dash-spotlight-card--teachers group"
                        >
                            <div class="dash-spotlight-icon-wrap dash-spotlight-icon-wrap--teachers">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                            <h3 class="mt-3 text-base font-black text-slate-900">Teachers</h3>
                            <p class="mt-1 text-sm font-semibold leading-snug text-slate-600">
                                Find tutors, compare profiles, and connect with educators.
                            </p>
                            <span class="dash-spotlight-cta">Browse directory</span>
                        </Link>
                        <Link
                            :href="route('institutes')"
                            class="dash-spotlight-card dash-spotlight-card--institutes group"
                        >
                            <div class="dash-spotlight-icon-wrap dash-spotlight-icon-wrap--institutes">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <h3 class="mt-3 text-base font-black text-slate-900">Institutes</h3>
                            <p class="mt-1 text-sm font-semibold leading-snug text-slate-600">
                                Explore schools and coaching centres with rich profiles.
                            </p>
                            <span class="dash-spotlight-cta">View institutes</span>
                        </Link>
                        <Link
                            :href="route('ai-adviser')"
                            class="dash-spotlight-card dash-spotlight-card--ai group"
                        >
                            <div class="dash-spotlight-icon-wrap dash-spotlight-icon-wrap--ai">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <h3 class="mt-3 text-base font-black text-slate-900">AI Adviser</h3>
                            <p class="mt-1 text-sm font-semibold leading-snug text-slate-600">
                                Get smart guidance tailored to your learning goals.
                            </p>
                            <span class="dash-spotlight-cta">Open adviser</span>
                        </Link>
                        <Link
                            :href="route('marketplace')"
                            class="dash-spotlight-card dash-spotlight-card--marketplace group"
                        >
                            <div class="dash-spotlight-icon-wrap dash-spotlight-icon-wrap--marketplace">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                            </div>
                            <h3 class="mt-3 text-base font-black text-slate-900">Marketplace</h3>
                            <p class="mt-1 text-sm font-semibold leading-snug text-slate-600">
                                Discover resources, offers, and tools in one place.
                            </p>
                            <span class="dash-spotlight-cta">Go to marketplace</span>
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div
                    v-for="card in countCards"
                    :key="card.key"
                    :class="['rounded-2xl border p-4 shadow-sm bg-white', card.accent]"
                >
                    <div class="text-[11px] font-black uppercase tracking-[0.18em] opacity-80">{{ card.label }}</div>
                    <div class="mt-2 text-3xl font-black">{{ card.value }}</div>
                </div>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-base font-black text-slate-900">Download Our App</h2>
                        <p class="text-xs font-semibold text-slate-500">Install SuGanta on your mobile device.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a
                        href="https://www.suganta.com/app"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 hover:border-emerald-300 transition"
                    >
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">Android</p>
                        <h3 class="mt-1 text-lg font-black text-emerald-900">Download for Android</h3>
                        <p class="mt-1 text-sm font-semibold text-emerald-800">Tap to download the latest Android app build.</p>
                        <span class="mt-3 inline-flex rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-black text-emerald-800">
                            Download Now
                        </span>
                    </a>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-600">iOS</p>
                        <h3 class="mt-1 text-lg font-black text-slate-900">iOS App</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Our iOS app is under preparation and will be available soon.</p>
                        <span class="mt-3 inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-black text-slate-600">
                            Coming Soon
                        </span>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-black text-slate-900">Calendar</h2>
                        <p class="text-xs font-semibold text-slate-500">Quick month view and upcoming events on dashboard.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('google-workspace.calendar')" class="dash-calendar-btn">
                            Open Full Calendar
                        </Link>
                        <button type="button" class="dash-calendar-btn" @click="dashboardGoToToday">Today</button>
                        <button type="button" class="dash-calendar-icon-btn" @click="dashboardChangeMonth(-1)">&#8249;</button>
                        <button type="button" class="dash-calendar-icon-btn" @click="dashboardChangeMonth(1)">&#8250;</button>
                        <span class="text-sm font-black text-slate-800">{{ dashboardCalendarMonthLabel }}</span>
                        <button type="button" class="dash-calendar-btn" :disabled="calendarLoading" @click="loadDashboardCalendar">
                            {{ calendarLoading ? 'Loading...' : 'Refresh' }}
                        </button>
                    </div>
                </div>

                <div class="dashboard-calendar-layout">
                    <div class="min-w-0">
                        <div class="mb-1 grid grid-cols-7 border-x border-t border-slate-200">
                            <div
                                v-for="day in dashboardWeekDayHeaders"
                                :key="`dash-calendar-header-${day}`"
                                class="border-b border-r border-slate-200 bg-slate-50 px-2 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-500"
                            >
                                {{ day }}
                            </div>
                        </div>

                        <div class="grid grid-cols-7 border-l border-slate-200">
                            <div
                                v-for="cell in dashboardMonthCells"
                                :key="cell.key"
                                :class="[
                                    'dash-calendar-cell',
                                    cell.isCurrentMonth ? 'bg-white' : 'bg-slate-50',
                                    cell.isToday ? 'dash-calendar-cell-today' : '',
                                ]"
                            >
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-[11px] font-bold" :class="cell.isCurrentMonth ? 'text-slate-700' : 'text-slate-400'">{{ cell.day }}</span>
                                    <span v-if="cell.events.length" class="text-[10px] font-black text-slate-400">{{ cell.events.length }}</span>
                                </div>
                                <div class="space-y-1">
                                    <button
                                        v-for="event in cell.events.slice(0, 2)"
                                        :key="`dash-calendar-chip-${event.id}`"
                                        type="button"
                                        class="dash-event-chip"
                                        @click="dashboardSelectedEvent = event"
                                    >
                                        {{ event.summary || '(No title)' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Event List</h3>
                        <div class="mt-1 text-[11px] font-bold text-slate-400">
                            Showing {{ dashboardCalendarEventListPreview.length }} of {{ dashboardCalendarEventList.length }}
                        </div>
                        <div v-if="dashboardCalendarEventListPreview.length === 0" class="mt-2 rounded-lg border border-dashed border-slate-300 bg-white p-3 text-xs font-semibold text-slate-500">
                            No events available.
                        </div>
                        <div v-else class="mt-2 max-h-[330px] space-y-2 overflow-y-auto pr-1">
                            <article
                                v-for="event in dashboardCalendarEventListPreview"
                                :key="`dash-calendar-list-${event.id}`"
                                class="rounded-lg border border-slate-200 bg-white p-2"
                            >
                                <p class="truncate text-xs font-black text-slate-800">{{ event.summary || '(No title)' }}</p>
                                <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">{{ formatDateTime(event.start?.dateTime || event.start?.date) }}</p>
                                <p class="truncate text-[11px] font-semibold text-slate-400">{{ event.location || '-' }}</p>
                            </article>
                        </div>

                        <div v-if="dashboardSelectedEvent" class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-2.5">
                            <p class="text-[10px] font-black uppercase tracking-wide text-blue-600">Selected</p>
                            <p class="mt-1 text-xs font-black text-slate-800">{{ dashboardSelectedEvent.summary || '-' }}</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-600">{{ formatDateTime(dashboardSelectedEvent.start?.dateTime || dashboardSelectedEvent.start?.date) }}</p>
                            <p class="text-[11px] font-semibold text-slate-500">{{ dashboardSelectedEvent.location || '-' }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-stretch">
                <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5 h-[640px] flex flex-col min-h-0">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                        <div>
                            <h2 class="text-base font-black text-slate-900">Latest Leads</h2>
                            <p class="text-xs font-semibold text-slate-500">
                                Showing {{ leadMeta.returned ?? 0 }} of {{ leadMeta.total ?? 0 }} leads
                            </p>
                            <p v-if="lastLoadedAt" class="text-[11px] font-semibold text-slate-400 mt-1">
                                Synced {{ formatDateTime(lastLoadedAt) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="latestLeadsPercent" class="text-xs font-bold text-slate-500">Latest %</label>
                            <input
                                id="latestLeadsPercent"
                                v-model.number="latestLeadsPercent"
                                type="number"
                                min="1"
                                max="100"
                                class="w-20 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            />
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition disabled:opacity-60"
                                :disabled="isLeadLoading"
                                @click="fetchDashboard"
                            >
                                {{ isLeadLoading ? 'Loading...' : 'Apply' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="isLeadLoading" class="space-y-3 flex-1 overflow-y-auto pr-1 min-h-0">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                            <span class="h-2 w-2 rounded-full bg-blue-600 animate-pulse"></span>
                            Loading latest leads from API...
                        </div>
                        <div v-for="i in 4" :key="`lead-loading-${i}`" class="h-16 rounded-xl bg-slate-100 animate-pulse"></div>
                    </div>

                    <div v-else-if="latestLeads.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center flex-1 flex items-center justify-center min-h-0">
                        <p class="text-sm font-semibold text-slate-600">No leads found for the selected percentage.</p>
                    </div>

                    <div v-else class="space-y-3 flex-1 overflow-y-auto pr-1 min-h-0">
                        <article
                            v-for="lead in latestLeads"
                            :key="lead.id"
                            class="rounded-xl border border-slate-200 p-4 hover:border-slate-300 transition"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black text-slate-900">{{ lead.name || 'Unknown lead' }}</h3>
                                    <p class="text-xs font-semibold text-slate-500">{{ lead.email || 'No email' }} - {{ lead.phone || 'No phone' }}</p>
                                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Priority: {{ lead.priority || '-' }} | Subject: {{ lead.subject_interest || '-' }}</p>
                                </div>
                                <span :class="['text-xs font-black rounded-lg border px-2 py-1 uppercase', leadStatusClass(lead.status)]">
                                    {{ lead.status || 'new' }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] font-bold text-slate-600">
                                <div>ID: {{ lead.lead_id || lead.id }}</div>
                                <div>Type: {{ lead.type || '-' }}</div>
                                <div>Created: {{ formatDate(lead.created_at) }}</div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5 h-[640px] flex flex-col min-h-0">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-base font-black text-slate-900">Recent Payments</h2>
                        <Link :href="route('payments')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition">
                            View All
                        </Link>
                    </div>

                    <div v-if="isLoading" class="space-y-3 flex-1 overflow-y-auto pr-1 min-h-0">
                        <div v-for="i in 5" :key="`payment-loading-${i}`" class="h-14 rounded-xl bg-slate-100 animate-pulse"></div>
                    </div>

                    <div v-else-if="recentPayments.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center flex-1 flex items-center justify-center min-h-0">
                        <p class="text-sm font-semibold text-slate-600">No recent payments available.</p>
                    </div>

                    <div v-else class="space-y-3 flex-1 overflow-y-auto pr-1 min-h-0">
                        <article
                            v-for="payment in recentPayments"
                            :key="payment.id"
                            class="rounded-xl border border-slate-200 p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-black text-slate-900">{{ formatMoney(payment.amount, payment.currency) }}</p>
                                <span :class="['text-[11px] font-black rounded-md border px-2 py-0.5 uppercase', statusBadgeClass(payment.status)]">
                                    {{ payment.status || 'unknown' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ payment.type || 'Payment' }} - {{ payment.order_id || 'No order ID' }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">{{ formatDateTime(payment.created_at) }}</p>
                        </article>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5 h-[640px] flex flex-col min-h-0">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-black text-slate-900">Latest Notifications</h2>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50 transition" :disabled="isLoading" @click="fetchDashboard">
                        Refresh
                    </button>
                </div>

                    <div v-if="isLoading" class="space-y-3 flex-1 overflow-y-auto pr-1 min-h-0">
                    <div v-for="i in 5" :key="`notification-loading-${i}`" class="h-16 rounded-xl bg-slate-100 animate-pulse"></div>
                </div>

                    <div v-else-if="latestNotifications.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center flex-1 flex items-center justify-center min-h-0">
                    <p class="text-sm font-semibold text-slate-600">No notifications yet.</p>
                </div>

                    <div v-else class="space-y-3 flex-1 overflow-y-auto pr-1 min-h-0">
                    <article
                        v-for="notification in latestNotifications"
                        :key="notification.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-900">{{ notification.title || 'Notification' }}</h3>
                                <p class="mt-1 text-sm font-medium text-slate-600">{{ notification.message }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="['text-[11px] font-black rounded-md border px-2 py-0.5 uppercase', notificationPriorityClass(notification.priority)]">
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
            </div>
        </div>
        <GoogleConnectModal
            :show="isGoogleConnectModalOpen"
            title="Connect Google Calendar"
            message="Your Google account is not connected. To view and manage your calendar events directly from the dashboard, please connect your account."
            @close="isGoogleConnectModalOpen = false"
        />
    </AppLayout>
</template>

<style scoped>
.mesh-pattern {
    background-image: radial-gradient(circle at 2px 2px, rgba(255, 255, 255, 0.9) 1px, transparent 0);
    background-size: 44px 44px;
}

.dash-spotlight-card {
    position: relative;
    display: block;
    border-radius: 1rem;
    border: 1px solid rgb(226 232 240);
    background: rgba(255, 255, 255, 0.92);
    padding: 1.1rem 1.15rem;
    box-shadow: 0 4px 20px -8px rgba(15, 23, 42, 0.12);
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.dash-spotlight-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 40px -12px rgba(79, 70, 229, 0.22);
}

.dash-spotlight-card--teachers:hover {
    border-color: rgb(165 180 252);
}
.dash-spotlight-card--institutes:hover {
    border-color: rgb(110 231 183);
}
.dash-spotlight-card--ai:hover {
    border-color: rgb(251 191 36);
}
.dash-spotlight-card--marketplace:hover {
    border-color: rgb(244 114 182);
}

.dash-spotlight-icon-wrap {
    display: flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
}

.dash-spotlight-icon-wrap--teachers {
    background: rgb(224 231 255);
    color: rgb(67 56 202);
}
.dash-spotlight-icon-wrap--institutes {
    background: rgb(209 250 229);
    color: rgb(5 150 105);
}
.dash-spotlight-icon-wrap--ai {
    background: rgb(254 243 199);
    color: rgb(180 83 9);
}
.dash-spotlight-icon-wrap--marketplace {
    background: rgb(252 231 243);
    color: rgb(190 24 93);
}

.dash-spotlight-cta {
    margin-top: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(79 70 229);
}

.dash-spotlight-card--teachers .dash-spotlight-cta {
    color: rgb(67 56 202);
}
.dash-spotlight-card--institutes .dash-spotlight-cta {
    color: rgb(5 120 85);
}
.dash-spotlight-card--ai .dash-spotlight-cta {
    color: rgb(180 83 9);
}
.dash-spotlight-card--marketplace .dash-spotlight-cta {
    color: rgb(190 24 93);
}

.dash-spotlight-cta::after {
    content: '→';
    transition: transform 0.2s ease;
}

.group:hover .dash-spotlight-cta::after {
    transform: translateX(3px);
}

.dashboard-calendar-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 0.9rem;
}

.dash-calendar-btn {
    border: 1px solid rgb(203 213 225);
    border-radius: 0.5rem;
    background: white;
    padding: 0.35rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: rgb(51 65 85);
}

.dash-calendar-icon-btn {
    border: 1px solid rgb(203 213 225);
    border-radius: 0.5rem;
    width: 1.9rem;
    height: 1.9rem;
    font-weight: 800;
    color: rgb(51 65 85);
}

.dash-calendar-cell {
    border-right: 1px solid rgb(226 232 240);
    border-bottom: 1px solid rgb(226 232 240);
    min-height: 88px;
    padding: 0.3rem;
}

.dash-calendar-cell-today {
    box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.dash-event-chip {
    width: 100%;
    text-align: left;
    border-radius: 0.35rem;
    border: 1px solid rgb(167 243 208);
    background: rgb(220 252 231);
    color: rgb(22 101 52);
    padding: 0.1rem 0.3rem;
    font-size: 0.64rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 1200px) {
    .dashboard-calendar-layout {
        grid-template-columns: 1fr;
    }
}
</style>
