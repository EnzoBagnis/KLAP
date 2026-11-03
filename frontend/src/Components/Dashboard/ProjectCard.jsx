/**
 * @file ProjectCard.jsx
 * @description Carte individuelle de projet artistique sur le Dashboard KLAP.
 * Conforme au design Figma, sans balise <div>, 100% sémantique HTML5.
 */

import React from 'react';

/**
 * @typedef {Object} Project
 * @property {number} id - Identifiant unique du projet
 * @property {string} category - Catégorie (ex: PARTENARIAT LYCÉE)
 * @property {string} location - Arrondissement ou localisation (ex: 1er)
 * @property {string} title - Titre du projet artistique
 * @property {string} subtitle - Établissement scolaire / description
 * @property {number} completedSessions - Nombre de séances effectuées
 * @property {number} totalSessions - Nombre total de séances prévues
 * @property {string} nextSessionDate - Date de la prochaine séance
 */

/**
 * Composant ProjectCard
 *
 * @param {Object} props
 * @param {Project} props.project - Données du projet artistique
 * @param {Function} [props.onAddSession] - Callback pour ajouter une séance
 * @param {Function} [props.onViewProject] - Callback pour afficher la vue détaillée
 * @returns {JSX.Element}
 */
export const ProjectCard = ({ project, onAddSession, onViewProject }) => {
  const {
    category,
    location,
    title,
    subtitle,
    completedSessions,
    totalSessions,
    nextSessionDate,
  } = project;

  const percentage = Math.min(
    100,
    Math.round((completedSessions / totalSessions) * 100)
  );

  return (
    <article className="project-card">
      <section className="card-body">
        {/* En-tête de la carte */}
        <header className="card-top">
          <span className="card-category-badge">{category}</span>
          <span className="card-location-tag">{location}</span>
        </header>

        {/* Titre & Sous-titre */}
        <section className="card-main-info">
          <h2 className="card-title">{title}</h2>
          <p className="card-subtitle">{subtitle}</p>
        </section>

        {/* Métriques */}
        <section className="card-metrics">
          <p className="metric-block">
            <span className="metric-label">Séances</span>
            <span className="metric-value-sessions">
              {completedSessions} <span className="total">/ {totalSessions}</span>
            </span>
          </p>
          <p className="metric-block">
            <span className="metric-label" style={{ textAlign: 'right' }}>
              Prochaine
            </span>
            <span className="metric-value-next">{nextSessionDate}</span>
          </p>
        </section>

        {/* Barre de progression rouge */}
        <figure
          className="progress-bar-track"
          role="progressbar"
          aria-valuenow={completedSessions}
          aria-valuemin="0"
          aria-valuemax={totalSessions}
        >
          <span
            className="progress-bar-fill"
            style={{ width: `${percentage}%` }}
          />
        </figure>
      </section>

      {/* Actions */}
      <footer className="card-actions">
        <button
          type="button"
          className="btn-card-add-session"
          onClick={() => onAddSession && onAddSession(project)}
        >
          + Ajouter une séance
        </button>
        <button
          type="button"
          className="btn-card-view-project"
          onClick={() => onViewProject && onViewProject(project)}
        >
          Voir le projet
        </button>
      </footer>
    </article>
  );
};

export default ProjectCard;
