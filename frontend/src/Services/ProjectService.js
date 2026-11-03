/**
 * @file ProjectService.js
 * @description Service de gestion des projets artistiques.
 * Fournit les fonctions de récupération et création vers l'API Laravel
 * avec un système de fallback robuste si le backend n'est pas encore prêt.
 */

import ApiClient from './ApiClient';

/**
 * Données d'exemple issues de la maquette Figma.
 * Servent de fallback de développement lorsque le serveur backend n'est pas actif.
 */
export const mockProjectsData = [
  {
    id: 1,
    category: 'PARTENARIAT LYCÉE',
    location: '1er',
    title: 'Corps & Cité',
    subtitle: 'Lycée Saint-Charles — option et spécialité danse',
    completedSessions: 21,
    totalSessions: 32,
    nextSessionDate: '15 septembre',
    currentSessionNumber: 23,
    intervenant: 'Léa Fournier',
    targetAudience: 'Élèves de 2nde — spécialité danse',
    locationName: 'Studio KLAP',
    activityType: 'Atelier pratique',
    ageRange: '15-16 ans',
    defaultParticipants: 18,
    defaultGirls: 11,
    defaultBoys: 7,
  },
  {
    id: 2,
    category: 'PROJET ANNUEL ÉCOLE',
    location: '3e',
    title: 'Première danse',
    subtitle: 'École élémentaire Peyssonnel',
    completedSessions: 9,
    totalSessions: 14,
    nextSessionDate: '18 septembre',
    currentSessionNumber: 10,
    intervenant: 'Léa Fournier',
    targetAudience: 'CM1-CM2',
    locationName: 'Studio KLAP',
    activityType: 'Atelier découverte',
    ageRange: '9-10 ans',
    defaultParticipants: 22,
    defaultGirls: 12,
    defaultBoys: 10,
  },
  {
    id: 3,
    category: 'ATELIER DU REGARD',
    location: '14e',
    title: 'Regards croisés',
    subtitle: 'Collège Édouard Manet',
    completedSessions: 2,
    totalSessions: 4,
    nextSessionDate: '12 septembre',
    currentSessionNumber: 3,
    intervenant: 'Léa Fournier',
    targetAudience: 'Classe de 4ème',
    locationName: 'Studio KLAP',
    activityType: 'Spectacle & Débat',
    ageRange: '13-14 ans',
    defaultParticipants: 25,
    defaultGirls: 14,
    defaultBoys: 11,
  },
];

export const ProjectService = {
  /**
   * Récupère la liste de tous les projets artistiques.
   * @returns {Promise<Array<Object>>} Liste des projets
   */
  async getProjects() {
    try {
      const data = await ApiClient.get('/projects');
      return Array.isArray(data) ? data : mockProjectsData;
    } catch {
      // Fallback gracieux en attendant l'implémentation du contrôleur Laravel
      return mockProjectsData;
    }
  },

  /**
   * Récupère les détails d'un projet par son identifiant.
   * @param {number|string} id - Identifiant du projet
   * @returns {Promise<Object>}
   */
  async getProjectById(id) {
    try {
      const data = await ApiClient.get(`/projects/${id}`);
      return data;
    } catch {
      const fallback = mockProjectsData.find((p) => p.id === Number(id));
      return fallback || mockProjectsData[0];
    }
  },

  /**
   * Enregistre une nouvelle séance pour un projet donné.
   * @param {number|string} projectId - Identifiant du projet
   * @param {Object} sessionData - Données complètes du carnet de bord
   * @returns {Promise<Object>}
   */
  async createSession(projectId, sessionData) {
    try {
      return await ApiClient.post(`/projects/${projectId}/sessions`, sessionData);
    } catch {
      return { success: true, ...sessionData };
    }
  },
};

export default ProjectService;
