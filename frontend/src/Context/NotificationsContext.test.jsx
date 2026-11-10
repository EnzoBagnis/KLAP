import { act, render, screen } from '@testing-library/react';
import { useContext } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';
import { NotificationsContext, NotificationsProvider, REFRESH_EVERY_MS } from './NotificationsContext.jsx';
import { countUnreadNotifications } from '../Services/NotificationService.js';

vi.mock('../Services/NotificationService.js', () => ({ countUnreadNotifications: vi.fn() }));
const logout = vi.fn();
vi.mock('../Hooks/useAuth.js', () => ({ default: () => ({ logout }) }));

function Bell() {
  const { unreadCount } = useContext(NotificationsContext);
  return <p>non lues : {unreadCount}</p>;
}

const renderBell = () =>
  render(
    <MemoryRouter>
      <NotificationsProvider>
        <Bell />
      </NotificationsProvider>
    </MemoryRouter>,
  );

// Laisse les promesses en attente se résoudre (le service est simulé).
const flush = () => act(async () => {});

let visibility = 'visible';

beforeEach(() => {
  vi.useFakeTimers();
  visibility = 'visible';
  vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() => visibility);
  countUnreadNotifications.mockReset();
  countUnreadNotifications.mockResolvedValue(0);
  logout.mockReset();
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('NotificationsProvider : la cloche se met à jour seule (#211)', () => {
  it('recharge le compteur toutes les 30 secondes, sans changer de page', async () => {
    countUnreadNotifications.mockResolvedValueOnce(0).mockResolvedValueOnce(1);
    renderBell();
    await flush();
    expect(screen.getByText('non lues : 0')).toBeInTheDocument();

    await act(async () => vi.advanceTimersByTime(REFRESH_EVERY_MS));

    expect(countUnreadNotifications).toHaveBeenCalledTimes(2);
    expect(screen.getByText('non lues : 1')).toBeInTheDocument();
  });

  it('onglet caché : aucune requête ; au retour sur l’onglet, rechargement immédiat', async () => {
    countUnreadNotifications.mockResolvedValue(2);
    renderBell();
    await flush();
    expect(countUnreadNotifications).toHaveBeenCalledTimes(1);

    visibility = 'hidden';
    await act(async () => vi.advanceTimersByTime(REFRESH_EVERY_MS * 3));
    expect(countUnreadNotifications).toHaveBeenCalledTimes(1);

    visibility = 'visible';
    await act(async () => document.dispatchEvent(new Event('visibilitychange')));
    expect(countUnreadNotifications).toHaveBeenCalledTimes(2);
    expect(screen.getByText('non lues : 2')).toBeInTheDocument();
  });

  it('arrête de recharger une fois la cloche retirée (déconnexion)', async () => {
    countUnreadNotifications.mockResolvedValue(0);
    const { unmount } = renderBell();
    await flush();
    unmount();

    await act(async () => vi.advanceTimersByTime(REFRESH_EVERY_MS * 2));
    document.dispatchEvent(new Event('visibilitychange'));

    expect(countUnreadNotifications).toHaveBeenCalledTimes(1);
  });

  it('une requête en échec laisse le compteur tel quel, sans déconnecter', async () => {
    countUnreadNotifications.mockResolvedValueOnce(3).mockRejectedValueOnce({ status: 0, message: 'réseau' });
    renderBell();
    await flush();
    await act(async () => vi.advanceTimersByTime(REFRESH_EVERY_MS));

    expect(screen.getByText('non lues : 3')).toBeInTheDocument();
    expect(logout).not.toHaveBeenCalled();
  });

  it('session expirée (401) : déconnexion, au lieu de réessayer sans fin', async () => {
    countUnreadNotifications.mockResolvedValueOnce(1).mockRejectedValueOnce({ status: 401 });
    renderBell();
    await flush();
    await act(async () => vi.advanceTimersByTime(REFRESH_EVERY_MS));

    expect(logout).toHaveBeenCalledTimes(1);
  });

  it('une mise à jour faite par un écran l’emporte sur un rechargement encore en route', async () => {
    let answer;
    countUnreadNotifications.mockResolvedValueOnce(2).mockReturnValueOnce(new Promise((resolve) => (answer = resolve)));
    function Reader() {
      const { setUnreadCount } = useContext(NotificationsContext);
      return (
        <button type="button" onClick={() => setUnreadCount((count) => count - 1)}>
          lire
        </button>
      );
    }
    render(
      <MemoryRouter>
        <NotificationsProvider>
          <Bell />
          <Reader />
        </NotificationsProvider>
      </MemoryRouter>,
    );
    await flush();
    await act(async () => vi.advanceTimersByTime(REFRESH_EVERY_MS)); // rechargement parti, pas encore revenu
    await act(async () => screen.getByRole('button', { name: 'lire' }).click());
    await act(async () => answer(5));

    expect(screen.getByText('non lues : 1')).toBeInTheDocument();
  });
});
