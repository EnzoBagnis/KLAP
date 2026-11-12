import { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import useAuth from '../Hooks/useAuth.js';
import { countUnreadNotifications } from '../Services/NotificationService.js';

/** Le contexte du compteur de la cloche. On le lit avec le hook useNotifications. */
export const NotificationsContext = createContext(null);

/** L'intervalle entre deux rechargements de la cloche, tant que l'onglet est visible. */
export const REFRESH_EVERY_MS = 30_000;

/**
 * Le nombre de notifications non lues, pour la cloche. On le recharge à chaque changement de
 * page, toutes les 30 secondes tant que l'onglet est visible, et au retour sur l'onglet
 * (#211) : une fiche soumise ailleurs allume la cloche sans qu'on ait à changer de page. La
 * page Notifications le met à jour directement après une lecture. En cas d'erreur, on laisse
 * le compteur tel quel : la cloche n'est qu'un repère, les écrans gèrent leurs propres erreurs.
 *
 * @param {object} props
 * @param {React.ReactNode} props.children
 */
export function NotificationsProvider({ children }) {
  const [unreadCount, setUnreadCount] = useState(0);
  const { pathname } = useLocation();
  const lastRequest = useRef(0);

  const { logout } = useAuth();

  const refresh = useCallback(() => {
    const request = ++lastRequest.current;
    countUnreadNotifications()
      .then((count) => request === lastRequest.current && setUnreadCount(count))
      .catch((error) => {
        // La session a expiré ou le compte est désactivé : on déconnecte, sinon la cloche
        // réessaierait sans fin.
        if (error?.status === 401) logout();
      });
  }, [logout]);

  useEffect(() => {
    refresh();
  }, [pathname, refresh]);

  // Tant que l'onglet est caché, on n'envoie aucune requête ; on le remet à jour dès qu'on y revient.
  useEffect(() => {
    let timer;
    const start = () => {
      clearInterval(timer);
      timer = setInterval(() => document.visibilityState === 'visible' && refresh(), REFRESH_EVERY_MS);
    };
    const onVisibility = () => {
      if (document.visibilityState !== 'visible') return;
      refresh();
      start(); // on repart de zéro, pour ne pas recharger une seconde fois quelques secondes après
    };
    start();
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      clearInterval(timer);
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [refresh]);

  // Une mise à jour faite par un écran l'emporte sur un rechargement encore en route. On
  // accepte un nombre, ou une fonction du compteur courant (une lecture fait « - 1 »).
  const setCount = useCallback((count) => {
    lastRequest.current += 1;
    setUnreadCount(count);
  }, []);

  const value = useMemo(() => ({ unreadCount, setUnreadCount: setCount, refresh }), [unreadCount, setCount, refresh]);
  return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}
