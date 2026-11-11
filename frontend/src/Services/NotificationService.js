import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Cloche de notifications (#122). Chacun ne reçoit que les siennes.

/** GET /notifications : { notifications, unreadCount } (30 dernières). */
export async function listNotifications() {
  const response = await apiRequest('/notifications', { token: getToken() });
  return { notifications: response.data, unreadCount: response.meta.unread_count };
}

/** GET /notifications/unread-count : le nombre de non-lues seulement, pour la cloche (#227). */
export async function countUnreadNotifications() {
  return (await apiRequest('/notifications/unread-count', { token: getToken() })).data.unread_count;
}

export async function markNotificationRead(id) {
  return (await apiRequest(`/notifications/${encodeURIComponent(id)}/read`, { method: 'PATCH', body: {}, token: getToken() }))
    .data;
}

export async function markAllNotificationsRead() {
  await apiRequest('/notifications/read-all', { method: 'POST', body: {}, token: getToken() });
}
