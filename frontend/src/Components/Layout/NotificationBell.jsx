import { Link } from 'react-router-dom';

/**
 * La cloche, qu'on montre à tous les rôles (choix de l'équipe). L'administratrice et la
 * super-utilisatrice y reçoivent les fiches soumises (#84, #122) ; l'administratrice seule,
 * les demandes de réouverture (#130).
 *
 * @param {object} props
 * @param {number} [props.count=0]  le nombre de notifications non lues
 * @param {() => void} [props.onClick]
 */
export default function NotificationBell({ count = 0, onClick }) {
  const label =
    count > 0 ? `Notifications, ${count} non lue${count > 1 ? 's' : ''}` : 'Notifications';

  return (
    <Link to="/notifications" className="icon-button" aria-label={label} onClick={onClick}>
      <svg width="18" height="20" viewBox="0 0 18 20" fill="none" aria-hidden="true">
        <path
          d="M9 1.5a5.5 5.5 0 0 0-5.5 5.5v3.6L1.8 14h14.4l-1.7-3.4V7A5.5 5.5 0 0 0 9 1.5Z"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinejoin="round"
        />
        <path d="M6.8 16.8a2.3 2.3 0 0 0 4.4 0" stroke="currentColor" strokeWidth="1.5" />
      </svg>
      {count > 0 && <span className="notification-dot" data-testid="notification-dot" />}
    </Link>
  );
}
