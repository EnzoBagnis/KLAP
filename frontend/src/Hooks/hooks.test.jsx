import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import useAuth from './useAuth.js';
import useLogbook from './useLogbook.js';
import useNotifications from './useNotifications.js';

/** On affiche ce que le hook renvoie, ou l'erreur qu'il lève. */
function Probe({ hook }) {
  const value = hook();
  return <p>{Object.keys(value).join(',')}</p>;
}

describe('Hooks de contexte', () => {
  // React journalise l'erreur d'un composant qui plante : on fait taire la console pour ces tests.
  beforeEach(() => vi.spyOn(console, 'error').mockImplementation(() => {}));

  it('useAuth exige AuthProvider, avec un message qui dit quoi faire', () => {
    expect(() => render(<Probe hook={useAuth} />)).toThrow('useAuth doit être utilisé à l’intérieur de <AuthProvider>.');
  });

  it('useLogbook exige LogbookProvider, avec un message qui dit quoi faire', () => {
    expect(() => render(<Probe hook={useLogbook} />)).toThrow('useLogbook doit être utilisé à l’intérieur de <LogbookProvider>.');
  });

  it('useNotifications donne un compteur neutre hors du gabarit connecté', () => {
    render(<Probe hook={useNotifications} />);
    expect(screen.getByText('unreadCount,setUnreadCount,refresh')).toBeInTheDocument();
  });
});
