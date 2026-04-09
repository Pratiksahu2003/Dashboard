import api from '@/api';

export const AI_ADVISER_API_BASE = 'https://www.suganta.in/api/v2/ai-adviser';

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

export function listConversations(params) {
    return api.get(`${AI_ADVISER_API_BASE}/conversations`, { params }).then(unwrap);
}

export function getConversation(conversationId) {
    return api.get(`${AI_ADVISER_API_BASE}/conversations/${conversationId}`).then(unwrap);
}

export function startConversation(payload) {
    return api.post(`${AI_ADVISER_API_BASE}/conversations`, payload).then(unwrap);
}

export function sendMessage(conversationId, payload) {
    return api.post(`${AI_ADVISER_API_BASE}/conversations/${conversationId}/message`, payload).then(unwrap);
}

export function getUsage() {
    return api.get(`${AI_ADVISER_API_BASE}/usage`).then(unwrap);
}

