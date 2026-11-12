import { useContext } from 'react';
import { NotificationsContext } from '../Context/NotificationsContext.jsx';

/**
 * Le compteur de la cloche. Hors du gabarit connecté, on renvoie un compteur neutre.
 *
 * @returns {{unreadCount: number, setUnreadCount: Function, refresh: Function}}
 */
export default function useNotifications() {
  return useContext(NotificationsContext) ?? { unreadCount: 0, setUnreadCount: () => {}, refresh: () => {} };
}
