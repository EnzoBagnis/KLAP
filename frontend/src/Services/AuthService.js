import ApiClient from './ApiClient';

// Données de secours pendant que le backend Laravel n'est pas encore branché
export const defaultFallbackUser = {
  id: 1,
  name: 'Léa',
  fullName: 'Léa Fournier',
  email: 'lea.fournier@klap.art',
  role: 'intervenant',
};

export const AuthService = {
  // Récupère l'utilisateur connecté depuis Laravel (ex: GET /api/user)
  // et utilise le fallback si le backend n'est pas encore disponible
  async getCurrentUser() {
    try {
      const data = await ApiClient.get('/user');
      return data;
    } catch {
      // Backend Laravel pas encore démarré ou route non existante :
      // On retourne le profil de test pour permettre le dev du frontend
      return defaultFallbackUser;
    }
  },

  async login(credentials) {
    return ApiClient.post('/login', credentials);
  },

  async logout() {
    return ApiClient.post('/logout', {});
  },
};

export default AuthService;
