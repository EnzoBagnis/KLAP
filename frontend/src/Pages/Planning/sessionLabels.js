// Libellés des séances, repris de la fiche papier (cahier des charges, « Type de séance »).

export const SESSION_TYPES = [
  ['guided_tour', 'Visite commentée de KLAP'],
  ['viewing_workshop', 'Atelier du regard'],
  ['practice_workshop', 'Atelier de pratique'],
  ['complementary_workshop', 'Atelier complémentaire'],
  ['performance', 'Spectacle'],
  ['ongoing_project', 'Projet en cours'],
  ['other', 'Autre'],
];

const TYPE_LABELS = Object.fromEntries(SESSION_TYPES);

export function sessionTypeLabel(session) {
  if (session.session_type === 'other' && session.session_type_detail) return session.session_type_detail;
  return TYPE_LABELS[session.session_type] ?? 'Séance';
}

export const SESSION_STATUS_LABELS = {
  planned: 'Programmée',
  done: 'Réalisée',
  cancelled: 'Annulée',
};

const pad = (n) => String(n).padStart(2, '0');

/** Mois courant au format « AAAA-MM », en heure locale. */
export function currentMonth(now = new Date()) {
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
}

/** « 2026-10 » valide ? Sinon null. */
export function parseMonth(value) {
  const match = /^(\d{4})-(\d{2})$/.exec(value ?? '');
  if (!match) return null;
  const year = Number(match[1]);
  const month = Number(match[2]);
  // Une année avant 1900 serait lue comme 19xx par Date : on retombe sur le mois courant.
  return year >= 1900 && month >= 1 && month <= 12 ? { year, month } : null;
}

/** Premier et dernier jour du mois, en AAAA-MM-JJ (sans passer par UTC). */
export function monthBounds({ year, month }) {
  const lastDay = new Date(year, month, 0).getDate();
  return { from: `${year}-${pad(month)}-01`, to: `${year}-${pad(month)}-${pad(lastDay)}` };
}

export function shiftMonth({ year, month }, delta) {
  const date = new Date(year, month - 1 + delta, 1);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

/** « Octobre 2026 ». */
export function monthTitle({ year, month }) {
  const label = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1));
  return label.charAt(0).toUpperCase() + label.slice(1);
}

/** « lundi 5 octobre ». */
export function dayTitle(isoDate) {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(
    new Date(year, month - 1, day),
  );
}
