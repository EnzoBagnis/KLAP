import { Route, Routes } from 'react-router-dom';
import AuthLayout from '../Layouts/AuthLayout.jsx';
import MainLayout from '../Layouts/MainLayout.jsx';
import LoginPage from '../Pages/Auth/LoginPage.jsx';
import ProtectedRoute from './ProtectedRoute.jsx';
import { NAV_ITEMS } from '../Components/Layout/navigation.js';

/*
 * Les routes du menu sont dérivées de NAV_ITEMS : chemin, rôles autorisés et écran
 * (champ `page`) viennent de la même entrée, il n'y a pas de seconde liste ici.
 * Une entrée sans `page` affiche un écran provisoire.
 */
export default function AppRouter() {
  return (
    <Routes>
      <Route element={<AuthLayout />}>
        <Route path="/connexion" element={<LoginPage />} />
      </Route>

      <Route element={<ProtectedRoute />}>
        <Route element={<MainLayout />}>
          {NAV_ITEMS.map(({ path, label, roles, page: Page }) => (
            <Route
              key={path}
              path={path}
              element={
                <ProtectedRoute roles={roles}>
                  {Page ? <Page /> : <Placeholder title={label} />}
                </ProtectedRoute>
              }
            />
          ))}
          <Route path="/notifications" element={<Placeholder title="Notifications" />} />
          <Route path="/profil" element={<Placeholder title="Profil" />} />
        </Route>
      </Route>

      <Route path="*" element={<Placeholder title="Page introuvable" />} />
    </Routes>
  );
}

// Écran provisoire, à supprimer quand les vraies pages seront branchées.
function Placeholder({ title }) {
  return (
    <section className="placeholder">
      <h1>{title}</h1>
      <p>Écran en cours de développement.</p>
    </section>
  );
}
