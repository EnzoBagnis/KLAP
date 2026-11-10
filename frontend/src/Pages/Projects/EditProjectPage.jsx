import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import LocationSelect from '../../Components/Form/LocationSelect.jsx';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import BaseModal from '../../Modals/BaseModal.jsx';
import {
  archiveProject,
  getProject,
  listProjectTypes,
  restoreProject,
  updateProject,
} from '../../Services/ProjectService.js';
import { listContributors, listFunders, listLocations } from '../../Services/ReferenceService.js';
import { mapServerErrors, validateProject } from './NewProjectPage.jsx';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';

/*
 * Modifier, archiver et restaurer un projet (#154), administratrice. API : #152.
 * Mêmes champs et contrôles qu'à la création, sauf les sous-projets (lecture seule).
 * Seuls les champs modifiés partent en PATCH. Un projet n'est jamais effacé : archiver
 * demande le nom exact, et un projet archivé se restaure.
 */

const FIELDS = ['name', 'project_type_id', 'planned_sessions', 'start_date', 'end_date', 'default_location_id', 'principal_user_id'];
const NUMBERS = new Set(['project_type_id', 'planned_sessions', 'default_location_id', 'principal_user_id']);

/** Formulaire prérempli à partir du détail renvoyé par l'API. */
export function projectToForm(project) {
  const lead = project.team?.find((member) => member.member_role === 'lead');
  return {
    name: project.name ?? '',
    project_type_id: project.project_type ? String(project.project_type.id) : '',
    planned_sessions: project.planned_sessions ? String(project.planned_sessions) : '',
    start_date: project.start_date ?? '',
    end_date: project.end_date ?? '',
    default_location_id: project.default_location ? String(project.default_location.id) : '',
    principal_user_id: lead ? String(lead.id) : '',
    funder_ids: (project.funders ?? []).map((funder) => funder.id),
    // Champs de la création absents ici : vides, pour réutiliser validateProject tel quel.
    organization: '',
    city: '',
    postal_code: '',
    sub_projects: [],
  };
}

/** Champs modifiés seulement, au format de l'API : vide devient null, nombres convertis. */
export function toChanges(initial, form) {
  const changes = {};
  for (const key of FIELDS) {
    const value = key === 'name' ? form[key].trim() : String(form[key]);
    if (value === initial[key]) continue;
    if (value === '') changes[key] = null;
    else changes[key] = NUMBERS.has(key) ? Number(value) : value;
  }
  const sorted = (ids) => [...ids].map(Number).sort((a, b) => a - b);
  if (sorted(form.funder_ids).join() !== sorted(initial.funder_ids).join()) changes.funder_ids = sorted(form.funder_ids);
  return changes;
}

/** Ajoute à une liste l'élément actuel du projet s'il n'y figure plus (désactivé depuis). */
function withCurrent(list, current) {
  return current && !list.some((item) => item.id === current.id) ? [...list, current] : list;
}

export default function EditProjectPage() {
  const { id } = useParams();
  const { logout } = useAuth();
  const formRef = useRef(null);
  const [state, setState] = useState({ status: 'loading' });
  const [initial, setInitial] = useState(null);
  const [form, setForm] = useState(null);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [archiving, setArchiving] = useState({ open: false, name: '', error: '', sending: false });

  const lastRequest = useRef(0);

  // Message d'erreur à afficher, ou null après une 401 (déconnexion).
  const fail = useCallback(
    (error, fallback) => {
      if (error.status === 401) {
        logout();
        return null;
      }
      return error.status === 0 ? error.message : fallback;
    },
    [logout],
  );

  const showProject = useCallback((project, references) => {
    const lead = project.team?.find((member) => member.member_role === 'lead');
    setState({
      status: 'ready',
      project,
      references: {
        ...references,
        types: withCurrent(references.types, project.project_type),
        locations: withCurrent(references.locations, project.default_location),
        contributors: withCurrent(references.contributors, lead ? { id: lead.id, name: lead.name } : null),
      },
    });
    const values = projectToForm(project);
    setInitial(values);
    setForm(values);
    setErrors({});
  }, []);

  const load = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setState({ status: 'loading' });
    Promise.all([getProject(id), listProjectTypes(), listLocations(), listContributors(), listFunders()])
      .then(([project, types, locations, contributors, funders]) => {
        if (outdated()) return;
        // Financeurs actifs, plus ceux déjà rattachés au projet même désactivés (#249).
        const attached = new Set((project.funders ?? []).map((funder) => funder.id));
        showProject(project, { types: types.filter((type) => type.is_active), locations, contributors, funders: funders.filter((funder) => funder.is_active !== false || attached.has(funder.id)) });
      })
      .catch((error) => {
        if (outdated()) return;
        const text = fail(error, 'Le projet n’a pas pu être chargé. Réessayez dans un instant.');
        if (text !== null) setState({ status: error.status === 404 ? 'missing' : 'error', error: text });
      });
  }, [id, fail, showProject]);

  useEffect(() => {
    load();
    return () => {
      lastRequest.current += 1;
    };
  }, [load]);

  useEffect(() => {
    if (state.status === 'ready') document.title = `Modifier ${state.project.name} · KLAP`;
  }, [state]);

  const update = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }));
  const toggleFunder = (funderId) =>
    setForm((current) => ({
      ...current,
      funder_ids: current.funder_ids.includes(funderId)
        ? current.funder_ids.filter((other) => other !== funderId)
        : [...current.funder_ids, funderId],
    }));
  const addLocation = (place) =>
    setState((current) => ({
      ...current,
      references: {
        ...current.references,
        locations: [...current.references.locations, place].sort((a, b) => a.name.localeCompare(b.name, 'fr')),
      },
    }));

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    setMessage('');
    const localErrors = validateProject(form);
    setErrors(localErrors);
    if (Object.keys(localErrors).length) {
      formRef.current?.querySelector(`[name="${CSS.escape(Object.keys(localErrors)[0])}"]`)?.focus();
      return;
    }
    const changes = toChanges(initial, form);
    if (!Object.keys(changes).length) {
      setMessage('Aucune modification à enregistrer.');
      return;
    }
    setSubmitting(true);
    try {
      showProject(await updateProject(id, changes), state.references);
      setMessage('Modifications enregistrées.');
    } catch (error) {
      if (error.status === 422) {
        const { fields, others } = mapServerErrors(error.errors);
        setErrors(fields);
        setFormError(['Le projet n’a pas été modifié : corrigez les champs signalés.', ...others].join(' '));
      } else {
        const text = fail(error, 'Le projet n’a pas pu être modifié. Réessayez dans un instant.');
        if (text !== null) setFormError(text);
      }
    } finally {
      setSubmitting(false);
    }
  }

  const closeArchive = useCallback(() => setArchiving({ open: false, name: '', error: '', sending: false }), []);

  async function confirmArchive(event) {
    event.preventDefault();
    if (archiving.sending) return;
    setArchiving((current) => ({ ...current, sending: true, error: '' }));
    try {
      const project = await archiveProject(id, archiving.name);
      closeArchive();
      showProject(project, state.references);
      setMessage('Projet archivé. Ses séances, ses fiches et ses statistiques sont conservées.');
    } catch (error) {
      const text =
        error.status === 422
          ? error.errors?.confirm_name?.[0] ?? 'Le nom saisi ne correspond pas au nom du projet.'
          : fail(error, 'Le projet n’a pas pu être archivé. Réessayez dans un instant.');
      if (text !== null) setArchiving((current) => ({ ...current, sending: false, error: text }));
    }
  }

  async function handleRestore() {
    setMessage('');
    setFormError('');
    try {
      showProject(await restoreProject(id), state.references);
      setMessage('Projet restauré : il est de nouveau en cours.');
    } catch (error) {
      const text = fail(error, 'Le projet n’a pas pu être restauré. Réessayez dans un instant.');
      if (text !== null) setFormError(text);
    }
  }

  const project = state.project;
  const archived = project?.status === 'archived';

  return (
    <section className="page">
      <p className="page-eyebrow">Gestion</p>
      <h1 className="page-title">{project ? `Modifier « ${project.name} »` : 'Modifier un projet'}</h1>
      <p>
        <Link to={`/projets/${id}`} className="back-link">
          ← Retour au projet
        </Link>
      </p>

      {state.status === 'loading' && <LoadingSpinner label="Chargement du projet…" />}
      {state.status === 'missing' && (
        <div className="page-error" role="alert">
          <p>Ce projet n’existe pas.</p>
          <Link to="/gestion" className="btn btn-line">
            Retour à la gestion
          </Link>
        </div>
      )}
      {state.status === 'error' && (
        <div className="page-error" role="alert">
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}

      {message && (
        <p className="form-success" role="status">
          {message}
        </p>
      )}

      {state.status === 'ready' && archived && (
        <div className="project-archived">
          {formError && (
            <p className="form-alert" role="alert">
              {formError}
            </p>
          )}
          <p>
            Ce projet est archivé : il n’apparaît plus dans la liste et ses fiches ne se remplissent plus. Rien n’a été
            effacé, et ses statistiques restent comptées.
          </p>
          <Button onClick={handleRestore}>Restaurer le projet</Button>
        </div>
      )}

      {state.status === 'ready' && !archived && form && (
        <>
          <form ref={formRef} className="project-form" onSubmit={handleSubmit} noValidate>
            {formError && (
              <p className="form-alert" role="alert">
                {formError}
              </p>
            )}
            <fieldset className="form-column">
              <legend className="form-legend">Le projet</legend>
              <TextInput label="Nom du projet" name="name" required maxLength={150} value={form.name} onChange={update('name')} error={errors.name} />
              <SelectInput
                label="Type de projet"
                name="project_type_id"
                value={form.project_type_id}
                onChange={update('project_type_id')}
                error={errors.project_type_id}
                options={state.references.types.map((type) => ({ value: type.id, label: type.name }))}
              />
              <TextInput label="Séances prévues" name="planned_sessions" type="number" min={1} max={65535} value={form.planned_sessions} onChange={update('planned_sessions')} error={errors.planned_sessions} />
              <div className="form-row">
                <TextInput label="Début" name="start_date" type="date" value={form.start_date} onChange={update('start_date')} error={errors.start_date} />
                <TextInput label="Fin" name="end_date" type="date" value={form.end_date} onChange={update('end_date')} error={errors.end_date} />
              </div>
              <LocationSelect
                label="Lieu par défaut"
                name="default_location_id"
                required
                value={String(form.default_location_id)}
                onChange={(value) => setForm((current) => ({ ...current, default_location_id: String(value) }))}
                onCreated={addLocation}
                error={errors.default_location_id}
                locations={state.references.locations}
              />
            </fieldset>

            <fieldset className="form-column">
              <legend className="form-legend">L’équipe et le financement</legend>
              <SelectInput
                label="Intervenant·e principal·e"
                name="principal_user_id"
                required
                value={form.principal_user_id}
                onChange={update('principal_user_id')}
                error={errors.principal_user_id}
                options={state.references.contributors.map((person) => ({ value: person.id, label: person.name }))}
              />
              <p className="form-hint">L’ancien·ne principal·e reste dans l’équipe comme ponctuel·le et garde ses fiches.</p>

              <fieldset className="field">
                <legend className="field-label">Financeurs destinataires</legend>
                {state.references.funders.length === 0 && <p className="form-hint">Aucun financeur enregistré.</p>}
                {state.references.funders.map((funder) => (
                  <label key={funder.id} className="checkbox">
                    <input type="checkbox" name="funder_ids" checked={form.funder_ids.includes(funder.id)} onChange={() => toggleFunder(funder.id)} />
                    {funder.name}
                  </label>
                ))}
                {errors.funder_ids && <p className="field-error">{errors.funder_ids}</p>}
              </fieldset>

              <div className="field">
                <p className="field-label">Sous-projets</p>
                <p className="form-hint">Les sous-projets ne se modifient pas ici.</p>
                <ul className="project-edit-subprojects">
                  {(project.sub_projects ?? []).map((sub) => (
                    <li key={sub.id}>{sub.name}</li>
                  ))}
                </ul>
              </div>

              <div className="form-actions">
                <Link to={`/projets/${id}`} className="btn btn-line">
                  Annuler
                </Link>
                <Button type="submit" disabled={submitting}>
                  {submitting ? 'Enregistrement…' : 'Enregistrer'}
                </Button>
              </div>
            </fieldset>
          </form>

          <section className="project-archive-zone" aria-labelledby="archiver">
            <h2 id="archiver" className="section-title">
              Archiver le projet
            </h2>
            <p>Le projet quitte la liste et ses fiches ne se remplissent plus. Rien n’est effacé : il pourra être restauré.</p>
            <Button variant="line" onClick={() => setArchiving({ open: true, name: '', error: '', sending: false })}>
              Archiver…
            </Button>
          </section>
        </>
      )}

      <BaseModal
        open={archiving.open}
        title="Archiver le projet"
        onClose={closeArchive}
        actions={
          <>
            <Button variant="line" onClick={closeArchive}>
              Annuler
            </Button>
            <Button type="submit" form="archive-form" disabled={archiving.sending}>
              {archiving.sending ? 'Archivage…' : 'Archiver'}
            </Button>
          </>
        }
      >
        <form id="archive-form" onSubmit={confirmArchive} noValidate>
          <p>
            Pour confirmer, saisissez le nom du projet : <strong>{project?.name}</strong>
          </p>
          <TextInput
            label="Nom du projet"
            name="confirm_name"
            value={archiving.name}
            onChange={(event) => setArchiving((current) => ({ ...current, name: event.target.value }))}
            error={archiving.error}
            autoComplete="off"
          />
        </form>
      </BaseModal>
    </section>
  );
}
