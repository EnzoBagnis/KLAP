import { useEffect } from 'react';
import { Link, Route, Routes } from 'react-router-dom';
import AuthLayout from '../Layouts/AuthLayout.jsx';
import MainLayout from '../Layouts/MainLayout.jsx';
import NewProjectPage from '../Pages/Projects/NewProjectPage.jsx';
import EditProjectPage from '../Pages/Projects/EditProjectPage.jsx';
import NotificationsPage from '../Pages/Notifications/NotificationsPage.jsx';
import { ROLES } from '../Context/roles.js';
import ProjectDetailsPage from '../Pages/Projects/ProjectDetailsPage.jsx';
import ActivationPage from '../Pages/Auth/ActivationPage.jsx';
import ForgotPasswordPage from '../Pages/Auth/ForgotPasswordPage.jsx';
import ResetPasswordPage from '../Pages/Auth/ResetPasswordPage.jsx';
import LoginPage from '../Pages/Auth/LoginPage.jsx';
import UserManagementPage from '../Pages/Users/UserManagementPage.jsx';
import ReferenceListsPage from '../Pages/Management/ReferenceListsPage.jsx';
import ProfilePage from '../Pages/Profile/ProfilePage.jsx';
import LogbookEntryPage from '../Pages/Logbook/LogbookEntryPage.jsx';
import OpenLogbookPage from '../Pages/Logbook/OpenLogbookPage.jsx';
import ProtectedRoute from './ProtectedRoute.jsx';
import { NAV_ITEMS } from '../Components/Layout/navigation.js';

/**
 * Toutes les routes de l'application. On dérive celles du menu de NAV_ITEMS : le chemin, les
 * rôles autorisés et l'écran (champ `page`) viennent de la même entrée, il n'y a pas de
 * seconde liste ici.
 */
export default function AppRouter() {
  return (
    <Routes>
      <Route element={<AuthLayout />}>
        <Route path="/connexion" element={<LoginPage />} />
        {/* Le lien reçu par mail (#127) : on l'ouvre sans être connecté. */}
        <Route path="/activation/:token" element={<ActivationPage />} />
        {/* Mot de passe oublié (#81) : sans être connecté, puis par le lien reçu par mail. */}
        <Route path="/mot-de-passe-oublie" element={<ForgotPasswordPage />} />
        <Route path="/reinitialisation/:token" element={<ResetPasswordPage />} />
      </Route>

      <Route element={<ProtectedRoute />}>
        <Route element={<MainLayout />}>
          {NAV_ITEMS.map(({ path, roles, page: Page }) => (
            <Route
              key={path}
              path={path}
              element={
                <ProtectedRoute roles={roles}>
                  <Page />
                </ProtectedRoute>
              }
            />
          ))}
          {/* Le détail d'un projet : hors menu, « Projets » reste actif. C'est l'API qui filtre par rôle. */}
          <Route path="/projets/:id" element={<ProjectDetailsPage />} />
          {/* Gestion, onglet Utilisateurs : inviter une personne (#127), administratrice seulement. */}
          <Route
            path="/gestion/utilisateurs"
            element={
              <ProtectedRoute roles={[ROLES.ADMIN]}>
                <UserManagementPage />
              </ProtectedRoute>
            }
          />
          {/* Gestion, onglet Listes (#249) : financeurs, types de projet et lieux. */}
          <Route
            path="/gestion/listes"
            element={
              <ProtectedRoute roles={[ROLES.ADMIN]}>
                <ReferenceListsPage />
              </ProtectedRoute>
            }
          />
          {/* Création d'un projet, sous Gestion : administratrice seulement (RG02). */}
          <Route
            path="/gestion/projets/nouveau"
            element={
              <ProtectedRoute roles={[ROLES.ADMIN]}>
                <NewProjectPage />
              </ProtectedRoute>
            }
          />
          {/* Modifier, archiver, restaurer un projet (#154) : administratrice seulement. */}
          <Route
            path="/gestion/projets/:id/modifier"
            element={
              <ProtectedRoute roles={[ROLES.ADMIN]}>
                <EditProjectPage />
              </ProtectedRoute>
            }
          />
          {/* La fiche de séance : hors menu, on y entre par le planning. C'est l'API qui vérifie les droits. */}
          <Route path="/planning/seances/:sessionId/fiche" element={<OpenLogbookPage />} />
          <Route path="/fiches/:id" element={<LogbookEntryPage />} />
          <Route path="/notifications" element={<NotificationsPage />} />
          {/* Mon profil (#71) : on y arrive par son nom, en bas du menu. */}
          <Route path="/profil" element={<ProfilePage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}

/**
 * L'écran d'une adresse inconnue. On l'affiche hors des gabarits : il a donc sa propre zone
 * principale (repère <main> pour les lecteurs d'écran), et un chemin de retour.
 */
function NotFound() {
  useEffect(() => {
    document.title = 'Page introuvable · KLAP';
  }, []);
  return (
    <main className="placeholder">
      <h1>Page introuvable</h1>
      <p>Cette adresse ne correspond à aucune page. Le lien est peut-être incomplet.</p>
      <p>
        <Link to="/">Retour à l’accueil</Link>
      </p>
    </main>
  );
}
