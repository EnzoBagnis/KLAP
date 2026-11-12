import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Cloche de notifications (#122). Chacun ne reçoit que les siennes.

/**
 * GET /notifications : les 30 dernières notifications, et le nombre de non-lues.
 *
 * @returns {Promise<{notifications: object[], unreadCount: number}>}
 */
export async function listNotifications() {
  const response = await apiRequest('/notifications', { token: getToken() });
  return { notifications: response.data, unreadCount: response.meta.unread_count };
}

/**
 * GET /notifications/unread-count : le nombre de non-lues seulement, pour la cloche (#227).
 *
 * @returns {Promise<number>}
 */
export async function countUnreadNotifications() {
  return (await apiRequest('/notifications/unread-count', { token: getToken() })).data.unread_count;
}

/**
 * PATCH /notifications/{id}/read : on marque une notification comme lue.
 *
 * @returns {Promise<object>} la notification à jour
 */
export async function markNotificationRead(id) {
  return (await apiRequest(`/notifications/${encodeURIComponent(id)}/read`, { method: 'PATCH', body: {}, token: getToken() }))
    .data;
}

/** POST /notifications/read-all : on marque toutes ses notifications comme lues. */
export async function markAllNotificationsRead() {
  await apiRequest('/notifications/read-all', { method: 'POST', body: {}, token: getToken() });
}
