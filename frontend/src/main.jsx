import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

// Les polices de la maquette : on les embarque dans le build, sans aucun appel à un CDN.
import '@fontsource/archivo/400.css';
import '@fontsource/archivo/500.css';
import '@fontsource/archivo/600.css';
import '@fontsource/jetbrains-mono/400.css';

import './assets/css/Variables.css';
import './assets/css/GlobalStyles.css';

import App from './App.jsx';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
