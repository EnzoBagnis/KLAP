import { Navigate, Outlet } from 'react-router-dom';
import useAuth from '../Hooks/useAuth.js';

/*
 * Garde des écrans connectés.
 * - sans utilisateur : renvoi vers /connexion ;
 * - avec `roles` : un rôle absent de la liste est renvoyé à l'accueil.
 * C'est un confort d'interface, pas une sécurité : l'API refuse aussi l'accès.
 */
export default function ProtectedRoute({ roles, children }) {
  const { user } = useAuth();

  if (!user) {
    return <Navigate to="/connexion" replace />;
  }
  if (roles && !roles.includes(user.role)) {
    return <Navigate to="/" replace />;
  }
  return children ?? <Outlet />;
}
