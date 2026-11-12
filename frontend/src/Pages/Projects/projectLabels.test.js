import { commonLocality, formatDate, formatPeriod, statusLabel } from './projectLabels.js';

describe('Libellés des projets', () => {
  it('nomme chaque statut, et garde un libellé lisible pour un statut inconnu', () => {
    expect(statusLabel('active')).toBe('En cours');
    expect(statusLabel('archived')).toBe('Archivé');
    expect(statusLabel('nouveau_statut')).toBe('Statut inconnu');
  });

  it('écrit une date en toutes lettres, sans décalage de fuseau', () => {
    expect(formatDate('2026-09-15')).toBe('15 septembre 2026');
    expect(formatDate('2027-01-01')).toBe('1 janvier 2027');
    expect(formatDate(null)).toBeNull();
  });

  it('écrit la période selon les dates renseignées', () => {
    expect(formatPeriod('2026-09-15', '2027-06-19')).toBe('du 15 septembre 2026 au 19 juin 2027');
    expect(formatPeriod('2026-09-15', null)).toBe('à partir du 15 septembre 2026');
    expect(formatPeriod(null, '2027-06-19')).toBe('jusqu’au 19 juin 2027');
    expect(formatPeriod(null, null)).toBeNull();
  });

  it('ne donne une provenance que si tous les groupes la partagent', () => {
    const marseille = { city: 'Marseille', postal_code: '13003' };
    expect(commonLocality([marseille, { ...marseille }])).toBe('Marseille · 13003');
    expect(commonLocality([marseille, { city: 'Aix-en-Provence', postal_code: '13100' }])).toBeNull();
    // Un groupe sans provenance ne compte pas : il n'en contredit aucune.
    expect(commonLocality([marseille, { city: null, postal_code: null }])).toBe('Marseille · 13003');
    expect(commonLocality([{ city: 'Marseille', postal_code: null }])).toBe('Marseille');
    expect(commonLocality([])).toBeNull();
    expect(commonLocality()).toBeNull();
  });
});
