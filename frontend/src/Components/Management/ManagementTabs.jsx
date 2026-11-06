import { NavLink } from 'react-router-dom';

/** Onglets de Gestion (maquette, écrans 07 et 08). */
export default function ManagementTabs() {
  return (
    <nav className="management-tabs" aria-label="Gestion">
      <NavLink to="/gestion" end className="management-tab">
        Projets
      </NavLink>
      <NavLink to="/gestion/utilisateurs" className="management-tab">
        Utilisateurs
      </NavLink>
    </nav>
  );
}
