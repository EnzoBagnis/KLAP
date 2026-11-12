// Le modèle de la fiche côté écran : ses étapes, ses libellés, et le passage entre la
// ressource de l'API, l'état du formulaire et la saisie qu'on envoie (PATCH).

/** Les six étapes de la fiche, dans l'ordre de la fiche papier. */
export const STEPS = [
  { number: 1, title: 'Généralités' },
  { number: 2, title: 'Objectifs' },
  { number: 3, title: 'La séance' },
  { number: 4, title: 'Les participants' },
  { number: 5, title: 'Les artistes' },
  { number: 6, title: 'La suite' },
];

/** Les sensations proposées : [valeur de l'API, libellé]. */
export const FEELINGS = [
  ['well_being', 'Bien-être'],
  ['discomfort', 'Mal-être'],
  ['tension', 'Tensions'],
  ['buy_in', 'Adhésion'],
  ['enthusiasm', 'Enthousiasme'],
  ['shyness', 'Timidité'],
  ['other', 'Autre'],
];

/** Les évolutions proposées : [valeur de l'API, libellé]. */
export const PROGRESS = [
  ['first_session', 'Première séance'],
  ['improvement', 'Amélioration'],
  ['stagnation', 'Stagnation'],
  ['regression', 'Retour en arrière'],
];

/** Les mots-clés que le cahier des charges de la cliente suggère pour l'interaction. */
export const INTERACTION_KEYWORDS = ['écoute', 'entraide', 'conflit'];

/** Le nombre d'exercices qu'une fiche soumise doit avoir au moins, comme l'API. */
export const MIN_EXERCISES = 2;
/** Le nombre d'exercices qu'une fiche ne dépasse pas, comme l'API. */
export const MAX_EXERCISES = 10;

/** Les champs de texte libre de la fiche. */
const TEXT_FIELDS = [
  'objectives', 'adjustment_detail', 'highlights', 'tools_resources', 'learnings',
  'artist_review', 'next_steps', 'dropped_leads', 'additional_notes',
];

/** On complète la liste avec des lignes vides, jusqu'au minimum demandé. */
const padExercises = (labels) => [...labels, ...Array(Math.max(0, MIN_EXERCISES - labels.length)).fill('')];

/**
 * L'état du formulaire, à partir de la fiche que l'API renvoie.
 *
 * @param {object} entry
 * @returns {object}
 */
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
    // On affiche deux lignes d'emblée sur une fiche modifiable : c'est le minimum demandé à la soumission.
    exercises: entry.editable ? padExercises(entry.exercises ?? []) : [...(entry.exercises ?? [])],
    attendees: Object.fromEntries(
      (entry.session?.sub_projects ?? []).map((group) => [group.id, group.attendees ?? '']),
    ),
    // Le lieu de la séance, qu'on peut corriger depuis la fiche (#215).
    location_id: entry.session?.location ? String(entry.session.location.id) : '',
  };
}

/** Un texte sans espaces autour, ou null s'il est vide. */
const textOrNull = (value) => (value.trim() === '' ? null : value.trim());

/**
 * La saisie qu'on envoie à l'API (PATCH) : les textes vides deviennent null, et on retire les
 * lignes d'exercice vides.
 *
 * @param {object} form
 * @param {number[]} groupIds  les groupes de la séance, dans l'ordre affiché
 * @returns {object}
 */
export function payloadFromForm(form, groupIds) {
  const evaluation = form.evaluation;
  return {
    ...Object.fromEntries(TEXT_FIELDS.map((key) => [key, textOrNull(form[key])])),
    adjustment_done: form.adjustment_done,
    // « Non fait » : on ne demande aucune explication.
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
    // On ne l'envoie pas (undefined) quand le formulaire n'a pas de lieu : l'API ne touche alors pas à la séance.
    location_id: form.location_id ? Number(form.location_id) : undefined,
  };
}

/**
 * Les présents, dans l'ordre des groupes affichés : l'API renvoie ses erreurs par index
 * (attendees.N.count), il faut donc que N désigne le même groupe des deux côtés.
 *
 * @param {Object<number, string|number>} attendees  les présents saisis, par groupe
 * @param {number[]} [groupIds]
 * @returns {Array<{sub_project_id: number, count: number|null}>}
 */
function attendeesFromForm(attendees, groupIds = Object.keys(attendees).map(Number)) {
  return groupIds.map((id) => {
    const count = attendees[id];
    return { sub_project_id: id, count: count === '' || count === null || count === undefined ? null : Number(count) };
  });
}

/**
 * L'étape où on affiche une erreur de l'API, d'après sa clé.
 *
 * @param {string} key
 * @returns {number} de 1 à 6
 */
export function stepOfError(key) {
  if (key === 'session' || key === 'location_id') return 1;
  if (key === 'objectives') return 2;
  if (/^(exercises|adjustment_|highlights|tools_resources)/.test(key)) return 3;
  if (/^(evaluation\.|attendees)/.test(key)) return 4;
  if (key === 'learnings' || key === 'artist_review') return 5;
  return 6;
}

/**
 * On ramène les erreurs de l'API (un tableau par clé) au premier message de chaque clé.
 *
 * @param {Object<string, string[]>} errors
 * @returns {Object<string, string>}
 */
export function flattenErrors(errors) {
  return Object.fromEntries(Object.entries(errors ?? {}).map(([key, messages]) => [key, messages[0]]));
}

/**
 * Les étapes qui contiennent au moins une erreur, dans l'ordre.
 *
 * @param {Object<string, string>} errors
 * @returns {number[]}
 */
export function stepsWithErrors(errors) {
  return [...new Set(Object.keys(errors).map(stepOfError))].sort((a, b) => a - b);
}

/** Une date d'échéance : « 2026-10-30 » devient « 30/10/2026 ». */
export const formatDate = (isoDate) => isoDate.slice(0, 10).split('-').reverse().join('/');

/** Un horodatage de l'API (UTC), écrit en date à l'heure de KLAP : « 21/09/2026 ». */
export const formatInstant = (iso) => new Date(iso).toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' });
