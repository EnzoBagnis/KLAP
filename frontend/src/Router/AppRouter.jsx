import { Route, Routes } from 'react-router-dom';

// Point d'entrée des routes. Chaque écran s'ajoute ici quand il est développé,
// avec son chemin en français (ex. /projets, /planning), comme dans la maquette.
export default function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<Placeholder title="KLAP · Carnet de bord" />} />
      <Route path="*" element={<Placeholder title="Page introuvable" />} />
    </Routes>
  );
}

// Écran provisoire, à supprimer quand les vraies pages seront branchées.
function Placeholder({ title }) {
  return (
    <main className="placeholder">
      <h1>{title}</h1>
      <p>Application en cours de développement.</p>
    </main>
  );
}
