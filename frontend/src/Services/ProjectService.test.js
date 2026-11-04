import { getProject, listProjects } from './ProjectService.js';
import { setToken } from './AuthService.js';
import { jsonResponse, mockApi } from '../testUtils.js';

describe('ProjectService', () => {
  it('liste les projets avec le jeton et déballe data', async () => {
    setToken('jeton');
    const fetchMock = mockApi({ 'GET /projects': () => jsonResponse({ data: [{ id: 1, name: 'AIMER' }] }) });

    await expect(listProjects()).resolves.toEqual([{ id: 1, name: 'AIMER' }]);
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe('Bearer jeton');
  });

  it('lit le détail d’un projet', async () => {
    mockApi({ 'GET /projects/12': () => jsonResponse({ data: { id: 12, name: 'BRAISES' } }) });

    await expect(getProject(12)).resolves.toEqual({ id: 12, name: 'BRAISES' });
  });

  it.each([
    [404, 'GET /projects/99'],
    [500, 'GET /projects/99'],
  ])('laisse passer une erreur %i', async (status, route) => {
    mockApi({ [route]: () => jsonResponse({ message: 'x' }, status) });

    await expect(getProject(99)).rejects.toMatchObject({ status });
  });

  it('remonte une panne réseau en statut 0', async () => {
    await expect(listProjects()).rejects.toMatchObject({ status: 0 });
  });
});
