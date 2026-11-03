/**
 * @file Footer.jsx
 * @description Composant Footer officiel du projet KLAP.
 * Inspiré de la charte KLAP Maison pour la danse (réseaux, liens légaux, style épuré).
 * 100% sémantique HTML5 — STRICTEMENT AUCUNE BALISE <div>.
 */

import React from 'react';
import '../../assets/css/LayoutStyles.css';

/**
 * Composant Footer
 *
 * @param {Object} props
 * @param {Function} [props.onNavigate] - Callback optionnel de navigation interne
 * @returns {JSX.Element}
 */
export const Footer = ({ onNavigate }) => {
  // L'année en cours se calcule et se met à jour automatiquement chaque année
  const currentYear = new Date().getFullYear();

  /**
   * Gestionnaire de clics pour les liens de navigation interne
   * @param {React.MouseEvent} e
   * @param {string} targetView
   */
  const handleLinkClick = (e, targetView) => {
    if (onNavigate && targetView) {
      e.preventDefault();
      onNavigate(targetView);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  return (
    <footer className="klap-footer" role="contentinfo">
      {/* Rangée supérieure : Newsletter / Programme & Réseaux sociaux officiels KLAP */}
      <section className="footer-top-bar">
        <section className="footer-top-inner">
          <nav className="footer-quick-links" aria-label="Liens rapides">
            <a href="#newsletter" className="footer-quick-link">
              Abonnez-vous à la newsletter
            </a>
            <span className="footer-quick-separator" aria-hidden="true">·</span>
            <a href="#programme" className="footer-quick-link">
              Téléchargez le programme
            </a>
          </nav>

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

      {/* Corps intermédiaire : Marque KLAP, Navigation et Ressources */}
      <section className="footer-main">
        <section className="footer-grid">
          {/* Colonne 1 : Uniquement la marque KLAP */}
          <section className="footer-col footer-col-brand">
            <h2 className="footer-brand-logo">
              KLAP<span className="brand-dot">.</span>
            </h2>
          </section>

          {/* Colonne 2 : Navigation de l'application */}
          <nav className="footer-col" aria-label="Navigation secondaire">
            <h3 className="footer-col-title">NAVIGATION</h3>
            <ul className="footer-links-list">
              <li>
                <a
                  href="#dashboard"
                  onClick={(e) => handleLinkClick(e, 'dashboard')}
                  className="footer-link"
                >
                  Tableau de bord
                </a>
              </li>
              <li>
                <a
                  href="#projets"
                  onClick={(e) => handleLinkClick(e, 'projects')}
                  className="footer-link"
                >
                  Projets en cours
                </a>
              </li>
              <li>
                <a
                  href="#add-session"
                  onClick={(e) => handleLinkClick(e, 'add-session')}
                  className="footer-link"
                >
                  Ajouter une séance
                </a>
              </li>
              <li>
                <a
                  href="#statistiques"
                  onClick={(e) => handleLinkClick(e, 'statistics')}
                  className="footer-link"
                >
                  Statistiques & bilans
                </a>
              </li>
            </ul>
          </nav>

          {/* Colonne 3 : Ressources essentielles */}
          <nav className="footer-col" aria-label="Ressources et aide">
            <h3 className="footer-col-title">RESSOURCES</h3>
            <ul className="footer-links-list">
              <li>
                <a href="#guide" className="footer-link">
                  Guide d'utilisation
                </a>
              </li>
              <li>
                <a href="#support" className="footer-link">
                  Support & assistance
                </a>
              </li>
            </ul>
          </nav>
        </section>
      </section>

      {/* Ligne séparatrice fine */}
      <hr className="footer-divider" />

      {/* Rangée inférieure : Liens légaux & copyright (fidèle à la maquette du site KLAP) */}
      <section className="footer-bottom">
        <section className="footer-bottom-inner">
          <nav aria-label="Liens institutionnels et légaux">
            <ul className="footer-legal-links">
              <li>
                <a href="#contact" className="footer-legal-link">
                  CONTACT & ACCÈS
                </a>
              </li>
              <li>
                <a href="#partenaires" className="footer-legal-link">
                  PARTENAIRES
                </a>
              </li>
              <li>
                <a href="#plan-du-site" className="footer-legal-link">
                  PLAN DU SITE
                </a>
              </li>
              <li>
                <a href="#mentions-legales" className="footer-legal-link">
                  MENTIONS LÉGALES
                </a>
              </li>
              <li>
                <a href="#credits" className="footer-legal-link">
                  CRÉDITS
                </a>
              </li>
            </ul>
          </nav>

          <small className="footer-copyright">
            © {currentYear} KLAP — Tous droits réservés.
          </small>
        </section>
      </section>
    </footer>
  );
};

export default Footer;
