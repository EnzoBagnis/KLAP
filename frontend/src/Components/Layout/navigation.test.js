import { ROLES } from '../../Context/roles.js';
import { NAV_ITEMS, navItemsForRole } from './navigation.js';

const labels = (role) => navItemsForRole(role).map((item) => item.label);

describe('navItemsForRole', () => {
  it("donne toutes les entrées à l'administratrice, dans l'ordre de la maquette", () => {
    expect(labels(ROLES.ADMIN)).toEqual([
      'Tableau de bord',
      'Projets',
      'Planning',
      'Statistiques',
      'Pré-bilan',
      'Gestion',
    ]);
  });

  it('donne tout sauf Gestion à la super-utilisatrice', () => {
    expect(labels(ROLES.SUPER_USER)).toEqual([
      'Tableau de bord',
      'Projets',
      'Planning',
      'Statistiques',
      'Pré-bilan',
    ]);
  });

  it("ne donne ni chiffres agrégés ni Gestion à l'intervenant", () => {
    expect(labels(ROLES.CONTRIBUTOR)).toEqual(['Tableau de bord', 'Projets', 'Planning']);
  });

  it('ne donne rien sans rôle connu', () => {
    expect(navItemsForRole(undefined)).toEqual([]);
    expect(navItemsForRole('visiteur')).toEqual([]);
  });

  it("n'utilise que des rôles existants, sans chemin en double", () => {
    const known = Object.values(ROLES);
    NAV_ITEMS.forEach((item) => {
      expect(item.roles.length).toBeGreaterThan(0);
      item.roles.forEach((role) => expect(known).toContain(role));
    });
    const paths = NAV_ITEMS.map((item) => item.path);
    expect(new Set(paths).size).toBe(paths.length);
  });
});
