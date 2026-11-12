import { NavLink } from 'react-router-dom';

/** Les onglets de Gestion (maquette, écrans 07 et 08), plus « Listes » qu'on a ajouté (#249). */
export default function ManagementTabs() {
  return (
    <nav className="management-tabs" aria-label="Gestion">
      <NavLink to="/gestion" end className="management-tab">
        Projets
      </NavLink>
      <NavLink to="/gestion/utilisateurs" className="management-tab">
        Utilisateurs
      </NavLink>
      <NavLink to="/gestion/listes" className="management-tab">
        Listes
      </NavLink>
    </nav>
  );
}
