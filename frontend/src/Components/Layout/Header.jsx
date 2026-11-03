import { Link } from 'react-router-dom';
import logo from '../../assets/images/KlapLogo.png';
import HamburgerMenu from './HamburgerMenu.jsx';
import NotificationBell from './NotificationBell.jsx';

// Barre du haut sur téléphone (maquette « Barre mobile », 64 px).
// Masquée sur ordinateur, où la sidebar porte le logo et la cloche.
export default function Header({
  menuOpen,
  onToggleMenu,
  onNavigate,
  menuId,
  menuButtonRef,
  notificationCount,
}) {
  return (
    <header className="mobile-bar">
      <Link to="/" onClick={onNavigate} className="brand-link">
        <img src={logo} alt="KLAP Maison pour la danse, accueil" className="brand-logo" />
      </Link>
      <div className="mobile-bar-actions">
        <NotificationBell count={notificationCount} onClick={onNavigate} />
        <HamburgerMenu
          ref={menuButtonRef}
          open={menuOpen}
          onToggle={onToggleMenu}
          controls={menuId}
        />
      </div>
    </header>
  );
}
