import api from '@/api';

/**
 * Review API V2 — @see docs/ReviewApiV2.md
 * Uses Sanctum cookie auth like v1. Pass skipAuthRedirect on GETs so optional reads
 * (e.g. public teacher profile) do not trigger a global login redirect on 401.
 */

const ALLOWED_API_ORIGIN = (import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '');
const V1_BASE = import.meta.env.VITE_API_BASE_URL || `${ALLOWED_API_ORIGIN}/api/v1`;

export function getApiV2BaseUrl() {
    if (import.meta.env.VITE_API_V2_BASE_URL) {
        return String(import.meta.env.VITE_API_V2_BASE_URL).replace(/\/$/, '');
    }
    return String(V1_BASE).replace(/\/v1\/?$/i, '/v2');
}

function normaliseError(e) {
    if (e instanceof Error) return e;
    const err = new Error(e?.message || 'Request failed');
    err.status = e?.code ?? null;
    if (e?.errors) err.errors = e.errors;
    return err;
}

/** Strip a single layer of wrapping quotes some APIs return on comments. */
function normaliseReviewRow(rev) {
    if (!rev || typeof rev !== 'object') return rev;
    const out = { ...rev };
    if (typeof out.comment === 'string') {
        let s = out.comment.trim();
        if (s.length >= 2 && ((s.startsWith('"') && s.endsWith('"')) || (s.startsWith("'") && s.endsWith("'")))) {
            s = s.slice(1, -1);
        }
        out.comment = s;
    }
    return out;
}

/**
 * Supports both documented shape `data: { data: [], current_page, … }` and live shape
 * `data: []` with Laravel `meta` + `links` at the root.
 */
function parseReviewsListPayload(body, fallbackPage, fallbackPerPage) {
    const rootData = body?.data;
    const meta = body?.meta && typeof body.meta === 'object' ? body.meta : null;

    let rows = [];
    let current_page = fallbackPage;
    let last_page = 1;
    let total = 0;
    let per_page = fallbackPerPage;

    if (Array.isArray(rootData)) {
        rows = rootData;
        if (meta) {
            current_page = Number(meta.current_page) > 0 ? Number(meta.current_page) : fallbackPage;
            last_page = Number(meta.last_page) > 0 ? Number(meta.last_page) : 1;
            total = Number(meta.total) >= 0 ? Number(meta.total) : rows.length;
            per_page = Number(meta.per_page) > 0 ? Number(meta.per_page) : fallbackPerPage;
        } else {
            total = rows.length;
            last_page = 1;
        }
    } else if (rootData && typeof rootData === 'object' && !Array.isArray(rootData)) {
        rows = Array.isArray(rootData.data) ? rootData.data : [];
        current_page = Number(rootData.current_page ?? meta?.current_page) > 0
            ? Number(rootData.current_page ?? meta?.current_page)
            : fallbackPage;
        last_page = Number(rootData.last_page ?? meta?.last_page) > 0
            ? Number(rootData.last_page ?? meta?.last_page)
            : 1;
        total = Number(rootData.total ?? meta?.total) >= 0
            ? Number(rootData.total ?? meta?.total)
            : rows.length;
        per_page = Number(rootData.per_page ?? meta?.per_page) > 0
            ? Number(rootData.per_page ?? meta?.per_page)
            : fallbackPerPage;
    }

    return {
        items: rows.map(normaliseReviewRow),
        pagination: {
            current_page,
            last_page,
            total,
            per_page,
        },
    };
}

const reviewFetchHeaders = {
    'Cache-Control': 'no-cache',
    Pragma: 'no-cache',
};

/**
 * @param {number} reviewableUserId
 * @returns {Promise<object|null>} stats payload or null
 */
export async function getTeacherReviewStats(reviewableUserId) {
    const id = Number(reviewableUserId);
    if (!Number.isFinite(id) || id <= 0) return null;
    try {
        const body = await api.get('/reviews/stats', {
            baseURL: getApiV2BaseUrl(),
            params: { reviewable_type: 'user', reviewable_id: id, _cb: Date.now() },
            headers: reviewFetchHeaders,
            skipAuthRedirect: true,
        });
        return body?.data ?? null;
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {number} reviewableUserId
 * @param {{ page?: number, per_page?: number, sort?: string }} opts
 */
export async function listTeacherReviews(reviewableUserId, opts = {}) {
    const id = Number(reviewableUserId);
    if (!Number.isFinite(id) || id <= 0) {
        const err = new Error('Invalid user id');
        err.status = 400;
        throw err;
    }
    const page = Number(opts.page) > 0 ? Number(opts.page) : 1;
    const per_page = Number(opts.per_page) > 0 ? Math.min(50, Number(opts.per_page)) : 10;
    const sort = opts.sort || 'latest';
    try {
        const body = await api.get('/reviews', {
            baseURL: getApiV2BaseUrl(),
            params: {
                reviewable_type: 'user',
                reviewable_id: id,
                sort,
                per_page,
                page,
                _cb: Date.now(),
            },
            headers: reviewFetchHeaders,
            skipAuthRedirect: true,
        });
        return parseReviewsListPayload(body, page, per_page);
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {number} reviewableUserId
 * @returns {Promise<{ can_review?: boolean, has_reviewed?: boolean, existing_review?: object }>}
 */
export async function checkReviewEligibility(reviewableUserId) {
    const id = Number(reviewableUserId);
    if (!Number.isFinite(id) || id <= 0) {
        const err = new Error('Invalid user id');
        err.status = 400;
        throw err;
    }
    try {
        const body = await api.get('/reviews/check', {
            baseURL: getApiV2BaseUrl(),
            params: { reviewable_type: 'user', reviewable_id: id, _cb: Date.now() },
            headers: reviewFetchHeaders,
        });
        return body?.data ?? {};
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {number} reviewableUserId
 * @param {{ rating: number, title?: string, comment?: string, tags?: string[] }} payload
 */
export async function submitTeacherReview(reviewableUserId, payload) {
    const id = Number(reviewableUserId);
    if (!Number.isFinite(id) || id <= 0) {
        const err = new Error('Invalid user id');
        err.status = 400;
        throw err;
    }
    try {
        const body = await api.post(
            '/reviews',
            {
                reviewable_type: 'user',
                reviewable_id: id,
                rating: payload.rating,
                ...(payload.title != null && String(payload.title).trim() !== ''
                    ? { title: String(payload.title).trim().slice(0, 255) }
                    : {}),
                ...(payload.comment != null && String(payload.comment).trim() !== ''
                    ? { comment: String(payload.comment).trim().slice(0, 5000) }
                    : {}),
                ...(Array.isArray(payload.tags) && payload.tags.length ? { tags: payload.tags } : {}),
            },
            { baseURL: getApiV2BaseUrl() },
        );
        return body?.data ?? null;
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {number} reviewId
 * @param {{ rating?: number, title?: string, comment?: string, tags?: string[] }} payload
 */
export async function updateTeacherReview(reviewId, payload) {
    const rid = Number(reviewId);
    if (!Number.isFinite(rid) || rid <= 0) {
        const err = new Error('Invalid review id');
        err.status = 400;
        throw err;
    }
    const bodyPayload = {};
    if (payload.rating != null) bodyPayload.rating = payload.rating;
    if (payload.title !== undefined) {
        bodyPayload.title = payload.title == null || String(payload.title).trim() === ''
            ? ''
            : String(payload.title).trim().slice(0, 255);
    }
    if (payload.comment !== undefined) {
        bodyPayload.comment = payload.comment == null || String(payload.comment).trim() === ''
            ? ''
            : String(payload.comment).trim().slice(0, 5000);
    }
    if (Array.isArray(payload.tags)) bodyPayload.tags = payload.tags;
    try {
        const body = await api.patch(`/reviews/${rid}`, bodyPayload, {
            baseURL: getApiV2BaseUrl(),
        });
        return body?.data ?? null;
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {{ page?: number, per_page?: number, sort?: string, status?: string }} opts
 * `status`: omit or `'all'` for every status; else `published` | `pending` | `rejected` | `hidden`
 */
export async function listMyReviews(opts = {}) {
    const page = Number(opts.page) > 0 ? Number(opts.page) : 1;
    const per_page = Number(opts.per_page) > 0 ? Math.min(50, Number(opts.per_page)) : 10;
    const sort = opts.sort || 'latest';
    const params = {
        sort,
        per_page,
        page,
        _cb: Date.now(),
    };
    const st = opts.status && String(opts.status).trim().toLowerCase();
    if (st && st !== 'all') {
        params.status = st;
    }
    try {
        const body = await api.get('/reviews/my', {
            baseURL: getApiV2BaseUrl(),
            params,
            headers: reviewFetchHeaders,
        });
        return parseReviewsListPayload(body, page, per_page);
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {number} reviewId
 * @returns {Promise<object|null>}
 */
export async function getReview(reviewId) {
    const rid = Number(reviewId);
    if (!Number.isFinite(rid) || rid <= 0) {
        const err = new Error('Invalid review id');
        err.status = 400;
        throw err;
    }
    try {
        const body = await api.get(`/reviews/${rid}`, {
            baseURL: getApiV2BaseUrl(),
            headers: reviewFetchHeaders,
        });
        return body?.data ?? null;
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * @param {number} reviewId
 */
export async function deleteTeacherReview(reviewId) {
    const rid = Number(reviewId);
    if (!Number.isFinite(rid) || rid <= 0) {
        const err = new Error('Invalid review id');
        err.status = 400;
        throw err;
    }
    try {
        await api.delete(`/reviews/${rid}`, {
            baseURL: getApiV2BaseUrl(),
        });
    } catch (e) {
        throw normaliseError(e);
    }
}
