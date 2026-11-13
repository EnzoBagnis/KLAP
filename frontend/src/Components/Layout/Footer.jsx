import { Link } from 'react-router-dom';
import useAuth from '../../Hooks/useAuth.js';
import { navItemsForRole } from './navigation.js';

/** Le tutoriel vidéo de l'application : le seul guide qu'on a à proposer pour l'instant. */
const GUIDE_URL = 'https://drive.google.com/file/d/1f6ANKz2Tft5s51lcYvOVgsPL_abmQTwh/view?usp=sharing';

/**
 * Le pied de page de l'application, sous chaque écran connecté : les réseaux sociaux de KLAP,
 * un rappel de la navigation et le guide d'utilisation. Sémantique HTML5, sans balise <div>.
 *
 * On n'y met que des liens qui mènent quelque part : pas de lien vers une page qu'on n'a pas.
 * La navigation reprend le menu de la personne connectée, pour ne pas proposer aux
 * intervenant·e·s un écran qui leur est fermé.
 */
export const Footer = () => {
  const { user } = useAuth();
  // L'année se met à jour automatiquement chaque année
  const currentYear = new Date().getFullYear();

  return (
    <footer className="klap-footer" role="contentinfo">
      {/* Rangée supérieure : réseaux sociaux officiels */}
      <section className="footer-top-bar">
        <section className="footer-top-inner">
          <section className="footer-socials" aria-label="Réseaux sociaux KLAP">
            <span className="socials-label">Rejoignez-nous sur</span>
            <ul className="socials-list">
              <li>
                <a
                  href="https://www.facebook.com/klapkelemenis"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-link"
                >
                  Facebook
                </a>
              </li>
              <li>
                <a
                  href="https://twitter.com/klapkelemenis"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-link"
                >
                  X
                </a>
              </li>
              <li>
                <a
                  href="https://www.instagram.com/klapkelemenis/"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-link"
                >
                  Instagram
                </a>
              </li>
              <li>
                <a
                  href="https://fr.linkedin.com/company/klap-maison-pour-la-danse"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-link"
                >
                  Linkedin
                </a>
              </li>
            </ul>
          </section>
        </section>
      </section>

      {/* Corps intermédiaire : Marque KLAP, Navigation, Ressources */}
      <section className="footer-main">
        <section className="footer-grid">
          {/* Colonne 1 : Uniquement la marque KLAP */}
          <section className="footer-col footer-col-brand">
            <h2 className="footer-brand-logo">
              KLAP<span className="brand-dot">.</span>
            </h2>
          </section>

          {/* Colonne 2 : Navigation */}
          <section className="footer-col" aria-label="Navigation secondaire">
            <h3 className="footer-col-title">NAVIGATION</h3>
            <ul className="footer-links-list">
              {navItemsForRole(user?.role).map((item) => (
                <li key={item.path}>
                  <Link to={item.path} className="footer-link">{item.label}</Link>
                </li>
              ))}
            </ul>
          </section>

          {/* Colonne 3 : Ressources essentielles */}
          <section className="footer-col" aria-label="Ressources et aide">
            <h3 className="footer-col-title">RESSOURCES</h3>
            <ul className="footer-links-list">
              <li>
                <a href={GUIDE_URL} target="_blank" rel="noopener noreferrer" className="footer-link">
                  Guide d’utilisation (vidéo)
                </a>
              </li>
            </ul>
          </section>
        </section>
      </section>

      {/* Ligne séparatrice fine */}
      <hr className="footer-divider" />

      {/* Rangée inférieure : copyright */}
      <section className="footer-bottom">
        <section className="footer-bottom-inner">
          <small className="footer-copyright">
            © {currentYear} KLAP — Tous droits réservés.
          </small>
        </section>
      </section>
    </footer>
  );
};

export default Footer;
