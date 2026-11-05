import { Route, Routes } from 'react-router-dom';
import AuthLayout from '../Layouts/AuthLayout.jsx';
import MainLayout from '../Layouts/MainLayout.jsx';
import NewProjectPage from '../Pages/Projects/NewProjectPage.jsx';
import NotificationsPage from '../Pages/Notifications/NotificationsPage.jsx';
import { ROLES } from '../Context/roles.js';
import ProjectDetailsPage from '../Pages/Projects/ProjectDetailsPage.jsx';
import LoginPage from '../Pages/Auth/LoginPage.jsx';
import LogbookEntryPage from '../Pages/Logbook/LogbookEntryPage.jsx';
import OpenLogbookPage from '../Pages/Logbook/OpenLogbookPage.jsx';
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
          {/* Détail d'un projet : hors menu, « Projets » reste actif. L'API filtre par rôle. */}
          <Route path="/projets/:id" element={<ProjectDetailsPage />} />
          {/* Création d'un projet, sous Gestion : administratrice seulement (RG02). */}
          <Route
            path="/gestion/projets/nouveau"
            element={
              <ProtectedRoute roles={[ROLES.ADMIN]}>
                <NewProjectPage />
              </ProtectedRoute>
            }
          />
          {/* Fiche de séance : hors menu, « Planning » reste le point d'entrée. L'API vérifie les droits. */}
          <Route path="/planning/seances/:sessionId/fiche" element={<OpenLogbookPage />} />
          <Route path="/fiches/:id" element={<LogbookEntryPage />} />
          <Route path="/notifications" element={<NotificationsPage />} />
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
