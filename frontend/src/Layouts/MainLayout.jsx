import { useCallback, useEffect, useRef, useState } from 'react';
import { Outlet } from 'react-router-dom';
import Header from '../Components/Layout/Header.jsx';
import Sidebar from '../Components/Layout/Sidebar.jsx';
import { NotificationsProvider } from '../Context/NotificationsContext.jsx';
import useNotifications from '../Hooks/useNotifications.js';
import '../assets/css/LayoutStyles.css';
import Footer from '../Components/Layout/Footer.jsx';

const MENU_ID = 'menu-principal';

// Gabarit des écrans connectés : navigation et zone de contenu.
export default function MainLayout() {
  return (
    <NotificationsProvider>
      <Layout />
    </NotificationsProvider>
  );
}

function Layout() {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuButtonRef = useRef(null);
  const closeMenu = useCallback(() => setMenuOpen(false), []);
  const toggleMenu = useCallback(() => setMenuOpen((open) => !open), []);

  // Menu mobile ouvert : focus sur la première entrée, page de fond figée,
  // Échap ferme le menu et rend le focus au bouton.
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
      {/* Lien d'évitement (RGAA 12.7, #233) : premier élément au clavier, visible quand il a le focus. */}
      <a
        href="#contenu"
        className="skip-link"
        onClick={(event) => {
          // Pas de changement d'adresse : le focus va directement à la zone principale.
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
