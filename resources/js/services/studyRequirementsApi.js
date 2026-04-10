import api from '@/api';

export const STUDY_REQUIREMENTS_API_BASE = `${(import.meta.env.VITE_API_DOMAIN || 'https://www.suganta.in').replace(/\/$/, '')}/api/v1/study-requirements`;

function unwrap(body) {
    if (!body || typeof body !== 'object') {
        const err = new Error('Invalid response');
        err.code = 0;
        throw err;
    }
    if (body.success === false) {
        const err = new Error(body.message || 'Request failed');
        err.code = body.code;
        err.errors = body.errors;
        throw err;
    }
    return body.data || {};
}

export function listStudyRequirements(params) {
    return api.get(STUDY_REQUIREMENTS_API_BASE, { params }).then(unwrap);
}

export function createStudyRequirement(payload) {
    return api.post(STUDY_REQUIREMENTS_API_BASE, payload).then(unwrap);
}

export function getStudyRequirement(requirementId) {
    return api.get(`${STUDY_REQUIREMENTS_API_BASE}/${requirementId}`).then(unwrap);
}

export function listMyConnections(params) {
    return api.get(`${STUDY_REQUIREMENTS_API_BASE}/my-connections`, { params }).then(unwrap);
}

export function connectToRequirement(requirementId, payload) {
    return api.post(`${STUDY_REQUIREMENTS_API_BASE}/${requirementId}/connect`, payload || {}).then(unwrap);
}

