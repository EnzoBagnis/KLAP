import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import { ROLES } from '../../Context/roles.js';
import useAuth from '../../Hooks/useAuth.js';
import ScheduleSessionModal from '../../Modals/ScheduleSessionModal.jsx';
import { cancelSession, listSessions, restoreSession } from '../../Services/SessionService.js';
import {
  SESSION_STATUS_LABELS,
  currentMonth,
  dayTitle,
  monthBounds,
  monthTitle,
  parseMonth,
  sessionTypeLabel,
  shiftMonth,
  todayKey,
  entryDeadline,
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
  // Séance en cours de modification (#180) ; null pour programmer une nouvelle séance.
  const [editing, setEditing] = useState(null);
  const [actionError, setActionError] = useState('');
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
    setEditing(null);
    const sessionMonth = session.session_date.slice(0, 7);
    if (sessionMonth === monthKey) load();
    else goTo(sessionMonth);
  }

  const closeModal = useCallback(() => {
    setModalOpen(false);
    setEditing(null);
  }, []);
  const openEdit = (session) => {
    setActionError('');
    setEditing(session);
    setModalOpen(true);
  };

  // Annuler ou rétablir une séance (#180), après confirmation ; le planning est relu ensuite.
  async function changeStatus(session, action) {
    const question =
      action === 'cancel'
        ? `Annuler la séance du ${dayTitle(session.session_date).toLowerCase()} (${session.project.name}) ? Elle restera visible, marquée « annulée ».`
        : `Rétablir la séance du ${dayTitle(session.session_date).toLowerCase()} (${session.project.name}) ?`;
    if (!window.confirm(question)) return;
    setActionError('');
    try {
      await (action === 'cancel' ? cancelSession(session.id) : restoreSession(session.id));
      load();
    } catch (error) {
      if (error.status === 401) {
        logout();
        return;
      }
      setActionError(
        error.status === 422
          ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message
          : [0, 409].includes(error.status)
            ? error.message
            : 'La séance n’a pas pu être modifiée. Réessayez dans un instant.',
      );
    }
  }
  // La fiche se remplit à partir du jour de la séance (l'API le vérifie aussi).
  const today = todayKey();
  // Droits emboîtés (#141) : l'administration ouvre et modifie la fiche de n'importe quelle séance.
  const canManageEntries = user?.role === ROLES.ADMIN || user?.role === ROLES.SUPER_USER;

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

        {actionError && (
          <p className="form-alert" role="alert">
            {actionError}
          </p>
        )}

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
                        <EntryLink session={session} label={dayTitle(date).toLowerCase()} userId={user?.id} manages={canManageEntries} today={today} />
                        {user?.role === ROLES.ADMIN && (
                          <SessionActions session={session} label={dayTitle(date).toLowerCase()} onEdit={openEdit} onStatus={changeStatus} />
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              </li>
            ))}
          </ol>
        )}
      </div>

      <ScheduleSessionModal open={modalOpen} onClose={closeModal} onCreated={handleCreated} session={editing} />
    </section>
  );
}

/*
 * Actions de l'administratrice sur une séance (#180) : une séance sans fiche se modifie ou
 * s'annule ; une séance annulée se rétablit. Une séance qui a une fiche ne bouge plus.
 */
function SessionActions({ session, label, onEdit, onStatus }) {
  // Jour, horaire et projet : deux séances du même jour ont des boutons distincts au lecteur d'écran.
  const hidden = <span className="visually-hidden"> du {label}, {session.start_time}, {session.project.name}</span>;
  // Projet archivé : ses séances ne bougent plus (l'API refuserait).
  if (session.project.status === 'archived') return null;
  if (session.status === 'cancelled') {
    return (
      <div className="planning-actions">
        <button type="button" className="planning-entry-link planning-entry-button" onClick={() => onStatus(session, 'restore')}>
          Rétablir{hidden}
        </button>
      </div>
    );
  }
  if (session.logbook_entry) return null;
  return (
    <div className="planning-actions">
      <button type="button" className="planning-entry-link planning-entry-button" onClick={() => onEdit(session)}>
        Modifier{hidden}
      </button>
      <button type="button" className="planning-entry-link planning-entry-button" onClick={() => onStatus(session, 'cancel')}>
        Annuler la séance{hidden}
      </button>
    </div>
  );
}

/*
 * Lien vers la fiche d'une séance (#141) :
 * - le ou la référent·e la remplit (création au besoin) ;
 * - une fiche existante se consulte : collègues du projet, administration ;
 * - l'administration peut créer la fiche pour le ou la référent·e, après confirmation.
 */
function EntryLink({ session, label, userId, manages, today }) {
  const navigate = useNavigate();
  const fillable = session.status !== 'cancelled' && session.session_date <= today;
  // Après le délai de 10 jours, l'API refuse d'ouvrir une fiche (#212) : on ne la propose plus.
  // Un brouillon déjà ouvert reste accessible ; il s'affiche alors verrouillé.
  const openable = fillable && today <= entryDeadline(session.session_date);
  const hidden = <span className="visually-hidden"> du {label}</span>;

  // Référent·e : remplir, reprendre un brouillon ; une fiche rendue se consulte (#163).
  const rendered = session.logbook_entry && session.logbook_entry.status !== 'draft';
  if (session.referent.id === userId && !rendered && (session.logbook_entry ? fillable : openable)) {
    return (
      <Link to={`/planning/seances/${session.id}/fiche`} className="planning-entry-link">
        {session.logbook_entry ? 'Reprendre la fiche' : 'Remplir la fiche'}
        {hidden}
      </Link>
    );
  }
  if (session.logbook_entry) {
    return (
      <Link to={`/fiches/${session.logbook_entry.id}`} className="planning-entry-link">
        Consulter la fiche{hidden}
      </Link>
    );
  }
  if (manages && openable) {
    const create = () => {
      if (window.confirm(`Créer la fiche de cette séance pour ${session.referent.name} ?`)) {
        navigate(`/planning/seances/${session.id}/fiche`);
      }
    };
    return (
      <button type="button" className="planning-entry-link planning-entry-button" onClick={create}>
        Créer la fiche{hidden}
      </button>
    );
  }
  return null;
}
