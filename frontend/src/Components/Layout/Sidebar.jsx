import { Link, NavLink } from 'react-router-dom';
import logo from '../../assets/images/KlapLogo.png';
import useAuth from '../../Hooks/useAuth.js';
import { navItemsForRole } from './navigation.js';
import NotificationBell from './NotificationBell.jsx';

// « Léa Fournier » donne « LF ».
export function initials(name = '') {
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('');
}

/*
 * Navigation principale. Sur ordinateur, sidebar fixe de 260 px.
 * Sur téléphone, le même élément devient le menu plein écran ouvert par le Header.
 * Les entrées viennent de navigation.js, filtrées selon le rôle de l'utilisateur.
 */
export default function Sidebar({ id, open, onNavigate, notificationCount }) {
  const { user, logout } = useAuth();
  const items = navItemsForRole(user?.role);

  // ProtectedRoute renvoie vers /connexion dès que l'utilisateur disparaît.
  function handleLogout() {
    onNavigate?.();
    logout();
  }

  return (
    <aside id={id} className={`sidebar${open ? ' is-open' : ''}`}>
      <div className="sidebar-brand">
        <Link to="/" onClick={onNavigate} className="brand-link">
          <img src={logo} alt="KLAP Maison pour la danse, accueil" className="brand-logo" />
        </Link>
        <NotificationBell count={notificationCount} onClick={onNavigate} />
      </div>

      <nav aria-label="Navigation principale">
        <ol className="nav-list">
          {items.map((item, index) => (
            <li key={item.path}>
              <NavLink to={item.path} end={item.end} className="nav-link" onClick={onNavigate}>
                <span className="nav-index" aria-hidden="true">
                  {String(index + 1).padStart(2, '0')}
                </span>
                {item.label}
              </NavLink>
            </li>
          ))}
        </ol>
      </nav>

      {user && (
        <div className="sidebar-user">
          <Link to="/profil" className="user-card" onClick={onNavigate}>
            <span className="user-avatar" aria-hidden="true">
              {initials(user.name)}
            </span>
            <span className="user-meta">
              <span className="user-name">{user.name}</span>
              <span className="user-role">{user.role_label}</span>
            </span>
          </Link>
          <button
            type="button"
            className="icon-button"
            aria-label="Se déconnecter"
            onClick={handleLogout}
          >
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M8 2H2v16h6" stroke="currentColor" strokeWidth="1.5" />
              <path d="M7 10h11M14 6l4 4-4 4" stroke="currentColor" strokeWidth="1.5" />
            </svg>
          </button>
        </div>
      )}
    </aside>
  );
}
