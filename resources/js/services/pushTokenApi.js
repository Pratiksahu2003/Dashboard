import api from '@/api';

/**
 * Register FCM token with SuGanta API (v1).
 * @param {{ token: string, platform?: 'web'|'android'|'ios'|'unknown', device_name?: string }} body
 */
export function registerPushToken(body) {
    return api.post('/notifications/push-token', {
        platform: 'web',
        ...body,
    });
}

/**
 * Remove token on logout / disable notifications.
 * @param {{ token: string }} body
 */
export function unregisterPushToken(body) {
    return api.delete('/notifications/push-token', { data: body });
}
