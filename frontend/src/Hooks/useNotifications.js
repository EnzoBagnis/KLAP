import { useContext } from 'react';
import { NotificationsContext } from '../Context/NotificationsContext.jsx';

/** Compteur de la cloche ; hors du gabarit connecté, un compteur neutre. */
export default function useNotifications() {
  return useContext(NotificationsContext) ?? { unreadCount: 0, setUnreadCount: () => {}, refresh: () => {} };
}
