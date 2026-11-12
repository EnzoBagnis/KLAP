// Les libellés et les dates des séances. Les types viennent de la fiche papier (cahier des
// charges, « Type de séance »).


/** Les types de séance : [valeur de l'API, libellé]. */
export const SESSION_TYPES = [
  ['guided_tour', 'Visite commentée de KLAP'],
  ['viewing_workshop', 'Atelier du regard'],
  ['practice_workshop', 'Atelier de pratique'],
  ['complementary_workshop', 'Atelier complémentaire'],
  ['performance', 'Spectacle'],
  ['ongoing_project', 'Projet en cours'],
  ['other', 'Autre'],
];

/** Les mêmes, indexés par valeur. */
const TYPE_LABELS = Object.fromEntries(SESSION_TYPES);

/**
 * Le type d'une séance, tel qu'on l'affiche. Pour « autre », c'est la précision saisie.
 *
 * @param {{session_type: string, session_type_detail?: string|null}} session
 * @returns {string}
 */
export function sessionTypeLabel(session) {
  if (session.session_type === 'other' && session.session_type_detail) return session.session_type_detail;
  return TYPE_LABELS[session.session_type] ?? 'Séance';
}

/** Les statuts d'une séance, tels qu'on les affiche. */
export const SESSION_STATUS_LABELS = {
  planned: 'Programmée',
  done: 'Réalisée',
  cancelled: 'Annulée',
};

/** Un nombre sur deux chiffres : 5 donne « 05 ». */
const pad = (n) => String(n).padStart(2, '0');

/**
 * Le mois courant au format « AAAA-MM », en heure locale.
 *
 * @param {Date} [now]
 * @returns {string}
 */
export function currentMonth(now = new Date()) {
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
}

/**
 * On lit un mois « 2026-10 ».
 *
 * @param {string|null} value
 * @returns {{year: number, month: number}|null} null si la valeur n'est pas un mois valide
 */
export function parseMonth(value) {
  const match = /^(\d{4})-(\d{2})$/.exec(value ?? '');
  if (!match) return null;
  const year = Number(match[1]);
  const month = Number(match[2]);
  // Date lirait une année avant 1900 comme 19xx : on la refuse, et l'écran retombe sur le mois courant.
  return year >= 1900 && month >= 1 && month <= 12 ? { year, month } : null;
}

/**
 * Le premier et le dernier jour d'un mois, en AAAA-MM-JJ (sans passer par UTC).
 *
 * @param {{year: number, month: number}} month
 * @returns {{from: string, to: string}}
 */
export function monthBounds({ year, month }) {
  const lastDay = new Date(year, month, 0).getDate();
  return { from: `${year}-${pad(month)}-01`, to: `${year}-${pad(month)}-${pad(lastDay)}` };
}

/**
 * Le mois situé `delta` mois plus tard (ou plus tôt, si `delta` est négatif), au format « AAAA-MM ».
 *
 * @param {{year: number, month: number}} month
 * @param {number} delta
 * @returns {string}
 */
export function shiftMonth({ year, month }, delta) {
  const date = new Date(year, month - 1 + delta, 1);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

/** Le titre d'un mois : « Octobre 2026 ». */
export function monthTitle({ year, month }) {
  const label = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1));
  return label.charAt(0).toUpperCase() + label.slice(1);
}

/**
 * Le titre d'un jour : « lundi 5 octobre ».
 *
 * @param {string} isoDate  AAAA-MM-JJ
 */
export function dayTitle(isoDate) {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(
    new Date(year, month - 1, day),
  );
}

/** Le délai d'une fiche : jusqu'au 10ᵉ jour après la séance inclus, comme l'API (LogbookEntry::EDIT_DAYS). */
export const ENTRY_EDIT_DAYS = 10;

/**
 * Le dernier jour où on peut ouvrir la fiche d'une séance : « 2026-10-05 » donne « 2026-10-15 ».
 *
 * @param {string} isoDate  AAAA-MM-JJ
 * @returns {string}
 */
export function entryDeadline(isoDate) {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Date(Date.UTC(year, month - 1, day + ENTRY_EDIT_DAYS)).toISOString().slice(0, 10);
}

/**
 * La date du jour à l'heure de KLAP (Paris), comme l'API, au format « 2026-11-05 ».
 *
 * @param {Date} [date]
 * @returns {string}
 */
export function todayKey(date = new Date()) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Paris' }).format(date);
}
