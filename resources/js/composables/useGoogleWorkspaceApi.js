import api from '@/api';

const GOOGLE_API_BASE_URL = (import.meta.env.VITE_GOOGLE_API_BASE_URL || `${(import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '')}/api/v4/google`).trim();

const readPayload = response => response?.data?.data || response?.data || {};

export const useGoogleWorkspaceApi = () => {
    const getStatus = async () => readPayload(await api.get(`${GOOGLE_API_BASE_URL}/status`));
    const getUrls = async () => readPayload(await api.get(`${GOOGLE_API_BASE_URL}/urls`));
    const getOauthUrl = async () => readPayload(await api.get(`${GOOGLE_API_BASE_URL}/oauth/url`));
    const exchangeCode = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/oauth/exchange-code`, body));
    const disconnect = async () => readPayload(await api.delete(`${GOOGLE_API_BASE_URL}/disconnect`));
    const refreshToken = async () => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/token/refresh`));
    const createWatch = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/watch`, body));
    const deleteWatch = async channelId => readPayload(await api.delete(`${GOOGLE_API_BASE_URL}/watch/${channelId}`));
    const runSync = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/sync`, body));
    const getCalendarEvents = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/calendar/events`, body || {}));
    const createCalendarEvent = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/calendar/events/create`, body));
    const getCalendarEvent = async eventId => readPayload(await api.get(`${GOOGLE_API_BASE_URL}/calendar/events/${eventId}`));
    const updateCalendarEvent = async (eventId, body) => readPayload(await api.put(`${GOOGLE_API_BASE_URL}/calendar/events/${eventId}`, body));
    const deleteCalendarEvent = async eventId => readPayload(await api.delete(`${GOOGLE_API_BASE_URL}/calendar/events/${eventId}`));
    const getDriveFiles = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/drive/files`, body || {}));
    const searchDriveFiles = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/drive/files/search`, body || {}));
    const renameDriveFile = async (fileId, name) => readPayload(await api.patch(`${GOOGLE_API_BASE_URL}/drive/files/${fileId}/rename`, { name }));
    const deleteDriveFile = async fileId => readPayload(await api.delete(`${GOOGLE_API_BASE_URL}/drive/files/${fileId}`));
    const getYoutubeChannels = async body => readPayload(await api.post(`${GOOGLE_API_BASE_URL}/youtube/channels`, body || {}));

    return {
        getStatus,
        getUrls,
        getOauthUrl,
        exchangeCode,
        disconnect,
        refreshToken,
        createWatch,
        deleteWatch,
        runSync,
        getCalendarEvents,
        createCalendarEvent,
        getCalendarEvent,
        updateCalendarEvent,
        deleteCalendarEvent,
        getDriveFiles,
        searchDriveFiles,
        renameDriveFile,
        deleteDriveFile,
        getYoutubeChannels,
    };
};

export const formatDateTime = value => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleString();
};

export const formatFileSize = bytes => {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value < 0) return '-';
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1024 * 1024 * 1024) return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    return `${(value / (1024 * 1024 * 1024)).toFixed(1)} GB`;
};

