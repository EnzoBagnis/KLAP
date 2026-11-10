import { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import useAuth from '../Hooks/useAuth.js';
import { listNotifications } from '../Services/NotificationService.js';

export const NotificationsContext = createContext(null);

/** Rechargement de la cloche tant que l'onglet est visible. */
export const REFRESH_EVERY_MS = 30_000;

/*
 * Nombre de notifications non lues, pour la cloche. Rechargé à chaque changement de page,
 * toutes les 30 secondes tant que l'onglet est visible, et au retour sur l'onglet (#211) :
 * une fiche soumise ailleurs allume la cloche sans qu'on ait à changer de page. La page
 * Notifications le met à jour directement après une lecture. Une erreur laisse le compteur tel quel : la cloche
 * n'est qu'un repère, les écrans gèrent leurs propres erreurs.
 */
export function NotificationsProvider({ children }) {
  const [unreadCount, setUnreadCount] = useState(0);
  const { pathname } = useLocation();
  const lastRequest = useRef(0);

  const { logout } = useAuth();

  const refresh = useCallback(() => {
    const request = ++lastRequest.current;
    listNotifications()
      .then(({ unreadCount: count }) => request === lastRequest.current && setUnreadCount(count))
      .catch((error) => {
        // Session expirée ou compte désactivé : on déconnecte, sinon la cloche réessaierait sans fin.
        if (error?.status === 401) logout();
      });
  }, [logout]);

  useEffect(() => {
    refresh();
  }, [pathname, refresh]);

  // Onglet caché : aucune requête ; il se remet à jour dès qu'on y revient.
  useEffect(() => {
    let timer;
    const start = () => {
      clearInterval(timer);
      timer = setInterval(() => document.visibilityState === 'visible' && refresh(), REFRESH_EVERY_MS);
    };
    const onVisibility = () => {
      if (document.visibilityState !== 'visible') return;
      refresh();
      start(); // repart de zéro : pas de second rechargement quelques secondes après
    };
    start();
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      clearInterval(timer);
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [refresh]);

  // Une mise à jour faite par un écran l'emporte sur un rechargement encore en route.
  // Accepte un nombre, ou une fonction du compteur courant (une lecture fait « - 1 »).
  const setCount = useCallback((count) => {
    lastRequest.current += 1;
    setUnreadCount(count);
  }, []);

  const value = useMemo(() => ({ unreadCount, setUnreadCount: setCount, refresh }), [unreadCount, setCount, refresh]);
  return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}
