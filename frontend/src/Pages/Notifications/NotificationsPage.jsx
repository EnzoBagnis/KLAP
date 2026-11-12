import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import useNotifications from '../../Hooks/useNotifications.js';
import { listNotifications, markAllNotificationsRead, markNotificationRead } from '../../Services/NotificationService.js';
import { formatInstant } from '../Logbook/logbookModel.js';
import '../../assets/css/ProjectStyles.css';
import '../../assets/css/NotificationStyles.css';

/**
 * Les notifications du compte connecté (#123) : les 30 dernières. Un clic marque la
 * notification comme lue et ouvre la fiche concernée.
 */
export default function NotificationsPage() {
  const { logout } = useAuth();
  const { unreadCount, setUnreadCount } = useNotifications();
  const navigate = useNavigate();
  const [state, setState] = useState({ status: 'loading', notifications: [], error: '' });
  const [actionError, setActionError] = useState('');
  const [busy, setBusy] = useState(false);
  const [pendingId, setPendingId] = useState(null);
  const lastRequest = useRef(0);

  const fail = useCallback(
    (error, fallback) => {
      if (error.status === 401) {
        logout();
        return '';
      }
      return error.status === 0 ? error.message : fallback;
    },
    [logout],
  );

  // Le nombre de non lues que la liste affichée connaît déjà (voir plus bas).
  const known = useRef(null);

  // Avec `quiet`, on recharge en arrière-plan, sans écran d'attente (nouvelle notification).
  const load = useCallback((quiet = false) => {
    const request = ++lastRequest.current;
    if (!quiet) setState({ status: 'loading', notifications: [], error: '' });
    listNotifications()
      .then(({ notifications, unreadCount }) => {
        if (request !== lastRequest.current) return;
        setState({ status: 'ready', notifications, error: '' });
        known.current = unreadCount;
        setUnreadCount(unreadCount);
      })
      .catch((error) => {
        if (request !== lastRequest.current) return;
        setState({ status: 'error', notifications: [], error: fail(error, 'Les notifications n’ont pas pu être chargées.') });
      });
  }, [fail, setUnreadCount]);

  useEffect(() => {
    document.title = 'Notifications · KLAP';
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  // La cloche se recharge seule (#211) : quand elle voit une nouvelle notification, on met
  // aussi à jour la liste affichée (#231). Une lecture fait baisser le compteur : là, on n'a
  // rien à recharger.
  useEffect(() => {
    if (state.status === 'ready' && known.current !== null && unreadCount > known.current) load(true);
    if (state.status === 'ready') known.current = unreadCount;
  }, [unreadCount, state.status, load]);

  // Le nombre vient de l'API : il compte aussi les non lues au-delà des 30 affichées.
  const unread = unreadCount;

  function markLocally(ids) {
    const now = new Date().toISOString();
    setState((current) => {
      const notifications = current.notifications.map((notification) =>
        ids === 'all' || ids.includes(notification.id) ? { ...notification, read_at: notification.read_at ?? now } : notification,
      );
      return { ...current, notifications };
    });
  }

  async function open(notification) {
    if (pendingId !== null) return;
    setActionError('');
    if (notification.read_at === null) {
      setPendingId(notification.id);
      try {
        await markNotificationRead(notification.id);
        markLocally([notification.id]);
        setUnreadCount((count) => Math.max(0, count - 1));
      } catch (error) {
        // Si le marquage échoue, on ouvre la fiche quand même.
        setActionError(fail(error, 'La notification n’a pas pu être marquée comme lue.'));
      } finally {
        setPendingId(null);
      }
    }
    if (notification.logbook_entry_id) navigate(`/fiches/${notification.logbook_entry_id}`);
  }

  async function readAll() {
    setBusy(true);
    setActionError('');
    try {
      await markAllNotificationsRead();
      markLocally('all');
      setUnreadCount(0);
    } catch (error) {
      setActionError(fail(error, 'Les notifications n’ont pas pu être marquées comme lues.'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="page notifications-page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Notifications</h1>
          {/* On garde cette région toujours présente : les lecteurs d'écran annoncent ses changements. */}
          <p className="page-subtitle" aria-live="polite">
            {state.status === 'ready' && (unread === 0 ? 'Tout est lu' : `${unread} non lue${unread > 1 ? 's' : ''}`)}
          </p>
        </div>
        {state.status === 'ready' && unread > 0 && (
          <Button variant="line" onClick={readAll} disabled={busy}>
            Tout marquer comme lu
          </Button>
        )}
      </div>

      {actionError && (
        <p className="form-alert" role="alert">
          {actionError}
        </p>
      )}

      {state.status === 'loading' && <LoadingSpinner label="Chargement des notifications…" />}
      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}
      {state.status === 'ready' && state.notifications.length === 0 && (
        <p className="page-empty">Aucune notification pour le moment.</p>
      )}
      {state.status === 'ready' && state.notifications.length > 0 && (
        <ul className="notification-list">
          {state.notifications.map((notification) => {
            const isUnread = notification.read_at === null;
            const content = (
              <>
                <span className="notification-message">
                  {isUnread && <span className="visually-hidden">Non lue : </span>}
                  {notification.message}
                </span>
                <span className="notification-date">{formatInstant(notification.created_at)}</span>
              </>
            );
            return (
              <li key={notification.id} className={`notification-item${isUnread ? ' is-unread' : ''}`}>
                {notification.logbook_entry_id || isUnread ? (
                  <button
                    type="button"
                    className="notification-button"
                    onClick={() => open(notification)}
                    disabled={pendingId === notification.id}
                  >
                    {content}
                    <span className="notification-action">
                      {notification.logbook_entry_id ? 'Ouvrir la fiche' : 'Marquer comme lue'}
                    </span>
                  </button>
                ) : (
                  <div className="notification-button">{content}</div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
