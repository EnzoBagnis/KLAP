import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, NavLink, useParams } from 'react-router-dom';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import Button from '../../Components/UI/Button.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { ROLES } from '../../Context/roles.js';
import { addProjectMember, createSubProject, getProject, listProjects, removeProjectMember, updateSubProject } from '../../Services/ProjectService.js';
import { listContributors } from '../../Services/ReferenceService.js';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import { formatDate } from '../Logbook/logbookModel.js';
import ReferenceList from '../../Components/Management/ReferenceList.jsx';
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

      {state.status === 'ready' && (
        <ProjectDetails
          project={state.project}
          canEdit={user?.role === ROLES.ADMIN}
          canReport={user?.role === ROLES.ADMIN || user?.role === ROLES.SUPER_USER}
        />
      )}
    </section>
  );
}

function ProjectDetails({ project, canEdit, canReport }) {
  // L'équipe suit les ajouts et retraits de la section Équipe (#251), en-tête compris.
  const [team, setTeam] = useState(project.team ?? []);
  const lead = team.filter((member) => member.member_role === 'lead');
  // Les membres retiré·e·s de l'équipe (#251) ne figurent plus parmi les ponctuel·le·s actuel·le·s.
  const occasional = team.filter((member) => member.member_role === 'occasional' && !member.end_date);
  const subProjects = project.sub_projects ?? [];
  const locality = commonLocality(subProjects);
  const period = formatPeriod(project.start_date, project.end_date);
  // Un projet non découpé a un seul sous-projet qui porte son nom : pas de découpage à montrer.
  // Seuls les groupes actifs comptent (#250) : un groupe retiré ne fait pas un découpage.
  const isSplit = subProjects.filter((sub) => sub.is_active !== false).length > 1;

  return (
    <>
      <header className="project-header">
        <p className="project-tags">
          {project.project_type && <span className="tag tag-accent">{project.project_type.name}</span>}
          {project.status !== 'active' && <span className="tag">{statusLabel(project.status)}</span>}
          {locality && <span className="project-locality">{locality}</span>}
        </p>
        <h1 className="project-title">{project.name}</h1>
        {(canEdit || canReport) && (
          <p className="project-actions">
            {canEdit && (
              <Link to={`/gestion/projets/${project.id}/modifier`} className="btn btn-line">
                {project.status === 'archived' ? 'Restaurer' : 'Modifier'}
              </Link>
            )}
            {/* Pré-bilan Word (#144) : administratrice et super-utilisatrice. */}
            {canReport && (
              <Link to={`/pre-bilan?projet=${project.id}`} className="btn btn-line">
                Pré-bilan
              </Link>
            )}
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
              <dt>Intervenant·e·s ponctuel·le·s</dt>
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

      {canEdit && project.status !== 'archived' && (
        <div className="project-section">
          <GroupManager projectId={project.id} />
        </div>
      )}

      {canEdit && project.status !== 'archived' && (
        <div className="project-section">
          <TeamManager project={project} team={team} onTeamChange={setTeam} />
        </div>
      )}

      {isSplit && !(canEdit && project.status !== 'archived') && (
        <section className="project-section" aria-labelledby="sous-projets">
          <h2 id="sous-projets" className="section-title">
            Sous-projets
          </h2>
          <ul className="subproject-grid">
            {subProjects.map((sub) => (
              <li key={sub.id} className="subproject-card">
                <p className="subproject-name">
                  {sub.name}
                  {sub.is_active === false && <span className="tag">Retiré</span>}
                </p>
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

// ---------- Groupes du projet, gérés par l'administratrice (#250) ----------

const GROUP_FIELDS = [
  { key: 'name', label: 'Nom du groupe', required: true, maxLength: 150, requiredMessage: 'Indiquez le nom du groupe.' },
  { key: 'class_group', label: 'Classe ou groupe', maxLength: 100 },
  { key: 'age_range', label: 'Tranche d’âge', maxLength: 30 },
  { key: 'expected_headcount', label: 'Effectif prévu', inputMode: 'numeric', pattern: /^[1-9]\d{0,4}$/, patternMessage: 'Indiquez un nombre entier d’élèves.' },
  { key: 'organization', label: 'Structure', maxLength: 150 },
  { key: 'city', label: 'Ville', maxLength: 100 },
  { key: 'postal_code', label: 'Code postal', inputMode: 'numeric', pattern: /^\d{5}$/, patternMessage: 'Le code postal doit compter 5 chiffres.' },
];

const GROUP_WORDING = {
  off: 'Retirer',
  on: 'Remettre',
  tag: 'Retiré',
  doneOff: 'retiré',
  doneOn: 'remis',
  askOff: (n) => `Retirer le groupe « ${n} » ? Il ne sera plus proposé pour les nouvelles séances ; ses séances et ses chiffres restent.`,
  askOn: (n) => `Remettre le groupe « ${n} » ? Il sera de nouveau proposé pour les séances.`,
};

const groupMeta = (g) =>
  [
    g.class_group,
    g.age_range,
    g.expected_headcount ? `${g.expected_headcount} élèves prévus` : null,
    g.organization,
    [g.postal_code, g.city].filter(Boolean).join(' '),
  ]
    .filter(Boolean)
    .join(' · ') || 'Âge et effectif à compléter';

function GroupManager({ projectId }) {
  const load = useCallback(() => getProject(projectId).then((project) => project.sub_projects ?? []), [projectId]);
  const create = useCallback((payload) => createSubProject(projectId, payload), [projectId]);
  return (
    <ReferenceList
      title="Groupes"
      intro="Les classes ou groupes du projet. La tranche d’âge et l’effectif prévu servent aux statistiques (âge, assiduité) et au pré-bilan."
      noun="Groupe"
      addLabel="Ajouter un groupe"
      fields={GROUP_FIELDS}
      load={load}
      create={create}
      update={updateSubProject}
      meta={groupMeta}
      wording={GROUP_WORDING}
    />
  );
}

// ---------- Équipe du projet, gérée par l'administratrice (#251) ----------

function TeamManager({ project, team, onTeamChange: setTeam }) {
  const { logout } = useAuth();
  const [contributors, setContributors] = useState([]);
  const [contributorsFailed, setContributorsFailed] = useState(false);
  const [choice, setChoice] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState({ kind: '', text: '' });
  const [busy, setBusy] = useState(false);
  const headingRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    listContributors()
      .then((list) => !cancelled && setContributors(list))
      .catch((failure) => {
        if (failure.status === 401) logout();
        else if (!cancelled) setContributorsFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, [logout]);

  const current = team.filter((member) => !member.end_date);
  const former = team.filter((member) => member.end_date);
  const available = contributors.filter((person) => !current.some((member) => member.id === person.id));

  function failed(failure, fallback) {
    if (failure.status === 401) {
      logout();
      return;
    }
    // Équipe changée entre-temps (retrait déjà fait ailleurs…) : on la relit.
    if (failure.status === 404) {
      getProject(project.id)
        .then((fresh) => setTeam(fresh.team ?? []))
        .catch(() => {});
      setNotice({ kind: 'error', text: 'L’équipe a changé entre-temps : elle vient d’être rechargée.' });
      return;
    }
    const message = failure.errors?.user_id?.[0] ?? (failure.status === 409 || failure.status === 422 ? failure.message : null);
    setNotice({ kind: 'error', text: message || (failure.status === 0 ? failure.message : fallback) });
  }

  async function add(event) {
    event.preventDefault();
    if (busy) return;
    setNotice({ kind: '', text: '' });
    if (!choice) {
      setError('Choisissez l’intervenant·e à ajouter.');
      return;
    }
    setError('');
    setBusy(true);
    try {
      const updated = await addProjectMember(project.id, choice);
      setTeam(updated.team ?? []);
      const person = contributors.find((item) => String(item.id) === String(choice));
      setChoice('');
      setNotice({ kind: 'success', text: `${person?.name ?? 'L’intervenant·e'} fait maintenant partie de l’équipe : vous pouvez lui confier des séances.` });
    } catch (failure) {
      failed(failure, 'L’ajout n’a pas abouti. Réessayez dans un instant.');
    } finally {
      setBusy(false);
    }
  }

  async function remove(member) {
    if (!window.confirm(`Retirer ${member.name} de l’équipe ? Ses fiches restent lisibles ; on ne pourra plus lui confier de séance sur ce projet.`)) return;
    setBusy(true);
    setNotice({ kind: '', text: '' });
    try {
      const updated = await removeProjectMember(project.id, member.id);
      setTeam(updated.team ?? []);
      setNotice({ kind: 'success', text: `${member.name} a été retiré·e de l’équipe.` });
      requestAnimationFrame(() => headingRef.current?.focus());
    } catch (failure) {
      failed(failure, 'Le retrait n’a pas abouti. Réessayez dans un instant.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="reference-list" aria-labelledby="equipe">
      <h2 id="equipe" className="section-title" ref={headingRef} tabIndex={-1}>
        Équipe
      </h2>
      <p className="form-hint">
        Les intervenant·e·s qui peuvent animer les séances du projet. Ajoutez ici les ponctuel·le·s ; l’intervenant·e principal·e se change dans
        « Modifier ».
      </p>
      {notice.text && (
        <p className={notice.kind === 'success' ? 'form-success' : 'form-alert'} role={notice.kind === 'success' ? 'status' : 'alert'}>
          {notice.text}
        </p>
      )}
      <ul className="account-rows">
        {current.map((member) => (
          <li key={member.id} className="account-row">
            <div>
              <p className="account-name">{member.name}</p>
              <p className="account-meta">{member.member_role === 'lead' ? 'Intervenant·e principal·e' : 'Intervenant·e ponctuel·le'}</p>
            </div>
            {member.member_role !== 'lead' && (
              <Button variant="line" onClick={() => remove(member)} disabled={busy}>
                Retirer<span className="visually-hidden">{` ${member.name}`}</span>
              </Button>
            )}
          </li>
        ))}
        {former.map((member) => (
          <li key={member.id} className="account-row is-inactive">
            <div>
              <p className="account-name">
                {member.name}
                <span className="tag">Retiré·e</span>
              </p>
              <p className="account-meta">A quitté l’équipe le {formatDate(member.end_date)} · ses fiches restent lisibles</p>
            </div>
          </li>
        ))}
      </ul>
      <form className="reference-form" onSubmit={add} noValidate>
        <SelectInput
          label="Ajouter un·e intervenant·e"
          name="nouveau_membre"
          value={choice}
          onChange={(event) => setChoice(event.target.value)}
          error={error}
          placeholder={
            contributorsFailed
              ? 'La liste des intervenant·e·s n’a pas pu être chargée'
              : available.length
                ? 'Choisir…'
                : 'Tous les intervenant·e·s actifs sont déjà dans l’équipe'
          }
          options={available.map((person) => ({ value: String(person.id), label: person.name }))}
        />
        <div className="reference-form-actions">
          <Button type="submit" disabled={busy || !available.length}>
            Ajouter à l’équipe
          </Button>
        </div>
      </form>
    </section>
  );
}
