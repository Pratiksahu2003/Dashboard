<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { splitCommaSeparatedString, toCommaSeparatedString, usePortfolioApi } from '@/composables/usePortfolioApi';

const { requireAuth } = useAuth();
const { success: showSuccess, error: showError, info: showInfo } = useAlerts();
const { getPortfolioOptions, getPortfolio, createPortfolio, updatePortfolio } = usePortfolioApi();
const props = defineProps({
    pageMode: {
        type: String,
        default: 'default',
    },
});

const isLoading = ref(false);
const isSubmitting = ref(false);
const options = ref({ statuses: {}, categories: [], tags: [] });
const planLimits = ref({ max_images: 0, max_files: 0 });
const portfolio = ref(null);
const uiMode = ref('view');

const removeImages = ref([]);
const removeFiles = ref([]);
const selectedImageFiles = ref([]);
const selectedDocumentFiles = ref([]);
const selectedImagePreviews = ref([]);
const form = ref({
    title: '',
    description: '',
    category: '',
    tags: '',
    url: '',
    status: 'draft',
    order: 0,
    is_featured: false,
});

const hasPortfolio = computed(() => Boolean(portfolio.value?.id));
const existingImages = computed(() => Array.isArray(portfolio.value?.images) ? portfolio.value.images : []);
const existingFiles = computed(() => Array.isArray(portfolio.value?.files) ? portfolio.value.files : []);
const maxImages = computed(() => Number(planLimits.value?.max_images || 0));
const maxFiles = computed(() => Number(planLimits.value?.max_files || 0));
const existingImageCountAfterRemove = computed(() => existingImages.value.filter(item => !removeImages.value.includes(item.path)).length);
const existingFileCountAfterRemove = computed(() => existingFiles.value.filter(item => !removeFiles.value.includes(item.path)).length);
const finalImageCount = computed(() => existingImageCountAfterRemove.value + selectedImageFiles.value.length);
const finalFileCount = computed(() => existingFileCountAfterRemove.value + selectedDocumentFiles.value.length);
const canSubmit = computed(() => form.value.title.trim().length > 0 && !isSubmitting.value);
const statusOptions = computed(() => Object.entries(options.value?.statuses || {}));
const parsedCategories = computed(() => splitCommaSeparatedString(form.value.category));
const parsedTags = computed(() => splitCommaSeparatedString(form.value.tags));
const isEditing = computed(() => uiMode.value === 'edit');
const isCreating = computed(() => uiMode.value === 'create');
const isFormVisible = computed(() => isEditing.value || isCreating.value);
const isCreatePage = computed(() => props.pageMode === 'create');
const quillToolbar = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const getFriendlyMessage = (error, fallback = 'Something went wrong. Please try again.') => {
    const code = Number(error?.code || 0);
    if (code === 401 || code === 403) return 'Please sign in again to continue.';
    if (code === 404) return 'We could not find your portfolio right now. Please refresh and try again.';
    if (code === 422) return 'Please check your input and try again.';
    if (code === 429) return 'Too many attempts. Please wait and try again.';
    if (code >= 500) return 'Server is busy right now. Please try again in a moment.';
    return fallback;
};

const releaseImagePreviewUrls = () => {
    selectedImagePreviews.value.forEach((item) => {
        if (item?.url) URL.revokeObjectURL(item.url);
    });
};

const resetUploads = () => {
    releaseImagePreviewUrls();
    selectedImageFiles.value = [];
    selectedDocumentFiles.value = [];
    selectedImagePreviews.value = [];
    removeImages.value = [];
    removeFiles.value = [];
};

const fillFormFromPortfolio = (value) => {
    form.value = {
        title: value?.title || '',
        description: value?.description || '',
        category: value?.category || '',
        tags: value?.tags || '',
        url: value?.url || '',
        status: value?.status || 'draft',
        order: Number(value?.order || 0),
        is_featured: Boolean(value?.is_featured),
    };
};

const loadData = async () => {
    isLoading.value = true;
    try {
        const [optionsData, portfolioData] = await Promise.all([
            getPortfolioOptions(),
            getPortfolio(),
        ]);
        options.value = {
            statuses: optionsData?.statuses || {},
            categories: Array.isArray(optionsData?.categories) ? optionsData.categories : [],
            tags: Array.isArray(optionsData?.tags) ? optionsData.tags : [],
        };
        portfolio.value = portfolioData?.portfolio || null;
        planLimits.value = portfolioData?.plan_limits || { max_images: 0, max_files: 0 };
        if (portfolio.value) {
            fillFormFromPortfolio(portfolio.value);
            uiMode.value = 'view';
        }
        else {
            const fallbackStatus = statusOptions.value[0]?.[0] || 'draft';
            form.value.status = fallbackStatus;
            uiMode.value = isCreatePage.value ? 'create' : 'view';
        }
        resetUploads();
    } catch (error) {
        showError(getFriendlyMessage(error, 'Unable to load your portfolio right now.'), 'Portfolio');
    } finally {
        isLoading.value = false;
    }
};

const fileListToArray = (fileList) => Array.from(fileList || []);

const onImagesSelected = (event) => {
    selectedImageFiles.value = fileListToArray(event?.target?.files);
};

const onFilesSelected = (event) => {
    selectedDocumentFiles.value = fileListToArray(event?.target?.files);
};

const toggleRemoveImage = (path) => {
    if (!path) return;
    if (removeImages.value.includes(path)) {
        removeImages.value = removeImages.value.filter(item => item !== path);
    } else {
        removeImages.value = [...removeImages.value, path];
    }
};

const toggleRemoveFile = (path) => {
    if (!path) return;
    if (removeFiles.value.includes(path)) {
        removeFiles.value = removeFiles.value.filter(item => item !== path);
    } else {
        removeFiles.value = [...removeFiles.value, path];
    }
};

const validateCounts = () => {
    if (maxImages.value > 0 && finalImageCount.value > maxImages.value) {
        showInfo(`Image limit exceeded. Allowed: ${maxImages.value}, selected total: ${finalImageCount.value}.`, 'Portfolio Limit');
        return false;
    }
    if (maxFiles.value > 0 && finalFileCount.value > maxFiles.value) {
        showInfo(`File limit exceeded. Allowed: ${maxFiles.value}, selected total: ${finalFileCount.value}.`, 'Portfolio Limit');
        return false;
    }
    return true;
};

const buildFormData = () => {
    const payload = new FormData();
    payload.append('title', form.value.title.trim());
    payload.append('description', form.value.description || '');
    payload.append('category', toCommaSeparatedString(parsedCategories.value));
    payload.append('tags', toCommaSeparatedString(parsedTags.value));
    payload.append('url', form.value.url || '');
    payload.append('status', form.value.status || 'draft');
    payload.append('order', String(Math.max(0, Number(form.value.order || 0))));
    payload.append('is_featured', form.value.is_featured ? '1' : '0');

    selectedImageFiles.value.forEach(file => payload.append('images[]', file));
    selectedDocumentFiles.value.forEach(file => payload.append('files[]', file));
    removeImages.value.forEach(path => payload.append('remove_images[]', path));
    removeFiles.value.forEach(path => payload.append('remove_files[]', path));

    return payload;
};

const submitPortfolio = async () => {
    if (!canSubmit.value) {
        showInfo('Title is required before saving.', 'Portfolio');
        return;
    }
    if (!validateCounts()) return;

    isSubmitting.value = true;
    try {
        const payload = buildFormData();
        if (hasPortfolio.value) {
            const data = await updatePortfolio(payload);
            portfolio.value = data;
            fillFormFromPortfolio(data);
            showSuccess('Portfolio updated successfully.', 'Portfolio');
        } else {
            const data = await createPortfolio(payload);
            portfolio.value = data;
            fillFormFromPortfolio(data);
            showSuccess('Portfolio created successfully.', 'Portfolio');
        }
        resetUploads();
        await loadData();
        uiMode.value = 'view';
    } catch (error) {
        showError(getFriendlyMessage(error, 'Unable to save your portfolio right now.'), 'Portfolio');
    } finally {
        isSubmitting.value = false;
    }
};

const startCreate = () => {
    const fallbackStatus = statusOptions.value[0]?.[0] || 'draft';
    form.value = {
        title: '',
        description: '',
        category: '',
        tags: '',
        url: '',
        status: fallbackStatus,
        order: 0,
        is_featured: false,
    };
    resetUploads();
    uiMode.value = 'create';
};

const startEdit = () => {
    if (!portfolio.value) return;
    fillFormFromPortfolio(portfolio.value);
    resetUploads();
    uiMode.value = 'edit';
};

const cancelForm = () => {
    resetUploads();
    if (hasPortfolio.value) {
        fillFormFromPortfolio(portfolio.value);
        uiMode.value = 'view';
        return;
    }
    startCreate();
};

onMounted(async () => {
    if (!requireAuth()) return;
    await loadData();
});

watch(selectedImageFiles, (files) => {
    releaseImagePreviewUrls();
    selectedImagePreviews.value = files.map((file) => ({
        name: file?.name || 'image',
        url: URL.createObjectURL(file),
    }));
});

onBeforeUnmount(() => {
    releaseImagePreviewUrls();
});
</script>

<template>
    <Head title="Portfolio" />

    <AppLayout>
        <template #breadcrumb>Portfolio</template>

        <div class="space-y-5">
            <section class="rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-slate-800 p-6 text-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-white/70">Portfolio API</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight">Portfolio Manager</h1>
                        <p class="mt-1 text-sm font-semibold text-white/85">Create and update your single portfolio with media, categories, tags, and status.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-white px-4 py-2 text-xs font-black text-slate-900 hover:bg-slate-100 disabled:opacity-60"
                        :disabled="isLoading"
                        @click="loadData"
                    >
                        {{ isLoading ? 'Refreshing...' : 'Refresh' }}
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Link
                        v-if="isCreatePage"
                        :href="route('portfolio')"
                        class="rounded-lg border border-white/35 bg-white/10 px-3 py-1.5 text-xs font-black text-white hover:bg-white/20"
                    >
                        Back To Portfolio
                    </Link>
                    <Link
                        v-else
                        :href="route('portfolio.create')"
                        class="rounded-lg border border-white/35 bg-white/10 px-3 py-1.5 text-xs font-black text-white hover:bg-white/20"
                    >
                        Open Create Page
                    </Link>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">Portfolio State</p>
                        <p class="mt-1 font-semibold">{{ hasPortfolio ? 'Created' : 'Not Created' }}</p>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">Image Limit</p>
                        <p class="mt-1 font-semibold">{{ maxImages || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="text-[11px] font-black uppercase text-white/70">File Limit</p>
                        <p class="mt-1 font-semibold">{{ maxFiles || 0 }}</p>
                    </div>
                </div>
            </section>

            <section v-if="!hasPortfolio && !isFormVisible" class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Portfolio Not Created</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Create your portfolio to add title, description, media, categories, and tags.</p>
                <Link
                    :href="route('portfolio.create')"
                    class="mt-4 inline-flex rounded-lg border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800"
                >
                    Create Portfolio
                </Link>
            </section>

            <section v-if="hasPortfolio && !isFormVisible" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Portfolio Details</h2>
                        <p class="text-xs font-semibold text-slate-500">Your portfolio is created. You can review details or edit it anytime.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800"
                        @click="startEdit"
                    >
                        Edit Portfolio
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-black uppercase text-slate-500">Title</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ portfolio?.title || '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-black uppercase text-slate-500">Status</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ portfolio?.status || '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 md:col-span-2">
                        <p class="text-[11px] font-black uppercase text-slate-500">Description</p>
                        <div
                            v-if="portfolio?.description"
                            class="portfolio-description-view mt-1 text-sm font-semibold text-slate-700"
                            v-html="portfolio.description"
                        />
                        <p v-else class="mt-1 text-sm font-semibold text-slate-700">-</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-black uppercase text-slate-500">Categories</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ portfolio?.category || '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-black uppercase text-slate-500">Tags</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ portfolio?.tags || '-' }}</p>
                    </div>
                </div>
            </section>

            <section v-if="isFormVisible" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">{{ isEditing ? 'Update Portfolio' : 'Create Portfolio' }}</h2>
                        <p class="text-xs font-semibold text-slate-500">Title is required. Media count respects plan limits.</p>
                    </div>
                    <span class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-black text-slate-700">
                        Images {{ finalImageCount }}/{{ maxImages || 0 }} | Files {{ finalFileCount }}/{{ maxFiles || 0 }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Title *</span>
                        <input v-model="form.title" type="text" class="field-input" placeholder="Portfolio title" />
                    </label>

                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Description</span>
                        <QuillEditor
                            v-model:content="form.description"
                            content-type="html"
                            theme="snow"
                            :toolbar="quillToolbar"
                            class="quill-editor"
                        />
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Categories (comma separated)</span>
                        <input v-model="form.category" type="text" class="field-input" placeholder="Web Design, Mobile App" list="portfolio-categories" />
                        <datalist id="portfolio-categories">
                            <option v-for="item in options.categories" :key="`category-${item}`" :value="item" />
                        </datalist>
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Tags (comma separated)</span>
                        <input v-model="form.tags" type="text" class="field-input" placeholder="React, Laravel" list="portfolio-tags" />
                        <datalist id="portfolio-tags">
                            <option v-for="item in options.tags" :key="`tag-${item}`" :value="item" />
                        </datalist>
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Project URL</span>
                        <input v-model="form.url" type="url" class="field-input" placeholder="https://example.com/project" />
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Status</span>
                        <select v-model="form.status" class="field-input bg-white">
                            <option v-for="[key, label] in statusOptions" :key="`status-${key}`" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Display Order</span>
                        <input v-model.number="form.order" type="number" min="0" class="field-input" />
                    </label>

                    <label class="field-wrap justify-center">
                        <span class="field-title">Featured</span>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">
                            <input v-model="form.is_featured" type="checkbox" />
                            Mark as featured
                        </label>
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Upload Images</span>
                        <input type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp" class="field-input" @change="onImagesSelected" />
                        <p class="text-[11px] font-semibold text-slate-500">Allowed: jpg, jpeg, png, gif, webp (max 5MB each).</p>
                        <div v-if="selectedImagePreviews.length" class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-4">
                            <div
                                v-for="item in selectedImagePreviews"
                                :key="`preview-${item.name}-${item.url}`"
                                class="overflow-hidden rounded-lg border border-slate-200"
                            >
                                <img :src="item.url" :alt="item.name" class="h-20 w-full object-cover" />
                                <p class="truncate px-2 py-1 text-[10px] font-semibold text-slate-600">{{ item.name }}</p>
                            </div>
                        </div>
                    </label>

                    <label class="field-wrap">
                        <span class="field-title">Upload Files</span>
                        <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" class="field-input" @change="onFilesSelected" />
                        <p class="text-[11px] font-semibold text-slate-500">Allowed: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, zip, rar (max 10MB each).</p>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800 disabled:opacity-70"
                        :disabled="!canSubmit"
                        @click="submitPortfolio"
                    >
                        {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update Portfolio' : 'Create Portfolio') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50"
                        :disabled="isLoading || isSubmitting"
                        @click="loadData"
                    >
                        Reload
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50"
                        :disabled="isSubmitting"
                        @click="cancelForm"
                    >
                        Cancel
                    </button>
                </div>
            </section>

            <section v-if="hasPortfolio" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-black text-slate-900">Existing Media</h3>
                <p class="mt-1 text-xs font-semibold text-slate-500">Select items to remove during update and then click Update Portfolio.</p>

                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="space-y-2">
                        <h4 class="text-sm font-black text-slate-800">Images</h4>
                        <div v-if="existingImages.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 text-xs font-semibold text-slate-500">
                            No images uploaded.
                        </div>
                        <label
                            v-for="item in existingImages"
                            :key="`img-${item.path}`"
                            class="flex items-center gap-2 rounded-lg border border-slate-200 p-2"
                        >
                            <input
                                type="checkbox"
                                :checked="removeImages.includes(item.path)"
                                @change="toggleRemoveImage(item.path)"
                            />
                            <a :href="item.url" target="_blank" rel="noopener noreferrer" class="truncate text-xs font-semibold text-blue-700 hover:underline">
                                {{ item.path }}
                            </a>
                        </label>
                        <div v-if="existingImages.length" class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-3">
                            <a
                                v-for="item in existingImages"
                                :key="`img-preview-${item.path}`"
                                :href="item.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="overflow-hidden rounded-lg border border-slate-200"
                            >
                                <img :src="item.url" :alt="item.path" class="h-20 w-full object-cover" />
                            </a>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h4 class="text-sm font-black text-slate-800">Files</h4>
                        <div v-if="existingFiles.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 text-xs font-semibold text-slate-500">
                            No files uploaded.
                        </div>
                        <label
                            v-for="item in existingFiles"
                            :key="`file-${item.path}`"
                            class="flex items-center gap-2 rounded-lg border border-slate-200 p-2"
                        >
                            <input
                                type="checkbox"
                                :checked="removeFiles.includes(item.path)"
                                @change="toggleRemoveFile(item.path)"
                            />
                            <a :href="item.url" target="_blank" rel="noopener noreferrer" class="truncate text-xs font-semibold text-blue-700 hover:underline">
                                {{ item.name || item.path }}
                            </a>
                        </label>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.field-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.field-title {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgb(71 85 105);
}

.field-input {
    border-radius: 0.5rem;
    border: 1px solid rgb(203 213 225);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(51 65 85);
}

.field-input:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    border-color: rgb(59 130 246);
}

.quill-editor :deep(.ql-toolbar) {
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
    border: 1px solid rgb(203 213 225);
}

.quill-editor :deep(.ql-container) {
    min-height: 140px;
    border-bottom-left-radius: 0.5rem;
    border-bottom-right-radius: 0.5rem;
    border: 1px solid rgb(203 213 225);
    border-top: 0;
    font-size: 0.875rem;
}

.portfolio-description-view :deep(p) {
    margin-bottom: 0.35rem;
}
</style>
