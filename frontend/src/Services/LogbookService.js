import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Fiche de séance (#106). Les droits sont vérifiés par l'API (référent, auteur, échéance).

const data = async (promise) => (await promise).data;

export const getEntry = (id) => data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}`, { token: getToken() }));

export const getEntryForSession = (sessionId) =>
  data(apiRequest(`/sessions/${encodeURIComponent(sessionId)}/logbook-entry`, { token: getToken() }));

export const createEntryForSession = (sessionId) =>
  data(apiRequest(`/sessions/${encodeURIComponent(sessionId)}/logbook-entry`, { method: 'POST', body: {}, token: getToken() }));

export const updateEntry = (id, payload) =>
  data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}`, { method: 'PATCH', body: payload, token: getToken() }));

export const submitEntry = (id) =>
  data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}/submit`, { method: 'POST', body: {}, token: getToken() }));
