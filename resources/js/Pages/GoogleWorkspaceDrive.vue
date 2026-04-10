<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GoogleWorkspaceTabs from '@/Components/GoogleWorkspaceTabs.vue';
import GoogleConnectModal from '@/Components/GoogleConnectModal.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useGoogleWorkspaceApi, formatFileSize } from '@/composables/useGoogleWorkspaceApi';

const { requireAuth } = useAuth();
const { error: showError, success: showSuccess } = useAlerts();
const { getDriveFiles, searchDriveFiles, renameDriveFile, deleteDriveFile } = useGoogleWorkspaceApi();

const data = ref(null);
const query = ref('');
const pageSize = ref(25);
const isLoading = ref(false);
const isRenaming = ref(false);
const isDeleting = ref(false);
const renameId = ref('');
const renameValue = ref('');
const isGoogleConnectModalOpen = ref(false);
const items = computed(() => (Array.isArray(data.value?.files) ? data.value.files : []));
const folderCount = computed(() => items.value.filter(file => file?.mimeType === 'application/vnd.google-apps.folder').length);

const load = async () => {
    isLoading.value = true;
    try {
        data.value = query.value.trim()
            ? await searchDriveFiles({ drive: { query: query.value.trim(), page_size: Number(pageSize.value) || 25 } })
            : await getDriveFiles({ drive: { page_size: Number(pageSize.value) || 25 } });
    } catch (error) {
        const message = error?.response?.data?.message || error?.message || '';
        if (message.includes('not connected') || message.includes('refresh token')) {
            isGoogleConnectModalOpen.value = true;
        } else {
            showError('Unable to load Drive files. Please try again.', 'Google Workspace');
        }
    } finally {
        isLoading.value = false;
    }
};

const startRename = file => {
    renameId.value = file.id;
    renameValue.value = file.name || '';
};

const submitRename = async () => {
    if (!renameId.value || !renameValue.value.trim()) return;
    isRenaming.value = true;
    try {
        await renameDriveFile(renameId.value, renameValue.value.trim());
        showSuccess('Drive file renamed.', 'Google Drive');
        renameId.value = '';
        renameValue.value = '';
        await load();
    } catch (error) {
        showError('Unable to rename this file. Please try again.', 'Google Drive');
    } finally {
        isRenaming.value = false;
    }
};

const removeFile = async fileId => {
    isDeleting.value = true;
    try {
        await deleteDriveFile(fileId);
        showSuccess('Drive file deleted.', 'Google Drive');
        await load();
    } catch (error) {
        showError('Unable to delete this file right now. Please try again.', 'Google Drive');
    } finally {
        isDeleting.value = false;
    }
};

onMounted(() => {
    if (!requireAuth()) return;
    load();
});
</script>

<template>
    <Head title="Google Workspace Drive" />
    <AppLayout>
        <template #breadcrumb>Google Workspace - Drive</template>
        <div class="space-y-5">
            <GoogleWorkspaceTabs />
            <section class="rounded-3xl border border-blue-200 bg-white p-5 shadow-sm">
                <div>
                    <h1 class="text-lg font-black text-blue-700">Google Drive Operations</h1>
                    <p class="text-xs font-semibold text-slate-500">Search, list, and inspect file metadata from Google Drive.</p>
                    <div class="mt-2 flex gap-2 text-[11px] font-black uppercase">
                        <span class="rounded px-2 py-1 bg-blue-100 text-blue-700">Drive Blue</span>
                        <span class="rounded px-2 py-1 bg-amber-100 text-amber-700">Drive Yellow</span>
                        <span class="rounded px-2 py-1 bg-emerald-100 text-emerald-700">Drive Green</span>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-end gap-2 mb-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="text-[11px] font-black text-slate-500 uppercase">Drive Search Query (optional)</label>
                        <input v-model="query" type="text" placeholder="mimeType='application/pdf'" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700" />
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-500 uppercase">Page Size</label>
                        <input v-model.number="pageSize" type="number" min="1" max="1000" class="mt-1 w-24 rounded-lg border border-slate-300 px-2.5 py-2 text-sm font-medium text-slate-700" />
                    </div>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-black text-slate-700" :disabled="isLoading" @click="load">{{ isLoading ? 'Loading...' : 'Refresh' }}</button>
                </div>
                <div class="mb-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-3">
                        <p class="text-[11px] font-black uppercase text-blue-700">Loaded Files</p>
                        <p class="mt-1 font-black text-blue-700">{{ items.length }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-[11px] font-black uppercase text-emerald-700">Folders</p>
                        <p class="mt-1 font-black text-emerald-700">{{ folderCount }}</p>
                    </div>
                </div>
                <div v-if="renameId" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[11px] font-black uppercase text-amber-700">Rename File</p>
                    <div class="mt-2 flex gap-2">
                        <input v-model="renameValue" type="text" class="flex-1 rounded-lg border border-amber-200 px-3 py-2 text-sm" />
                        <button type="button" class="rounded-lg bg-amber-600 text-white px-3 py-2 text-xs font-black disabled:opacity-60" :disabled="isRenaming" @click="submitRename">{{ isRenaming ? 'Renaming...' : 'Rename' }}</button>
                        <button type="button" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-black text-amber-700" @click="renameId=''; renameValue=''">Cancel</button>
                    </div>
                </div>
                <div v-if="items.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm font-semibold text-slate-600">No drive files available.</div>
                <div v-else class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="max-h-[480px] overflow-y-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-blue-50 text-blue-700 uppercase tracking-wide">
                                <tr><th class="px-3 py-2 text-left font-black">Name</th><th class="px-3 py-2 text-left font-black">Type</th><th class="px-3 py-2 text-left font-black">Size</th><th class="px-3 py-2 text-left font-black">Actions</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="file in items" :key="file.id" class="border-t border-slate-200">
                                    <td class="px-3 py-2 text-slate-800 font-semibold">{{ file.name || '(Untitled)' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ file.mimeType || '-' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ formatFileSize(file.size) }}</td>
                                    <td class="px-3 py-2 text-slate-600 space-x-2">
                                        <a v-if="file.webViewLink" :href="file.webViewLink" target="_blank" rel="noopener" class="text-blue-700 hover:underline font-semibold">Open</a>
                                        <button type="button" class="text-amber-700 hover:underline font-semibold" @click="startRename(file)">Rename</button>
                                        <button type="button" class="text-rose-700 hover:underline font-semibold disabled:opacity-50" :disabled="isDeleting" @click="removeFile(file.id)">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <GoogleConnectModal
            :show="isGoogleConnectModalOpen"
            title="Connect Google Workspace"
            message="Your Google account is not connected. To access and manage your Google Drive files, please connect your account."
            @close="isGoogleConnectModalOpen = false"
        />
    </AppLayout>
</template>

