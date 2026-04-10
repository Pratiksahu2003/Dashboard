<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GoogleWorkspaceTabs from '@/Components/GoogleWorkspaceTabs.vue';
import GoogleConnectModal from '@/Components/GoogleConnectModal.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useGoogleWorkspaceApi, formatDateTime } from '@/composables/useGoogleWorkspaceApi';

const { requireAuth } = useAuth();
const { error: showError, success: showSuccess } = useAlerts();
const {
    getCalendarEvents,
    getCalendarEvent,
    createCalendarEvent,
    updateCalendarEvent,
    deleteCalendarEvent,
} = useGoogleWorkspaceApi();

const data = ref(null);
const selectedEvent = ref(null);
const isLoading = ref(false);
const isSubmitting = ref(false);
const maxResults = ref(25);
const mode = ref('create');
const currentMonth = ref(new Date());
const dragStartX = ref(null);
const dragDeltaX = ref(0);
const isDraggingMonth = ref(false);
const activeEventTypeFilters = ref([]);
const endDateTimeManuallyEdited = ref(false);
const isGoogleConnectModalOpen = ref(false);

const form = ref({
    id: null,
    summary: '',
    description: '',
    location: '',
    start_date: '',
    start_time: '',
    end_date: '',
    end_time: '',
    timezone: 'Asia/Kolkata',
});

const items = computed(() => (Array.isArray(data.value?.items) ? data.value.items : []));
const upcomingCount = computed(() => items.value.filter(event => {
    const start = event?.start?.dateTime || event?.start?.date;
    return start && new Date(start).getTime() > Date.now();
}).length);
const monthLabel = computed(() => currentMonth.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const miniMonthLabel = computed(() => currentMonth.value.toLocaleDateString(undefined, { month: 'short', year: 'numeric' }));
const weekDayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const normalizeDate = value => {
    if (!value) return null;
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return null;
    return parsed;
};

const dateKey = value => {
    const parsed = normalizeDate(value);
    if (!parsed) return '';
    const year = parsed.getFullYear();
    const month = `${parsed.getMonth() + 1}`.padStart(2, '0');
    const day = `${parsed.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const currentMonthGridStart = computed(() => {
    const value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1);
    value.setDate(value.getDate() - value.getDay());
    return value;
});

const eventsByDate = computed(() => {
    const map = new Map();
    items.value.forEach(event => {
        const key = dateKey(event?.start?.dateTime || event?.start?.date);
        if (!key) return;
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(event);
    });
    return map;
});

const monthCells = computed(() => {
    const result = [];
    const start = new Date(currentMonthGridStart.value);
    const month = currentMonth.value.getMonth();
    const todayKey = dateKey(new Date());

    for (let i = 0; i < 42; i++) {
        const cellDate = new Date(start);
        cellDate.setDate(start.getDate() + i);
        const key = dateKey(cellDate);
        result.push({
            key: `${key}-${i}`,
            date: cellDate,
            day: cellDate.getDate(),
            isCurrentMonth: cellDate.getMonth() === month,
            isToday: key === todayKey,
            dateKey: key,
            events: eventsByDate.value.get(key) || [],
        });
    }

    return result;
});

const eventColorClass = event => {
    const type = eventType(event);
    if (type === 'birthday') return 'event-chip-birthday';
    if (type === 'holiday') return 'event-chip-holiday';
    if (type === 'meeting') return 'event-chip-meeting';
    if (type === 'task') return 'event-chip-task';
    return 'event-chip-default';
};

const eventType = event => {
    const title = String(event?.summary || '').toLowerCase();
    const source = String(event?.organizer?.email || event?.creator?.email || '').toLowerCase();
    const key = `${title} ${source}`;

    if (key.includes('birthday')) return 'birthday';
    if (key.includes('holiday') || key.includes('festival')) return 'holiday';
    if (key.includes('meeting') || key.includes('sync')) return 'meeting';
    if (key.includes('task') || key.includes('deadline') || key.includes('due')) return 'task';
    return 'general';
};

const resetForm = () => {
    const defaults = getDefaultDateTimeRange();
    endDateTimeManuallyEdited.value = false;
    mode.value = 'create';
    form.value = {
        id: null,
        summary: '',
        description: '',
        location: '',
        start_date: defaults.start_date,
        start_time: defaults.start_time,
        end_date: defaults.end_date,
        end_time: defaults.end_time,
        timezone: 'Asia/Kolkata',
    };
};

const changeMonth = step => {
    const next = new Date(currentMonth.value);
    next.setMonth(next.getMonth() + step);
    currentMonth.value = next;
};

const goToToday = () => {
    currentMonth.value = new Date();
};

const onMonthDragStart = event => {
    dragStartX.value = event.clientX;
    dragDeltaX.value = 0;
    isDraggingMonth.value = true;
};

const onMonthDragMove = event => {
    if (!isDraggingMonth.value || dragStartX.value === null) return;
    dragDeltaX.value = event.clientX - dragStartX.value;
};

const onMonthDragEnd = () => {
    if (!isDraggingMonth.value) return;
    const threshold = 65;
    if (dragDeltaX.value <= -threshold) changeMonth(1);
    else if (dragDeltaX.value >= threshold) changeMonth(-1);
    dragStartX.value = null;
    dragDeltaX.value = 0;
    isDraggingMonth.value = false;
};

const isEventVisible = event => {
    if (!activeEventTypeFilters.value.length) return true;
    return activeEventTypeFilters.value.includes(eventType(event));
};

const toggleEventTypeFilter = type => {
    if (!type) return;
    if (activeEventTypeFilters.value.includes(type)) {
        activeEventTypeFilters.value = activeEventTypeFilters.value.filter(item => item !== type);
    } else {
        activeEventTypeFilters.value = [...activeEventTypeFilters.value, type];
    }
};

const isFilterActive = type => activeEventTypeFilters.value.includes(type);

const clearEventTypeFilters = () => {
    activeEventTypeFilters.value = [];
};
const activeFilterLabel = computed(() => {
    const count = activeEventTypeFilters.value.length;
    if (!count) return '';
    return `Filtered: ${count} type${count > 1 ? 's' : ''}`;
});
const visibleEventList = computed(() => {
    return [...items.value]
        .filter(isEventVisible)
        .sort((a, b) => {
            const aTime = normalizeDate(a?.start?.dateTime || a?.start?.date)?.getTime() || 0;
            const bTime = normalizeDate(b?.start?.dateTime || b?.start?.date)?.getTime() || 0;
            return aTime - bTime;
        });
});

const pad = (value) => String(value).padStart(2, '0');

const toDateInput = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const toTimeInput = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;

const getDefaultDateTimeRange = () => {
    const now = new Date();
    const start = new Date(now);
    start.setSeconds(0, 0);
    const minute = start.getMinutes();
    const roundedMinute = Math.ceil(minute / 15) * 15;

    if (roundedMinute >= 60) start.setHours(start.getHours() + 1, 0, 0, 0);
    else start.setMinutes(roundedMinute, 0, 0);

    const end = new Date(start);
    end.setHours(end.getHours() + 1);

    return {
        start_date: toDateInput(start),
        start_time: toTimeInput(start),
        end_date: toDateInput(end),
        end_time: toTimeInput(end),
    };
};

const addOneHourFromStart = (startDate, startTime) => {
    const start = new Date(`${startDate}T${startTime}`);
    if (Number.isNaN(start.getTime())) return null;
    const end = new Date(start);
    end.setHours(end.getHours() + 1);
    return {
        end_date: toDateInput(end),
        end_time: toTimeInput(end),
    };
};

const load = async () => {
    isLoading.value = true;
    try {
        data.value = await getCalendarEvents({
            calendar: { max_results: Number(maxResults.value) || 25 },
        });
    } catch (error) {
        const message = error?.response?.data?.message || error?.message || '';
        if (message.includes('not connected') || message.includes('refresh token')) {
            isGoogleConnectModalOpen.value = true;
        } else {
            showError('Unable to load calendar events. Please try again.', 'Google Workspace');
        }
    } finally {
        isLoading.value = false;
    }
};

const viewEvent = async eventId => {
    try {
        selectedEvent.value = await getCalendarEvent(eventId);
    } catch (error) {
        showError('Unable to load event details. Please try again.', 'Calendar');
    }
};

const editEvent = event => {
    const startRaw = event.start?.dateTime || '';
    const endRaw = event.end?.dateTime || '';
    const startDate = startRaw ? startRaw.slice(0, 10) : '';
    const startTime = startRaw ? startRaw.slice(11, 16) : '';
    const endDate = endRaw ? endRaw.slice(0, 10) : '';
    const endTime = endRaw ? endRaw.slice(11, 16) : '';

    mode.value = 'edit';
    endDateTimeManuallyEdited.value = true;
    form.value = {
        id: event.id,
        summary: event.summary || '',
        description: event.description || '',
        location: event.location || '',
        start_date: startDate,
        start_time: startTime,
        end_date: endDate,
        end_time: endTime,
        timezone: event.start?.timeZone || 'Asia/Kolkata',
    };
};

const selectEventFromCell = event => {
    if (!event?.id) return;
    viewEvent(event.id);
};

const submitEvent = async () => {
    if (
        !form.value.summary
        || !form.value.start_date
        || !form.value.start_time
        || !form.value.end_date
        || !form.value.end_time
    ) {
        showError('Summary, start date/time, and end date/time are required.', 'Calendar');
        return;
    }

    const start = `${form.value.start_date}T${form.value.start_time}`;
    const end = `${form.value.end_date}T${form.value.end_time}`;
    const startDate = new Date(start);
    const endDate = new Date(end);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        showError('Please pick valid date and time values.', 'Calendar');
        return;
    }
    if (endDate.getTime() <= startDate.getTime()) {
        showError('End date/time must be after start date/time.', 'Calendar');
        return;
    }

    isSubmitting.value = true;
    const payload = {
        summary: form.value.summary,
        description: form.value.description,
        location: form.value.location,
        start,
        end,
        timezone: form.value.timezone,
    };

    try {
        if (mode.value === 'edit' && form.value.id) {
            await updateCalendarEvent(form.value.id, payload);
            showSuccess('Event updated.', 'Calendar');
        } else {
            await createCalendarEvent(payload);
            showSuccess('Event created.', 'Calendar');
        }
        resetForm();
        await load();
    } catch (error) {
        showError('Unable to save this event. Please check details and try again.', 'Calendar');
    } finally {
        isSubmitting.value = false;
    }
};

const removeEvent = async eventId => {
    try {
        await deleteCalendarEvent(eventId);
        if (selectedEvent.value?.id === eventId) selectedEvent.value = null;
        showSuccess('Event deleted.', 'Calendar');
        await load();
    } catch (error) {
        showError('Unable to delete this event right now. Please try again.', 'Calendar');
    }
};

onMounted(() => {
    if (!requireAuth()) return;
    resetForm();
    load();
});

watch(
    () => [form.value.start_date, form.value.start_time, mode.value],
    ([startDate, startTime, currentMode]) => {
        if (currentMode !== 'create') return;
        if (endDateTimeManuallyEdited.value) return;
        if (!startDate || !startTime) return;
        const nextEnd = addOneHourFromStart(startDate, startTime);
        if (!nextEnd) return;
        form.value.end_date = nextEnd.end_date;
        form.value.end_time = nextEnd.end_time;
    },
);
</script>

<template>
    <Head title="Google Workspace Calendar" />
    <AppLayout>
        <template #breadcrumb>Google Workspace - Calendar</template>
        <div class="space-y-5">
            <GoogleWorkspaceTabs />
            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="calendar-shell">
                    <aside class="calendar-sidebar">
                        <button
                            type="button"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-black text-slate-800 hover:bg-slate-50"
                            @click="resetForm"
                        >
                            + Create
                        </button>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ miniMonthLabel }}</p>
                                <div class="flex gap-1">
                                    <button type="button" class="mini-nav-btn" @click="changeMonth(-1)">&#8249;</button>
                                    <button type="button" class="mini-nav-btn" @click="changeMonth(1)">&#8250;</button>
                                </div>
                            </div>
                            <div class="mini-week-grid">
                                <span v-for="day in weekDayHeaders" :key="`mini-head-${day}`" class="mini-header">{{ day.slice(0, 1) }}</span>
                                <span
                                    v-for="cell in monthCells"
                                    :key="`mini-cell-${cell.key}`"
                                    :class="[
                                        'mini-cell',
                                        cell.isCurrentMonth ? 'text-slate-700' : 'text-slate-300',
                                        cell.isToday ? 'mini-cell-today' : '',
                                    ]"
                                >
                                    {{ cell.day }}
                                </span>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <h2 class="text-xs font-black uppercase tracking-wide text-slate-500">My calendars</h2>
                            <div class="mt-2 space-y-2">
                                <label class="calendar-pill"><span class="dot bg-sky-500"></span> My Calendar</label>
                                <label class="calendar-pill"><span class="dot bg-emerald-500"></span> Birthdays</label>
                                <label class="calendar-pill"><span class="dot bg-indigo-500"></span> Reminders</label>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <h2 class="text-xs font-black uppercase tracking-wide text-slate-500">Event colors</h2>
                            <div class="mt-2 space-y-2">
                                <button type="button" class="legend-row legend-btn" :class="{ 'legend-btn-active': isFilterActive('meeting') }" @click="toggleEventTypeFilter('meeting')">
                                    <span class="legend-swatch event-chip-meeting"></span>
                                    <span>Meeting / Sync</span>
                                </button>
                                <button type="button" class="legend-row legend-btn" :class="{ 'legend-btn-active': isFilterActive('task') }" @click="toggleEventTypeFilter('task')">
                                    <span class="legend-swatch event-chip-task"></span>
                                    <span>Task / Deadline</span>
                                </button>
                                <button type="button" class="legend-row legend-btn" :class="{ 'legend-btn-active': isFilterActive('holiday') }" @click="toggleEventTypeFilter('holiday')">
                                    <span class="legend-swatch event-chip-holiday"></span>
                                    <span>Holiday / Festival</span>
                                </button>
                                <button type="button" class="legend-row legend-btn" :class="{ 'legend-btn-active': isFilterActive('birthday') }" @click="toggleEventTypeFilter('birthday')">
                                    <span class="legend-swatch event-chip-birthday"></span>
                                    <span>Birthday</span>
                                </button>
                                <button type="button" class="legend-row legend-btn" :class="{ 'legend-btn-active': isFilterActive('general') }" @click="toggleEventTypeFilter('general')">
                                    <span class="legend-swatch event-chip-default"></span>
                                    <span>General Event</span>
                                </button>
                                <button
                                    v-if="activeEventTypeFilters.length"
                                    type="button"
                                    class="mt-1 text-[11px] font-black text-blue-700 hover:underline"
                                    @click="clearEventTypeFilters"
                                >
                                    Clear filters
                                </button>
                            </div>
                        </div>
                    </aside>

                    <div class="calendar-main">
                        <header class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <button type="button" class="nav-btn" @click="goToToday">Today</button>
                                <button type="button" class="icon-btn" @click="changeMonth(-1)">&#8249;</button>
                                <button type="button" class="icon-btn" @click="changeMonth(1)">&#8250;</button>
                                <h1 class="ml-2 text-lg font-black text-slate-900">{{ monthLabel }}</h1>
                                <span v-if="activeFilterLabel" class="filter-badge">{{ activeFilterLabel }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model.number="maxResults" type="number" min="1" max="250" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold text-slate-700" />
                                <button type="button" class="nav-btn" :disabled="isLoading" @click="load">
                                    {{ isLoading ? 'Loading...' : 'Refresh' }}
                                </button>
                            </div>
                        </header>

                        <div class="mb-2 grid grid-cols-7 border-x border-t border-slate-200">
                            <div v-for="day in weekDayHeaders" :key="`header-${day}`" class="border-b border-r border-slate-200 bg-slate-50 px-2 py-2 text-[11px] font-black uppercase tracking-wide text-slate-500">
                                {{ day }}
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-7 border-l border-slate-200 month-drag-surface"
                            @pointerdown="onMonthDragStart"
                            @pointermove="onMonthDragMove"
                            @pointerup="onMonthDragEnd"
                            @pointercancel="onMonthDragEnd"
                            @pointerleave="onMonthDragEnd"
                        >
                            <div
                                v-for="cell in monthCells"
                                :key="cell.key"
                                class="calendar-cell"
                                :class="[
                                    cell.isCurrentMonth ? 'bg-white' : 'bg-slate-50',
                                    cell.isToday ? 'calendar-cell-today' : '',
                                ]"
                            >
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-xs font-bold" :class="cell.isCurrentMonth ? 'text-slate-700' : 'text-slate-400'">{{ cell.day }}</span>
                                    <span v-if="cell.events.length" class="text-[10px] font-black text-slate-400">{{ cell.events.length }}</span>
                                </div>
                                <div class="space-y-1">
                                    <button
                                        v-for="event in cell.events.filter(isEventVisible).slice(0, 3)"
                                        :key="`chip-${event.id}`"
                                        type="button"
                                        :class="['event-chip', eventColorClass(event)]"
                                        @click="selectEventFromCell(event)"
                                    >
                                        {{ event.summary || '(No title)' }}
                                    </button>
                                    <p v-if="cell.events.filter(isEventVisible).length > 3" class="px-1 text-[10px] font-semibold text-slate-400">
                                        +{{ cell.events.filter(isEventVisible).length - 3 }} more
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="calendar-panel">
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <h2 class="text-sm font-black text-slate-800">{{ mode === 'edit' ? 'Edit Event' : 'Create Event' }}</h2>
                                <button v-if="mode === 'edit'" type="button" class="text-[11px] font-black text-slate-500 hover:text-slate-700" @click="resetForm">Reset</button>
                            </div>
                            <div class="space-y-2">
                                <input v-model="form.summary" type="text" placeholder="Event title" class="form-input" />
                                <input v-model="form.location" type="text" placeholder="Location" class="form-input" />
                                <div class="grid grid-cols-2 gap-2">
                                    <input v-model="form.start_date" type="date" class="form-input" />
                                    <input v-model="form.start_time" type="time" step="300" class="form-input" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input
                                        v-model="form.end_date"
                                        type="date"
                                        class="form-input"
                                        @input="endDateTimeManuallyEdited = true"
                                    />
                                    <input
                                        v-model="form.end_time"
                                        type="time"
                                        step="300"
                                        class="form-input"
                                        @input="endDateTimeManuallyEdited = true"
                                    />
                                </div>
                                <input v-model="form.timezone" type="text" placeholder="Timezone" class="form-input" />
                                <textarea v-model="form.description" placeholder="Description" class="form-input min-h-[76px]"></textarea>
                                <button type="button" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-black text-white hover:bg-blue-700 disabled:opacity-60" :disabled="isSubmitting" @click="submitEvent">
                                    {{ isSubmitting ? 'Saving...' : mode === 'edit' ? 'Update Event' : 'Create Event' }}
                                </button>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Stats</h3>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded-lg border border-slate-200 bg-white p-2">
                                    <p class="text-[10px] font-black uppercase text-slate-400">Loaded</p>
                                    <p class="mt-1 text-sm font-black text-slate-800">{{ items.length }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-2">
                                    <p class="text-[10px] font-black uppercase text-slate-400">Upcoming</p>
                                    <p class="mt-1 text-sm font-black text-slate-800">{{ upcomingCount }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Event List</h3>
                                <span class="text-[11px] font-black text-slate-400">{{ visibleEventList.length }}</span>
                            </div>
                            <div v-if="visibleEventList.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 text-xs font-semibold text-slate-500">
                                No events available for current filter.
                            </div>
                            <div v-else class="event-list-scroll space-y-2">
                                <article v-for="event in visibleEventList" :key="`list-${event.id}`" class="rounded-lg border border-slate-200 bg-slate-50 p-2">
                                    <div class="flex items-start gap-2">
                                        <span :class="['event-dot', eventColorClass(event)]"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-black text-slate-800">{{ event.summary || '(No title)' }}</p>
                                            <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">
                                                {{ formatDateTime(event.start?.dateTime || event.start?.date) }}
                                            </p>
                                            <p class="truncate text-[11px] font-semibold text-slate-400">{{ event.location || '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-1.5 flex gap-2">
                                        <button type="button" class="text-[11px] font-black text-blue-700 hover:underline" @click="viewEvent(event.id)">View</button>
                                        <button type="button" class="text-[11px] font-black text-amber-700 hover:underline" @click="editEvent(event)">Edit</button>
                                        <button type="button" class="text-[11px] font-black text-rose-700 hover:underline" @click="removeEvent(event.id)">Delete</button>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <div v-if="selectedEvent" class="rounded-xl border border-slate-200 bg-white p-3">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Selected Event</h3>
                            <div class="mt-2 space-y-2 text-xs">
                                <p class="font-black text-slate-800">{{ selectedEvent.summary || '-' }}</p>
                                <p class="font-semibold text-slate-500">{{ selectedEvent.location || '-' }}</p>
                                <p class="font-semibold text-slate-500">{{ formatDateTime(selectedEvent.start?.dateTime || selectedEvent.start?.date) }}</p>
                                <p class="font-semibold text-slate-500">{{ formatDateTime(selectedEvent.end?.dateTime || selectedEvent.end?.date) }}</p>
                                <p class="font-semibold text-slate-600">{{ selectedEvent.description || '-' }}</p>
                                <div class="flex gap-2 pt-1">
                                    <button type="button" class="text-xs font-black text-amber-700 hover:underline" @click="editEvent(selectedEvent)">Edit</button>
                                    <button type="button" class="text-xs font-black text-rose-700 hover:underline" @click="removeEvent(selectedEvent.id)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>
        </div>
        <GoogleConnectModal
            :show="isGoogleConnectModalOpen"
            title="Connect Google Workspace"
            message="Your Google account is not connected. To access and manage your calendar events, please connect your account."
            @close="isGoogleConnectModalOpen = false"
        />
    </AppLayout>
</template>

<style scoped>
.calendar-shell {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr) 300px;
    gap: 1rem;
}

.calendar-sidebar,
.calendar-panel {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.calendar-main {
    min-width: 0;
}

.mini-nav-btn {
    border: 1px solid rgb(203 213 225);
    border-radius: 0.4rem;
    width: 1.5rem;
    height: 1.5rem;
    font-weight: 800;
    color: rgb(71 85 105);
}

.mini-week-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.2rem;
}

.mini-header {
    text-align: center;
    font-size: 0.62rem;
    font-weight: 800;
    color: rgb(148 163 184);
}

.mini-cell {
    text-align: center;
    font-size: 0.66rem;
    font-weight: 700;
    border-radius: 999px;
    line-height: 1.4rem;
    height: 1.4rem;
}

.mini-cell-today {
    background: rgb(37 99 235);
    color: white !important;
}

.calendar-pill {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgb(51 65 85);
}

.dot {
    width: 0.66rem;
    height: 0.66rem;
    border-radius: 999px;
}

.nav-btn {
    border: 1px solid rgb(203 213 225);
    border-radius: 0.5rem;
    background: white;
    padding: 0.38rem 0.7rem;
    font-size: 0.74rem;
    font-weight: 800;
    color: rgb(51 65 85);
}

.icon-btn {
    border: 1px solid rgb(203 213 225);
    border-radius: 0.5rem;
    width: 2rem;
    height: 2rem;
    font-size: 1rem;
    font-weight: 800;
    color: rgb(51 65 85);
}

.calendar-cell {
    border-right: 1px solid rgb(226 232 240);
    border-bottom: 1px solid rgb(226 232 240);
    min-height: 106px;
    padding: 0.32rem;
}

.calendar-cell-today {
    box-shadow: inset 0 0 0 2px rgba(37, 99, 235, 0.22);
}

.event-chip {
    width: 100%;
    text-align: left;
    border-radius: 0.35rem;
    padding: 0.12rem 0.35rem;
    font-size: 0.66rem;
    font-weight: 700;
    color: rgb(22 101 52);
    background: rgb(220 252 231);
    border: 1px solid rgb(167 243 208);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.event-chip-default {
    color: rgb(22 101 52);
    background: rgb(220 252 231);
    border-color: rgb(167 243 208);
}

.event-chip-meeting {
    color: rgb(30 64 175);
    background: rgb(219 234 254);
    border-color: rgb(147 197 253);
}

.event-chip-task {
    color: rgb(146 64 14);
    background: rgb(254 243 199);
    border-color: rgb(253 230 138);
}

.event-chip-holiday {
    color: rgb(190 24 93);
    background: rgb(252 231 243);
    border-color: rgb(249 168 212);
}

.event-chip-birthday {
    color: rgb(109 40 217);
    background: rgb(237 233 254);
    border-color: rgb(196 181 253);
}

.month-drag-surface {
    touch-action: pan-y;
    user-select: none;
    cursor: grab;
}

.month-drag-surface:active {
    cursor: grabbing;
}

.legend-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: rgb(71 85 105);
}

.legend-swatch {
    width: 0.95rem;
    height: 0.95rem;
    border-radius: 999px;
    border: 1px solid transparent;
}

.legend-btn {
    width: 100%;
    border: 1px solid transparent;
    border-radius: 0.5rem;
    padding: 0.2rem 0.35rem;
    text-align: left;
}

.legend-btn-active {
    border-color: rgb(147 197 253);
    background: rgb(239 246 255);
    color: rgb(30 64 175);
}

.event-list-scroll {
    max-height: 240px;
    overflow-y: auto;
    padding-right: 0.15rem;
}

.event-dot {
    margin-top: 0.25rem;
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 999px;
    flex-shrink: 0;
}

.filter-badge {
    border: 1px solid rgb(147 197 253);
    background: rgb(239 246 255);
    color: rgb(30 64 175);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.67rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.form-input {
    width: 100%;
    border: 1px solid rgb(203 213 225);
    border-radius: 0.5rem;
    padding: 0.5rem 0.65rem;
    font-size: 0.79rem;
    font-weight: 600;
    color: rgb(51 65 85);
}

.form-input:focus {
    outline: none;
    border-color: rgb(59 130 246);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

@media (max-width: 1400px) {
    .calendar-shell {
        grid-template-columns: 220px minmax(0, 1fr);
    }

    .calendar-panel {
        grid-column: span 2;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 1024px) {
    .calendar-shell {
        grid-template-columns: 1fr;
    }

    .calendar-panel {
        grid-column: auto;
        grid-template-columns: 1fr;
    }
}
</style>
