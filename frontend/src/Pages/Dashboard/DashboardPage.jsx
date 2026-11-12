import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { getDashboard } from '../../Services/DashboardService.js';
import { dayTitle, sessionTypeLabel, todayKey } from '../Planning/sessionLabels.js';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';
import '../../assets/css/StatisticsStyles.css';
import '../../assets/css/DashboardStyles.css';

/** Les noms des groupes d'une séance, séparés par des virgules. */
const groupNames = (session) => session.sub_projects.map((group) => group.name).join(', ');

/**
 * Le jour d'une séance : « Aujourd'hui », sinon « lundi 5 octobre ».
 *
 * @param {string} isoDate  AAAA-MM-JJ
 * @param {string} today  AAAA-MM-JJ
 * @returns {string}
 */
function dayLabel(isoDate, today) {
  return isoDate === today ? 'Aujourd’hui' : dayTitle(isoDate);
}

/**
 * Le tableau de bord (#72), la page d'accueil de tous les rôles. On fait une seule requête
 * (GET /dashboard) : l'API ne renvoie que ce que la personne a le droit de voir.
 * - Les fiches à remplir, en premier : c'est ce que l'intervenant·e vient faire après sa séance.
 * - Les prochaines séances et les dernières fiches rendues.
 * - Le suivi (administration) : fiches en retard, demandes de réouverture.
 */
export default function DashboardPage() {
  const { user, logout } = useAuth();
  const [state, setState] = useState({ status: 'loading', data: null, error: '' });
  const lastRequest = useRef(0);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading', data: null, error: '' });
    getDashboard()
      .then((data) => !outdated() && setState({ status: 'ready', data, error: '' }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setState({
          status: 'error',
          data: null,
          error: error.status === 0 ? error.message : 'Le tableau de bord n’a pas pu être chargé. Réessayez dans un instant.',
        });
      });
  }, [logout]);

  useEffect(() => {
    document.title = 'Tableau de bord · KLAP';
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  const firstName = user?.name?.split(' ')[0];
  const today = todayKey();

  return (
    <section className="page dashboard-page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Tableau de bord</h1>
          {firstName && <p className="page-subtitle">Bonjour {firstName}</p>}
        </div>
      </div>

      {state.status === 'loading' && <LoadingSpinner label="Chargement du tableau de bord…" />}

      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}

      {state.status === 'ready' && (
        <>
          {state.data.follow_up && <FollowUp followUp={state.data.follow_up} />}
          {/* L'administration n'anime pas forcément de séance : sans fiche à remplir, on ne lui montre pas la section. */}
          {(state.data.to_fill.length > 0 || !state.data.follow_up) && <ToFill sessions={state.data.to_fill} today={today} />}
          <div className="dashboard-columns">
            <Upcoming sessions={state.data.upcoming} today={today} userId={user?.id} />
            <RecentEntries entries={state.data.recent_entries} />
          </div>
        </>
      )}
    </section>
  );
}

/**
 * Le suivi de l'administration : ce qui attend une action, avec le lien pour la faire.
 *
 * @param {object} props
 * @param {object} props.followUp  le bloc `follow_up` du tableau de bord
 */
function FollowUp({ followUp }) {
  const { entries_missing: missing, entries_overdue: overdue, unlock_requests: requests } = followUp;
  return (
    <section className="dashboard-section" aria-labelledby="suivi-titre">
      <h2 id="suivi-titre" className="section-title">
        Suivi
      </h2>
      <ul className="stat-cards dashboard-follow-up">
        <li className={`stat-card${missing > 0 ? ' is-alert' : ''}`}>
          <span className="stat-label">Fiches en retard</span>
          <span className="stat-value">{missing}</span>
          <span className="stat-hint">
            {missing === 0
              ? 'Toutes les séances passées ont leur fiche.'
              : `Séances passées sans fiche rendue, dont ${overdue} hors délai.`}
          </span>
          <Link to="/statistiques" className="dashboard-card-link">
            Voir les statistiques
          </Link>
        </li>
        {requests !== null && requests !== undefined && (
          <li className={`stat-card${requests > 0 ? ' is-alert' : ''}`}>
            <span className="stat-label">Demandes de réouverture</span>
            <span className="stat-value">{requests}</span>
            <span className="stat-hint">
              {requests === 0 ? 'Aucune demande en attente.' : 'Fiches verrouillées que leur auteur·rice demande à modifier.'}
            </span>
            <Link to="/notifications" className="dashboard-card-link">
              Voir les notifications
            </Link>
          </li>
        )}
      </ul>
    </section>
  );
}

/**
 * Les fiches à remplir, les plus anciennes d'abord (ce sont les plus urgentes), avec leur échéance.
 *
 * @param {object} props
 * @param {object[]} props.sessions
 * @param {string} props.today  AAAA-MM-JJ
 */
function ToFill({ sessions, today }) {
  return (
    <section className="dashboard-section" aria-labelledby="a-remplir-titre">
      <h2 id="a-remplir-titre" className="section-title">
        Fiches à remplir
      </h2>
      {sessions.length === 0 ? (
        <p className="page-empty">Aucune fiche à remplir pour le moment.</p>
      ) : (
        <ul className="dashboard-list">
          {sessions.map((session) => {
            const day = dayLabel(session.session_date, today).toLowerCase();
            return (
              <li key={session.id} className="dashboard-item is-todo">
                <div className="dashboard-item-body">
                  <p className="dashboard-item-when">
                    {dayLabel(session.session_date, today)} · {session.start_time} – {session.end_time}
                  </p>
                  <p className="dashboard-item-title">{session.project.name}</p>
                  <p className="dashboard-item-meta">
                    {sessionTypeLabel(session)} · {groupNames(session)}
                  </p>
                  <p className="dashboard-item-deadline">À rendre au plus tard le {dayTitle(session.deadline)}</p>
                </div>
                <Link to={`/planning/seances/${session.id}/fiche`} className="btn btn-primary dashboard-item-action">
                  {session.logbook_entry ? 'Reprendre la fiche' : 'Remplir la fiche'}
                  <span className="visually-hidden">{`, ${session.project.name}, ${day}`}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

/**
 * Les prochaines séances qu'on voit au planning (les cinq premières).
 *
 * @param {object} props
 * @param {object[]} props.sessions
 * @param {string} props.today  AAAA-MM-JJ
 * @param {number} props.userId  pour signaler les séances dont on est référent·e
 */
function Upcoming({ sessions, today, userId }) {
  return (
    <section className="dashboard-section" aria-labelledby="prochaines-titre">
      <h2 id="prochaines-titre" className="section-title">
        Prochaines séances
      </h2>
      {sessions.length === 0 ? (
        <p className="page-empty">Aucune séance programmée à venir.</p>
      ) : (
        <ul className="dashboard-list">
          {sessions.map((session) => (
            <li key={session.id} className="dashboard-item">
              <div className="dashboard-item-body">
                <p className="dashboard-item-when">
                  {dayLabel(session.session_date, today)} · {session.start_time} – {session.end_time}
                </p>
                <p className="dashboard-item-title">
                  <Link to={`/projets/${session.project.id}`}>{session.project.name}</Link>
                </p>
                <p className="dashboard-item-meta">
                  {groupNames(session)} · {session.location.name}
                  {session.referent.id !== userId && ` · ${session.referent.name}`}
                </p>
              </div>
            </li>
          ))}
        </ul>
      )}
      <p>
        <Link to="/planning" className="dashboard-more">
          Tout le planning
        </Link>
      </p>
    </section>
  );
}

/**
 * Les dernières fiches rendues que la personne peut lire.
 *
 * @param {object} props
 * @param {object[]} props.entries
 */
function RecentEntries({ entries }) {
  return (
    <section className="dashboard-section" aria-labelledby="dernieres-titre">
      <h2 id="dernieres-titre" className="section-title">
        Dernières fiches rendues
      </h2>
      {entries.length === 0 ? (
        <p className="page-empty">Aucune fiche rendue pour le moment.</p>
      ) : (
        <ul className="dashboard-list">
          {entries.map((entry) => (
            <li key={entry.id} className="dashboard-item">
              <div className="dashboard-item-body">
                <p className="dashboard-item-when">Séance du {dayTitle(entry.session.session_date)}</p>
                <p className="dashboard-item-title">
                  <Link to={`/fiches/${entry.id}`}>
                    {entry.session.project.name}
                    <span className="visually-hidden">, fiche du {dayTitle(entry.session.session_date)}</span>
                  </Link>
                </p>
                <p className="dashboard-item-meta">
                  {entry.session.sub_projects.map((group) => group.name).join(', ')}
                  {entry.author && ` · ${entry.author.name}`}
                  {entry.status === 'locked' && ' · verrouillée'}
                </p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
