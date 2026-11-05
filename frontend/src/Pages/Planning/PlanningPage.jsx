import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import { ROLES } from '../../Context/roles.js';
import useAuth from '../../Hooks/useAuth.js';
import ScheduleSessionModal from '../../Modals/ScheduleSessionModal.jsx';
import { listSessions } from '../../Services/SessionService.js';
import {
  SESSION_STATUS_LABELS,
  currentMonth,
  dayTitle,
  monthBounds,
  monthTitle,
  parseMonth,
  sessionTypeLabel,
  shiftMonth,
} from './sessionLabels.js';
import '../../assets/css/ProjectStyles.css';
import '../../assets/css/PlanningStyles.css';

/** Séances groupées par jour, dans l'ordre renvoyé par l'API (date puis heure). */
function groupByDay(sessions) {
  const days = new Map();
  for (const session of sessions) {
    if (!days.has(session.session_date)) days.set(session.session_date, []);
    days.get(session.session_date).push(session);
  }
  return [...days.entries()];
}

/*
 * Planning (maquette, écrans 51 et 57) : séances d'un mois, groupées par jour.
 * Le mois est dans l'URL (?mois=2026-10) pour survivre au rechargement.
 * L'API filtre selon le rôle ; seule l'administratrice voit « Programmer une séance ».
 */
export default function PlanningPage() {
  const { user, logout } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const monthKey = parseMonth(searchParams.get('mois')) ? searchParams.get('mois') : currentMonth();
  const month = parseMonth(monthKey);
  const [state, setState] = useState({ status: 'loading', sessions: [], error: '' });
  const [modalOpen, setModalOpen] = useState(false);
  const lastRequest = useRef(0);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    const { from, to } = monthBounds(parseMonth(monthKey));
    setState({ status: 'loading', sessions: [], error: '' });
    listSessions(from, to)
      .then((sessions) => !outdated() && setState({ status: 'ready', sessions, error: '' }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setState({
          status: 'error',
          sessions: [],
          error: error.status === 0 ? error.message : 'Le planning n’a pas pu être chargé. Réessayez dans un instant.',
        });
      });
  }, [monthKey, logout]);

  useEffect(() => {
    document.title = 'Planning · KLAP';
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  const goTo = (key) => setSearchParams({ mois: key });

  function handleCreated(session) {
    setModalOpen(false);
    const sessionMonth = session.session_date.slice(0, 7);
    if (sessionMonth === monthKey) load();
    else goTo(sessionMonth);
  }

  const closeModal = useCallback(() => setModalOpen(false), []);

  return (
    <section className="page">
      <div inert={modalOpen}>
        <div className="page-heading">
          <div>
            <h1 className="page-title">Planning</h1>
            <p className="page-subtitle" aria-live="polite">
              {monthTitle(month)}
            </p>
          </div>
          {user?.role === ROLES.ADMIN && (
            <Button onClick={() => setModalOpen(true)}>
              <span aria-hidden="true">+&nbsp;</span>Programmer une séance
            </Button>
          )}
        </div>

        <nav className="month-nav" aria-label="Changer de mois">
          <Button variant="line" onClick={() => goTo(shiftMonth(month, -1))}>
            <span aria-hidden="true">←&nbsp;</span>Mois précédent
          </Button>
          <Button variant="line" onClick={() => goTo(shiftMonth(month, 1))}>
            Mois suivant<span aria-hidden="true">&nbsp;→</span>
          </Button>
        </nav>

        {state.status === 'loading' && <LoadingSpinner label="Chargement du planning…" />}

        {state.status === 'error' && (
          <div className="page-error" role="alert">
            <p>{state.error}</p>
            <Button variant="line" onClick={load}>
              Réessayer
            </Button>
          </div>
        )}

        {state.status === 'ready' && state.sessions.length === 0 && (
          <p className="page-empty">Aucune séance programmée en {monthTitle(month).toLowerCase()}.</p>
        )}

        {state.status === 'ready' && state.sessions.length > 0 && (
          <ol className="planning-days">
            {groupByDay(state.sessions).map(([date, sessions]) => (
              <li key={date} className="planning-day">
                <h2 className="planning-day-title">{dayTitle(date)}</h2>
                <ul className="planning-sessions">
                  {sessions.map((session) => (
                    <li key={session.id} className={`planning-session status-${session.status}`}>
                      <p className="planning-time">
                        {session.start_time} – {session.end_time}
                      </p>
                      <div className="planning-body">
                        <p className="planning-project">
                          <Link to={`/projets/${session.project.id}`}>{session.project.name}</Link>
                          {session.status !== 'planned' && (
                            <span className="tag">{SESSION_STATUS_LABELS[session.status] ?? session.status}</span>
                          )}
                        </p>
                        <p className="planning-meta">
                          {sessionTypeLabel(session)} · {session.sub_projects.map((group) => group.name).join(', ')}
                        </p>
                        <p className="planning-meta">
                          {session.location.name} · {session.referent.name}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              </li>
            ))}
          </ol>
        )}
      </div>

      <ScheduleSessionModal open={modalOpen} onClose={closeModal} onCreated={handleCreated} />
    </section>
  );
}
