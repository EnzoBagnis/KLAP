/**
 * @file AddSessionPage.jsx
 * @description Composant complet du carnet de bord (formulaire d'ajout / édition de séance en 6 étapes).
 * Conforme au design Figma, sans aucune balise <div>, 100% balisage sémantique HTML5.
 */

import React, { useState, useEffect, useCallback } from 'react';
import useAuth from '../../Hooks/useAuth';
import '../../assets/css/FormStyles.css';

/**
 * @typedef {Object} Step
 * @property {number} id - Numéro d'ordre de l'étape
 * @property {string} label - Libellé affiché dans le stepper
 */
const STEPS = [
  { id: 1, label: 'Généralités' },
  { id: 2, label: 'Objectifs' },
  { id: 3, label: 'La séance' },
  { id: 4, label: 'Les participants' },
  { id: 5, label: 'Bilan intervenant' },
  { id: 6, label: 'La suite' },
];

/**
 * Retourne l'heure locale formatée au format HH:MM.
 * @returns {string} Exemple: "15:35"
 */
const getFormattedCurrentTime = () => {
  return new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date());
};

/**
 * Composant principal de la page d'ajout / saisie de séance.
 *
 * @param {Object} props
 * @param {Object} [props.project] - Projet artistique concerné
 * @param {Function} props.onBack - Callback de retour vers le Dashboard
 * @param {Function} [props.onSave] - Callback lors de l'enregistrement final
 * @returns {JSX.Element}
 */
export const AddSessionPage = ({ project, onBack, onSave }) => {
  const { user } = useAuth();
  const [activeStep, setActiveStep] = useState(1);
  const [savedTime, setSavedTime] = useState(getFormattedCurrentTime);

  // État complet du formulaire pour les 6 étapes
  const [formData, setFormData] = useState({
    // Étape 1 : Généralités
    sessionNumber: project?.currentSessionNumber || 23,
    date: '15/09/2026',
    location: project?.locationName || 'Studio KLAP',
    activityType: project?.activityType || 'Atelier pratique',
    publicTarget: project?.targetAudience || 'Élèves de 2nde — spécialité danse',
    participantsCount: project?.defaultParticipants || 18,
    girlsCount: project?.defaultGirls || 11,
    boysCount: project?.defaultBoys || 7,
    ageRange: project?.ageRange || '15-16 ans',
    intervenant: project?.intervenant || user?.fullName || 'Léa Fournier',
    provenance: project?.location || '1er',

    // Étape 2 : Objectifs
    objectifsPedagogiques:
      'Développer la conscience corporelle et le vocabulaire chorégraphique. Préparer les épreuves de la spécialité danse au baccalauréat.',
    objectifsArtistiques:
      "Construire une écriture collective à partir de l'improvisation. Aborder la composition dans l'espace scénique.",
    progressionPrecedente: '',
    competencesTravaillees: '',

    // Étape 3 : La séance
    tempsForts: '',
    anecdotes: '',
    introSeance: '',
    exercices: [
      { id: 1, text: '' },
    ],
    clotureSeance: '',
    retoursParticipants: '',
    outilsSupports: '',
    ajustementsSeance: '',

    // Étape 4 : Les participants (Évaluations 1-5 & Observations)
    evalEngagement: { score: null, note: '' },
    evalExpression: { score: null, note: '' },
    evalAutonomie: { score: null, note: '' },
    evalInteraction: { score: null, note: '' },
    evalSensations: { score: null, note: '' },

    // Étape 5 : Bilan intervenant
    ceQuiFonctionne: '',
    aSurveiller: '',
    aAmeliorer: '',
    notesLibres: '',

    // Étape 6 : La suite
    continuiteTransition: '',
    ideesParticipants: '',
    ressourcesMobiliser: '',
  });

  /**
   * Met à jour un champ du formulaire et rafraîchit l'heure du brouillon.
   * @param {string} field - Nom de la clé
   * @param {*} value - Nouvelle valeur
   */
  const handleChange = useCallback((field, value) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));
    setSavedTime(getFormattedCurrentTime());
  }, []);

  /**
   * Gestion de l'évaluation sur 5 pour l'étape 4.
   * @param {string} criterion - Nom du critère (evalEngagement, etc.)
   * @param {number} score - Score entre 1 et 5
   */
  const handleScoreChange = useCallback((criterion, score) => {
    setFormData((prev) => ({
      ...prev,
      [criterion]: {
        ...prev[criterion],
        score: prev[criterion]?.score === score ? null : score,
      },
    }));
    setSavedTime(getFormattedCurrentTime());
  }, []);

  /**
   * Gestion du texte d'observation d'un critère.
   * @param {string} criterion - Nom du critère
   * @param {string} note - Commentaire
   */
  const handleScoreNoteChange = useCallback((criterion, note) => {
    setFormData((prev) => ({
      ...prev,
      [criterion]: {
        ...prev[criterion],
        note,
      },
    }));
    setSavedTime(getFormattedCurrentTime());
  }, []);

  /**
   * Ajout d'une ligne d'exercice (Étape 3).
   */
  const handleAddExercise = useCallback(() => {
    setFormData((prev) => ({
      ...prev,
      exercices: [
        ...prev.exercices,
        { id: Date.now(), text: '' },
      ],
    }));
    setSavedTime(getFormattedCurrentTime());
  }, []);

  /**
   * Modification d'un exercice par son index.
   * @param {number} index - Index de l'exercice
   * @param {string} text - Contenu de la consigne
   */
  const handleExerciseChange = useCallback((index, text) => {
    setFormData((prev) => {
      const updated = [...prev.exercices];
      updated[index] = { ...updated[index], text };
      return { ...prev, exercices: updated };
    });
    setSavedTime(getFormattedCurrentTime());
  }, []);

  /**
   * Suppression d'un exercice.
   * @param {number} index - Index de l'exercice à supprimer
   */
  const handleRemoveExercise = useCallback((index) => {
    setFormData((prev) => {
      const updated = prev.exercices.filter((_, i) => i !== index);
      return {
        ...prev,
        exercices: updated.length > 0 ? updated : [{ id: Date.now(), text: '' }],
      };
    });
    setSavedTime(getFormattedCurrentTime());
  }, []);

  /**
   * Passage à l'étape suivante ou enregistrement.
   */
  const handleNext = () => {
    if (activeStep < STEPS.length) {
      setActiveStep((prev) => prev + 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else if (onSave) {
      onSave(formData);
    }
  };

  /**
   * Retour à l'étape précédente.
   */
  const handlePrev = () => {
    if (activeStep > 1) {
      setActiveStep((prev) => prev - 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const projectTitle = project?.title || 'Corps & Cité';

  // Calcul du pourcentage de la barre rouge du stepper
  const progressPercentage = (activeStep / STEPS.length) * 100;

  return (
    <main className="session-form-container">
      {/* Barre supérieure : retour et horodatage réel */}
      <header className="session-top-bar">
        <button type="button" className="btn-back" onClick={onBack}>
          ← Retour
        </button>

        <aside className="draft-status">
          <span className="status-dot" aria-hidden="true" />
          <span>Brouillon enregistré · {savedTime}</span>
        </aside>
      </header>

      {/* Titres de la séance */}
      <header className="session-header">
        <p className="session-project-subtitle">
          {projectTitle.toUpperCase()} — CARNET DE BORD
        </p>
        <h1 className="session-main-title">
          Séance nº {formData.sessionNumber}
        </h1>
      </header>

      {/* Stepper avec indicateur rouge progressif */}
      <section className="stepper-container" aria-label="Progression du carnet de bord">
        <span
          className="stepper-progress-line"
          style={{ width: `${progressPercentage}%` }}
          aria-hidden="true"
        />
        <nav className="session-stepper">
          {STEPS.map((step) => {
            const isActive = step.id === activeStep;
            const isCompleted = step.id < activeStep;
            return (
              <button
                key={step.id}
                type="button"
                className={`stepper-step ${isActive ? 'active' : ''} ${isCompleted ? 'completed' : ''}`}
                onClick={() => setActiveStep(step.id)}
              >
                <span className="step-number">{step.id}</span>
                <span className="step-label">{step.label}</span>
              </button>
            );
          })}
        </nav>
      </section>

      {/* ============================================================== */}
      {/* ÉTAPE 1 : GÉNÉRALITÉS                                         */}
      {/* ============================================================== */}
      {activeStep === 1 && (
        <form
          className="form-grid-2cols"
          onSubmit={(e) => {
            e.preventDefault();
            handleNext();
          }}
        >
          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="sessionNumber">
                Numéro de séance
              </label>
            </header>
            <input
              id="sessionNumber"
              type="text"
              className="field-input"
              value={formData.sessionNumber}
              onChange={(e) => handleChange('sessionNumber', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="sessionDate">
                Date <span className="required-star">*</span>
              </label>
            </header>
            <span className="field-input-wrapper">
              <input
                id="sessionDate"
                type="text"
                className="field-input"
                value={formData.date}
                onChange={(e) => handleChange('date', e.target.value)}
              />
              <span className="field-input-icon" aria-hidden="true">
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
              </span>
            </span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="sessionLocation">
                Lieu <span className="required-star">*</span>
              </label>
            </header>
            <input
              id="sessionLocation"
              type="text"
              className="field-input"
              value={formData.location}
              onChange={(e) => handleChange('location', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="activityType">
                Type d'activité <span className="required-star">*</span>
              </label>
            </header>
            <input
              id="activityType"
              type="text"
              className="field-input"
              value={formData.activityType}
              onChange={(e) => handleChange('activityType', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="publicTarget">
                Public
              </label>
            </header>
            <input
              id="publicTarget"
              type="text"
              className="field-input"
              value={formData.publicTarget}
              onChange={(e) => handleChange('publicTarget', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="participantsCount">
                Nombre de participants <span className="required-star">*</span>
              </label>
            </header>
            <input
              id="participantsCount"
              type="number"
              className="field-input"
              value={formData.participantsCount}
              onChange={(e) => handleChange('participantsCount', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="girlsCount">
                Filles
              </label>
            </header>
            <input
              id="girlsCount"
              type="number"
              className="field-input"
              value={formData.girlsCount}
              onChange={(e) => handleChange('girlsCount', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="boysCount">
                Garçons
              </label>
            </header>
            <input
              id="boysCount"
              type="number"
              className="field-input"
              value={formData.boysCount}
              onChange={(e) => handleChange('boysCount', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="ageRange">
                Tranche d'âge
              </label>
            </header>
            <input
              id="ageRange"
              type="text"
              className="field-input"
              value={formData.ageRange}
              onChange={(e) => handleChange('ageRange', e.target.value)}
            />
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="intervenant">
                Intervenant·e <span className="required-star">*</span>
              </label>
            </header>
            <input
              id="intervenant"
              type="text"
              className="field-input"
              value={formData.intervenant}
              onChange={(e) => handleChange('intervenant', e.target.value)}
            />
          </section>

          <section className="form-field project-field-container">
            <header className="field-label-row">
              <label className="field-label" htmlFor="provenance">
                Provenance —<br />
                Arrondissement
              </label>
              <span className="project-field-badge">Champ du projet</span>
            </header>
            <input
              id="provenance"
              type="text"
              className="field-input"
              value={formData.provenance}
              onChange={(e) => handleChange('provenance', e.target.value)}
            />
          </section>
        </form>
      )}

      {/* ============================================================== */}
      {/* ÉTAPE 2 : OBJECTIFS                                           */}
      {/* ============================================================== */}
      {activeStep === 2 && (
        <form
          className="form-stack"
          onSubmit={(e) => {
            e.preventDefault();
            handleNext();
          }}
        >
          {/* Objectifs pédagogiques */}
          <section className="form-field project-field-container">
            <header className="field-label-row">
              <label className="field-label" htmlFor="objectifsPedagogiques">
                Objectifs pédagogiques
              </label>
              <span className="project-field-badge">Champ du projet</span>
            </header>
            <textarea
              id="objectifsPedagogiques"
              className="field-textarea"
              rows="4"
              value={formData.objectifsPedagogiques}
              onChange={(e) => handleChange('objectifsPedagogiques', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">
              {formData.objectifsPedagogiques.length} / 600
            </span>
            <p className="field-helper-text">
              Pré-remplis depuis le projet — modifiables.
            </p>
          </section>

          {/* Objectifs artistiques */}
          <section className="form-field project-field-container">
            <header className="field-label-row">
              <label className="field-label" htmlFor="objectifsArtistiques">
                Objectifs artistiques
              </label>
              <span className="project-field-badge">Champ du projet</span>
            </header>
            <textarea
              id="objectifsArtistiques"
              className="field-textarea"
              rows="4"
              value={formData.objectifsArtistiques}
              onChange={(e) => handleChange('objectifsArtistiques', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">
              {formData.objectifsArtistiques.length} / 600
            </span>
            <p className="field-helper-text">
              Pré-remplis depuis le projet — modifiables.
            </p>
          </section>

          {/* Progression par rapport à la séance précédente */}
          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="progressionPrecedente">
                Progression par rapport à la séance précédente
              </label>
            </header>
            <textarea
              id="progressionPrecedente"
              className="field-textarea"
              rows="4"
              value={formData.progressionPrecedente}
              onChange={(e) => handleChange('progressionPrecedente', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">
              {formData.progressionPrecedente.length} / 600
            </span>
          </section>

          {/* Compétences travaillées */}
          <section className="form-field project-field-container">
            <header className="field-label-row">
              <label className="field-label" htmlFor="competencesTravaillees">
                Compétences travaillées
              </label>
              <span className="project-field-badge">Champ du projet</span>
            </header>
            <textarea
              id="competencesTravaillees"
              className="field-textarea"
              rows="4"
              value={formData.competencesTravaillees}
              onChange={(e) => handleChange('competencesTravaillees', e.target.value)}
              maxLength={400}
            />
            <span className="char-counter">
              {formData.competencesTravaillees.length} / 400
            </span>
            <p className="field-helper-text">
              Référentiel propre à ce projet.
            </p>
          </section>
        </form>
      )}

      {/* ============================================================== */}
      {/* ÉTAPE 3 : LA SÉANCE                                           */}
      {/* ============================================================== */}
      {activeStep === 3 && (
        <form
          className="form-stack"
          onSubmit={(e) => {
            e.preventDefault();
            handleNext();
          }}
        >
          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="tempsForts">
                Temps forts
              </label>
            </header>
            <textarea
              id="tempsForts"
              className="field-textarea"
              rows="3"
              value={formData.tempsForts}
              onChange={(e) => handleChange('tempsForts', e.target.value)}
              maxLength={800}
            />
            <span className="char-counter">{formData.tempsForts.length} / 800</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="anecdotes">
                Anecdotes
              </label>
            </header>
            <textarea
              id="anecdotes"
              className="field-textarea"
              rows="3"
              value={formData.anecdotes}
              onChange={(e) => handleChange('anecdotes', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.anecdotes.length} / 600</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="introSeance">
                Introduction de la séance
              </label>
            </header>
            <textarea
              id="introSeance"
              className="field-textarea"
              rows="3"
              value={formData.introSeance}
              onChange={(e) => handleChange('introSeance', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.introSeance.length} / 600</span>
          </section>

          {/* Liste dynamique d'exercices */}
          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label">Exercices</label>
            </header>
            <section className="exercises-list">
              {formData.exercices.map((ex, index) => (
                <article key={ex.id || index} className="exercise-item">
                  <span className="exercise-number">{index + 1}.</span>
                  <span className="exercise-input-wrapper">
                    <textarea
                      className="field-textarea"
                      rows="2"
                      placeholder="Décrire l'exercice, sa consigne, sa durée..."
                      value={ex.text}
                      onChange={(e) => handleExerciseChange(index, e.target.value)}
                    />
                  </span>
                  <button
                    type="button"
                    className="btn-remove-exercise"
                    onClick={() => handleRemoveExercise(index)}
                    title="Supprimer l'exercice"
                    aria-label={`Supprimer l'exercice ${index + 1}`}
                  >
                    ✕
                  </button>
                </article>
              ))}
            </section>
            <button
              type="button"
              className="btn-add-exercise"
              onClick={handleAddExercise}
            >
              + Ajouter un exercice
            </button>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="clotureSeance">
                Clôture de la séance
              </label>
            </header>
            <textarea
              id="clotureSeance"
              className="field-textarea"
              rows="3"
              value={formData.clotureSeance}
              onChange={(e) => handleChange('clotureSeance', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.clotureSeance.length} / 600</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="retoursParticipants">
                Retours des participants
              </label>
            </header>
            <textarea
              id="retoursParticipants"
              className="field-textarea"
              rows="3"
              value={formData.retoursParticipants}
              onChange={(e) => handleChange('retoursParticipants', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.retoursParticipants.length} / 600</span>
          </section>

          <section className="form-field project-field-container">
            <header className="field-label-row">
              <label className="field-label" htmlFor="outilsSupports">
                Outils / supports utilisés
              </label>
              <span className="project-field-badge">Champ du projet</span>
            </header>
            <input
              id="outilsSupports"
              type="text"
              className="field-input"
              value={formData.outilsSupports}
              onChange={(e) => handleChange('outilsSupports', e.target.value)}
            />
            <p className="field-helper-text">
              Matériel et ressources propres à ce projet.
            </p>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="ajustementsSeance">
                Ajustements en cours de séance
              </label>
            </header>
            <textarea
              id="ajustementsSeance"
              className="field-textarea"
              rows="3"
              value={formData.ajustementsSeance}
              onChange={(e) => handleChange('ajustementsSeance', e.target.value)}
              maxLength={400}
            />
            <span className="char-counter">{formData.ajustementsSeance.length} / 400</span>
          </section>
        </form>
      )}

      {/* ============================================================== */}
      {/* ÉTAPE 4 : LES PARTICIPANTS                                    */}
      {/* ============================================================== */}
      {activeStep === 4 && (
        <section className="participants-layout">
          {/* Colonne gauche : Notations & Observations */}
          <section className="evaluations-col">
            {[
              { key: 'evalEngagement', label: 'Engagement', placeholder: "Observations sur l'engagement..." },
              { key: 'evalExpression', label: 'Expression', placeholder: "Observations sur l'expression..." },
              { key: 'evalAutonomie', label: 'Autonomie', placeholder: "Observations sur l'autonomie..." },
              { key: 'evalInteraction', label: 'Interaction', placeholder: "Observations sur l'interaction..." },
              { key: 'evalSensations', label: 'Sensations', placeholder: "Observations sur les sensations..." },
            ].map((crit) => {
              const currentVal = formData[crit.key];
              const scoreText = currentVal?.score ? `${currentVal.score} / 5` : '— / 5';

              return (
                <article key={crit.key} className="rating-section">
                  <header className="rating-header-row">
                    <h3 className="rating-title">{crit.label}</h3>
                    <span className="rating-score-display">{scoreText}</span>
                  </header>

                  <section className="rating-buttons-group" role="group" aria-label={`Note pour ${crit.label}`}>
                    {[1, 2, 3, 4, 5].map((num) => (
                      <button
                        key={num}
                        type="button"
                        className={`btn-rating ${currentVal?.score === num ? 'selected' : ''}`}
                        onClick={() => handleScoreChange(crit.key, num)}
                      >
                        {num}
                      </button>
                    ))}
                  </section>

                  <textarea
                    className="field-textarea"
                    rows="2"
                    placeholder={crit.placeholder}
                    value={currentVal?.note || ''}
                    onChange={(e) => handleScoreNoteChange(crit.key, e.target.value)}
                  />
                </article>
              );
            })}
          </section>

          {/* Colonne droite : Séance précédente */}
          <aside className="previous-session-sidebar">
            <h4 className="sidebar-title">Séance précédente</h4>

            <article className="sidebar-block">
              <h5 className="sidebar-block-title">Engagement</h5>
              <p className="sidebar-block-text">
                4/5 — Groupe très mobilisé sur l'improvisation.
              </p>
            </article>

            <article className="sidebar-block">
              <h5 className="sidebar-block-title">Autonomie</h5>
              <p className="sidebar-block-text">
                3/5 — Encore besoin de cadre sur les transitions.
              </p>
            </article>

            <article className="sidebar-block">
              <h5 className="sidebar-block-title">À surveiller</h5>
              <p className="sidebar-block-text">
                Deux élèves en retrait à réintégrer.
              </p>
            </article>
          </aside>
        </section>
      )}

      {/* ============================================================== */}
      {/* ÉTAPE 5 : BILAN INTERVENANT                                   */}
      {/* ============================================================== */}
      {activeStep === 5 && (
        <form
          className="form-stack"
          onSubmit={(e) => {
            e.preventDefault();
            handleNext();
          }}
        >
          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="ceQuiFonctionne">
                Ce qui fonctionne
              </label>
            </header>
            <textarea
              id="ceQuiFonctionne"
              className="field-textarea"
              rows="4"
              value={formData.ceQuiFonctionne}
              onChange={(e) => handleChange('ceQuiFonctionne', e.target.value)}
              maxLength={800}
            />
            <span className="char-counter">{formData.ceQuiFonctionne.length} / 800</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="aSurveiller">
                À surveiller
              </label>
            </header>
            <textarea
              id="aSurveiller"
              className="field-textarea"
              rows="4"
              value={formData.aSurveiller}
              onChange={(e) => handleChange('aSurveiller', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.aSurveiller.length} / 600</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="aAmeliorer">
                À améliorer
              </label>
            </header>
            <textarea
              id="aAmeliorer"
              className="field-textarea"
              rows="4"
              value={formData.aAmeliorer}
              onChange={(e) => handleChange('aAmeliorer', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.aAmeliorer.length} / 600</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="notesLibres">
                Notes libres
              </label>
            </header>
            <textarea
              id="notesLibres"
              className="field-textarea"
              rows="4"
              value={formData.notesLibres}
              onChange={(e) => handleChange('notesLibres', e.target.value)}
              maxLength={1000}
            />
            <span className="char-counter">{formData.notesLibres.length} / 1000</span>
          </section>
        </form>
      )}

      {/* ============================================================== */}
      {/* ÉTAPE 6 : LA SUITE                                            */}
      {/* ============================================================== */}
      {activeStep === 6 && (
        <form
          className="form-stack"
          onSubmit={(e) => {
            e.preventDefault();
            handleNext();
          }}
        >
          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="continuiteTransition">
                Continuité ou transition envisagée
              </label>
            </header>
            <textarea
              id="continuiteTransition"
              className="field-textarea"
              rows="4"
              value={formData.continuiteTransition}
              onChange={(e) => handleChange('continuiteTransition', e.target.value)}
              maxLength={800}
            />
            <span className="char-counter">{formData.continuiteTransition.length} / 800</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="ideesParticipants">
                Idées proposées par les participants
              </label>
            </header>
            <textarea
              id="ideesParticipants"
              className="field-textarea"
              rows="4"
              value={formData.ideesParticipants}
              onChange={(e) => handleChange('ideesParticipants', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.ideesParticipants.length} / 600</span>
          </section>

          <section className="form-field">
            <header className="field-label-row">
              <label className="field-label" htmlFor="ressourcesMobiliser">
                Ressources à mobiliser
              </label>
            </header>
            <textarea
              id="ressourcesMobiliser"
              className="field-textarea"
              rows="4"
              value={formData.ressourcesMobiliser}
              onChange={(e) => handleChange('ressourcesMobiliser', e.target.value)}
              maxLength={600}
            />
            <span className="char-counter">{formData.ressourcesMobiliser.length} / 600</span>
          </section>
        </form>
      )}

      {/* Ligne de séparation footer */}
      <hr className="form-footer-separator" />

      {/* Navigation footer */}
      <footer className="form-footer-navigation">
        <button
          type="button"
          className="btn-nav-prev"
          onClick={handlePrev}
          disabled={activeStep === 1}
        >
          — Précédent
        </button>

        <span className="step-indicator-text">
          Étape {activeStep} / {STEPS.length}
        </span>

        {activeStep < STEPS.length ? (
          <button type="button" className="btn-nav-next" onClick={handleNext}>
            Suivant →
          </button>
        ) : (
          <button
            type="button"
            className="btn-nav-next"
            style={{ padding: '10px 24px' }}
            onClick={handleNext}
          >
            Enregistrer la fiche
          </button>
        )}
      </footer>
    </main>
  );
};

export default AddSessionPage;
