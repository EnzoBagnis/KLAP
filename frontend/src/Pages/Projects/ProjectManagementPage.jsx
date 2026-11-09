import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import Button from '../../Components/UI/Button.jsx';
import ManagementTabs from '../../Components/Management/ManagementTabs.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { listProjects } from '../../Services/ProjectService.js';
import { errorMessage } from './ProjectListPage.jsx';
import { statusLabel } from './projectLabels.js';
import '../../assets/css/ProjectStyles.css';

/*
 * Gestion des projets (maquette, écran 07), réservée à l'administratrice.
 * Pas de bouton Modifier ni Supprimer : ces actions n'ont pas encore d'API, et la
 * suppression passera par un parcours long d'archivage (contexte client).
 * L'onglet Utilisateurs (écran 08) permet d'inviter une personne (#127).
 * Les projets archivés sont masqués par défaut ; la case « Afficher les projets archivés »
 * (?archives=1) les montre, pour ouvrir leur page et les restaurer (#196).
 */
export default function ProjectManagementPage() {
  const { logout } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const includeArchived = searchParams.get('archives') === '1';
  const [state, setState] = useState({ status: 'loading', projects: [], error: '' });
  const lastRequest = useRef(0);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading', projects: [], error: '' });
    listProjects({ includeArchived })
      .then((projects) => !outdated() && setState({ status: 'ready', projects, error: '' }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setState({ status: 'error', projects: [], error: errorMessage(error) });
      });
  }, [logout, includeArchived]);

  useEffect(() => {
    document.title = 'Gestion · KLAP';
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  return (
    <section className="page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Gestion</h1>
          <p className="page-subtitle">Projets</p>
        </div>
        <Link to="/gestion/projets/nouveau" className="btn btn-primary">
          + Nouveau projet
        </Link>
      </div>
      <ManagementTabs />

      <label className="checkbox management-filter">
        <input
          type="checkbox"
          checked={includeArchived}
          onChange={(event) => setSearchParams(event.target.checked ? { archives: '1' } : {}, { replace: true })}
        />
        Afficher les projets archivés
      </label>

      {state.status === 'loading' && <LoadingSpinner label="Chargement des projets…" />}

      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}

      {state.status === 'ready' && state.projects.length === 0 && (
        <p className="page-empty">
          {includeArchived ? 'Aucun projet, archivé ou non.' : 'Aucun projet en cours. Créez-en un avec « Nouveau projet ».'}
        </p>
      )}

      {state.status === 'ready' && state.projects.length > 0 && (
        <ul className="management-list">
          {state.projects.map((project) => (
            <li key={project.id} className="management-row">
              <p className="management-name">
                <Link to={`/projets/${project.id}`}>{project.name}</Link>
                {project.project_type && <span className="tag">{project.project_type.name}</span>}
                {project.status !== 'active' && <span className="tag">{statusLabel(project.status)}</span>}
              </p>
              <p className="management-meta">
                {project.sub_projects_count > 1 ? `${project.sub_projects_count} sous-projets` : 'Un seul groupe'}
                {project.planned_sessions > 0 ? ` · ${project.planned_sessions} séances prévues` : ''}
              </p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
