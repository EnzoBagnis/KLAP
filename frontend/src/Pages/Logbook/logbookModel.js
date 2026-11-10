// Modèle de la fiche côté écran : étapes, libellés, passage entre la ressource de l'API,
// l'état du formulaire et la saisie envoyée (PATCH).

export const STEPS = [
  { number: 1, title: 'Généralités' },
  { number: 2, title: 'Objectifs' },
  { number: 3, title: 'La séance' },
  { number: 4, title: 'Les participants' },
  { number: 5, title: 'Les artistes' },
  { number: 6, title: 'La suite' },
];

export const FEELINGS = [
  ['well_being', 'Bien-être'],
  ['discomfort', 'Mal-être'],
  ['tension', 'Tensions'],
  ['buy_in', 'Adhésion'],
  ['enthusiasm', 'Enthousiasme'],
  ['shyness', 'Timidité'],
  ['other', 'Autre'],
];

export const PROGRESS = [
  ['first_session', 'Première séance'],
  ['improvement', 'Amélioration'],
  ['stagnation', 'Stagnation'],
  ['regression', 'Retour en arrière'],
];

/** Mots-clés suggérés par le cahier des charges de la cliente pour l'interaction. */
export const INTERACTION_KEYWORDS = ['écoute', 'entraide', 'conflit'];

export const MIN_EXERCISES = 2;
export const MAX_EXERCISES = 10;

const TEXT_FIELDS = [
  'objectives', 'adjustment_detail', 'highlights', 'tools_resources', 'learnings',
  'artist_review', 'next_steps', 'dropped_leads', 'additional_notes',
];

/** État du formulaire à partir de la fiche renvoyée par l'API. */
const padExercises = (labels) => [...labels, ...Array(Math.max(0, MIN_EXERCISES - labels.length)).fill('')];

export function formFromEntry(entry) {
  const evaluation = entry.evaluation ?? {};
  return {
    ...Object.fromEntries(TEXT_FIELDS.map((key) => [key, entry[key] ?? ''])),
    adjustment_done: entry.adjustment_done ?? null,
    keep_in_class: Boolean(entry.keep_in_class),
    evaluation: {
      engagement: evaluation.engagement ?? null,
      expression: evaluation.expression ?? null,
      autonomy: evaluation.autonomy ?? null,
      interaction: evaluation.interaction ?? '',
      feeling: evaluation.feeling ?? '',
      feeling_detail: evaluation.feeling_detail ?? '',
      progress: evaluation.progress ?? '',
    },
    // Deux lignes d'emblée sur une fiche modifiable : c'est le minimum demandé à la soumission.
    exercises: entry.editable ? padExercises(entry.exercises ?? []) : [...(entry.exercises ?? [])],
    attendees: Object.fromEntries(
      (entry.session?.sub_projects ?? []).map((group) => [group.id, group.attendees ?? '']),
    ),
    // Lieu de la séance, corrigeable depuis la fiche (#215).
    location_id: entry.session?.location ? String(entry.session.location.id) : '',
  };
}

const textOrNull = (value) => (value.trim() === '' ? null : value.trim());

/** Saisie envoyée à l'API (PATCH) : textes vides en null, lignes d'exercice vides retirées. */
export function payloadFromForm(form, groupIds) {
  const evaluation = form.evaluation;
  return {
    ...Object.fromEntries(TEXT_FIELDS.map((key) => [key, textOrNull(form[key])])),
    adjustment_done: form.adjustment_done,
    // « Non fait » : aucune explication n'est demandée.
    adjustment_detail: form.adjustment_done ? textOrNull(form.adjustment_detail) : null,
    keep_in_class: form.keep_in_class,
    evaluation: {
      engagement: evaluation.engagement,
      expression: evaluation.expression,
      autonomy: evaluation.autonomy,
      interaction: textOrNull(evaluation.interaction),
      feeling: evaluation.feeling || null,
      feeling_detail: evaluation.feeling === 'other' ? textOrNull(evaluation.feeling_detail) : null,
      progress: evaluation.progress || null,
    },
    exercises: form.exercises.map((label) => label.trim()).filter(Boolean),
    attendees: attendeesFromForm(form.attendees, groupIds),
    // Absent (undefined) quand le formulaire n'a pas de lieu : l'API ne touche alors pas à la séance.
    location_id: form.location_id ? Number(form.location_id) : undefined,
  };
}

/*
 * Présents dans l'ordre des groupes affichés : l'API renvoie ses erreurs par index
 * (attendees.N.count), il faut que N désigne le même groupe des deux côtés.
 */
function attendeesFromForm(attendees, groupIds = Object.keys(attendees).map(Number)) {
  return groupIds.map((id) => {
    const count = attendees[id];
    return { sub_project_id: id, count: count === '' || count === null || count === undefined ? null : Number(count) };
  });
}

/** Étape où s'affiche une erreur de l'API, d'après sa clé. */
export function stepOfError(key) {
  if (key === 'session' || key === 'location_id') return 1;
  if (key === 'objectives') return 2;
  if (/^(exercises|adjustment_|highlights|tools_resources)/.test(key)) return 3;
  if (/^(evaluation\.|attendees)/.test(key)) return 4;
  if (key === 'learnings' || key === 'artist_review') return 5;
  return 6;
}

/** Erreurs de l'API (tableaux par clé) ramenées au premier message de chaque clé. */
export function flattenErrors(errors) {
  return Object.fromEntries(Object.entries(errors ?? {}).map(([key, messages]) => [key, messages[0]]));
}

export function stepsWithErrors(errors) {
  return [...new Set(Object.keys(errors).map(stepOfError))].sort((a, b) => a - b);
}

/** Date d'échéance « 2026-10-30 » → « 30/10/2026 ». */
export const formatDate = (isoDate) => isoDate.slice(0, 10).split('-').reverse().join('/');

/** Horodatage de l'API (UTC) → date à l'heure de KLAP, « 21/09/2026 ». */
export const formatInstant = (iso) => new Date(iso).toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' });
