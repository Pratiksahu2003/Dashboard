import api from '@/api';

const MARKETPLACE_BASE = 'https://www.suganta.in/api/marketplace';

const readRoot = payload => payload || {};
const readData = payload => payload?.data ?? {};

const HTTP_URL_KEYS = ['checkout_url', 'checkoutUrl', 'payment_url', 'paymentUrl', 'redirect_url', 'redirectUrl', 'url'];

const normalizeHttpUrl = value => {
    if (value == null) return '';
    const s = String(value).trim();
    return /^https?:\/\//i.test(s) ? s : '';
};

const pickUrlFromRecord = obj => {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return '';
    for (const key of HTTP_URL_KEYS) {
        const u = normalizeHttpUrl(obj[key]);
        if (u) return u;
    }
    return '';
};

/**
 * Handles multiple API shapes (root / data / nested / camelCase) and axios error envelopes.
 */
export function extractMarketplaceCheckoutUrl(source) {
    if (!source || typeof source !== 'object') return '';

    const roots = [];
    if (source.responsePayload && typeof source.responsePayload === 'object') {
        roots.push(source.responsePayload);
    }
    roots.push(source);

    for (const root of roots) {
        let u = pickUrlFromRecord(root);
        if (u) return u;

        const d = root.data;
        if (typeof d === 'string') {
            u = normalizeHttpUrl(d);
            if (u) return u;
        }
        if (d && typeof d === 'object' && !Array.isArray(d)) {
            u = pickUrlFromRecord(d);
            if (u) return u;
            for (const v of Object.values(d)) {
                if (v && typeof v === 'object' && !Array.isArray(v)) {
                    u = pickUrlFromRecord(v);
                    if (u) return u;
                }
            }
        }
    }
    return '';
}

const DOWNLOAD_URL_KEYS = ['download_url', 'downloadUrl', 'download_path', 'downloadPath', 'file_url', 'fileUrl'];

const pickDownloadUrlFromRecord = obj => {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return '';
    for (const key of DOWNLOAD_URL_KEYS) {
        const u = normalizeHttpUrl(obj[key]);
        if (u) return u;
    }
    return '';
};

/** Resolves file URL from marketplace download API (root / data / nested; includes download_path). */
export function extractMarketplaceDownloadUrl(source) {
    if (!source || typeof source !== 'object') return '';

    const roots = [];
    if (source.responsePayload && typeof source.responsePayload === 'object') {
        roots.push(source.responsePayload);
    }
    roots.push(source);

    for (const root of roots) {
        let u = pickDownloadUrlFromRecord(root);
        if (u) return u;

        const d = root.data;
        if (d && typeof d === 'object' && !Array.isArray(d)) {
            u = pickDownloadUrlFromRecord(d);
            if (u) return u;
            for (const v of Object.values(d)) {
                if (v && typeof v === 'object' && !Array.isArray(v)) {
                    u = pickDownloadUrlFromRecord(v);
                    if (u) return u;
                }
            }
        }
    }
    return '';
}

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
            checkoutUrl: extractMarketplaceCheckoutUrl(payload),
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

    /** When the user already owns the listing (`is_purchased`), omit `token` so the API can authorize via Bearer only. */
    const secureDownload = async (listingId, token) => {
        const params = {};
        if (token !== undefined && token !== null && String(token).trim() !== '') {
            params.token = String(token).trim();
        }
        const payload = await api.get(`${MARKETPLACE_BASE}/listings/${encodeURIComponent(listingId)}/download`, {
            params,
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
