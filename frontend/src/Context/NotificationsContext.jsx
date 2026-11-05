import { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { listNotifications } from '../Services/NotificationService.js';

export const NotificationsContext = createContext(null);

/*
 * Nombre de notifications non lues, pour la cloche. Rechargé à chaque changement de page
 * (une fiche soumise ailleurs apparaît sans recharger) ; la page Notifications le met à
 * jour directement après une lecture. Une erreur laisse le compteur tel quel : la cloche
 * n'est qu'un repère, les écrans gèrent leurs propres erreurs.
 */
export function NotificationsProvider({ children }) {
  const [unreadCount, setUnreadCount] = useState(0);
  const { pathname } = useLocation();
  const lastRequest = useRef(0);

  const refresh = useCallback(() => {
    const request = ++lastRequest.current;
    listNotifications()
      .then(({ unreadCount: count }) => request === lastRequest.current && setUnreadCount(count))
      .catch(() => {});
  }, []);

  useEffect(() => {
    refresh();
  }, [pathname, refresh]);

  // Une mise à jour faite par un écran l'emporte sur un rechargement encore en route.
  const setCount = useCallback((count) => {
    lastRequest.current += 1;
    setUnreadCount(count);
  }, []);

  const value = useMemo(() => ({ unreadCount, setUnreadCount: setCount, refresh }), [unreadCount, setCount, refresh]);
  return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}
