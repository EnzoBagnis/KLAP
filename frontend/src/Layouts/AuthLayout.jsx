import { Outlet } from 'react-router-dom';
import logo from '../assets/images/KlapLogo.png';
import logoClair from '../assets/images/KlapLogoClair.png';
import '../assets/css/LayoutStyles.css';
import '../assets/css/FormStyles.css';

/**
 * Le gabarit des écrans hors connexion (maquette, écrans 01 et M1).
 * Sur ordinateur : un panneau noir de présentation à gauche, le formulaire à droite.
 * Sur téléphone : le formulaire seul, avec le logo au-dessus.
 */
export default function AuthLayout() {
  return (
    <div className="auth-layout">
      <aside className="auth-panel">
        <img src={logoClair} alt="KLAP Maison pour la danse" className="auth-panel-logo" />
        <div className="auth-panel-pitch">
          <p className="auth-panel-title">Le carnet de bord des projets d’action artistique.</p>
          <p className="auth-panel-text">
            Éducadanse et Partage artistique : suivi des séances, évaluation des publics et bilans
            pour les financeurs.
          </p>
        </div>
        <p className="auth-panel-address">
          KLAP Maison pour la danse, Saint-Mauront, Marseille · 13003
        </p>
      </aside>

      <main className="auth-main">
        <div className="auth-card">
          <img src={logo} alt="KLAP Maison pour la danse" className="auth-card-logo" />
          <Outlet />
        </div>
      </main>
    </div>
  );
}
