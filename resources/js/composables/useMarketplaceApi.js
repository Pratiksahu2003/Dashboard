import api from '@/api';

const MARKETPLACE_BASE = 'https://www.suganta.in/api/marketplace';

const readRoot = payload => payload || {};
const readData = payload => payload?.data ?? {};
const readRowsAndMeta = payload => {
    const root = readRoot(payload);
    const dataNode = root?.data;

    // Shape A (nested pagination): { data: { data: [...], current_page, ... } }
    if (dataNode && !Array.isArray(dataNode) && Array.isArray(dataNode?.data)) {
        return {
            rows: dataNode.data,
            meta: dataNode,
        };
    }

    // Shape B (flat pagination): { data: [...], current_page, per_page, total, ... }
    if (Array.isArray(dataNode)) {
        return {
            rows: dataNode,
            meta: root,
        };
    }

    return {
        rows: [],
        meta: root || null,
    };
};

export const useMarketplaceApi = () => {
    const listListings = async ({ category = '', type = '', search = '', page = 1 } = {}) => {
        const payload = await api.get(`${MARKETPLACE_BASE}/listings`, {
            params: {
                category: category || undefined,
                type: type || undefined,
                search: search || undefined,
                page,
            },
        });
        return readRowsAndMeta(payload);
    };

    const getListing = async listingId => {
        const payload = await api.get(`${MARKETPLACE_BASE}/listings/${encodeURIComponent(listingId)}`);
        return readData(payload);
    };

    const listTrending = async () => {
        const payload = await api.get(`${MARKETPLACE_BASE}/trending`);
        const data = readData(payload);
        return Array.isArray(data) ? data : (data?.listings || []);
    };

    const listPlans = async () => {
        const payload = await api.get(`${MARKETPLACE_BASE}/plans`);
        const data = readData(payload);
        return Array.isArray(data) ? data : (data?.plans || []);
    };

    const purchaseSoftCopy = async listingId => {
        const payload = await api.post(`${MARKETPLACE_BASE}/listings/${encodeURIComponent(listingId)}/purchase`);
        const root = readRoot(payload);
        return {
            checkoutUrl: root?.checkout_url || '',
            message: root?.message || '',
            status: root?.status || '',
            data: root?.data || null,
        };
    };

    const contactSeller = async listingId => {
        const payload = await api.post(`${MARKETPLACE_BASE}/listings/${encodeURIComponent(listingId)}/contact`);
        const root = readRoot(payload);
        return {
            message: root?.message || '',
            conversationId: root?.conversation_id || root?.data?.conversation_id || null,
            status: root?.status || '',
        };
    };

    const secureDownload = async (listingId, token) => {
        const payload = await api.get(`${MARKETPLACE_BASE}/listings/${encodeURIComponent(listingId)}/download`, {
            params: { token },
        });
        return readRoot(payload);
    };

    const listMyListings = async ({ page = 1 } = {}) => {
        const payload = await api.get(`${MARKETPLACE_BASE}/my-listings`, {
            params: { page },
        });
        return readRowsAndMeta(payload);
    };

    const createMyListing = async formData => {
        const payload = await api.post(`${MARKETPLACE_BASE}/my-listings`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return readRoot(payload);
    };

    const updateMyListing = async (listingId, formData) => {
        formData.append('_method', 'PUT');
        const payload = await api.post(`${MARKETPLACE_BASE}/my-listings/${encodeURIComponent(listingId)}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return readRoot(payload);
    };

    const deleteMyListing = async listingId => {
        const payload = await api.delete(`${MARKETPLACE_BASE}/my-listings/${encodeURIComponent(listingId)}`);
        return readRoot(payload);
    };

    return {
        listListings,
        getListing,
        listTrending,
        listPlans,
        purchaseSoftCopy,
        contactSeller,
        secureDownload,
        listMyListings,
        createMyListing,
        updateMyListing,
        deleteMyListing,
    };
};
