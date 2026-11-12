// Analyse statique du frontend (#258) : npm run lint
import js from '@eslint/js';
import globals from 'globals';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';

export default [
  { ignores: ['dist/**', 'build/**', 'node_modules/**'] },
  js.configs.recommended,
  react.configs.flat.recommended,
  react.configs.flat['jsx-runtime'],
  reactHooks.configs.flat.recommended,
  jsxA11y.flatConfigs.recommended,
  {
    files: ['**/*.{js,jsx}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser },
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
    settings: { react: { version: 'detect' } },
    rules: {
      // Le projet n'utilise pas PropTypes : les props sont décrites en JSDoc.
      'react/prop-types': 'off',
      // Ces deux règles préparent le React Compiler, qu'on n'utilise pas : elles refusent
      // le chargement de données au montage (useEffect + setState), le modèle de toutes nos pages.
      'react-hooks/set-state-in-effect': 'off',
      'react-hooks/preserve-manual-memoization': 'off',
      // Un tableau qui défile doit être atteignable au clavier (WCAG 2.1.1) : on autorise
      // tabIndex sur les zones role="region".
      'jsx-a11y/no-noninteractive-tabindex': ['error', { roles: ['region'], tags: [] }],
    },
  },
  {
    files: ['**/*.test.{js,jsx}', 'src/test/**', 'vite.config.js'],
    languageOptions: { globals: { ...globals.node, ...globals.vitest } },
  },
];
