<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useGoogleWorkspaceApi } from '@/composables/useGoogleWorkspaceApi';
import { useAlerts } from '@/composables/useAlerts';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Google Account Required',
    },
    message: {
        type: String,
        default: 'Your Google account is not connected. Please connect your account to access Google Workspace features.',
    },
});

const emit = defineEmits(['close']);

const { getOauthUrl } = useGoogleWorkspaceApi();
const { error: showError } = useAlerts();

const isConnecting = ref(false);

const handleConnect = async () => {
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

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div class="p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">{{ title }}</h3>
                    <p class="mt-1 text-sm font-medium text-slate-500">
                        {{ message }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <button
                    type="button"
                    class="inline-flex w-full justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-blue-700 transition disabled:opacity-60 sm:w-auto"
                    :disabled="isConnecting"
                    @click="handleConnect"
                >
                    <span v-if="isConnecting" class="mr-2">Connecting...</span>
                    <span v-else>Connect Google Workspace</span>
                </button>
                <button
                    type="button"
                    class="inline-flex w-full justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-black text-slate-700 hover:bg-slate-50 transition sm:w-auto"
                    @click="close"
                >
                    Maybe Later
                </button>
            </div>
        </div>
    </Modal>
</template>
