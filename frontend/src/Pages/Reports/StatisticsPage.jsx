import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { listProjects } from '../../Services/ProjectService.js';
import { getStatistics } from '../../Services/StatisticsService.js';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';
import '../../assets/css/StatisticsStyles.css';

const RATINGS = [
  { key: 'avg_engagement', label: 'Engagement', tone: 'engagement' },
  { key: 'avg_expression', label: 'Expression', tone: 'expression' },
  { key: 'avg_autonomy', label: 'Autonomie', tone: 'autonomy' },
];

const formatAverage = (value) => (value === null ? 'Non renseigné' : `${value.toLocaleString('fr-FR')} / 5`);
const formatCount = (value) => value.toLocaleString('fr-FR');

const DATE = /^\d{4}-\d{2}-\d{2}$/;
const ID = /^\d+$/;

/*
 * Filtres lus dans l'URL (?projet=3&du=2026-09-01&au=2026-12-31), pour survivre au rechargement.
 * Une valeur mal formée est ignorée : le champ date ne pourrait pas l'afficher.
 */
function filtersFrom(searchParams) {
  const read = (key, pattern) => (pattern.test(searchParams.get(key) ?? '') ? searchParams.get(key) : '');
  return { projectId: read('projet', ID), from: read('du', DATE), to: read('au', DATE) };
}

function paramsOf({ projectId, from, to }) {
  const params = {};
  if (projectId) params.projet = projectId;
  if (from) params.du = from;
  if (to) params.au = to;
  return params;
}

/*
 * Statistiques (#74) : chiffres agrégés à partir des fiches rendues, filtrés par projet
 * et période. Participants : maximum des présents par groupe, somme par projet (pas un cumul).
 */
export default function StatisticsPage() {
  const { logout } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = filtersFrom(searchParams);
  const [draft, setDraft] = useState(filters);
  const [projects, setProjects] = useState([]);
  const [state, setState] = useState({ status: 'loading', data: null, error: '', fieldErrors: {} });
  const lastRequest = useRef(0);
  const { projectId, from, to } = filters;
  const urlKey = searchParams.toString();

  // Le formulaire suit l'URL (Retour, lien du menu), pas seulement le premier affichage.
  useEffect(() => {
    setDraft(filtersFrom(new URLSearchParams(urlKey)));
  }, [urlKey]);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading', data: null, error: '', fieldErrors: {} });
    getStatistics({ projectId, from, to })
      .then((data) => !outdated() && setState({ status: 'ready', data, error: '', fieldErrors: {} }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        const fieldErrors = Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
        setState({
          status: 'error',
          data: null,
          fieldErrors,
          error:
            error.status === 422
              ? 'Les filtres ne sont pas valides : corrigez les champs signalés.'
              : error.status === 0
                ? error.message
                : 'Les statistiques n’ont pas pu être chargées. Réessayez dans un instant.',
        });
      });
  }, [projectId, from, to, logout]);

  useEffect(() => {
    document.title = 'Statistiques · KLAP';
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  // La liste des projets ne sert qu'au filtre : sans elle, la page reste utilisable.
  useEffect(() => {
    let active = true;
    listProjects()
      .then((list) => active && setProjects(list))
      .catch(() => active && setProjects([]));
    return () => {
      active = false;
    };
  }, []);

  function go(next) {
    const params = paramsOf(next);
    // Sans changement, pas de nouvelle entrée d'historique (Retour semblerait ne rien faire).
    setSearchParams(params, { replace: new URLSearchParams(params).toString() === urlKey });
  }

  function apply(event) {
    event.preventDefault();
    go(draft);
  }

  function reset() {
    setDraft({ projectId: '', from: '', to: '' });
    go({});
  }

  const { data, fieldErrors } = state;

  // Projets du menu : ceux de l'API projets, plus ceux des statistiques (archivés compris),
  // plus celui de l'URL s'il est inconnu, pour que le menu montre toujours le filtre appliqué.
  const options = new Map(projects.map((project) => [String(project.id), project.name]));
  data?.projects.forEach((project) => options.has(String(project.id)) || options.set(String(project.id), project.name));
  if (draft.projectId && !options.has(draft.projectId)) options.set(draft.projectId, `Projet n° ${draft.projectId}`);

  return (
    <section className="page statistics-page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Statistiques</h1>
          <p className="page-subtitle">Calculées à partir des fiches rendues, séances annulées exclues.</p>
        </div>
      </div>

      <form className="statistics-filters" onSubmit={apply} aria-label="Filtres">
        <SelectInput
          label="Projet"
          placeholder="Tous les projets"
          value={draft.projectId}
          onChange={(event) => setDraft({ ...draft, projectId: event.target.value })}
          options={[...options].map(([value, label]) => ({ value, label }))}
          error={fieldErrors.project_id}
        />
        <TextInput
          label="Du"
          type="date"
          value={draft.from}
          onChange={(event) => setDraft({ ...draft, from: event.target.value })}
          error={fieldErrors.from}
        />
        <TextInput
          label="Au"
          type="date"
          value={draft.to}
          onChange={(event) => setDraft({ ...draft, to: event.target.value })}
          error={fieldErrors.to}
        />
        <div className="statistics-filter-actions">
          <Button type="submit">Appliquer</Button>
          <Button variant="ghost" onClick={reset}>
            Tout afficher
          </Button>
        </div>
      </form>

      {state.status === 'loading' && <LoadingSpinner label="Calcul des statistiques…" />}

      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <p>{state.error}</p>
          {state.error && !Object.keys(fieldErrors).length && (
            <Button variant="line" onClick={load}>
              Réessayer
            </Button>
          )}
        </div>
      )}

      {state.status === 'ready' && data.projects.length === 0 && <p className="page-empty">Aucun projet à afficher.</p>}

      {state.status === 'ready' && data.projects.length > 0 && (
        <>
          <ul className="stat-cards" aria-label="Totaux">
            <StatCard label="Participants" value={formatCount(data.totals.participants)} />
            <StatCard label="Séances documentées" value={formatCount(data.totals.sessions_documented)} />
            <StatCard
              label="Fiches manquantes"
              value={formatCount(data.totals.entries_missing)}
              alert={data.totals.entries_missing > 0}
            />
            <StatCard label="Évaluations" value={formatCount(data.totals.evaluations)} />
          </ul>

          <section aria-labelledby="moyennes-titre">
            <h2 id="moyennes-titre" className="section-title">
              Moyennes des participants
            </h2>
            <Ratings values={data.totals} />
          </section>

          <section aria-labelledby="projets-titre">
            <h2 id="projets-titre" className="section-title">
              Par projet
            </h2>
            <div className="statistics-table-wrapper" role="region" aria-labelledby="projets-titre" tabIndex={0}>
              <table className="statistics-table" aria-labelledby="projets-titre">
                <thead>
                  <tr>
                    <th scope="col">Projet et groupes</th>
                    <th scope="col">Participants</th>
                    <th scope="col">Séances documentées</th>
                    <th scope="col">Fiches manquantes</th>
                    <th scope="col">Évaluations</th>
                    {RATINGS.map((rating) => (
                      <th key={rating.key} scope="col">
                        {rating.label}
                      </th>
                    ))}
                  </tr>
                </thead>
                {data.projects.map((project) => (
                  <tbody key={project.id}>
                    <tr className="statistics-project">
                      <th scope="row">{project.name}</th>
                      <td>{formatCount(project.participants)}</td>
                      <td>{formatCount(project.sessions_documented)}</td>
                      <td>{formatCount(project.entries_missing)}</td>
                      <td>{formatCount(project.evaluations)}</td>
                      {RATINGS.map((rating) => (
                        <td key={rating.key}>{formatAverage(project[rating.key])}</td>
                      ))}
                    </tr>
                    {project.sub_projects.map((group) => (
                      <tr key={group.id} className="statistics-group">
                        <th scope="row">{group.name}</th>
                        <td>{formatCount(group.participants)}</td>
                        <td>{formatCount(group.sessions_documented)}</td>
                        {/* Fiches manquantes, évaluations et moyennes : comptées par projet seulement. */}
                        {Array.from({ length: RATINGS.length + 2 }, (_, index) => (
                          <td key={index} />
                        ))}
                      </tr>
                    ))}
                  </tbody>
                ))}
              </table>
            </div>
            <p className="form-hint">
              Participants d’un groupe : le maximum des présents sur ses séances. Participants d’un projet : la somme de ses groupes.
            </p>
          </section>
        </>
      )}
    </section>
  );
}

function StatCard({ label, value, alert = false }) {
  return (
    <li className={`stat-card${alert ? ' is-alert' : ''}`}>
      <span className="stat-label">{label}</span>
      <span className="stat-value">{value}</span>
    </li>
  );
}

/** Trois barres horizontales, avec la valeur écrite (la couleur n'est jamais la seule information). */
function Ratings({ values }) {
  return (
    <ul className="ratings">
      {RATINGS.map((rating) => {
        const value = values[rating.key];
        return (
          <li key={rating.key} className="rating-row">
            <span className="rating-label">{rating.label}</span>
            <span className="rating-track" aria-hidden="true">
              <span className={`rating-bar tone-${rating.tone}`} style={{ width: `${((value ?? 0) / 5) * 100}%` }} />
            </span>
            <span className="rating-value">{formatAverage(value)}</span>
          </li>
        );
      })}
    </ul>
  );
}
