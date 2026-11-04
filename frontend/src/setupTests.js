import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

// Aucun test ne doit joindre un vrai serveur : par défaut, le réseau est indisponible.
// Les tests qui ont besoin de l'API la simulent avec mockApi (testUtils.js).
beforeEach(() => {
  vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Réseau coupé dans les tests')));
});

afterEach(() => {
  cleanup();
  window.sessionStorage.clear();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});
