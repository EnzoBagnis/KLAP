import { Link } from 'react-router-dom';
import logo from '../../assets/images/KlapLogo.png';
import HamburgerMenu from './HamburgerMenu.jsx';
import NotificationBell from './NotificationBell.jsx';

/**
 * La barre du haut sur téléphone (maquette « Barre mobile », 64 px). On la masque sur
 * ordinateur, où la barre latérale porte le logo et la cloche.
 *
 * @param {object} props
 * @param {boolean} props.menuOpen
 * @param {() => void} props.onToggleMenu
 * @param {() => void} props.onNavigate  on ferme le menu quand on change de page
 * @param {string} props.menuId  l'identifiant du menu que le bouton ouvre
 * @param {React.RefObject<HTMLButtonElement>} props.menuButtonRef
 * @param {number} props.notificationCount
 */
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
