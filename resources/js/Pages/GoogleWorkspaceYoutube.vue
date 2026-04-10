<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GoogleWorkspaceTabs from '@/Components/GoogleWorkspaceTabs.vue';
import GoogleConnectModal from '@/Components/GoogleConnectModal.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useGoogleWorkspaceApi, formatDateTime } from '@/composables/useGoogleWorkspaceApi';

const { requireAuth } = useAuth();
const { error: showError } = useAlerts();
const { getYoutubeChannels } = useGoogleWorkspaceApi();

const data = ref(null);
const isLoading = ref(false);
const maxResults = ref(25);
const isGoogleConnectModalOpen = ref(false);
const items = computed(() => (Array.isArray(data.value?.items) ? data.value.items : []));
const totalViews = computed(() => items.value.reduce((sum, item) => sum + Number(item?.statistics?.viewCount || 0), 0));

const load = async () => {
    isLoading.value = true;
    try {
        data.value = await getYoutubeChannels({ youtube: { max_results: Number(maxResults.value) || 25 } });
    } catch (error) {
        const message = error?.response?.data?.message || error?.message || '';
        if (message.includes('not connected') || message.includes('refresh token')) {
            isGoogleConnectModalOpen.value = true;
        } else {
            showError('Unable to load YouTube channels. Please try again.', 'Google Workspace');
        }
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    if (!requireAuth()) return;
    load();
});
</script>

<template>
    <Head title="Google Workspace YouTube" />
    <AppLayout>
        <template #breadcrumb>Google Workspace - YouTube</template>
        <div class="space-y-5">
            <GoogleWorkspaceTabs />
            <section class="rounded-3xl border border-rose-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                    <div>
                        <h1 class="text-lg font-black text-rose-700">YouTube Channel View</h1>
                        <p class="text-xs font-semibold text-slate-500">Clean profile-style channel view with published date and statistics.</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <div>
                            <label class="text-[11px] font-black uppercase text-slate-500">Max Results</label>
                            <input v-model.number="maxResults" type="number" min="1" max="50" class="mt-1 w-20 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-700" />
                        </div>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700" :disabled="isLoading" @click="load">{{ isLoading ? 'Loading...' : 'Refresh' }}</button>
                    </div>
                </div>
                <div class="mb-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                        <p class="text-[11px] font-black uppercase text-rose-700">Channels</p>
                        <p class="mt-1 font-black text-rose-700">{{ items.length }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                        <p class="text-[11px] font-black uppercase text-rose-700">Total Views</p>
                        <p class="mt-1 font-black text-rose-700">{{ Number(totalViews || 0).toLocaleString() }}</p>
                    </div>
                </div>
                <div v-if="items.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm font-semibold text-slate-600">No YouTube channels available.</div>
                <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                    <article
                        v-for="channel in items"
                        :key="channel.id || channel.etag"
                        class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <img
                                v-if="channel.snippet?.thumbnails?.default?.url"
                                :src="channel.snippet.thumbnails.default.url"
                                alt="Channel thumbnail"
                                class="h-14 w-14 rounded-xl object-cover border border-slate-200 bg-white"
                            />
                            <div class="min-w-0">
                                <h2 class="text-sm font-black text-rose-700 truncate">{{ channel.snippet?.title || '-' }}</h2>
                                <p class="text-xs text-slate-500 truncate">{{ channel.id || '-' }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-600">Published: {{ formatDateTime(channel.snippet?.publishedAt) }}</p>
                                <a
                                    v-if="channel.id"
                                    :href="`https://www.youtube.com/channel/${channel.id}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-1 inline-block text-[11px] font-black text-rose-700 hover:underline"
                                >
                                    Open Channel
                                </a>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-[11px]">
                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                <p class="font-black uppercase text-slate-500">Subs</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ Number(channel.statistics?.subscriberCount || 0).toLocaleString() }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                <p class="font-black uppercase text-slate-500">Videos</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ Number(channel.statistics?.videoCount || 0).toLocaleString() }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                <p class="font-black uppercase text-slate-500">Views</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ Number(channel.statistics?.viewCount || 0).toLocaleString() }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
        <GoogleConnectModal
            :show="isGoogleConnectModalOpen"
            title="Connect Google Workspace"
            message="Your Google account is not connected. To access and view your YouTube channel details, please connect your account."
            @close="isGoogleConnectModalOpen = false"
        />
    </AppLayout>
</template>

