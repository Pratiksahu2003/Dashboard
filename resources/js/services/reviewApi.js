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
    return err;
}

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
            params: { reviewable_type: 'user', reviewable_id: id },
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
            },
            skipAuthRedirect: true,
        });
        const inner = body?.data ?? {};
        const rows = Array.isArray(inner.data) ? inner.data : [];
        return {
            items: rows,
            pagination: {
                current_page: inner.current_page ?? page,
                last_page: inner.last_page ?? 1,
                total: inner.total ?? rows.length,
                per_page: inner.per_page ?? per_page,
            },
        };
    } catch (e) {
        throw normaliseError(e);
    }
}
