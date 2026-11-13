import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import { ROLES } from '../../Context/roles.js';
import { MOCK_USERS } from '../../testUtils.js';
import Footer from './Footer.jsx';

/** On affiche le pied de page pour un rôle donné. */
function renderFooter(role) {
  render(
    <AuthProvider initialUser={MOCK_USERS[role]}>
      <MemoryRouter>
        <Footer />
      </MemoryRouter>
    </AuthProvider>,
  );
  return within(screen.getByRole('contentinfo'));
}

describe('Footer', () => {
  it('ne propose aucun lien vers une ancre qui n’existe pas', () => {
    const footer = renderFooter(ROLES.ADMIN);
    for (const link of footer.getAllByRole('link')) {
      expect(link.getAttribute('href')).not.toMatch(/^#/);
    }
  });

  it('reprend le menu de l’administratrice, statistiques comprises', () => {
    const footer = renderFooter(ROLES.ADMIN);
    expect(footer.getByRole('link', { name: 'Statistiques' })).toHaveAttribute('href', '/statistiques');
    expect(footer.getByRole('link', { name: 'Gestion' })).toHaveAttribute('href', '/gestion');
  });

  it('ne propose pas aux intervenant·e·s les écrans qui leur sont fermés', () => {
    const footer = renderFooter(ROLES.CONTRIBUTOR);
    expect(footer.getByRole('link', { name: 'Planning' })).toHaveAttribute('href', '/planning');
    expect(footer.queryByRole('link', { name: 'Statistiques' })).toBeNull();
    expect(footer.queryByRole('link', { name: 'Gestion' })).toBeNull();
  });

  it('ouvre le guide et les réseaux sociaux dans un nouvel onglet, sans lien avec la page d’origine', () => {
    const footer = renderFooter(ROLES.CONTRIBUTOR);
    const guide = footer.getByRole('link', { name: 'Guide d’utilisation (vidéo)' });
    expect(guide).toHaveAttribute('target', '_blank');
    expect(guide).toHaveAttribute('rel', 'noopener noreferrer');
    expect(footer.getByRole('link', { name: 'Instagram' })).toHaveAttribute('rel', 'noopener noreferrer');
  });
});
