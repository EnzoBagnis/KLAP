import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import Button from '../../Components/UI/Button.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { listProjects } from '../../Services/ProjectService.js';
import { statusLabel } from './projectLabels.js';
import '../../assets/css/ProjectStyles.css';

/**
 * La liste des projets qu'on peut voir : tous pour l'administratrice et la super-utilisatrice,
 * ceux où il est affecté pour un intervenant. C'est le point d'entrée vers le détail.
 */
export default function ProjectListPage() {
  const { logout } = useAuth();
  const [state, setState] = useState({ status: 'loading', projects: [], error: '' });

  // Le numéro de la dernière requête : on ignore une réponse plus ancienne.
  const lastRequest = useRef(0);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading', projects: [], error: '' });
    listProjects()
      .then((projects) => !outdated() && setState({ status: 'ready', projects, error: '' }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setState({ status: 'error', projects: [], error: errorMessage(error) });
      });
  }, [logout]);

  useEffect(() => {
    document.title = 'Projets · KLAP';
    load();
    // Au démontage, on rend obsolète toute réponse encore en route.
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  return (
    <section className="page">
      <h1 className="page-title">Projets</h1>

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
        <p className="page-empty">Aucun projet pour l’instant.</p>
      )}

      {state.status === 'ready' && state.projects.length > 0 && (
        <ul className="project-list">
          {state.projects.map((project) => (
            <li key={project.id}>
              <Link to={`/projets/${project.id}`} className="project-card">
                <span className="project-card-tags">
                  {project.project_type && <span className="tag tag-accent">{project.project_type.name}</span>}
                  {project.status !== 'active' && <span className="tag">{statusLabel(project.status)}</span>}
                </span>
                <span className="project-card-name">{project.name}</span>
                <span className="project-card-meta">
                  {project.sub_projects_count > 1
                    ? `${project.sub_projects_count} sous-projets`
                    : 'Un seul groupe'}
                  {project.planned_sessions > 0 ? ` · ${project.planned_sessions} séances prévues` : ''}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

/**
 * Le message à afficher quand les projets ne se chargent pas.
 *
 * @param {import('../../Services/ApiClient.js').ApiError} error
 * @returns {string}
 */
export function errorMessage(error) {
  if (error.status === 0) return error.message;
  return 'Les projets n’ont pas pu être chargés. Réessayez dans un instant.';
}
