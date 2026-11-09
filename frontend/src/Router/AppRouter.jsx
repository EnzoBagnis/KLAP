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
import ProfilePage from '../Pages/Profile/ProfilePage.jsx';
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
        {/* Lien reçu par mail (#127) : hors connexion. */}
        <Route path="/activation/:token" element={<ActivationPage />} />
        {/* Mot de passe oublié (#81) : hors connexion, lien reçu par mail. */}
        <Route path="/mot-de-passe-oublie" element={<ForgotPasswordPage />} />
        <Route path="/reinitialisation/:token" element={<ResetPasswordPage />} />
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
          {/* Gestion, onglet Utilisateurs : inviter une personne (#127), administratrice seulement. */}
          <Route
            path="/gestion/utilisateurs"
            element={
              <ProtectedRoute roles={[ROLES.ADMIN]}>
                <UserManagementPage />
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
          {/* Fiche de séance : hors menu, « Planning » reste le point d'entrée. L'API vérifie les droits. */}
          <Route path="/planning/seances/:sessionId/fiche" element={<OpenLogbookPage />} />
          <Route path="/fiches/:id" element={<LogbookEntryPage />} />
          <Route path="/notifications" element={<NotificationsPage />} />
          {/* Mon profil (#71) : lien du nom en bas du menu. */}
          <Route path="/profil" element={<ProfilePage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}

// Écran provisoire, à supprimer quand les vraies pages seront branchées.
function Placeholder({ title }) {
  // Titre d'onglet : sans lui, l'onglet garde celui de la page précédente (« Activer mon compte »).
  useEffect(() => {
    document.title = `${title} · KLAP`;
  }, [title]);
  return (
    <section className="placeholder">
      <h1>{title}</h1>
      <p>Écran en cours de développement.</p>
    </section>
  );
}

/*
 * Adresse inconnue : hors des gabarits, donc avec sa propre zone principale (repère <main>
 * pour les lecteurs d'écran), et un chemin de retour.
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
