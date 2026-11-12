// Les libellés et les petits calculs d'affichage que la liste et le détail des projets partagent.


/** Les statuts d'un projet, tels qu'on les affiche. */
export const STATUS_LABELS = {
  draft: 'Brouillon',
  active: 'En cours',
  finished: 'Terminé',
  archived: 'Archivé',
};

/**
 * Le libellé d'un statut. Pour une valeur de l'API qu'on ne connaît pas, on garde un libellé lisible.
 */
export function statusLabel(status) {
  return STATUS_LABELS[status] ?? 'Statut inconnu';
}

/** L'écriture longue d'une date : « 15 septembre 2026 ». */
const DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });

/**
 * « 2026-09-15 » devient « 15 septembre 2026 ».
 *
 * @param {string|null} isoDate
 * @returns {string|null}
 */
export function formatDate(isoDate) {
  if (!isoDate) return null;
  const [year, month, day] = isoDate.split('-').map(Number);
  return DATE_FORMAT.format(new Date(year, month - 1, day));
}

/**
 * La période d'un projet en toutes lettres : « du … au … », « à partir du … », « jusqu'au … ».
 *
 * @param {string|null} start  AAAA-MM-JJ
 * @param {string|null} end  AAAA-MM-JJ
 * @returns {string|null} null si aucune date n'est renseignée
 */
export function formatPeriod(start, end) {
  if (start && end) return `du ${formatDate(start)} au ${formatDate(end)}`;
  if (start) return `à partir du ${formatDate(start)}`;
  if (end) return `jusqu’au ${formatDate(end)}`;
  return null;
}

/**
 * La provenance commune des groupes, « Marseille · 13003 », s'ils partagent tous la même.
 *
 * @param {object[]} [subProjects]
 * @returns {string|null}
 */
export function commonLocality(subProjects = []) {
  const places = new Set(
    subProjects.filter((sub) => sub.city || sub.postal_code).map((sub) => [sub.city, sub.postal_code].filter(Boolean).join(' · ')),
  );
  return places.size === 1 ? [...places][0] : null;
}
