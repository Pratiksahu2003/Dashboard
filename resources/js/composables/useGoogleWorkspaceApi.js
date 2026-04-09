import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const GOOGLE_API_BASE_URL = (import.meta.env.VITE_GOOGLE_API_BASE_URL || 'https://www.suganta.in/api/v4/google').trim();

const readPayload = response => response?.data?.data || response?.data || {};

export const useGoogleWorkspaceApi = () => {
    const { getToken } = useAuth();

    const api = axios.create({
        baseURL: GOOGLE_API_BASE_URL,
        timeout: 20000,
        headers: {
            Accept: 'application/json',
        },
    });

    api.interceptors.request.use(config => {
        const token = getToken();
        if (token) config.headers.Authorization = `Bearer ${token}`;
        return config;
    });

    const getStatus = async () => readPayload(await api.get('/status'));
    const getUrls = async () => readPayload(await api.get('/urls'));
    const getOauthUrl = async () => readPayload(await api.get('/oauth/url'));
    const exchangeCode = async body => readPayload(await api.post('/oauth/exchange-code', body));
    const disconnect = async () => readPayload(await api.delete('/disconnect'));
    const refreshToken = async () => readPayload(await api.post('/token/refresh'));
    const createWatch = async body => readPayload(await api.post('/watch', body));
    const deleteWatch = async channelId => readPayload(await api.delete(`/watch/${channelId}`));
    const runSync = async body => readPayload(await api.post('/sync', body));
    const getCalendarEvents = async body => readPayload(await api.post('/calendar/events', body || {}));
    const createCalendarEvent = async body => readPayload(await api.post('/calendar/events/create', body));
    const getCalendarEvent = async eventId => readPayload(await api.get(`/calendar/events/${eventId}`));
    const updateCalendarEvent = async (eventId, body) => readPayload(await api.put(`/calendar/events/${eventId}`, body));
    const deleteCalendarEvent = async eventId => readPayload(await api.delete(`/calendar/events/${eventId}`));
    const getDriveFiles = async body => readPayload(await api.post('/drive/files', body || {}));
    const searchDriveFiles = async body => readPayload(await api.post('/drive/files/search', body || {}));
    const renameDriveFile = async (fileId, name) => readPayload(await api.patch(`/drive/files/${fileId}/rename`, { name }));
    const deleteDriveFile = async fileId => readPayload(await api.delete(`/drive/files/${fileId}`));
    const getYoutubeChannels = async body => readPayload(await api.post('/youtube/channels', body || {}));

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

