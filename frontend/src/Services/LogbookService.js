import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Fiche de séance (#106). C'est l'API qui vérifie les droits (référent, auteur, échéance).

/** On ne garde que le champ `data` de la réponse. */
const data = async (promise) => (await promise).data;

/** GET /logbook-entries/{id} : une fiche. */
export const getEntry = (id) => data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}`, { token: getToken() }));

/**
 * GET /sessions/{id}/logbook-entry : la fiche d'une séance ; 404 si elle n'en a pas encore.
 */
export const getEntryForSession = (sessionId) =>
  data(apiRequest(`/sessions/${encodeURIComponent(sessionId)}/logbook-entry`, { token: getToken() }));

/** POST /sessions/{id}/logbook-entry : on ouvre la fiche d'une séance, en brouillon. */
export const createEntryForSession = (sessionId) =>
  data(apiRequest(`/sessions/${encodeURIComponent(sessionId)}/logbook-entry`, { method: 'POST', body: {}, token: getToken() }));

/** PATCH /logbook-entries/{id} : on enregistre la saisie. */
export const updateEntry = (id, payload) =>
  data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}`, { method: 'PATCH', body: payload, token: getToken() }));

/** POST /logbook-entries/{id}/submit : on soumet la fiche, si elle est complète. */
export const submitEntry = (id) =>
  data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}/submit`, { method: 'POST', body: {}, token: getToken() }));

/**
 * POST /logbook-entries/{id}/unlock-request : l'auteur demande la réouverture de sa fiche verrouillée (#130).
 */
export const requestUnlock = (id) =>
  data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}/unlock-request`, { method: 'POST', body: {}, token: getToken() }));

/**
 * POST /logbook-entries/{id}/unlock : l'administratrice rouvre une fiche verrouillée ou hors délai (#109).
 */
export const unlockEntry = (id) =>
  data(apiRequest(`/logbook-entries/${encodeURIComponent(id)}/unlock`, { method: 'POST', body: {}, token: getToken() }));
