import { ApiError, NETWORK_ERROR_MESSAGE, apiRequest } from './ApiClient.js';
import { jsonResponse, mockApi } from '../testUtils.js';
import { vi } from 'vitest';

describe('apiRequest', () => {
  it('appelle l’API en JSON avec le jeton Bearer', async () => {
    const fetchMock = mockApi({ 'POST /login': () => jsonResponse({ ok: true }) });

    await expect(apiRequest('/login', { method: 'POST', body: { a: 1 }, token: 'abc' })).resolves.toEqual({ ok: true });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('http://localhost:8080/api/login');
    expect(options.headers).toEqual({
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer abc',
    });
    expect(options.body).toBe('{"a":1}');
  });

  it('n’envoie ni corps ni jeton quand il n’y en a pas', async () => {
    const fetchMock = mockApi({ 'GET /me': () => jsonResponse({}) });

    await apiRequest('/me');

    expect(fetchMock.mock.calls[0][1].headers).toEqual({ Accept: 'application/json' });
    expect(fetchMock.mock.calls[0][1].body).toBeUndefined();
  });

  it('remonte les erreurs de validation', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ message: 'invalide', errors: { email: ['Faux.'] } }, 422) });

    const error = await apiRequest('/login', { method: 'POST', body: {} }).catch((e) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(422);
    expect(error.errors.email).toEqual(['Faux.']);
  });

  it('lit le délai d’attente d’un 429', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ message: 'Too Many Attempts.' }, 429, { 'Retry-After': '42' }) });

    const error = await apiRequest('/login', { method: 'POST', body: {} }).catch((e) => e);

    expect(error.status).toBe(429);
    expect(error.retryAfter).toBe(42);
  });

  it('traduit une panne réseau en statut 0', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

    const error = await apiRequest('/me').catch((e) => e);

    expect(error.status).toBe(0);
    expect(error.message).toBe(NETWORK_ERROR_MESSAGE);
  });

  it('supporte une réponse d’erreur sans JSON', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>', { status: 500 })));

    const error = await apiRequest('/me').catch((e) => e);

    expect(error.status).toBe(500);
    expect(error.errors).toEqual({});
  });
});
