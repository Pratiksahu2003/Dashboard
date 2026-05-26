import api from '@/api';

const API_ORIGIN = (import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '');
const SOCIAL_API_BASE_URL = (import.meta.env.VITE_SOCIAL_API_BASE_URL || `${API_ORIGIN}/api`).replace(/\/$/, '');

export const socialApiConfig = (config = {}) => ({
    baseURL: SOCIAL_API_BASE_URL,
    skipCsrfBootstrap: true,
    ...config,
});

export const socialPost = (path, data = {}, config = {}) =>
    api.post(path, data, socialApiConfig(config));

export const socialGet = (path, config = {}) =>
    api.get(path, socialApiConfig(config));

export default {
    get: socialGet,
    post: socialPost,
};
