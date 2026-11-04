// Libellés et petits calculs d'affichage partagés par la liste et le détail.

export const STATUS_LABELS = {
  draft: 'Brouillon',
  active: 'En cours',
  finished: 'Terminé',
  archived: 'Archivé',
};

/** Libellé du statut ; une valeur inconnue de l'API garde un libellé lisible. */
export function statusLabel(status) {
  return STATUS_LABELS[status] ?? 'Statut inconnu';
}

const DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });

/** « 2026-09-15 » devient « 15 septembre 2026 ». */
export function formatDate(isoDate) {
  if (!isoDate) return null;
  const [year, month, day] = isoDate.split('-').map(Number);
  return DATE_FORMAT.format(new Date(year, month - 1, day));
}

export function formatPeriod(start, end) {
  if (start && end) return `du ${formatDate(start)} au ${formatDate(end)}`;
  if (start) return `à partir du ${formatDate(start)}`;
  if (end) return `jusqu’au ${formatDate(end)}`;
  return null;
}

/** « Marseille · 13003 » si tous les sous-projets partagent la même provenance. */
export function commonLocality(subProjects = []) {
  const places = new Set(
    subProjects.filter((sub) => sub.city || sub.postal_code).map((sub) => [sub.city, sub.postal_code].filter(Boolean).join(' · ')),
  );
  return places.size === 1 ? [...places][0] : null;
}
