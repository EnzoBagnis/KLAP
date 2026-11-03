/**
 * @file App.jsx
 * @description Composant racine de l'application KLAP.
 * Gère la navigation entre le Dashboard et le Carnet de bord (Ajouter une séance).
 * Conserve l'état des projets en mémoire pour une réactivité instantanée sans rechargement.
 */

import React, { useState, useEffect } from 'react';
import { AuthProvider } from './Context/AuthContext';
import DashboardPage from './Pages/Dashboard/DashboardPage';
import AddSessionPage from './Pages/Logbook/AddSessionPage';
import ProjectService, { mockProjectsData } from './Services/ProjectService';
import './assets/css/GlobalStyles.css';

function MainContent() {
  const [currentView, setCurrentView] = useState('dashboard');
  const [projects, setProjects] = useState(mockProjectsData);
  const [selectedProject, setSelectedProject] = useState(null);

  // Tentative de synchronisation initiale avec le backend au démarrage uniquement
  useEffect(() => {
    let isMounted = true;
    ProjectService.getProjects()
      .then((data) => {
        if (isMounted && Array.isArray(data) && data.length > 0) {
          setProjects(data);
        }
      })
      .catch(() => {
        // En cas d'indisponibilité du backend, on conserve mockProjectsData
      });

    return () => {
      isMounted = false;
    };
  }, []);

  /**
   * Ouvre le carnet de bord pour ajouter une séance sur un projet donné.
   * @param {Object} project
   */
  const handleNavigateToAddSession = (project) => {
    setSelectedProject(project || projects[0]);
    setCurrentView('add-session');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  /**
   * Navigation vers la vue détaillée d'un projet.
   * @param {Object} project
   */
  const handleNavigateToProject = (project) => {
    alert(`Ouverture du projet "${project.title}" (détails du projet)`);
  };

  /**
   * Retourne immédiatement sur le Dashboard sans perdre les données.
   */
  const handleBackToDashboard = () => {
    setCurrentView('dashboard');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  /**
   * Enregistrement final d'une séance (Étape 6).
   * Met à jour localement le projet concerné.
   * @param {Object} sessionData
   */
  const handleSaveSession = (sessionData) => {
    if (selectedProject) {
      setProjects((prev) =>
        prev.map((p) => {
          if (p.id === selectedProject.id) {
            return {
              ...p,
              completedSessions: Math.min(p.totalSessions, p.completedSessions + 1),
              currentSessionNumber: Number(sessionData.sessionNumber) + 1,
            };
          }
          return p;
        })
      );
    }

    alert(`Séance nº ${sessionData.sessionNumber} enregistrée avec succès !`);
    handleBackToDashboard();
  };

  return (
    <>
      {currentView === 'dashboard' && (
        <DashboardPage
          projects={projects}
          onNavigateToAddSession={handleNavigateToAddSession}
          onNavigateToProject={handleNavigateToProject}
        />
      )}

      {currentView === 'add-session' && (
        <AddSessionPage
          project={selectedProject || projects[0]}
          onBack={handleBackToDashboard}
          onSave={handleSaveSession}
        />
      )}
    </>
  );
}

export function App() {
  return (
    <AuthProvider>
      <MainContent />
    </AuthProvider>
  );
}

export default App;
