import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import BarChart from '../../Components/Charts/BarChart.jsx';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { listProjects } from '../../Services/ProjectService.js';
import { getStatistics } from '../../Services/StatisticsService.js';
import { FEELINGS, PROGRESS } from '../Logbook/logbookModel.js';
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
            <StatCard
              label="Participants"
              value={formatCount(data.totals.participants)}
              hint="Somme des groupes : pas un nombre de personnes distinctes."
            />
            <StatCard label="Séances documentées" value={formatCount(data.totals.sessions_documented)} />
            <StatCard
              label="Fiches en retard"
              value={formatCount(data.totals.entries_missing)}
              hint={`Séances passées sans fiche rendue, dont ${formatCount(data.totals.entries_overdue)} hors délai.`}
              alert={data.totals.entries_missing > 0}
            />
          </ul>

          <section aria-labelledby="moyennes-titre">
            <h2 id="moyennes-titre" className="section-title">
              Moyennes des fiches
            </h2>
            <p className="form-hint">Notes de 1 à 5 données sur chaque fiche rendue : une note par fiche, parfois pour plusieurs groupes.</p>
            <Ratings values={data.totals} />
          </section>

          {data.observations && <Observations data={data.observations} />}

          <div className="statistics-breakdowns">
            <Breakdown
              id="provenance-titre"
              title="Provenance"
              chartTitle="Participants par provenance"
              rows={data.by_city}
              columns={[
                ['Ville', (row) => row.city || 'Non renseignée'],
                ['Code postal', (row) => row.postal_code || 'Non renseigné'],
              ]}
            />
            <Breakdown
              id="age-titre"
              title="Âge"
              chartTitle="Participants par tranche d’âge"
              rows={data.by_age_range}
              columns={[['Tranche d’âge', (row) => row.age_range || 'Non renseignée']]}
            />
          </div>

          <section aria-labelledby="projets-titre">
            <h2 id="projets-titre" className="section-title">
              Par projet
            </h2>
            {data.projects.length > 1 && (
              <BarChart
                title="Participants par projet"
                rows={data.projects.map((project) => ({ key: project.id, label: project.name, value: project.participants }))}
              />
            )}
            <AttendanceChart projects={data.projects} />
            {/* Nom distinct de la section : deux repères de même nom gênent la navigation au lecteur d’écran. */}
            <div className="statistics-table-wrapper" role="region" aria-label="Tableau : par projet" tabIndex={0}>
              <table className="statistics-table" aria-labelledby="projets-titre">
                <thead>
                  <tr>
                    <th scope="col">Projet et groupes</th>
                    <th scope="col">Participants</th>
                    <th scope="col">Séances documentées</th>
                    <th scope="col">Assiduité</th>
                    <th scope="col">Fiches en retard</th>
                    <th scope="col">dont hors délai</th>
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
                      <td />
                      <td>{formatCount(project.entries_missing)}</td>
                      <td>{formatCount(project.entries_overdue)}</td>
                      {RATINGS.map((rating) => (
                        <td key={rating.key}>{formatAverage(project[rating.key])}</td>
                      ))}
                    </tr>
                    {project.sub_projects.map((group) => (
                      <tr key={group.id} className="statistics-group">
                        <th scope="row">
                          {group.name}
                          {groupDetails(group) && (
                            <span className="statistics-group-meta">
                              <span className="visually-hidden">, </span>
                              {groupDetails(group)}
                            </span>
                          )}
                        </th>
                        <td>{formatCount(group.participants)}</td>
                        <td>{formatCount(group.sessions_documented)}</td>
                        <td>{formatAttendance(group)}</td>
                        {/* Fiches et moyennes : comptées par projet (une fiche couvre parfois plusieurs groupes). */}
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
              Assiduité : présents en moyenne sur les séances documentées, rapportés à l’effectif prévu du groupe (plus de 100 % si plus de présents que prévu).
              Fiches en retard : séances passées sans fiche rendue ; hors délai : échéance de modification passée.
            </p>
          </section>
        </>
      )}
    </section>
  );
}

function StatCard({ label, value, hint, alert = false }) {
  return (
    <li className={`stat-card${alert ? ' is-alert' : ''}`}>
      <span className="stat-label">{label}</span>
      <span className="stat-value">{value}</span>
      {hint && <span className="stat-hint">{hint}</span>}
    </li>
  );
}

/** « 15-16 ans · Marseille (13003) », à partir de ce qui est renseigné sur le groupe. */
function groupDetails(group) {
  const place = group.city ? `${group.city}${group.postal_code ? ` (${group.postal_code})` : ''}` : group.postal_code;
  return [group.age_range, place].filter(Boolean).join(' · ');
}

/** « 11 présents en moyenne, 73 % des 15 prévus » ; sans effectif prévu, la moyenne seule. */
function formatAttendance(group) {
  if (group.average_attendees == null) return 'Non renseignée';
  const average = `${group.average_attendees.toLocaleString('fr-FR')} présents en moyenne`;
  return group.attendance_rate == null
    ? average
    : `${average}, ${group.attendance_rate} % des ${group.expected_headcount} prévus`;
}

/** Participants répartis par provenance ou par âge (somme des groupes concernés). */
function Breakdown({ id, title, chartTitle, rows, columns }) {
  const keyOf = (row) => columns.map(([, value]) => value(row)).join('|');
  return (
    <section aria-labelledby={id}>
      <h2 id={id} className="section-title">
        {title}
      </h2>
      {rows.length === 0 ? (
        <p className="page-empty">Aucun participant compté sur la période.</p>
      ) : (
        <>
          <BarChart
            title={chartTitle}
            rows={rows.map((row) => ({
              key: keyOf(row),
              label: columns.map(([, value]) => value(row)).join(' · '),
              value: row.participants,
            }))}
          />
          <div className="statistics-table-wrapper" role="region" aria-label={`Tableau : ${title}`} tabIndex={0}>
            <table className="statistics-table" aria-labelledby={id}>
              <thead>
                <tr>
                  {columns.map(([label]) => (
                    <th key={label} scope="col">
                      {label}
                    </th>
                  ))}
                  <th scope="col">Groupes documentés</th>
                  <th scope="col">Participants</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={keyOf(row)}>
                    {columns.map(([label, value], index) =>
                      index === 0 ? (
                        <th key={label} scope="row">
                          {value(row)}
                        </th>
                      ) : (
                        <td key={label}>{value(row)}</td>
                      ),
                    )}
                    <td>{formatCount(row.groups)}</td>
                    <td>{formatCount(row.participants)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  );
}

/*
 * Assiduité par groupe, quand un seul projet est affiché (filtre) : taux de présence par
 * rapport à l'effectif prévu. Les groupes sans effectif prévu ou sans fiche sont absents.
 */
function AttendanceChart({ projects }) {
  if (projects.length !== 1) return null;
  const rows = projects[0].sub_projects
    .filter((group) => group.attendance_rate != null)
    .map((group) => ({ key: group.id, label: group.name, value: group.attendance_rate }));
  if (rows.length === 0) return null;
  return (
    <BarChart
      title="Assiduité par groupe (présents en moyenne, en % de l’effectif prévu)"
      rows={rows}
      tone="attendance"
      max={Math.max(100, ...rows.map((row) => row.value))}
      format={(value) => `${value} %`}
    />
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

const FEELING_LABELS = Object.fromEntries(FEELINGS);
const PROGRESS_LABELS = Object.fromEntries(PROGRESS);

/*
 * Ce que notent les intervenant·e·s sur les fiches rendues : sensations (bleu), interaction
 * (ambre), couleurs choisies par la cliente, et évolution. Chaque graphique a son tableau.
 */
function Observations({ data }) {
  const blocks = [
    {
      id: 'sensations-titre',
      title: 'Sensations',
      hint: 'Sensation dominante notée sur chaque fiche.',
      tone: 'feeling',
      rows: data.feelings.map((row) => ({ key: row.key, label: FEELING_LABELS[row.key] ?? row.key, value: row.entries })),
    },
    {
      id: 'interaction-titre',
      title: 'Interaction',
      hint: 'Fiches dont l’interaction cite chaque mot-clé proposé, avec ou sans accent (« conflits » compte pour « conflit ») ; une fiche peut en citer plusieurs.',
      tone: 'interaction',
      rows: data.interaction_keywords.map((row) => ({ key: row.key, label: row.key.charAt(0).toUpperCase() + row.key.slice(1), value: row.entries })),
    },
    {
      id: 'evolution-titre',
      title: 'Évolution par rapport à la séance précédente',
      column: 'Évolution',
      tone: 'progress',
      rows: data.progress.map((row) => ({ key: row.key, label: PROGRESS_LABELS[row.key] ?? row.key, value: row.entries })),
    },
  ];
  return (
    <section aria-labelledby="observations-titre">
      <h2 id="observations-titre" className="section-title">
        Ce que notent les intervenant·e·s
      </h2>
      {data.entries === 0 ? (
        <p className="page-empty">Aucune fiche rendue sur la période.</p>
      ) : (
        <>
          <p className="form-hint">Sur {formatCount(data.entries)} fiche{data.entries > 1 ? 's' : ''} rendue{data.entries > 1 ? 's' : ''}.</p>
          <div className="statistics-observations">
          {blocks.map((block) => (
            <div key={block.id}>
              <h3 id={block.id} className="statistics-observation-title">
                {block.title}
              </h3>
              {block.hint && <p className="form-hint">{block.hint}</p>}
              <BarChart rows={block.rows} tone={block.tone} max={data.entries} />
              <div className="statistics-table-wrapper" role="region" aria-label={`Tableau : ${block.title}`} tabIndex={0}>
                <table className="statistics-table" aria-labelledby={block.id}>
                  <thead>
                    <tr>
                      <th scope="col">{block.column ?? block.title}</th>
                      <th scope="col">Fiches</th>
                    </tr>
                  </thead>
                  <tbody>
                    {block.rows.map((row) => (
                      <tr key={row.key}>
                        <th scope="row">{row.label}</th>
                        <td>{formatCount(row.value)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ))}
          </div>
        </>
      )}
    </section>
  );
}
