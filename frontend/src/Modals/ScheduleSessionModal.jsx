import { useCallback, useEffect, useRef, useState } from 'react';
import SelectInput from '../Components/Form/SelectInput.jsx';
import TextInput from '../Components/Form/TextInput.jsx';
import Button from '../Components/UI/Button.jsx';
import LoadingSpinner from '../Components/UI/LoadingSpinner.jsx';
import useAuth from '../Hooks/useAuth.js';
import { SESSION_TYPES } from '../Pages/Planning/sessionLabels.js';
import { getProject, listProjects } from '../Services/ProjectService.js';
import LocationSelect from '../Components/Form/LocationSelect.jsx';
import { listLocations } from '../Services/ReferenceService.js';
import { createSession } from '../Services/SessionService.js';
import BaseModal, { focusFirst } from './BaseModal.jsx';
import '../assets/css/FormStyles.css';

const EMPTY = {
  project_id: '',
  sub_project_ids: [],
  session_date: '',
  start_time: '',
  end_time: '',
  session_type: '',
  session_type_detail: '',
  location_id: '',
  referent_id: '',
};

/**
 * Contrôles avant l'envoi ; l'API reste juge (mêmes clés que ses erreurs 422).
 * Tant que le projet choisi n'est pas chargé, groupes et intervenant·e ne sont pas
 * affichés : l'erreur va alors sur le champ Projet, pour rester visible.
 */
export function validateSession(form, team, projectStatus = 'ready') {
  const errors = {};
  if (!form.project_id) errors.project_id = 'Choisissez le projet.';
  else if (projectStatus !== 'ready') errors.project_id = 'Le projet n’est pas encore chargé.';
  if (form.project_id && projectStatus === 'ready' && form.sub_project_ids.length === 0) {
    errors.sub_project_ids = 'Choisissez au moins un groupe.';
  }
  if (!form.session_date) errors.session_date = 'Choisissez la date.';
  if (!form.start_time) errors.start_time = 'Indiquez l’heure de début.';
  if (!form.end_time) errors.end_time = 'Indiquez l’heure de fin.';
  else if (form.start_time && form.end_time <= form.start_time) errors.end_time = 'La fin de la séance doit être après son début.';
  if (!form.session_type) errors.session_type = 'Choisissez le type de séance.';
  if (form.session_type === 'other' && !form.session_type_detail.trim()) {
    errors.session_type_detail = 'Précisez le type de séance.';
  }
  if (!form.location_id) errors.location_id = 'Choisissez le lieu.';
  if (form.project_id && projectStatus === 'ready') {
    if (team.length === 0) errors.referent_id = 'Aucun intervenant·e affecté·e à ce projet.';
    else if (!form.referent_id) errors.referent_id = 'Choisissez l’intervenant·e.';
  }
  return errors;
}

export function toSessionPayload(form) {
  const payload = {
    session_date: form.session_date,
    start_time: form.start_time,
    end_time: form.end_time,
    session_type: form.session_type,
    location_id: Number(form.location_id),
    referent_id: Number(form.referent_id),
    sub_project_ids: form.sub_project_ids.map(Number),
  };
  if (form.session_type === 'other') payload.session_type_detail = form.session_type_detail.trim();
  return payload;
}

const FIELDS = new Set([
  'project_id', 'sub_project_ids', 'session_date', 'start_time', 'end_time',
  'session_type', 'session_type_detail', 'location_id', 'referent_id',
]);

/** Erreurs 422 : `sub_project_ids.N` vont sous les groupes, les clés inconnues (dont `project`) en message général. */
export function mapSessionErrors(errors) {
  const fields = {};
  const others = [];
  for (const [key, messages] of Object.entries(errors ?? {})) {
    const target = key.startsWith('sub_project_ids.') ? 'sub_project_ids' : key;
    if (FIELDS.has(target)) fields[target] ??= messages[0];
    else others.push(messages[0]);
  }
  return { fields, others };
}

/** Modale « Programmer une séance » (maquette, écran 52), réservée à l'administratrice. */
export default function ScheduleSessionModal({ open, onClose, onCreated }) {
  const { logout } = useAuth();
  const formRef = useRef(null);
  const [references, setReferences] = useState({ status: 'loading' });
  const [project, setProject] = useState({ status: 'none', groups: [], team: [] });
  const [form, setForm] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const projectRequest = useRef(0);
  const referencesRequest = useRef(0);
  // Numéro d'ouverture : une réponse d'un envoi lancé avant la fermeture est ignorée.
  const openCount = useRef(0);

  const loadReferences = useCallback(() => {
    const request = ++referencesRequest.current;
    setReferences({ status: 'loading' });
    Promise.all([listProjects(), listLocations()])
      .then(([projects, locations]) => {
        if (request !== referencesRequest.current) return;
        setReferences({ status: 'ready', projects: projects.filter((p) => p.status !== 'archived'), locations });
      })
      .catch((error) => {
        if (request !== referencesRequest.current) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setReferences({ status: 'error', error: error.status === 0 ? error.message : 'Les listes n’ont pas pu être chargées.' });
      });
  }, [logout]);

  useEffect(() => {
    if (!open) return;
    openCount.current += 1;
    projectRequest.current += 1;
    setForm(EMPTY);
    setErrors({});
    setFormError('');
    setSubmitting(false);
    setProject({ status: 'none', groups: [], team: [] });
    loadReferences();
  }, [open, loadReferences]);

  // Le formulaire arrive après le chargement des listes : on y place le focus.
  useEffect(() => {
    if (open && references.status === 'ready') focusFirst(formRef.current);
  }, [open, references.status]);

  function loadProject(projectId) {
    const request = ++projectRequest.current;
    setProject({ status: 'loading', groups: [], team: [] });
    getProject(projectId)
      .then((detail) => {
        if (request !== projectRequest.current) return;
        const groups = (detail.sub_projects ?? []).filter((group) => group.is_active !== false);
        const team = detail.team ?? [];
        setProject({ status: 'ready', groups, team });
        // Un seul groupe : il est forcément concerné.
        if (groups.length === 1) setForm((current) => ({ ...current, sub_project_ids: [groups[0].id] }));
        const lead = team.find((member) => member.member_role === 'lead');
        if (lead) setForm((current) => ({ ...current, referent_id: String(lead.id) }));
      })
      .catch((error) => {
        if (request !== projectRequest.current) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setProject({ status: 'error', groups: [], team: [] });
      });
  }

  function chooseProject(event) {
    const projectId = event.target.value;
    // Changer de projet vide les groupes et l'intervenant·e, qui en dépendent.
    setForm((current) => ({ ...current, project_id: projectId, sub_project_ids: [], referent_id: '' }));
    if (!projectId) {
      projectRequest.current += 1;
      setProject({ status: 'none', groups: [], team: [] });
      return;
    }
    loadProject(projectId);
  }

  // Lieu créé depuis le menu (#135) : ajouté à la liste, rangée par nom.
  const addLocation = (place) =>
    setReferences((current) => ({
      ...current,
      locations: [...current.locations, place].sort((a, b) => a.name.localeCompare(b.name, 'fr')),
    }));

  const update = (key) => (event) => {
    const value = event.target.value;
    setForm((current) => ({
      ...current,
      [key]: value,
      // La précision ne vaut que pour « autre » : on la vide si le type change.
      ...(key === 'session_type' && value !== 'other' ? { session_type_detail: '' } : {}),
    }));
  };

  function toggleGroup(id) {
    setForm((current) => ({
      ...current,
      sub_project_ids: current.sub_project_ids.includes(id)
        ? current.sub_project_ids.filter((groupId) => groupId !== id)
        : [...current.sub_project_ids, id],
    }));
  }

  function focusFirstError(errorMap) {
    const key = Object.keys(errorMap)[0];
    if (key) formRef.current?.querySelector(`[name="${CSS.escape(key)}"]`)?.focus();
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    const localErrors = validateSession(form, project.team, project.status);
    setErrors(localErrors);
    if (Object.keys(localErrors).length) {
      focusFirstError(localErrors);
      return;
    }

    setSubmitting(true);
    const opening = openCount.current;
    try {
      const created = await createSession(form.project_id, toSessionPayload(form));
      if (opening === openCount.current) onCreated(created);
    } catch (error) {
      if (opening !== openCount.current) return;
      setSubmitting(false);
      if (error.status === 401) {
        logout();
        return;
      }
      if (error.status === 422) {
        const { fields, others } = mapSessionErrors(error.errors);
        setErrors(fields);
        setFormError(['La séance n’a pas été programmée.', ...others].join(' '));
        focusFirstError(fields);
      } else {
        setFormError(error.status === 0 ? error.message : 'La séance n’a pas pu être programmée. Réessayez dans un instant.');
      }
    }
  }

  return (
    // Pendant l'envoi, la fenêtre ne se ferme pas : la séance pourrait être créée sans qu'on le sache.
    <BaseModal open={open} title="Programmer une séance" onClose={onClose} closable={!submitting}>
      <p>La fiche de la séance reprendra ces informations.</p>

      {references.status === 'loading' && <LoadingSpinner label="Préparation du formulaire…" />}

      {references.status === 'error' && (
        <div className="page-error" role="alert">
          <p>{references.error}</p>
          <Button variant="line" onClick={loadReferences}>
            Réessayer
          </Button>
        </div>
      )}

      {references.status === 'ready' && (
        <form ref={formRef} className="session-form" onSubmit={handleSubmit} noValidate>
          {formError && (
            <p className="form-alert" role="alert">
              {formError}
            </p>
          )}

          <SelectInput
            label="Projet"
            name="project_id"
            required
            value={form.project_id}
            onChange={chooseProject}
            error={errors.project_id}
            options={references.projects.map((p) => ({ value: p.id, label: p.name }))}
          />

          {project.status === 'loading' && <LoadingSpinner label="Chargement du projet…" />}
          {project.status === 'error' && (
            <div className="page-error" role="alert">
              <p className="field-error">Le projet n’a pas pu être chargé.</p>
              <Button variant="line" onClick={() => loadProject(form.project_id)}>
                Réessayer
              </Button>
            </div>
          )}

          {project.status === 'ready' && (
            <>
              <fieldset className="field" aria-describedby={errors.sub_project_ids ? 'groupes-erreur' : undefined}>
                <legend className="field-label">
                  Groupes concernés<span className="field-required" aria-hidden="true"> *</span>
                  <span className="visually-hidden"> (au moins un, obligatoire)</span>
                </legend>
                {project.groups.map((group) => (
                  <label key={group.id} className="checkbox">
                    <input
                      type="checkbox"
                      name="sub_project_ids"
                      aria-invalid={errors.sub_project_ids ? true : undefined}
                      aria-describedby={errors.sub_project_ids ? 'groupes-erreur' : undefined}
                      checked={form.sub_project_ids.includes(group.id)}
                      onChange={() => toggleGroup(group.id)}
                    />
                    {group.name}
                  </label>
                ))}
                {errors.sub_project_ids && (
                  <p id="groupes-erreur" className="field-error">
                    {errors.sub_project_ids}
                  </p>
                )}
              </fieldset>

              <SelectInput
                label="Intervenant·e"
                name="referent_id"
                required
                value={form.referent_id}
                onChange={update('referent_id')}
                error={errors.referent_id}
                options={project.team.map((member) => ({ value: member.id, label: member.name }))}
              />
            </>
          )}

          <TextInput label="Date" name="session_date" type="date" required value={form.session_date} onChange={update('session_date')} error={errors.session_date} />
          <div className="form-row">
            <TextInput label="Début" name="start_time" type="time" required value={form.start_time} onChange={update('start_time')} error={errors.start_time} />
            <TextInput label="Fin" name="end_time" type="time" required value={form.end_time} onChange={update('end_time')} error={errors.end_time} />
          </div>
          <SelectInput
            label="Type de séance"
            name="session_type"
            required
            value={form.session_type}
            onChange={update('session_type')}
            error={errors.session_type}
            options={SESSION_TYPES.map(([value, label]) => ({ value, label }))}
          />
          {form.session_type === 'other' && (
            <TextInput
              label="Précision"
              name="session_type_detail"
              required
              maxLength={100}
              value={form.session_type_detail}
              onChange={update('session_type_detail')}
              error={errors.session_type_detail}
            />
          )}
          <LocationSelect
            label="Lieu"
            name="location_id"
            required
            value={String(form.location_id)}
            onChange={(value) => setForm((current) => ({ ...current, location_id: value }))}
            onCreated={addLocation}
            error={errors.location_id}
            locations={references.locations}
          />

          <div className="modal-actions">
            <Button variant="ghost" onClick={onClose} disabled={submitting}>
              Annuler
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Programmation…' : 'Programmer la séance'}
            </Button>
          </div>
        </form>
      )}
    </BaseModal>
  );
}
