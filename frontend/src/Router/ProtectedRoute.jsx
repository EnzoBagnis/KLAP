import { Navigate, Outlet } from 'react-router-dom';
import LoadingSpinner from '../Components/UI/LoadingSpinner.jsx';
import useAuth from '../Hooks/useAuth.js';

/**
 * La garde des écrans connectés.
 * - jeton en cours de vérification : on affiche un écran d'attente (sinon un rechargement
 *   renverrait vers /connexion avant que la session soit confirmée) ;
 * - sans utilisateur : on renvoie vers /connexion ;
 * - avec `roles` : on renvoie à l'accueil un rôle absent de la liste.
 * C'est un confort d'interface, pas une sécurité : l'API refuse aussi l'accès.
 *
 * @param {object} props
 * @param {string[]} [props.roles]  les rôles autorisés ; tous si on ne précise rien
 * @param {React.ReactNode} [props.children]  l'écran à protéger ; sinon les routes imbriquées
 */
export default function ProtectedRoute({ roles, children }) {
  const { user, status } = useAuth();

  if (status === 'loading') {
    return <LoadingSpinner label="Vérification de la session…" />;
  }
  if (!user) {
    return <Navigate to="/connexion" replace />;
  }
  if (roles && !roles.includes(user.role)) {
    return <Navigate to="/" replace />;
  }
  return children ?? <Outlet />;
}
