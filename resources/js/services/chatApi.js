import api from '@/api';

/** Matches `ALLOWED_API_ORIGIN` in `@/api.js` — Chat v3 lives under `/api/v3/chat`. */
export const CHAT_API_BASE = `${(import.meta.env.VITE_API_DOMAIN || 'https://www.suganta.in').replace(/\/$/, '')}/api/v3/chat`;

/** @param {unknown} body */
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
    return body.data;
}

/** @param {{ q: string, limit?: number }} params */
export function searchUsers(params) {
    return api.get(`${CHAT_API_BASE}/users/search`, { params }).then(unwrap);
}

/** @param {{ folder?: 'inbox'|'archived'|'all', page?: number }} params */
export function listConversations(params) {
    return api.get(`${CHAT_API_BASE}/conversations`, { params }).then(unwrap);
}

/** @param {{ type: 'private'|'group', title?: string, participants: number[] }} payload */
export function createConversation(payload) {
    return api.post(`${CHAT_API_BASE}/conversations`, payload).then(unwrap);
}

/** @param {number} conversationId */
export function getConversation(conversationId) {
    return api.get(`${CHAT_API_BASE}/conversations/${conversationId}`).then(unwrap);
}

/** @param {number} conversationId @param {{ muted?: boolean, archived?: boolean }} payload */
export function patchConversation(conversationId, payload) {
    return api.patch(`${CHAT_API_BASE}/conversations/${conversationId}`, payload).then(unwrap);
}

/** @param {number} conversationId @param {{ message_id?: number }} [payload] */
export function markConversationRead(conversationId, payload) {
    return api.post(`${CHAT_API_BASE}/conversations/${conversationId}/read`, payload ?? {}).then(unwrap);
}

/** @param {number} conversationId @param {{ user_id: number }} payload */
export function addParticipant(conversationId, payload) {
    return api.post(`${CHAT_API_BASE}/conversations/${conversationId}/participants`, payload).then(unwrap);
}

/** @param {number} conversationId @param {number} userId */
export function removeParticipant(conversationId, userId) {
    return api.delete(`${CHAT_API_BASE}/conversations/${conversationId}/participants/${userId}`).then(unwrap);
}

/** @param {number} conversationId */
export function leaveConversation(conversationId) {
    return api.post(`${CHAT_API_BASE}/conversations/${conversationId}/leave`).then(unwrap);
}

/**
 * @param {number} conversationId
 * @param {{ before_id?: number, per_page?: number, page?: number }} [params]
 */
export function listMessages(conversationId, params) {
    return api.get(`${CHAT_API_BASE}/conversations/${conversationId}/messages`, { params }).then(unwrap);
}

/** @param {number} conversationId @param {{ message: string, reply_to?: number }} payload */
export function sendMessage(conversationId, payload) {
    return api.post(`${CHAT_API_BASE}/conversations/${conversationId}/messages`, payload).then(unwrap);
}

/** @param {number} messageId @param {{ message: string }} payload */
export function editMessage(messageId, payload) {
    return api.patch(`${CHAT_API_BASE}/messages/${messageId}`, payload).then(unwrap);
}

/** @param {number} messageId */
export function deleteMessage(messageId) {
    return api.delete(`${CHAT_API_BASE}/messages/${messageId}`).then(unwrap);
}

/** @param {number} messageId */
export function markMessageRead(messageId) {
    return api.post(`${CHAT_API_BASE}/messages/${messageId}/read`).then(unwrap);
}

/** @param {number} messageId @param {{ reaction: string }} payload */
export function addReaction(messageId, payload) {
    return api.post(`${CHAT_API_BASE}/messages/${messageId}/reaction`, payload).then(unwrap);
}

/** @param {number} messageId */
export function removeReaction(messageId) {
    return api.delete(`${CHAT_API_BASE}/messages/${messageId}/reaction`).then(unwrap);
}

/** @param {number} conversationId @param {{ is_typing: boolean }} payload */
export function sendTyping(conversationId, payload) {
    return api.post(`${CHAT_API_BASE}/conversations/${conversationId}/typing`, payload).then(unwrap);
}
