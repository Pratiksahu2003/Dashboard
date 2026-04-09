<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { useMarketplaceApi } from '@/composables/useMarketplaceApi';

const { requireAuth } = useAuth();
const { success: showSuccess, error: showError, info: showInfo, confirmDanger } = useAlerts();
const marketplaceApi = useMarketplaceApi();

const apiBase = 'https://www.suganta.in/api/marketplace';
const activeMenu = ref('public');
const publicTab = ref('discover');

const listingsLoading = ref(false);
const listings = ref([]);
const listingsMeta = ref(null);
const category = ref('');
const listingType = ref('');
const search = ref('');
const listingsPage = ref(1);

const trendingLoading = ref(false);
const trending = ref([]);
const plansLoading = ref(false);
const plans = ref([]);

const detailLoading = ref(false);
const selectedListing = ref(null);
const detailsOpen = ref(false);
const actionLoadingId = ref('');
const downloadToken = ref('');
const callbackLoading = ref(false);
const callbackMessage = ref('');

const myListingsLoading = ref(false);
const myListings = ref([]);
const myListingsMeta = ref(null);
const myListingsPage = ref(1);
const myFormOpen = ref(false);
const myFormMode = ref('create');
const editingId = ref(null);
const submitLoading = ref(false);

const form = ref({
    title: '',
    description: '',
    price: '',
    category: '',
    type: 'soft',
    thumbnail: '',
    status: 'active',
});
const uploadFile = ref(null);
const existingImagesInEdit = ref([]);
const thumbnailFileInputRef = ref(null);
const softFileInputRef = ref(null);
const thumbDragDepth = ref(0);
const uploadDragDepth = ref(0);
const thumbnailDropActive = computed(() => thumbDragDepth.value > 0);
const uploadDropActive = computed(() => uploadDragDepth.value > 0);

const quillToolbar = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const hasListingsPrev = computed(() => (listingsMeta.value?.current_page || 1) > 1);
const hasListingsNext = computed(() => (listingsMeta.value?.current_page || 1) < (listingsMeta.value?.last_page || 1));
const hasMinePrev = computed(() => (myListingsMeta.value?.current_page || 1) > 1);
const hasMineNext = computed(() => (myListingsMeta.value?.current_page || 1) < (myListingsMeta.value?.last_page || 1));

/** API expects 4–6 image URLs; create uses thumbnail ×4; edit uses saved images (trimmed to 6, padded with thumbnail if needed). */
const buildImagesForSubmit = () => {
    const thumb = String(form.value.thumbnail || '').trim();
    if (myFormMode.value === 'edit') {
        let imgs = [...existingImagesInEdit.value];
        if (imgs.length > 6) imgs = imgs.slice(0, 6);
        if (imgs.length >= 4) return imgs;
        if (thumb) {
            while (imgs.length < 4) imgs.push(thumb);
            return imgs.slice(0, 6);
        }
        return imgs;
    }
    if (thumb) return [thumb, thumb, thumb, thumb];
    return [];
};

const formatMoney = (amount, currency = 'INR') => {
    const numericAmount = Number(amount ?? 0);
    if (Number.isNaN(numericAmount)) return '-';
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(numericAmount);
    } catch {
        return `${currency} ${numericAmount.toFixed(2)}`;
    }
};

const renderRichHtml = value => {
    const raw = String(value || '').trim();
    if (!raw) return '<p>-</p>';
    if (typeof window === 'undefined' || typeof DOMParser === 'undefined') return `<p>${raw}</p>`;
    const parser = new DOMParser();
    const doc = parser.parseFromString(raw, 'text/html');
    doc.querySelectorAll('script, style, iframe, object, embed, link, meta').forEach(node => node.remove());
    doc.querySelectorAll('*').forEach(el => {
        [...el.attributes].forEach(attr => {
            const name = attr.name.toLowerCase();
            const val = String(attr.value || '').toLowerCase();
            if (name.startsWith('on')) el.removeAttribute(attr.name);
            if ((name === 'href' || name === 'src') && val.startsWith('javascript:')) el.removeAttribute(attr.name);
        });
    });
    return doc.body.innerHTML || '<p>-</p>';
};

const loadPublicListings = async () => {
    listingsLoading.value = true;
    try {
        const payload = await marketplaceApi.listListings({
            category: category.value.trim(),
            type: listingType.value,
            search: search.value.trim(),
            page: listingsPage.value,
        });
        listings.value = payload.rows;
        listingsMeta.value = payload.meta;
    } catch (error) {
        showError(error?.message || 'Unable to load marketplace listings.', 'Marketplace');
    } finally {
        listingsLoading.value = false;
    }
};

const loadTrending = async () => {
    trendingLoading.value = true;
    try {
        trending.value = await marketplaceApi.listTrending();
    } catch (error) {
        trending.value = [];
        showError(error?.message || 'Unable to load trending listings.', 'Marketplace');
    } finally {
        trendingLoading.value = false;
    }
};

const loadPlans = async () => {
    plansLoading.value = true;
    try {
        plans.value = await marketplaceApi.listPlans();
    } catch (error) {
        plans.value = [];
        showError(error?.message || 'Unable to load marketplace plans.', 'Marketplace');
    } finally {
        plansLoading.value = false;
    }
};

const loadMyListings = async () => {
    myListingsLoading.value = true;
    try {
        const payload = await marketplaceApi.listMyListings({ page: myListingsPage.value });
        myListings.value = payload.rows;
        myListingsMeta.value = payload.meta;
    } catch (error) {
        myListings.value = [];
        myListingsMeta.value = null;
        showError(error?.message || 'Unable to load your listings.', 'Marketplace');
    } finally {
        myListingsLoading.value = false;
    }
};

const refreshPublic = async () => {
    await Promise.all([loadPublicListings(), loadTrending(), loadPlans()]);
};

const applyPublicFilters = async () => {
    listingsPage.value = 1;
    await loadPublicListings();
};

const prevListingsPage = async () => {
    if (!hasListingsPrev.value) return;
    listingsPage.value -= 1;
    await loadPublicListings();
};

const nextListingsPage = async () => {
    if (!hasListingsNext.value) return;
    listingsPage.value += 1;
    await loadPublicListings();
};

const openDetails = async listing => {
    const id = listing?.id;
    if (!id) return;
    detailsOpen.value = true;
    detailLoading.value = true;
    selectedListing.value = null;
    downloadToken.value = '';
    try {
        selectedListing.value = await marketplaceApi.getListing(id);
    } catch (error) {
        showError(error?.message || 'Unable to load listing details.', 'Marketplace');
    } finally {
        detailLoading.value = false;
    }
};

const closeDetails = () => {
    detailsOpen.value = false;
    selectedListing.value = null;
    detailLoading.value = false;
    downloadToken.value = '';
};

const purchaseSoftCopy = async listing => {
    const id = listing?.id;
    if (!id) return;
    actionLoadingId.value = `buy-${id}`;
    try {
        const payload = await marketplaceApi.purchaseSoftCopy(id);
        if (payload.checkoutUrl) {
            window.open(payload.checkoutUrl, '_blank', 'noopener,noreferrer');
            showInfo('Checkout opened. Complete payment and return for download token flow.', 'Marketplace');
            return;
        }
        showInfo(payload?.message || 'Purchase initiated.', 'Marketplace');
    } catch (error) {
        showError(error?.message || 'Unable to initiate purchase.', 'Marketplace');
    } finally {
        actionLoadingId.value = '';
    }
};

const contactSeller = async listing => {
    const id = listing?.id;
    if (!id) return;
    actionLoadingId.value = `contact-${id}`;
    try {
        const payload = await marketplaceApi.contactSeller(id);
        showSuccess(payload?.message || 'Conversation initiated.', 'Marketplace');
        if (payload?.conversationId) {
            router.visit(route('chat', { conversation: payload.conversationId }));
        }
    } catch (error) {
        showError(error?.message || 'Unable to contact seller.', 'Marketplace');
    } finally {
        actionLoadingId.value = '';
    }
};

const secureDownload = async listing => {
    const id = listing?.id;
    if (!id) return;
    const token = String(downloadToken.value || '').trim();
    if (!token) {
        showInfo('Enter download token from payment success callback.', 'Marketplace');
        return;
    }
    actionLoadingId.value = `download-${id}`;
    try {
        const payload = await marketplaceApi.secureDownload(id, token);
        const url = payload?.download_url || payload?.data?.download_url || '';
        if (url) {
            window.open(url, '_blank', 'noopener,noreferrer');
            return;
        }
        showInfo(payload?.message || 'Download requested successfully.', 'Marketplace');
    } catch (error) {
        showError(error?.message || 'Unable to fetch secure download link.', 'Marketplace');
    } finally {
        actionLoadingId.value = '';
    }
};

const handleDownloadCallback = async () => {
    try {
        const params = new URLSearchParams(window.location.search || '');
        const listingId = Number(params.get('listing_id') || 0);
        const token = String(params.get('token') || '').trim();
        if (!listingId || !token) return;

        activeMenu.value = 'public';
        publicTab.value = 'discover';
        callbackLoading.value = true;
        callbackMessage.value = 'Verifying secure download token...';

        const payload = await marketplaceApi.secureDownload(listingId, token);
        const url = payload?.download_url || payload?.data?.download_url || '';
        if (url) {
            callbackMessage.value = 'Download link verified. Opening file...';
            window.open(url, '_blank', 'noopener,noreferrer');
            showSuccess('Your marketplace file is ready to download.', 'Marketplace');
        } else {
            callbackMessage.value = payload?.message || 'Token verified, but no file URL returned.';
            showInfo(callbackMessage.value, 'Marketplace');
        }

        // Clean callback params to avoid duplicate processing on refresh.
        params.delete('listing_id');
        params.delete('token');
        const cleanQuery = params.toString();
        const cleanUrl = `${window.location.pathname}${cleanQuery ? `?${cleanQuery}` : ''}${window.location.hash || ''}`;
        window.history.replaceState({}, '', cleanUrl);
    } catch (error) {
        callbackMessage.value = error?.message || 'Unable to process callback token.';
        showError(callbackMessage.value, 'Marketplace');
    } finally {
        callbackLoading.value = false;
    }
};

const resetForm = () => {
    form.value = {
        title: '',
        description: '',
        price: '',
        category: '',
        type: 'soft',
        thumbnail: '',
        status: 'active',
    };
    uploadFile.value = null;
    existingImagesInEdit.value = [];
    editingId.value = null;
    myFormMode.value = 'create';
};

const openCreate = () => {
    resetForm();
    myFormOpen.value = true;
};

const openEdit = listing => {
    form.value = {
        title: listing?.title || '',
        description: listing?.description || '',
        price: String(listing?.price || ''),
        category: listing?.category || '',
        type: listing?.type || 'soft',
        thumbnail: listing?.thumbnail || '',
        status: listing?.status || 'active',
    };
    uploadFile.value = null;
    existingImagesInEdit.value = Array.isArray(listing?.images) ? listing.images : [];
    editingId.value = listing?.id || null;
    myFormMode.value = 'edit';
    myFormOpen.value = true;
};

const MAX_THUMB_FILE_BYTES = 2 * 1024 * 1024;

const setUploadFile = file => {
    uploadFile.value = file || null;
};

const onUploadFileChanged = event => {
    const file = event?.target?.files?.[0] || null;
    setUploadFile(file);
};

const setThumbnailFromFile = file => {
    if (!file || !String(file.type || '').startsWith('image/')) {
        showInfo('Please drop or choose an image file.', 'Marketplace');
        return;
    }
    if (file.size > MAX_THUMB_FILE_BYTES) {
        showInfo('Image must be 2 MB or smaller.', 'Marketplace');
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        form.value.thumbnail = String(reader.result || '');
    };
    reader.onerror = () => {
        showInfo('Could not read that image.', 'Marketplace');
    };
    reader.readAsDataURL(file);
};

const onThumbnailFileInputChange = event => {
    const file = event?.target?.files?.[0] || null;
    if (file) setThumbnailFromFile(file);
    event.target.value = '';
};

const openThumbnailFilePicker = () => {
    thumbnailFileInputRef.value?.click();
};

const onThumbnailDragEnter = e => {
    e.preventDefault();
    e.stopPropagation();
    thumbDragDepth.value += 1;
};

const onThumbnailDragLeave = e => {
    e.preventDefault();
    e.stopPropagation();
    thumbDragDepth.value = Math.max(0, thumbDragDepth.value - 1);
};

const onThumbnailDragOver = e => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
};

const onThumbnailDrop = e => {
    e.preventDefault();
    e.stopPropagation();
    thumbDragDepth.value = 0;
    const file = e.dataTransfer?.files?.[0] || null;
    if (file) setThumbnailFromFile(file);
};

const onUploadDragEnter = e => {
    e.preventDefault();
    e.stopPropagation();
    uploadDragDepth.value += 1;
};

const onUploadDragLeave = e => {
    e.preventDefault();
    e.stopPropagation();
    uploadDragDepth.value = Math.max(0, uploadDragDepth.value - 1);
};

const onUploadDragOver = e => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
};

const onUploadDrop = e => {
    e.preventDefault();
    e.stopPropagation();
    uploadDragDepth.value = 0;
    const file = e.dataTransfer?.files?.[0] || null;
    if (file) setUploadFile(file);
};

const openSoftFilePicker = () => {
    softFileInputRef.value?.click();
};

const submitMyListing = async () => {
    const images = buildImagesForSubmit();
    if (!form.value.title.trim()) {
        showInfo('Title is required.', 'Marketplace');
        return;
    }
    if (!form.value.description.trim()) {
        showInfo('Description is required.', 'Marketplace');
        return;
    }
    if (!form.value.price || Number(form.value.price) < 0) {
        showInfo('Valid price is required.', 'Marketplace');
        return;
    }
    if (images.length < 4 || images.length > 6) {
        if (myFormMode.value === 'create') {
            showInfo('Add a thumbnail URL — it is used to build the required gallery image set.', 'Marketplace');
        } else {
            showInfo('Listing must have 4 to 6 images. Refresh and try again, or contact support.', 'Marketplace');
        }
        return;
    }
    if (form.value.type === 'soft' && myFormMode.value === 'create' && !uploadFile.value) {
        showInfo('Soft listing requires file upload.', 'Marketplace');
        return;
    }

    const payload = new FormData();
    payload.append('title', form.value.title.trim());
    payload.append('description', form.value.description.trim());
    payload.append('price', String(form.value.price));
    payload.append('type', form.value.type);
    payload.append('category', form.value.category.trim());
    payload.append('thumbnail', form.value.thumbnail.trim());
    payload.append('status', form.value.status);
    images.forEach(url => payload.append('images[]', url));
    if (uploadFile.value) payload.append('file_path', uploadFile.value);

    submitLoading.value = true;
    try {
        if (myFormMode.value === 'edit' && editingId.value) {
            await marketplaceApi.updateMyListing(editingId.value, payload);
            showSuccess('Listing updated successfully.', 'Marketplace');
        } else {
            await marketplaceApi.createMyListing(payload);
            showSuccess('Listing created successfully.', 'Marketplace');
        }
        myFormOpen.value = false;
        resetForm();
        await loadMyListings();
    } catch (error) {
        showError(error?.message || 'Unable to save listing.', 'Marketplace');
    } finally {
        submitLoading.value = false;
    }
};

const removeListing = async listing => {
    const id = listing?.id;
    if (!id) return;
    const confirmed = await confirmDanger({
        title: 'Delete Listing?',
        text: 'This will permanently remove this listing.',
        confirmText: 'Delete',
    });
    if (!confirmed) return;

    actionLoadingId.value = `delete-${id}`;
    try {
        await marketplaceApi.deleteMyListing(id);
        showSuccess('Listing removed successfully.', 'Marketplace');
        await loadMyListings();
    } catch (error) {
        showError(error?.message || 'Unable to remove listing.', 'Marketplace');
    } finally {
        actionLoadingId.value = '';
    }
};

const prevMyPage = async () => {
    if (!hasMinePrev.value) return;
    myListingsPage.value -= 1;
    await loadMyListings();
};

const nextMyPage = async () => {
    if (!hasMineNext.value) return;
    myListingsPage.value += 1;
    await loadMyListings();
};

const switchMenu = async value => {
    activeMenu.value = value;
    if (value === 'public') await refreshPublic();
    if (value === 'seller') await loadMyListings();
};

onMounted(async () => {
    if (!requireAuth()) return;
    await refreshPublic();
    await handleDownloadCallback();
});
</script>

<template>
    <Head title="Marketplace" />

    <AppLayout>
        <template #breadcrumb>Marketplace</template>

        <div class="space-y-5">
            <section class="rounded-3xl bg-gradient-to-br from-indigo-700 via-blue-700 to-slate-800 p-6 text-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-white/70">Marketplace API V6</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight">Marketplace Dashboard</h1>
                        <p class="mt-1 text-sm font-semibold text-white/85">Public discovery and seller management in one optimized flow.</p>
                        <p class="mt-1 text-[11px] font-bold text-white/80">API Base: {{ apiBase }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-black transition"
                            :class="activeMenu === 'public' ? 'border-white bg-white text-slate-900' : 'border-white/50 bg-white/10 text-white hover:bg-white/20'"
                            @click="switchMenu('public')"
                        >
                            Menu 1: Public Listing
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-black transition"
                            :class="activeMenu === 'seller' ? 'border-white bg-white text-slate-900' : 'border-white/50 bg-white/10 text-white hover:bg-white/20'"
                            @click="switchMenu('seller')"
                        >
                            Menu 2: My Listings
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="callbackMessage" class="rounded-3xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-700">Payment Callback</p>
                        <p class="mt-1 text-sm font-semibold text-blue-800">{{ callbackMessage }}</p>
                    </div>
                    <span class="rounded-md border px-2 py-1 text-[11px] font-black uppercase"
                        :class="callbackLoading ? 'border-blue-300 bg-white text-blue-700' : 'border-emerald-300 bg-emerald-50 text-emerald-700'">
                        {{ callbackLoading ? 'Processing' : 'Done' }}
                    </span>
                </div>
            </section>

            <template v-if="activeMenu === 'public'">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-black transition"
                            :class="publicTab === 'discover' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                            @click="publicTab = 'discover'"
                        >
                            Discover
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-black transition"
                            :class="publicTab === 'trending' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                            @click="publicTab = 'trending'; loadTrending()"
                        >
                            Trending
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-black transition"
                            :class="publicTab === 'plans' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                            @click="publicTab = 'plans'; loadPlans()"
                        >
                            Plans
                        </button>
                    </div>
                </section>

                <section v-if="publicTab === 'discover'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-5">
                        <label class="block lg:col-span-2">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Search</span>
                            <input v-model="search" type="text" class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm font-semibold text-slate-700" placeholder="Search by title..." />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
                            <input v-model="category" type="text" class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm font-semibold text-slate-700" placeholder="Education" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                            <select v-model="listingType" class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm font-semibold text-slate-700">
                                <option value="">All</option>
                                <option value="soft">Soft</option>
                                <option value="hard">Hard</option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition"
                            :disabled="listingsLoading"
                            @click="applyPublicFilters"
                        >
                            {{ listingsLoading ? 'Loading...' : 'Apply' }}
                        </button>
                    </div>
                </section>

                <section v-if="publicTab === 'discover'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div v-if="listingsLoading" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="i in 9" :key="`market-loading-${i}`" class="h-48 rounded-xl bg-slate-100 animate-pulse"></div>
                    </div>
                    <div v-else-if="listings.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <p class="text-sm font-semibold text-slate-600">No listings found.</p>
                    </div>
                    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="item in listings" :key="item.id" class="rounded-xl border border-slate-200 p-4">
                            <img
                                v-if="item.thumbnail"
                                :src="item.thumbnail"
                                alt="thumb"
                                class="h-32 w-full rounded-lg object-cover border border-slate-200"
                            />
                            <h3 class="mt-3 text-base font-black text-slate-900">{{ item.title || 'Listing' }}</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-600 line-clamp-2">{{ item.description || '-' }}</p>
                            <p class="mt-2 text-lg font-black text-slate-900">{{ formatMoney(item.price, 'INR') }}</p>
                            <p class="text-[11px] font-bold text-slate-500">
                                {{ item.category || '-' }} | {{ item.type || '-' }} | Views {{ item.views_count || 0 }}
                            </p>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100" @click="openDetails(item)">Details</button>
                                <button
                                    v-if="item.type === 'soft'"
                                    type="button"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 disabled:opacity-60"
                                    :disabled="actionLoadingId === `buy-${item.id}`"
                                    @click="purchaseSoftCopy(item)"
                                >
                                    {{ actionLoadingId === `buy-${item.id}` ? 'Starting...' : 'Buy Soft Copy' }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100 disabled:opacity-60"
                                    :disabled="actionLoadingId === `contact-${item.id}`"
                                    @click="contactSeller(item)"
                                >
                                    {{ actionLoadingId === `contact-${item.id}` ? 'Connecting...' : 'Contact Seller' }}
                                </button>
                            </div>
                        </article>
                    </div>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-xs font-bold text-slate-500">Showing {{ listingsMeta?.from || 0 }}-{{ listingsMeta?.to || 0 }} of {{ listingsMeta?.total || 0 }}</p>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 disabled:opacity-50" :disabled="!hasListingsPrev || listingsLoading" @click="prevListingsPage">Previous</button>
                            <span class="text-xs font-black text-slate-700">Page {{ listingsMeta?.current_page || 1 }} / {{ listingsMeta?.last_page || 1 }}</span>
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 disabled:opacity-50" :disabled="!hasListingsNext || listingsLoading" @click="nextListingsPage">Next</button>
                        </div>
                    </div>
                </section>

                <section v-if="publicTab === 'trending'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div v-if="trendingLoading" class="space-y-3">
                        <div v-for="i in 8" :key="`trend-loading-${i}`" class="h-16 rounded-xl bg-slate-100 animate-pulse"></div>
                    </div>
                    <div v-else-if="trending.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <p class="text-sm font-semibold text-slate-600">No trending listings right now.</p>
                    </div>
                    <div v-else class="space-y-2">
                        <article v-for="item in trending" :key="`trend-${item.id}`" class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-black text-slate-900">{{ item.title || 'Listing' }}</h3>
                                <p class="text-sm font-black text-slate-900">{{ formatMoney(item.price, 'INR') }}</p>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ item.category || '-' }} | {{ item.type || '-' }}</p>
                        </article>
                    </div>
                </section>

                <section v-if="publicTab === 'plans'" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div v-if="plansLoading" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div v-for="i in 4" :key="`plan-loading-${i}`" class="h-40 rounded-xl bg-slate-100 animate-pulse"></div>
                    </div>
                    <div v-else-if="plans.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <p class="text-sm font-semibold text-slate-600">No plans available.</p>
                    </div>
                    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <article v-for="plan in plans" :key="`market-plan-${plan.id || plan.name}`" class="rounded-xl border border-slate-200 p-4">
                            <h3 class="text-base font-black text-slate-900">{{ plan.name || 'Marketplace Plan' }}</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ plan.description || '-' }}</p>
                            <p class="mt-2 text-lg font-black text-slate-900">{{ plan.formatted_price || formatMoney(plan.price, plan.currency || 'INR') }}</p>
                        </article>
                    </div>
                </section>
            </template>

            <template v-else>
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-black text-slate-900">My Listings Management</h2>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100" :disabled="myListingsLoading" @click="loadMyListings">
                                Refresh
                            </button>
                            <button type="button" class="rounded-lg border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-black text-white hover:bg-slate-800" @click="openCreate">
                                Create Listing
                            </button>
                        </div>
                    </div>
                </section>

                <section
                    v-if="myFormOpen"
                    class="relative overflow-hidden rounded-3xl border border-slate-200/90 bg-gradient-to-b from-white via-white to-slate-50/90 shadow-xl shadow-slate-300/30 ring-1 ring-slate-900/5"
                >
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-violet-500 via-fuchsia-500 to-amber-400" aria-hidden="true"></div>
                    <div class="p-6 sm:p-8">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide"
                                        :class="myFormMode === 'edit' ? 'bg-violet-100 text-violet-800 ring-1 ring-violet-200/80' : 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80'"
                                    >
                                        {{ myFormMode === 'edit' ? 'Edit' : 'Create' }}
                                    </span>
                                    <span v-if="myFormMode === 'edit' && editingId" class="text-xs font-semibold text-slate-500">ID {{ editingId }}</span>
                                </div>
                                <h3 class="mt-2 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
                                    {{ myFormMode === 'edit' ? 'Update your listing' : 'Publish a new listing' }}
                                </h3>
                                <p class="mt-1 max-w-xl text-sm font-medium leading-relaxed text-slate-600">
                                    {{
                                        myFormMode === 'edit'
                                            ? 'Adjust details, media, and delivery. Changes apply after you save.'
                                            : 'Add a title, rich description, pricing, and media. Soft listings need an upload on first save.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 space-y-10">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Basics</p>
                                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <label class="block md:col-span-2 group">
                                        <span class="mb-2 flex items-center gap-2 text-xs font-bold text-slate-700">
                                            Title
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-500">Required</span>
                                        </span>
                                        <input
                                            v-model="form.title"
                                            type="text"
                                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/15"
                                            placeholder="e.g. Class 12 Physics notes bundle"
                                        />
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="mb-2 block text-xs font-bold text-slate-700">Description</span>
                                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-900/[0.04] transition focus-within:border-violet-400 focus-within:ring-4 focus-within:ring-violet-500/15">
                                            <QuillEditor
                                                v-model:content="form.description"
                                                content-type="html"
                                                theme="snow"
                                                :toolbar="quillToolbar"
                                                class="quill-editor--modern"
                                            />
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Pricing &amp; type</p>
                                <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <label class="block">
                                        <span class="mb-2 block text-xs font-bold text-slate-700">Price (INR)</span>
                                        <input
                                            v-model="form.price"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/15"
                                            placeholder="0"
                                        />
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-xs font-bold text-slate-700">Category</span>
                                        <input
                                            v-model="form.category"
                                            type="text"
                                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/15"
                                            placeholder="Education, Science…"
                                        />
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-xs font-bold text-slate-700">Listing type</span>
                                        <select
                                            v-model="form.type"
                                            class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/15"
                                        >
                                            <option value="soft">Soft copy</option>
                                            <option value="hard">Hard copy</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-xs font-bold text-slate-700">Status</span>
                                        <select
                                            v-model="form.status"
                                            class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/15"
                                        >
                                            <option value="active">Active</option>
                                            <option value="sold">Sold</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Media</p>
                                <div class="mt-4 space-y-5">
                                    <label class="block">
                                        <span class="mb-2 block text-xs font-bold text-slate-700">Thumbnail URL</span>
                                        <input
                                            v-model="form.thumbnail"
                                            type="url"
                                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/15"
                                            placeholder="https://… or drop an image below"
                                        />
                                        <p class="mt-2 text-xs font-medium leading-relaxed text-slate-500">
                                            For new listings, this image is repeated to meet the gallery image requirement (4 URLs).
                                        </p>
                                    </label>

                                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                                        <div
                                            class="group relative overflow-hidden rounded-2xl border-2 border-dashed bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm transition-all duration-200"
                                            :class="thumbnailDropActive ? 'border-violet-400 from-violet-50/90 to-white shadow-md ring-2 ring-violet-500/20' : 'border-slate-200 hover:border-slate-300'"
                                            @dragenter="onThumbnailDragEnter"
                                            @dragleave="onThumbnailDragLeave"
                                            @dragover="onThumbnailDragOver"
                                            @drop="onThumbnailDrop"
                                        >
                                            <input
                                                ref="thumbnailFileInputRef"
                                                type="file"
                                                accept="image/*"
                                                class="sr-only"
                                                @change="onThumbnailFileInputChange"
                                            />
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Cover image</p>
                                                    <p class="mt-0.5 text-xs font-medium text-slate-500">Drop PNG or JPG · max 2 MB</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-800 shadow-sm transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-900"
                                                    @click.stop="openThumbnailFilePicker"
                                                >
                                                    Browse
                                                </button>
                                            </div>
                                            <div v-if="!form.thumbnail?.trim()" class="mt-4 flex min-h-[8rem] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200/80 bg-white/70 px-4 text-center">
                                                <div
                                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-100 to-slate-100 text-violet-600 shadow-inner"
                                                    aria-hidden="true"
                                                >
                                                    <svg class="h-5 w-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.75"
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                        />
                                                    </svg>
                                                </div>
                                                <p class="mt-3 text-xs font-semibold text-slate-400">Drag an image here</p>
                                            </div>
                                            <div v-else class="mt-4 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-inner">
                                                <img :src="form.thumbnail" alt="Thumbnail preview" class="h-36 w-full object-cover transition group-hover:scale-[1.02]" />
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50/80 to-white p-4 shadow-sm">
                                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Gallery (saved)</p>
                                            <p class="mt-1 text-xs font-medium text-slate-500">Images already stored for this listing.</p>
                                            <div v-if="myFormMode !== 'edit'" class="mt-6 flex min-h-[8rem] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white/60 px-3 text-center text-xs font-semibold text-slate-400">
                                                Shown when you edit a listing
                                            </div>
                                            <div v-else-if="existingImagesInEdit.length === 0" class="mt-6 flex min-h-[8rem] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white/60 px-3 text-center text-xs font-semibold text-slate-400">
                                                No saved images yet
                                            </div>
                                            <div v-else class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                <a
                                                    v-for="(url, idx) in existingImagesInEdit"
                                                    :key="`existing-image-${idx}-${url}`"
                                                    :href="url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="group/img overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm transition hover:ring-2 hover:ring-violet-300/60"
                                                >
                                                    <img :src="url" alt="Listing image" class="h-24 w-full object-cover transition group-hover/img:scale-105" />
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Delivery file</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">Required when creating a soft-copy listing.</p>
                                <input ref="softFileInputRef" type="file" class="sr-only" @change="onUploadFileChanged" />
                                <div
                                    class="mt-4 rounded-2xl border-2 border-dashed px-4 py-8 text-center transition-all duration-200"
                                    :class="uploadDropActive ? 'border-violet-400 bg-violet-50/80 shadow-md ring-2 ring-violet-500/20' : 'border-slate-200 bg-gradient-to-br from-slate-50/90 to-white hover:border-slate-300'"
                                    @dragenter="onUploadDragEnter"
                                    @dragleave="onUploadDragLeave"
                                    @dragover="onUploadDragOver"
                                    @drop="onUploadDrop"
                                >
                                    <p class="text-sm font-semibold text-slate-700">
                                        Drop your file here, or
                                        <button
                                            type="button"
                                            class="font-black text-violet-600 underline decoration-2 underline-offset-4 transition hover:text-violet-800"
                                            @click="openSoftFilePicker"
                                        >
                                            browse
                                        </button>
                                    </p>
                                    <p v-if="uploadFile" class="mt-3 inline-flex max-w-full items-center gap-2 truncate rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-200/80">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path
                                                fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.414l2.293 2.293 6.543-6.543a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        {{ uploadFile.name }}
                                    </p>
                                    <p v-else class="mt-3 text-xs font-medium text-slate-400">No file selected</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-10 flex flex-col-reverse gap-3 border-t border-slate-200/80 pt-6 sm:flex-row sm:items-center sm:justify-end">
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
                                :disabled="submitLoading"
                                @click="myFormOpen = false; resetForm()"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-transparent bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-3 text-sm font-black text-white shadow-lg shadow-slate-900/25 transition hover:from-slate-800 hover:to-slate-700 disabled:opacity-60"
                                :disabled="submitLoading"
                                @click="submitMyListing"
                            >
                                {{ submitLoading ? 'Saving…' : (myFormMode === 'edit' ? 'Save changes' : 'Publish listing') }}
                            </button>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div v-if="myListingsLoading" class="space-y-3">
                        <div v-for="i in 8" :key="`mine-loading-${i}`" class="h-20 rounded-xl bg-slate-100 animate-pulse"></div>
                    </div>
                    <div v-else-if="myListings.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <p class="text-sm font-semibold text-slate-600">No listings created yet.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <article v-for="item in myListings" :key="`mine-${item.id}`" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-black text-slate-900">{{ item.title || 'My Listing' }}</h3>
                                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ formatMoney(item.price, 'INR') }} | {{ item.type || '-' }}</p>
                                    <p class="mt-1 text-[11px] font-bold text-slate-500">Status: {{ item.status || '-' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100" @click="openEdit(item)">
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-black text-rose-700 hover:bg-rose-100 disabled:opacity-60"
                                        :disabled="actionLoadingId === `delete-${item.id}`"
                                        @click="removeListing(item)"
                                    >
                                        {{ actionLoadingId === `delete-${item.id}` ? 'Deleting...' : 'Delete' }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-xs font-bold text-slate-500">Showing {{ myListingsMeta?.from || 0 }}-{{ myListingsMeta?.to || 0 }} of {{ myListingsMeta?.total || 0 }}</p>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 disabled:opacity-50" :disabled="!hasMinePrev || myListingsLoading" @click="prevMyPage">Previous</button>
                            <span class="text-xs font-black text-slate-700">Page {{ myListingsMeta?.current_page || 1 }} / {{ myListingsMeta?.last_page || 1 }}</span>
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 disabled:opacity-50" :disabled="!hasMineNext || myListingsLoading" @click="nextMyPage">Next</button>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>

    <div v-if="detailsOpen" class="fixed inset-0 z-40">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeDetails"></div>
        <aside class="absolute right-0 top-0 h-full w-full max-w-xl bg-white border-l border-slate-200 shadow-xl p-5 overflow-y-auto">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Listing Details</p>
                    <h3 class="mt-1 text-lg font-black text-slate-900">{{ selectedListing?.title || 'Listing' }}</h3>
                </div>
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100" @click="closeDetails">Close</button>
            </div>
            <div v-if="detailLoading" class="mt-4 h-24 rounded-xl bg-slate-100 animate-pulse"></div>
            <div v-else-if="selectedListing" class="mt-4 space-y-3">
                <img v-if="selectedListing.thumbnail" :src="selectedListing.thumbnail" alt="thumb" class="h-48 w-full rounded-xl object-cover border border-slate-200" />
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="text-sm font-semibold text-slate-700 [&>*]:m-0" v-html="renderRichHtml(selectedListing.description)" />
                    <p class="mt-2 text-xs font-bold text-slate-500">
                        {{ selectedListing.category || '-' }} | {{ selectedListing.type || '-' }} | Views {{ selectedListing.views_count || 0 }}
                    </p>
                    <p class="mt-1 text-lg font-black text-slate-900">{{ formatMoney(selectedListing.price, 'INR') }}</p>
                </div>
                <div class="grid grid-cols-1 gap-2">
                    <button
                        v-if="selectedListing.type === 'soft'"
                        type="button"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 disabled:opacity-60"
                        :disabled="actionLoadingId === `buy-${selectedListing.id}`"
                        @click="purchaseSoftCopy(selectedListing)"
                    >
                        {{ actionLoadingId === `buy-${selectedListing.id}` ? 'Starting...' : 'Buy Soft Copy' }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100 disabled:opacity-60"
                        :disabled="actionLoadingId === `contact-${selectedListing.id}`"
                        @click="contactSeller(selectedListing)"
                    >
                        {{ actionLoadingId === `contact-${selectedListing.id}` ? 'Connecting...' : 'Contact Seller' }}
                    </button>
                    <div v-if="selectedListing.type === 'soft'" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Secure Download Token</p>
                        <input
                            v-model="downloadToken"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm font-semibold text-slate-700"
                            placeholder="Paste token from payment callback..."
                        />
                        <button
                            type="button"
                            class="mt-2 rounded-lg border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-black text-white hover:bg-slate-800 disabled:opacity-60"
                            :disabled="actionLoadingId === `download-${selectedListing.id}`"
                            @click="secureDownload(selectedListing)"
                        >
                            {{ actionLoadingId === `download-${selectedListing.id}` ? 'Requesting...' : 'Get Secure Download' }}
                        </button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>

<style scoped>
.quill-editor--modern :deep(.ql-toolbar.ql-snow) {
    border: 0;
    border-bottom: 1px solid rgb(226 232 240);
    border-radius: 0;
    background: rgb(248 250 252);
    padding: 0.5rem 0.75rem;
}

.quill-editor--modern :deep(.ql-container.ql-snow) {
    border: 0;
    min-height: 168px;
    font-size: 0.875rem;
    color: rgb(15 23 42);
}

.quill-editor--modern :deep(.ql-editor) {
    min-height: 140px;
    padding: 0.75rem 1rem;
}

.quill-editor--modern :deep(.ql-editor.ql-blank::before) {
    color: rgb(148 163 184);
    font-style: normal;
}
</style>
