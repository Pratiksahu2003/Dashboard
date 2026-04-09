<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GoogleWorkspaceTabs from '@/Components/GoogleWorkspaceTabs.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useGoogleWorkspaceApi, formatDateTime } from '@/composables/useGoogleWorkspaceApi';

const { requireAuth } = useAuth();
const { error: showError, success: showSuccess, info: showInfo } = useAlerts();
const { getStatus, getOauthUrl, exchangeCode, runSync, refreshToken, disconnect } = useGoogleWorkspaceApi();

const status = ref(null);
const isLoading = ref(false);
const isSyncing = ref(false);
const isConnecting = ref(false);
const isDisconnecting = ref(false);
const isRefreshingToken = ref(false);
const syncCalendar = ref(true);
const syncDrive = ref(true);
const syncYoutube = ref(true);

const cards = [
    { title: 'Calendar Operations', subtitle: 'List and inspect events with refresh controls', routeName: 'google-workspace.calendar' },
    { title: 'Drive Operations', subtitle: 'Search files and inspect metadata in one place', routeName: 'google-workspace.drive' },
    { title: 'YouTube View', subtitle: 'See channel identity with richer channel details', routeName: 'google-workspace.youtube' },
];

const isConnected = computed(() => Boolean(status.value?.connected));
const tokenStatusLabel = computed(() => (status.value?.token_valid ? 'Valid' : 'Invalid / Expired'));
const scopeCount = computed(() => Array.isArray(status.value?.scopes) ? status.value.scopes.length : 0);
const connectButtonLabel = computed(() => {
    if (isConnected.value) return 'Google Connected';
    if (isConnecting.value) return 'Connecting...';
    return 'Connect With Google';
});

const loadStatus = async () => {
    isLoading.value = true;
    try {
        status.value = await getStatus();
    } catch (error) {
        showError('Unable to load Google Workspace status. Please try again.', 'Google Workspace');
    } finally {
        isLoading.value = false;
    }
};

const connectGoogle = async () => {
    isConnecting.value = true;
    try {
        const data = await getOauthUrl();
        if (!data?.oauth_url) throw new Error('OAuth URL not available.');
        window.location.href = data.oauth_url;
    } catch (error) {
        showError('Unable to start Google OAuth flow. Please try again.', 'Google Workspace');
    } finally {
        isConnecting.value = false;
    }
};

const exchangeOAuthCallbackIfPresent = async () => {
    const query = new URLSearchParams(window.location.search);
    const code = query.get('code');
    const state = query.get('state');
    const oauthError = query.get('error');
    const oauthErrorDescription = query.get('error_description');

    if (oauthError) {
        showError(oauthErrorDescription || oauthError, 'Google OAuth Failed');
        return;
    }
    if (!code || !state) return;

    try {
        await exchangeCode({ code, state });
        showSuccess('Google account connected successfully.', 'Google Workspace');
    } catch (error) {
        showError('Google connection could not be completed. Please try again.', 'Google Workspace');
    } finally {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.delete('code');
        nextUrl.searchParams.delete('state');
        nextUrl.searchParams.delete('scope');
        window.history.replaceState({}, '', nextUrl.toString());
    }
};

const syncWithGoogle = async () => {
    if (!isConnected.value) {
        showInfo('Connect Google account first, then run sync.', 'Google Workspace');
        return;
    }

    const sync = [];
    if (syncCalendar.value) sync.push('calendar');
    if (syncDrive.value) sync.push('drive');
    if (syncYoutube.value) sync.push('youtube');
    if (sync.length === 0) {
        showInfo('Select at least one sync target.', 'Google Workspace');
        return;
    }

    isSyncing.value = true;
    try {
        await runSync({ sync });
        showSuccess('Google sync completed.', 'Google Workspace');
        await loadStatus();
    } catch (error) {
        showError('Google sync failed. Please retry in a moment.', 'Google Workspace');
    } finally {
        isSyncing.value = false;
    }
};

const refreshGoogleToken = async () => {
    if (!isConnected.value) return;
    isRefreshingToken.value = true;
    try {
        await refreshToken();
        await loadStatus();
        showSuccess('Google access token refreshed.', 'Google Workspace');
    } catch (error) {
        showError('Failed to refresh Google token. Please try again.', 'Google Workspace');
    } finally {
        isRefreshingToken.value = false;
    }
};

const disconnectGoogle = async () => {
    isDisconnecting.value = true;
    try {
        await disconnect();
        await loadStatus();
        showSuccess('Google disconnected.', 'Google Workspace');
    } catch (error) {
        showError('Unable to disconnect Google right now. Please try again.', 'Google Workspace');
    } finally {
        isDisconnecting.value = false;
    }
};

onMounted(async () => {
    if (!requireAuth()) return;
    await exchangeOAuthCallbackIfPresent();
    await loadStatus();
});
</script>

<template>
    <Head title="Google Workspace" />

    <AppLayout>
        <template #breadcrumb>Google Workspace</template>

        <div class="space-y-5">
            <GoogleWorkspaceTabs />

            <section class="rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-700 to-cyan-800 p-6 text-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-white/75">Google API v4</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight">Workspace Control Center</h1>
                        <p class="mt-1 text-sm font-medium text-white/85">Manage OAuth, sync targets, and app integrations from one dashboard.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-xs font-black disabled:opacity-60"
                            :class="isConnected ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-white text-slate-900 hover:bg-slate-100'"
                            :disabled="isConnecting || isConnected"
                            @click="connectGoogle"
                        >
                            {{ connectButtonLabel }}
                        </button>
                        <button
                            v-if="isConnected"
                            type="button"
                            class="rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-xs font-black text-white hover:bg-white/20 disabled:opacity-60"
                            :disabled="isRefreshingToken"
                            @click="refreshGoogleToken"
                        >
                            {{ isRefreshingToken ? 'Refreshing...' : 'Refresh Token' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-white/30 bg-white/10 px-3 py-1.5 text-xs font-black text-white hover:bg-white/20"
                            :disabled="isLoading"
                            @click="loadStatus"
                        >
                            {{ isLoading ? 'Refreshing...' : 'Refresh Status' }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">Google Email</p>
                        <p class="mt-1 font-semibold">{{ status?.google_email || '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">Token State</p>
                        <p class="mt-1 font-semibold">{{ tokenStatusLabel }}</p>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">Expires At</p>
                        <p class="mt-1 font-semibold">{{ formatDateTime(status?.token_expires_at) }}</p>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">Scopes</p>
                        <p class="mt-1 font-semibold">{{ scopeCount }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-white/20 bg-white/10 p-3">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-white/75">Sync Targets</p>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold flex items-center gap-2">
                            <input v-model="syncCalendar" type="checkbox" />
                            Calendar
                        </label>
                        <label class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold flex items-center gap-2">
                            <input v-model="syncDrive" type="checkbox" />
                            Drive
                        </label>
                        <label class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold flex items-center gap-2">
                            <input v-model="syncYoutube" type="checkbox" />
                            YouTube
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-white text-slate-900 px-4 py-2 text-xs font-black hover:bg-slate-100 disabled:opacity-60"
                        :disabled="isSyncing || isLoading"
                        @click="syncWithGoogle"
                    >
                        {{ isSyncing ? 'Syncing...' : 'Sync With Google' }}
                    </button>
                    <button
                        v-if="isConnected"
                        type="button"
                        class="rounded-lg bg-rose-600 text-white px-4 py-2 text-xs font-black hover:bg-rose-700 disabled:opacity-60"
                        :disabled="isDisconnecting"
                        @click="disconnectGoogle"
                    >
                        {{ isDisconnecting ? 'Disconnecting...' : 'Disconnect' }}
                    </button>
                </div>
                <p v-if="!isConnected" class="mt-2 text-xs font-semibold text-white/80">
                    Use <span class="font-black">Connect With Google</span> first. After connection, all Calendar, Drive, YouTube and Sync operations are enabled.
                </p>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <Link
                    v-for="card in cards"
                    :key="card.routeName"
                    :href="route(card.routeName)"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-slate-300 hover:shadow transition"
                >
                    <h2 class="text-base font-black text-slate-900">{{ card.title }}</h2>
                    <p class="mt-1 text-sm font-medium text-slate-600">{{ card.subtitle }}</p>
                </Link>
            </section>
        </div>
    </AppLayout>
</template>

