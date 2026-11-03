/**
 * @file DashboardPage.jsx
 * @description Vue principale du tableau de bord "Vos projets en cours".
 * Sans balise <div>, 100% sémantique HTML5.
 */

import React from 'react';
import useAuth from '../../Hooks/useAuth';
import ProjectCard from '../../Components/Dashboard/ProjectCard';
import { mockProjectsData } from '../../Services/ProjectService';
import '../../assets/css/DashboardStyles.css';

/**
 * Composant DashboardPage
 *
 * @param {Object} props
 * @param {Array<Object>} [props.projects] - Liste des projets transmise par le parent
 * @param {Function} [props.onNavigateToAddSession] - Navigation vers l'ajout de séance
 * @param {Function} [props.onNavigateToProject] - Navigation vers le détail du projet
 * @returns {JSX.Element}
 */
export const DashboardPage = ({
  projects = mockProjectsData,
  onNavigateToAddSession,
  onNavigateToProject,
}) => {
  const { user } = useAuth();

  // Salutation dynamique avec le nom de l'utilisateur connecté
  const userName = user?.name ? user.name.toUpperCase() : 'LÉA';

  const projectList = Array.isArray(projects) && projects.length > 0 ? projects : mockProjectsData;

  return (
    <main className="dashboard-container">
      {/* En-tête */}
      <header className="dashboard-header">
        <p className="dashboard-user-greeting">Bonjour {userName}</p>
        <section className="dashboard-title-row">
          <h1 className="dashboard-title">Vos projets en cours</h1>
          <button
            type="button"
            className="btn-add-primary"
            onClick={() => onNavigateToAddSession && onNavigateToAddSession(projectList[0])}
          >
            + Ajouter une séance
          </button>
        </section>
      </header>

      {/* Ligne de séparation */}
      <hr className="dashboard-separator" />

      {/* Grille de projets sémantique */}
      <section className="projects-grid" aria-label="Liste des projets artistiques">
        {projectList.map((project) => (
          <ProjectCard
            key={project.id}
            project={project}
            onAddSession={(p) => onNavigateToAddSession && onNavigateToAddSession(p)}
            onViewProject={(p) => onNavigateToProject && onNavigateToProject(p)}
          />
        ))}

        {/* Emplacement libre pour équilibrer la grille 2x2 comme sur la maquette */}
        {projectList.length % 2 !== 0 && (
          <aside className="empty-card-slot" aria-hidden="true" />
        )}
      </section>
    </main>
  );
};

export default DashboardPage;
