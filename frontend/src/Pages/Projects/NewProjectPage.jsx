import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import BaseModal from '../../Modals/BaseModal.jsx';
import { createProject, listProjectTypes } from '../../Services/ProjectService.js';
import LocationSelect from '../../Components/Form/LocationSelect.jsx';
import { listContributors, listFunders, listLocations } from '../../Services/ReferenceService.js';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';

const POSTAL_CODE = /^[0-9]{5}$/;

const EMPTY_FORM = {
  name: '',
  project_type_id: '',
  organization: '',
  city: '',
  postal_code: '',
  planned_sessions: '',
  start_date: '',
  end_date: '',
  default_location_id: '',
  principal_user_id: '',
  funder_ids: [],
  // Projet non découpé (#244) : âge et effectif du groupe unique.
  age_range: '',
  expected_headcount: '',
  sub_projects: [],
};

const EMPTY_SUB_PROJECT = { name: '', age_range: '', expected_headcount: '' };

/** Effectif prévu : vide, ou un entier de 1 à 65535. Message, ou null s'il est valable. */
function headcountError(value = '') {
  if (value === '') return null;
  const number = Number(value);
  return Number.isInteger(number) && number >= 1 && number <= 65535 ? null : 'Indiquez un nombre entier d’élèves, entre 1 et 65535.';
}
const AGE_RANGE_ERROR = 'La tranche d’âge ne doit pas dépasser 30 caractères.';

/*
 * Contrôles faits avant l'envoi. Ils reprennent ceux de l'API (qui reste juge) pour
 * répondre tout de suite. Les clés sont celles des erreurs de l'API, pour qu'une erreur
 * locale et une erreur 422 s'affichent au même endroit.
 */
export function validateProject(form) {
  const errors = {};
  if (!form.name.trim()) errors.name = 'Donnez un nom au projet.';
  else if (form.name.trim().length > 150) errors.name = 'Le nom ne doit pas dépasser 150 caractères.';
  if (!form.default_location_id) errors.default_location_id = 'Choisissez le lieu par défaut.';
  if (!form.principal_user_id) errors.principal_user_id = 'Choisissez l’intervenant·e principal·e.';
  if (form.organization.trim().length > 150) errors.organization = 'La structure ne doit pas dépasser 150 caractères.';
  if (form.city.trim().length > 100) errors.city = 'La ville ne doit pas dépasser 100 caractères.';
  if (form.postal_code.trim() && !POSTAL_CODE.test(form.postal_code.trim())) {
    errors.postal_code = 'Le code postal doit contenir 5 chiffres.';
  }
  const sessions = Number(form.planned_sessions);
  if (form.planned_sessions !== '' && !(Number.isInteger(sessions) && sessions >= 1 && sessions <= 65535)) {
    errors.planned_sessions = 'Indiquez un nombre entier de séances, entre 1 et 65535.';
  }
  if (form.start_date && form.end_date && form.end_date < form.start_date) {
    errors.end_date = 'La fin doit être après le début.';
  }
  const seen = new Set();
  form.sub_projects.forEach((sub, index) => {
    const key = `sub_projects.${index}.name`;
    const name = sub.name.trim();
    if (!name) errors[key] = 'Nommez ce sous-projet ou retirez la ligne.';
    else if (seen.has(name.toLowerCase())) errors[key] = 'Deux sous-projets portent le même nom.';
    seen.add(name.toLowerCase());
    if ((sub.age_range ?? '').trim().length > 30) errors[`sub_projects.${index}.age_range`] = AGE_RANGE_ERROR;
    const headcount = headcountError(sub.expected_headcount);
    if (headcount) errors[`sub_projects.${index}.expected_headcount`] = headcount;
  });
  if (!form.sub_projects.length) {
    if ((form.age_range ?? '').trim().length > 30) errors.age_range = AGE_RANGE_ERROR;
    const headcount = headcountError(form.expected_headcount);
    if (headcount) errors.expected_headcount = headcount;
  }
  return errors;
}

/** Données envoyées à POST /api/projects : champs vides retirés, nombres convertis. */
export function toPayload(form) {
  const payload = { name: form.name.trim() };
  for (const key of ['organization', 'city', 'postal_code', 'start_date', 'end_date']) {
    if (form[key].trim()) payload[key] = form[key].trim();
  }
  for (const key of ['project_type_id', 'planned_sessions', 'default_location_id', 'principal_user_id']) {
    if (form[key] !== '') payload[key] = Number(form[key]);
  }
  payload.funder_ids = form.funder_ids.map(Number);
  // Âge et effectif : sans eux, les statistiques n'ont ni tranche d'âge ni assiduité (#244).
  const details = ({ age_range: age = '', expected_headcount: headcount = '' }) => ({
    ...(age.trim() ? { age_range: age.trim() } : {}),
    ...(headcount !== '' ? { expected_headcount: Number(headcount) } : {}),
  });
  if (form.sub_projects.length) {
    payload.sub_projects = form.sub_projects.map((sub) => ({ name: sub.name.trim(), ...details(sub) }));
  } else {
    // Sans sous-projet, l'API en crée un qui porte le nom du projet, avec cet âge et cet effectif.
    Object.assign(payload, details(form));
  }
  return payload;
}

/** Champs affichés par le formulaire : une erreur sur une autre clé va dans l'alerte générale. */
const DISPLAYED_FIELDS = new Set([
  'name', 'project_type_id', 'organization', 'city', 'postal_code', 'planned_sessions',
  'start_date', 'end_date', 'default_location_id', 'principal_user_id', 'funder_ids',
  'age_range', 'expected_headcount',
]);

/**
 * Les erreurs 422 arrivent en tableaux par clé (`funder_ids.1`, `sub_projects.0.name`…).
 * Celles d'un financeur sont regroupées sous `funder_ids` ; celles d'un champ absent du
 * formulaire sont renvoyées à part, pour être lues dans l'alerte générale.
 */
export function mapServerErrors(errors) {
  const fields = {};
  const others = [];
  for (const [key, messages] of Object.entries(errors ?? {})) {
    const target = key.startsWith('funder_ids.') ? 'funder_ids' : key;
    if (DISPLAYED_FIELDS.has(target) || /^sub_projects\.\d+\.(name|age_range|expected_headcount)$/.test(target)) {
      fields[target] ??= messages[0];
    } else {
      others.push(messages[0]);
    }
  }
  return { fields, others };
}

export default function NewProjectPage() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const formRef = useRef(null);
  const [references, setReferences] = useState({ status: 'loading' });
  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [created, setCreated] = useState(null);

  const lastRequest = useRef(0);

  const loadReferences = useCallback(() => {
    const request = ++lastRequest.current;
    const outdated = () => request !== lastRequest.current;
    setReferences({ status: 'loading' });
    Promise.all([listProjectTypes(), listLocations(), listContributors(), listFunders()])
      .then(([types, locations, contributors, funders]) => {
        if (outdated()) return;
        setReferences({ status: 'ready', types: types.filter((type) => type.is_active), locations, contributors, funders });
      })
      .catch((error) => {
        if (outdated()) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setReferences({
          status: 'error',
          error: error.status === 0 ? error.message : 'Les listes du formulaire n’ont pas pu être chargées.',
        });
      });
  }, [logout]);

  useEffect(() => {
    document.title = 'Nouveau projet · KLAP';
    loadReferences();
    return () => {
      lastRequest.current += 1;
    };
  }, [loadReferences]);

  const update = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }));
  // Lieu créé depuis le menu (#135) : ajouté à la liste, rangée par nom.
  const addLocation = (place) =>
    setReferences((current) => ({
      ...current,
      locations: [...current.locations, place].sort((a, b) => a.name.localeCompare(b.name, 'fr')),
    }));

  function toggleFunder(id) {
    setForm((current) => ({
      ...current,
      funder_ids: current.funder_ids.includes(id)
        ? current.funder_ids.filter((funderId) => funderId !== id)
        : [...current.funder_ids, id],
    }));
  }

  function updateSubProject(index, key, value) {
    setForm((current) => ({
      ...current,
      sub_projects: current.sub_projects.map((sub, i) => (i === index ? { ...sub, [key]: value } : sub)),
    }));
  }

  function focusFirstError(errorMap) {
    const firstKey = Object.keys(errorMap)[0];
    formRef.current?.querySelector(`[name="${CSS.escape(firstKey)}"]`)?.focus();
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');

    const localErrors = validateProject(form);
    setErrors(localErrors);
    if (Object.keys(localErrors).length) {
      focusFirstError(localErrors);
      return;
    }

    setSubmitting(true);
    try {
      // Après un succès, le formulaire reste verrouillé : il ne doit pas repartir une seconde fois.
      setCreated(await createProject(toPayload(form)));
    } catch (error) {
      setSubmitting(false);
      if (error.status === 401) {
        logout();
        return;
      }
      if (error.status === 422) {
        const { fields, others } = mapServerErrors(error.errors);
        setErrors(fields);
        setFormError(['Le projet n’a pas été créé : corrigez les champs signalés.', ...others].join(' '));
        focusFirstError(fields);
      } else {
        setFormError(error.status === 0 ? error.message : 'Le projet n’a pas pu être créé. Réessayez dans un instant.');
      }
    }
  }

  const closeModal = useCallback(() => navigate('/gestion'), [navigate]);

  return (
    <section className="page">
      <div inert={created !== null}>
      <p className="page-eyebrow">Gestion</p>
      <h1 className="page-title">Nouveau projet</h1>
      <p>
        <Link to="/gestion" className="back-link">
          ← Retour à la liste des projets
        </Link>
      </p>
      </div>

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
        // Pendant la modale « Projet créé », le formulaire est inerte : ni clic ni tabulation.
        <form ref={formRef} className="project-form" onSubmit={handleSubmit} noValidate inert={created !== null}>
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
              options={references.types.map((type) => ({ value: type.id, label: type.name }))}
            />
            <TextInput label="Structure partenaire" name="organization" maxLength={150} value={form.organization} onChange={update('organization')} error={errors.organization} />
            <div className="form-row">
              <TextInput label="Ville" name="city" maxLength={100} value={form.city} onChange={update('city')} error={errors.city} />
              <TextInput label="Code postal" name="postal_code" inputMode="numeric" maxLength={5} value={form.postal_code} onChange={update('postal_code')} error={errors.postal_code} />
            </div>
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
              onChange={(value) => setForm((current) => ({ ...current, default_location_id: value }))}
              onCreated={addLocation}
              error={errors.default_location_id}
              locations={references.locations}
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
              options={references.contributors.map((person) => ({ value: person.id, label: person.name }))}
            />
            <p className="form-hint">Les intervenant·e·s ponctuel·le·s s’ajoutent ensuite, en remplissant une fiche sur le projet.</p>

            <fieldset className="field" aria-describedby={errors.funder_ids ? 'financeurs-erreur' : undefined}>
              <legend className="field-label">Financeurs destinataires</legend>
              {references.funders.length === 0 && <p className="form-hint">Aucun financeur enregistré.</p>}
              {references.funders.map((funder) => (
                <label key={funder.id} className="checkbox">
                  <input
                    type="checkbox"
                    name="funder_ids"
                    checked={form.funder_ids.includes(funder.id)}
                    onChange={() => toggleFunder(funder.id)}
                  />
                  {funder.name}
                </label>
              ))}
              {errors.funder_ids && (
                <p id="financeurs-erreur" className="field-error">
                  {errors.funder_ids}
                </p>
              )}
            </fieldset>

            <fieldset className="field">
              <legend className="field-label">Sous-projets</legend>
              <p className="form-hint">
                Une classe, un établissement ou un groupe. Sans sous-projet, le projet forme un seul groupe.
                La tranche d’âge et l’effectif prévu servent aux statistiques (âge, assiduité) et au pré-bilan.
              </p>
              {form.sub_projects.length === 0 && (
                <div className="subproject-row">
                  <TextInput label="Tranche d’âge du groupe" name="age_range" maxLength={30} placeholder="ex. 15-16 ans" value={form.age_range} onChange={update('age_range')} error={errors.age_range} />
                  <TextInput label="Effectif prévu" name="expected_headcount" type="number" min={1} max={65535} inputMode="numeric" value={form.expected_headcount} onChange={update('expected_headcount')} error={errors.expected_headcount} />
                </div>
              )}
              {form.sub_projects.map((sub, index) => {
                const key = `sub_projects.${index}.name`;
                return (
                  <div key={index} className="subproject-row subproject-row-named">
                    <TextInput
                      label={`Sous-projet ${index + 1}`}
                      name={key}
                      maxLength={150}
                      value={sub.name}
                      onChange={(event) => updateSubProject(index, 'name', event.target.value)}
                      error={errors[key]}
                    />
                    <TextInput
                      label="Tranche d’âge"
                      aria-label={`Tranche d’âge du sous-projet ${index + 1}`}
                      name={`sub_projects.${index}.age_range`}
                      maxLength={30}
                      placeholder="ex. 15-16 ans"
                      value={sub.age_range}
                      onChange={(event) => updateSubProject(index, 'age_range', event.target.value)}
                      error={errors[`sub_projects.${index}.age_range`]}
                    />
                    <TextInput
                      label="Effectif prévu"
                      aria-label={`Effectif prévu du sous-projet ${index + 1}`}
                      name={`sub_projects.${index}.expected_headcount`}
                      type="number"
                      min={1}
                      max={65535}
                      inputMode="numeric"
                      value={sub.expected_headcount}
                      onChange={(event) => updateSubProject(index, 'expected_headcount', event.target.value)}
                      error={errors[`sub_projects.${index}.expected_headcount`]}
                    />
                    <Button
                      variant="ghost"
                      aria-label={`Retirer le sous-projet ${index + 1}`}
                      onClick={() =>
                        setForm((current) => ({ ...current, sub_projects: current.sub_projects.filter((_, i) => i !== index) }))
                      }
                    >
                      Retirer
                    </Button>
                  </div>
                );
              })}
              <Button
                variant="line"
                onClick={() => setForm((current) => ({ ...current, sub_projects: [...current.sub_projects, { ...EMPTY_SUB_PROJECT }] }))}
              >
                + Ajouter un sous-projet
              </Button>
            </fieldset>

            <div className="form-actions">
              <Link to="/gestion" className="btn btn-line">
                Annuler
              </Link>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Création…' : 'Créer le projet'}
              </Button>
            </div>
          </fieldset>
        </form>
      )}

      <BaseModal
        open={created !== null}
        title="Projet créé"
        onClose={closeModal}
        actions={
          <>
            <Button variant="line" onClick={closeModal}>
              Retour à la gestion
            </Button>
            <Button onClick={() => navigate(`/projets/${created.id}`)}>Voir le projet</Button>
          </>
        }
      >
        <p>« {created?.name} » apparaît désormais dans la liste des projets.</p>
      </BaseModal>
    </section>
  );
}
