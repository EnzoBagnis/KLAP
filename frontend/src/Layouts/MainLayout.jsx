import { useCallback, useEffect, useRef, useState } from 'react';
import { Outlet } from 'react-router-dom';
import Header from '../Components/Layout/Header.jsx';
import Sidebar from '../Components/Layout/Sidebar.jsx';
import { NotificationsProvider } from '../Context/NotificationsContext.jsx';
import useNotifications from '../Hooks/useNotifications.js';
import '../assets/css/LayoutStyles.css';
import Footer from '../Components/Layout/Footer.jsx';

/** L'identifiant du menu, que le bouton du Header désigne (aria-controls). */
const MENU_ID = 'menu-principal';

/** Le gabarit des écrans connectés : la navigation et la zone de contenu. */
export default function MainLayout() {
  return (
    <NotificationsProvider>
      <Layout />
    </NotificationsProvider>
  );
}

/**
 * Le gabarit lui-même. On le sépare de MainLayout pour qu'il puisse lire le compteur de la
 * cloche, que NotificationsProvider fournit.
 */
function Layout() {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuButtonRef = useRef(null);
  const closeMenu = useCallback(() => setMenuOpen(false), []);
  const toggleMenu = useCallback(() => setMenuOpen((open) => !open), []);

  // Quand le menu mobile est ouvert : on met le focus sur la première entrée, on fige la page
  // de fond, et Échap ferme le menu en rendant le focus au bouton.
  useEffect(() => {
    if (!menuOpen) return undefined;

    document.getElementById(MENU_ID)?.querySelector('nav a')?.focus();
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
        menuButtonRef.current?.focus();
      }
    };
    window.addEventListener('keydown', onKeyDown);

    return () => {
      window.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [menuOpen]);

  const { unreadCount: notificationCount } = useNotifications();

  return (
    <div className={`app-layout${menuOpen ? ' menu-open' : ''}`}>
      {/* Le lien d'évitement (RGAA 12.7, #233) : premier élément au clavier, visible quand il a le focus. */}
      <a
        href="#contenu"
        className="skip-link"
        onClick={(event) => {
          // On ne change pas l'adresse : on envoie directement le focus à la zone principale.
          event.preventDefault();
          document.getElementById('contenu')?.focus();
        }}
      >
        Aller au contenu
      </a>
      <Header
        menuOpen={menuOpen}
        onToggleMenu={toggleMenu}
        onNavigate={closeMenu}
        menuId={MENU_ID}
        menuButtonRef={menuButtonRef}
        notificationCount={notificationCount}
      />
      <Sidebar
        id={MENU_ID}
        open={menuOpen}
        onNavigate={closeMenu}
        notificationCount={notificationCount}
      />
      <main className="app-main" id="contenu" tabIndex={-1}>
        <Outlet />
        <Footer />
      </main>
    </div>
  );
}
