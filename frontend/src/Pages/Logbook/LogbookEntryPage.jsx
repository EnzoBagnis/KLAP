import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import { LogbookProvider } from '../../Context/LogbookContext.jsx';
import useAuth from '../../Hooks/useAuth.js';
import useLogbook from '../../Hooks/useLogbook.js';
import { getEntry } from '../../Services/LogbookService.js';
import { dayTitle } from '../Planning/sessionLabels.js';
import { STEPS, formatDate, formatInstant, stepsWithErrors } from './logbookModel.js';
import ArtistFeedbackStep from './Steps/ArtistFeedbackStep.jsx';
import GeneralStep from './Steps/GeneralStep.jsx';
import NextStepsStep from './Steps/NextStepsStep.jsx';
import ObjectivesStep from './Steps/ObjectivesStep.jsx';
import ParticipantsStep from './Steps/ParticipantsStep.jsx';
import SessionStep from './Steps/SessionStep.jsx';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';
import '../../assets/css/LogbookStyles.css';

const STEP_COMPONENTS = {
  1: GeneralStep,
  2: ObjectivesStep,
  3: SessionStep,
  4: ParticipantsStep,
  5: ArtistFeedbackStep,
  6: NextStepsStep,
};

const LEAVE_WARNING = 'Des modifications ne sont pas enregistrées. Quitter la fiche quand même ?';

const STATUS_LABELS = { draft: 'Brouillon', submitted: 'Soumise', locked: 'Verrouillée' };

/** Fiche de séance (maquette 09 à 14) : chargement, puis formulaire en six étapes. */
export default function LogbookEntryPage() {
  const { id } = useParams();
  const { logout } = useAuth();
  const [state, setState] = useState({ status: 'loading', entry: null, error: '' });
  const lastRequest = useRef(0);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading', entry: null, error: '' });
    getEntry(id)
      .then((entry) => !outdated() && setState({ status: 'ready', entry, error: '' }))
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setState({
          status: error.status === 404 ? 'missing' : 'error',
          entry: null,
          error: error.status === 0 ? error.message : 'La fiche n’a pas pu être chargée. Réessayez dans un instant.',
        });
      });
  }, [id, logout]);

  useEffect(() => {
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  return (
    <section className="page logbook-page">
      {state.status === 'loading' && <LoadingSpinner label="Chargement de la fiche…" />}
      {state.status === 'missing' && (
        <div className="page-error" role="alert">
          <h1 className="page-title">Fiche introuvable</h1>
          <p>Cette fiche n’existe pas ou ne vous est pas accessible.</p>
          <Link to="/planning" className="btn btn-line">
            Retour au planning
          </Link>
        </div>
      )}
      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <h1 className="visually-hidden">Fiche de séance</h1>
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}
      {state.status === 'ready' && (
        <LogbookProvider key={state.entry.id} initialEntry={state.entry}>
          <LogbookForm />
        </LogbookProvider>
      )}
    </section>
  );
}

function LogbookForm() {
  const { entry, errors, message, busy, dirty, readOnlyReason, save, submit, unlock } = useLogbook();
  const [searchParams, setSearchParams] = useSearchParams();
  const requested = Number(searchParams.get('etape'));
  const step = STEPS.some((s) => s.number === requested) ? requested : 1;
  const errorSteps = stepsWithErrors(errors);
  const headingRef = useRef(null);
  const Step = STEP_COMPONENTS[step];
  const navigate = useNavigate();
  const unsaved = dirty && !readOnlyReason;
  const planningPath = `/planning?mois=${entry.session.session_date.slice(0, 7)}`;

  useEffect(() => {
    document.title = `Fiche du ${dayTitle(entry.session.session_date).toLowerCase()} · KLAP`;
  }, [entry.session.session_date]);

  // Saisie non enregistrée : on prévient avant de quitter (rechargement, menu, liens).
  useEffect(() => {
    if (!unsaved) return undefined;
    const onBeforeUnload = (event) => event.preventDefault();
    const onClick = (event) => {
      const link = event.target.closest?.('a[href]');
      if (!link || link.dataset.saveBeforeLeave !== undefined) return;
      if (!window.confirm(LEAVE_WARNING)) event.preventDefault();
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('click', onClick, true);
    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload);
      document.removeEventListener('click', onClick, true);
    };
  }, [unsaved]);

  // « Retour au planning » enregistre d'abord ; en cas d'échec, on reste sur la fiche.
  async function leave(event) {
    if (!unsaved) return;
    event.preventDefault();
    if (await save()) navigate(planningPath);
  }

  async function goTo(target) {
    if (target === step) return;
    // Brouillon : enregistré à chaque changement d'étape. Une fiche soumise s'enregistre
    // d'un bloc (elle doit rester complète), avec le bouton prévu.
    if (entry.status === 'draft' && dirty && !readOnlyReason && !(await save())) return;
    setSearchParams({ etape: String(target) });
    headingRef.current?.focus();
  }

  function handleUnlock() {
    if (window.confirm('Rouvrir cette fiche ? Son auteur·rice pourra la modifier pendant 10 jours.')) unlock();
  }

  // Après un échec (enregistrement ou soumission), on se place sur la première étape en erreur.
  useEffect(() => {
    if (message?.tone === 'error' && message.steps?.length && !message.steps.includes(step)) {
      setSearchParams({ etape: String(message.steps[0]) });
      headingRef.current?.focus();
    }
    // Uniquement quand un nouveau message arrive.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [message]);

  return (
    <>
      <div className="page-heading">
        <div>
          <p className="page-eyebrow">Fiche de séance · {STATUS_LABELS[entry.status] ?? entry.status}</p>
          <h1 className="page-title">{entry.session.project.name}</h1>
        </div>
        <Link to={planningPath} className="btn btn-line" onClick={leave} data-save-before-leave="">
          Retour au planning
        </Link>
      </div>

      {readOnlyReason && <p className="form-alert">{readOnlyReason}</p>}
      {!readOnlyReason && entry.edit_deadline && (
        <p className="form-hint">Modifiable jusqu’au {formatDate(entry.edit_deadline)} inclus.</p>
      )}
      {entry.unlocked_at && entry.unlocked_by && (
        <p className="form-hint">
          Rouverte le {formatInstant(entry.unlocked_at)} par {entry.unlocked_by.name}.
        </p>
      )}
      {entry.unlockable && (
        <div className="logbook-unlock">
          <Button variant="line" onClick={handleUnlock} disabled={busy}>
            Déverrouiller la fiche
          </Button>
        </div>
      )}

      <nav className="stepper" aria-label="Étapes de la fiche">
        <ol>
          {STEPS.map((s) => (
            <li key={s.number}>
              <button
                type="button"
                className={`step-pill${s.number === step ? ' is-current' : ''}${errorSteps.includes(s.number) ? ' has-error' : ''}`}
                aria-current={s.number === step ? 'step' : undefined}
                onClick={() => goTo(s.number)}
                disabled={busy}
              >
                <span className="step-number">{s.number}</span>
                <span className="step-title">{s.title}</span>
                {errorSteps.includes(s.number) && <span className="visually-hidden"> (à corriger)</span>}
              </button>
            </li>
          ))}
        </ol>
      </nav>

      {message && (
        <p className={message.tone === 'success' ? 'form-success' : 'form-alert'} role={message.tone === 'error' ? 'alert' : 'status'}>
          {message.text}
          {message.steps?.length > 0 &&
            ` Étapes concernées : ${message.steps.map((n) => STEPS[n - 1].title).join(', ')}.`}
        </p>
      )}

      <h2 ref={headingRef} tabIndex={-1} className="section-title">
        {step}. {STEPS[step - 1].title}
      </h2>
      <fieldset className="logbook-step" disabled={Boolean(readOnlyReason) || busy}>
        <Step />
      </fieldset>

      <div className="logbook-actions">
        <div className="logbook-nav">
          <Button variant="line" onClick={() => goTo(step - 1)} disabled={step === 1 || busy}>
            Étape précédente
          </Button>
          <Button variant="line" onClick={() => goTo(step + 1)} disabled={step === STEPS.length || busy}>
            Étape suivante
          </Button>
        </div>
        {!readOnlyReason && (
          <div className="logbook-save">
            {entry.status === 'draft' ? (
              <>
                <Button variant="ghost" onClick={() => save()} disabled={busy || !dirty}>
                  {dirty ? 'Enregistrer le brouillon' : 'Brouillon enregistré'}
                </Button>
                {step === STEPS.length && (
                  <Button onClick={submit} disabled={busy}>
                    {busy ? 'Envoi…' : 'Soumettre la fiche'}
                  </Button>
                )}
              </>
            ) : (
              <Button onClick={() => save()} disabled={busy || !dirty}>
                {dirty ? 'Enregistrer les modifications' : 'Modifications enregistrées'}
              </Button>
            )}
          </div>
        )}
      </div>
    </>
  );
}
