/**
 * @file ApiClient.js
 * @description Client HTTP pour interroger l'API Laravel.
 * Conçu pour échouer rapidement et proprement si le serveur n'est pas actif.
 */

// Si non défini dans les variables d'environnement, on peut tenter localhost:8080
const API_BASE_URL = import.meta.env.VITE_API_URL || '';

export const ApiClient = {
  /**
   * Effectue un appel GET avec un timeout de 1.5s pour éviter tout blocage UI.
   * @param {string} endpoint
   * @returns {Promise<any>}
   */
  async get(endpoint) {
    if (!API_BASE_URL) {
      // Aucun backend configuré pour l'instant (mode frontend pur)
      throw new Error('API_BASE_URL non configuré');
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 1500);

    try {
      const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        throw new Error(`Erreur API: ${response.status} ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      clearTimeout(timeoutId);
      throw error;
    }
  },

  /**
   * Effectue un appel POST.
   * @param {string} endpoint
   * @param {Object} data
   * @returns {Promise<any>}
   */
  async post(endpoint, data) {
    if (!API_BASE_URL) {
      throw new Error('API_BASE_URL non configuré');
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      throw new Error(`Erreur API POST: ${response.status} ${response.statusText}`);
    }

    return await response.json();
  },
};

export default ApiClient;
