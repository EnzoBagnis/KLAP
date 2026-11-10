/**
 * @file Footer.jsx
 * @description Composant Footer officiel du projet KLAP.
 * 100% sémantique HTML5 — STRICTEMENT AUCUNE BALISE <div>.
 */

import React from 'react';

export const Footer = () => {
  // L'année se met à jour automatiquement chaque année
  const currentYear = new Date().getFullYear();

  return (
    <footer className="klap-footer" role="contentinfo">
      {/* Rangée supérieure : Newsletter / Programme & Réseaux sociaux officiels */}
      <section className="footer-top-bar">
        <section className="footer-top-inner">
          <section className="footer-quick-links" aria-label="Liens rapides">
            <a href="#newsletter" className="footer-quick-link">
              Abonnez-vous à la newsletter
            </a>
            <span className="footer-quick-separator" aria-hidden="true">·</span>
            <a href="#programme" className="footer-quick-link">
              Téléchargez le programme
            </a>
          </section>

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
              <li><a href="/" className="footer-link">Tableau de bord</a></li>
              <li><a href="/projets" className="footer-link">Projets en cours</a></li>
              <li><a href="/planning" className="footer-link">Planning</a></li>
              <li><a href="/statistiques" className="footer-link">Statistiques & bilans</a></li>
            </ul>
          </section>

          {/* Colonne 3 : Ressources essentielles */}
          <section className="footer-col" aria-label="Ressources et aide">
            <h3 className="footer-col-title">RESSOURCES</h3>
            <ul className="footer-links-list">
              <li><a href="#guide" className="footer-link">Guide d'utilisation</a></li>
              <li><a href="#support" className="footer-link">Support & assistance</a></li>
            </ul>
          </section>
        </section>
      </section>

      {/* Ligne séparatrice fine */}
      <hr className="footer-divider" />

      {/* Rangée inférieure : Liens légaux & copyright */}
      <section className="footer-bottom">
        <section className="footer-bottom-inner">
          <section aria-label="Liens institutionnels et légaux">
            <ul className="footer-legal-links">
              <li><a href="#contact" className="footer-legal-link">CONTACT & ACCÈS</a></li>
              <li><a href="#partenaires" className="footer-legal-link">PARTENAIRES</a></li>
              <li><a href="#plan-du-site" className="footer-legal-link">PLAN DU SITE</a></li>
              <li><a href="#mentions-legales" className="footer-legal-link">MENTIONS LÉGALES</a></li>
              <li><a href="#credits" className="footer-legal-link">CRÉDITS</a></li>
            </ul>
          </section>

          <small className="footer-copyright">
            © {currentYear} KLAP — Tous droits réservés.
          </small>
        </section>
      </section>
    </footer>
  );
};

export default Footer;
