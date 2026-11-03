/**
 * @file App.jsx
 * @description Composant racine pour la branche Footer.
 * Affiche l'intégration du composant Footer au sein de la structure de mise en page KLAP.
 * 100% sémantique HTML5 — STRICTEMENT AUCUNE BALISE <div>.
 */

import React from 'react';
import Footer from './Components/Layout/Footer';
import './assets/css/GlobalStyles.css';

export function App() {
  return (
    <section className="app-shell">
      {/* Espace principal sémantique */}
      <main className="app-main-content" />

      {/* Footer KLAP */}
      <Footer />
    </section>
  );
}

export default App;
