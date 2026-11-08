import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, NavLink, useParams } from 'react-router-dom';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import Button from '../../Components/UI/Button.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { ROLES } from '../../Context/roles.js';
import { getProject, listProjects } from '../../Services/ProjectService.js';
import { commonLocality, formatPeriod, statusLabel } from './projectLabels.js';
import '../../assets/css/ProjectStyles.css';

/*
 * Détail d'un projet (maquette, écrans 04 et M4), en lecture seule.
 * Seules les données que l'API fournit aujourd'hui sont affichées : les indicateurs
 * (participants, présence…), la frise des séances et le nombre de séances réalisées
 * attendent les API de séances et de fiches. Aucun chiffre n'est inventé en attendant.
 */
export default function ProjectDetailsPage() {
  const { id } = useParams();
  const { logout, user } = useAuth();
  const [state, setState] = useState({ status: 'loading', project: null, error: '' });
  const [tabs, setTabs] = useState([]);

  // Numéro de la dernière requête : une réponse plus ancienne (autre projet, ou essai
  // précédent avant « Réessayer ») est ignorée et ne peut pas écraser la page.
  const lastRequest = useRef(0);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading', project: null, error: '' });
    getProject(id)
      .then((project) => !outdated() && setState({ status: 'ready', project, error: '' }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setState({
          status: error.status === 404 ? 'missing' : 'error',
          project: null,
          error: error.status === 0 ? error.message : 'Le projet n’a pas pu être chargé. Réessayez dans un instant.',
        });
      });
  }, [id, logout]);

  useEffect(() => {
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  useEffect(() => {
    if (state.status === 'ready') document.title = `${state.project.name} · KLAP`;
    else if (state.status === 'missing') document.title = 'Projet introuvable · KLAP';
  }, [state]);

  // Onglets de la maquette : les autres projets visibles. Un échec ici ne bloque pas la page.
  useEffect(() => {
    let cancelled = false;
    listProjects()
      .then((projects) => !cancelled && setTabs(projects))
      .catch(() => !cancelled && setTabs([]));
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <section className="page">
      {tabs.length > 1 && (
        <nav className="project-tabs" aria-label="Projets">
          {tabs.map((tab) => (
            <NavLink key={tab.id} to={`/projets/${tab.id}`} className="project-tab">
              {tab.name}
            </NavLink>
          ))}
        </nav>
      )}

      {state.status === 'loading' && (
        <>
          <h1 className="visually-hidden">Projet</h1>
          <LoadingSpinner label="Chargement du projet…" />
        </>
      )}

      {state.status === 'missing' && (
        <div className="page-error" role="alert">
          <h1 className="page-title">Projet introuvable</h1>
          <p>Ce projet n’existe pas ou ne vous est pas accessible.</p>
          <Link to="/projets" className="btn btn-line">
            Retour aux projets
          </Link>
        </div>
      )}

      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <h1 className="visually-hidden">Projet</h1>
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}

      {state.status === 'ready' && <ProjectDetails project={state.project} canEdit={user?.role === ROLES.ADMIN} />}
    </section>
  );
}

function ProjectDetails({ project, canEdit }) {
  const lead = project.team?.filter((member) => member.member_role === 'lead') ?? [];
  const occasional = project.team?.filter((member) => member.member_role === 'occasional') ?? [];
  const subProjects = project.sub_projects ?? [];
  const locality = commonLocality(subProjects);
  const period = formatPeriod(project.start_date, project.end_date);
  // Un projet non découpé a un seul sous-projet qui porte son nom : pas de découpage à montrer.
  const isSplit = subProjects.length > 1;

  return (
    <>
      <header className="project-header">
        <p className="project-tags">
          {project.project_type && <span className="tag tag-accent">{project.project_type.name}</span>}
          {project.status !== 'active' && <span className="tag">{statusLabel(project.status)}</span>}
          {locality && <span className="project-locality">{locality}</span>}
        </p>
        <h1 className="project-title">{project.name}</h1>
        {canEdit && (
          <p>
            <Link to={`/gestion/projets/${project.id}/modifier`} className="btn btn-line">
              {project.status === 'archived' ? 'Restaurer' : 'Modifier'}
            </Link>
          </p>
        )}
        {project.description && <p className="project-description">{project.description}</p>}

        <dl className="project-facts">
          <div>
            <dt>Intervenant·e principal·e</dt>
            <dd>{lead.length ? lead.map((member) => member.name).join(', ') : 'Non désigné·e'}</dd>
          </div>
          {occasional.length > 0 && (
            <div>
              <dt>Ponctuels (ajoutés par les fiches)</dt>
              <dd>{occasional.map((member) => member.name).join(', ')}</dd>
            </div>
          )}
          {project.planned_sessions > 0 && (
            <div>
              <dt>Séances prévues</dt>
              <dd>{project.planned_sessions}</dd>
            </div>
          )}
          {period && (
            <div>
              <dt>Période</dt>
              <dd>{period}</dd>
            </div>
          )}
          {project.default_location && (
            <div>
              <dt>Lieu par défaut</dt>
              <dd>{project.default_location.name}</dd>
            </div>
          )}
          {project.funders?.length > 0 && (
            <div>
              <dt>Financeurs</dt>
              <dd>{project.funders.map((funder) => funder.name).join(', ')}</dd>
            </div>
          )}
        </dl>
      </header>

      {isSplit && (
        <section className="project-section" aria-labelledby="sous-projets">
          <h2 id="sous-projets" className="section-title">
            Sous-projets
          </h2>
          <ul className="subproject-grid">
            {subProjects.map((sub) => (
              <li key={sub.id} className="subproject-card">
                <p className="subproject-name">{sub.name}</p>
                <p className="subproject-meta">
                  {[sub.organization, sub.class_group, sub.age_range].filter(Boolean).join(' · ') || 'Groupe'}
                </p>
                {sub.expected_headcount > 0 && (
                  <p className="subproject-meta">{sub.expected_headcount} participants attendus</p>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}
    </>
  );
}
