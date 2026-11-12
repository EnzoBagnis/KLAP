import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import { ROLES } from '../../Context/roles.js';
import useAuth from '../../Hooks/useAuth.js';
import { getProject, listProjects } from '../../Services/ProjectService.js';
import { downloadPreReport, saveFile } from '../../Services/ReportService.js';
import { formatPeriod } from '../Projects/projectLabels.js';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';
import '../../assets/css/StatisticsStyles.css';

/** Un identifiant de projet dans l'URL : des chiffres seulement. */
const ID = /^\d+$/;
/** Le formulaire du pré-bilan, avant toute saisie. */
const EMPTY_FORM = { funderId: '', from: '', to: '' };

/** Le nom de secours du fichier, si on n'arrive pas à lire son en-tête. */
const fallbackName = (project) => `pre-bilan-${project?.id ?? 'projet'}.docx`;

/**
 * Le pré-bilan (#144) : l'administratrice ou la super-utilisatrice choisit un projet, un de
 * ses financeurs et, si elle veut, une période ; elle reçoit un document Word prêt à
 * retravailler. Le projet peut venir de l'URL (?projet=3), depuis le bouton « Pré-bilan »
 * d'un projet.
 */
export default function PreAssessmentPage() {
  const { user, logout } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const projectId = ID.test(searchParams.get('projet') ?? '') ? searchParams.get('projet') : '';
  const [projects, setProjects] = useState({ status: 'loading', list: [] });
  const [project, setProject] = useState({ status: 'idle', data: null });
  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState(null);
  const [busy, setBusy] = useState(false);
  const lastProject = useRef(0);

  useEffect(() => {
    document.title = 'Pré-bilan · KLAP';
  }, []);

  // On inclut les archivés : un projet terminé et archivé a encore besoin de son bilan.
  useEffect(() => {
    let active = true;
    listProjects({ includeArchived: true })
      .then((list) => active && setProjects({ status: 'ready', list }))
      .catch((error) => {
        if (!active) return;
        if (error.status === 401) logout();
        else setProjects({ status: 'error', list: [] });
      });
    return () => {
      active = false;
    };
  }, [logout]);

  // Les financeurs viennent du détail du projet choisi ; s'il n'y en a qu'un, on le présélectionne.
  useEffect(() => {
    const request = ++lastProject.current;
    setBusy(false);
    setForm(EMPTY_FORM);
    setErrors({});
    setMessage(null);
    if (!projectId) {
      setProject({ status: 'idle', data: null });
      return;
    }
    setProject({ status: 'loading', data: null });
    getProject(projectId)
      .then((data) => {
        if (request !== lastProject.current) return;
        setProject({ status: 'ready', data });
        if (data.funders?.length === 1) setForm({ ...EMPTY_FORM, funderId: String(data.funders[0].id) });
      })
      .catch((error) => {
        if (request !== lastProject.current) return;
        if (error.status === 401) {
          logout();
          return;
        }
        setProject({ status: error.status === 404 ? 'missing' : 'error', data: null });
      });
  }, [projectId, logout]);

  function chooseProject(event) {
    const value = event.target.value;
    setSearchParams(value ? { projet: value } : {}, { replace: true });
  }

  function validate() {
    const found = {};
    if (!form.funderId) found.funder_id = 'Choisissez le financeur.';
    if (form.from && form.to && form.to < form.from) found.to = 'La fin de la période doit être après son début.';
    return found;
  }

  async function submit(event) {
    event.preventDefault();
    setMessage(null);
    const found = validate();
    setErrors(found);
    if (Object.keys(found).length) return;

    // Si on a changé de projet pendant la préparation, on enregistre quand même le fichier, mais
    // on n'affiche pas le message ni les erreurs de l'ancien projet sous le formulaire du nouveau.
    const request = lastProject.current;
    const current = () => request === lastProject.current;
    setBusy(true);
    try {
      const { blob, filename } = await downloadPreReport(projectId, form);
      const name = filename ?? fallbackName(project.data);
      saveFile(blob, name);
      if (current()) setMessage({ tone: 'success', text: `Pré-bilan téléchargé : ${name}` });
    } catch (error) {
      if (!current()) return;
      if (error.status === 401) {
        logout();
        return;
      }
      if (error.status === 422) {
        setErrors(Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]])));
      } else {
        setMessage({
          tone: 'error',
          text:
            error.status === 0
              ? error.message
              : error.status === 404
                ? 'Ce projet n’existe plus.'
                : 'Le pré-bilan n’a pas pu être créé. Réessayez dans un instant.',
        });
      }
    } finally {
      if (current()) setBusy(false);
    }
  }

  // On garde le projet de l'URL dans le menu, même s'il n'est pas (encore) dans la liste.
  const options = projects.list.map((item) => ({
    value: String(item.id),
    label: item.status === 'archived' ? `${item.name} (archivé)` : item.name,
  }));
  if (projectId && !options.some((option) => option.value === projectId)) {
    options.push({ value: projectId, label: project.data?.name ?? `Projet n° ${projectId}` });
  }

  const funders = project.data?.funders ?? [];
  const period = formatPeriod(project.data?.start_date, project.data?.end_date);

  return (
    <section className="page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Pré-bilan</h1>
          <p className="page-subtitle">Un document Word prêt à retravailler, pour un financeur du projet.</p>
        </div>
      </div>

      <form className="project-form pre-report-form" onSubmit={submit} noValidate aria-label="Pré-bilan">
        <SelectInput
          label="Projet"
          required
          placeholder={projects.status === 'loading' ? 'Chargement des projets…' : 'Choisir un projet'}
          value={projectId}
          onChange={chooseProject}
          options={options}
        />
        {projects.status === 'error' && (
          <p className="form-alert" role="alert">
            La liste des projets n’a pas pu être chargée. Rechargez la page.
          </p>
        )}

        {project.status === 'loading' && <p className="form-hint">Chargement des financeurs…</p>}
        {project.status === 'missing' && (
          <p className="form-alert" role="alert">
            Ce projet n’existe pas ou ne vous est pas accessible.
          </p>
        )}
        {project.status === 'error' && (
          <p className="form-alert" role="alert">
            Le projet n’a pas pu être chargé. Réessayez dans un instant.
          </p>
        )}

        {project.status === 'ready' && funders.length === 0 && (
          <p className="form-alert" role="alert">
            Ce projet n’a aucun financeur : le pré-bilan s’adresse à un financeur.
            {user?.role === ROLES.ADMIN && project.data.status === 'archived' ? (
              ' Restaurez d’abord le projet pour lui ajouter un financeur.'
            ) : user?.role === ROLES.ADMIN ? (
              <>
                {' '}
                <Link to={`/gestion/projets/${projectId}/modifier`}>Ajouter un financeur au projet</Link>.
              </>
            ) : (
              ' Voyez avec l’administratrice pour en ajouter un.'
            )}
          </p>
        )}

        {project.status === 'ready' && funders.length > 0 && (
          <>
            <SelectInput
              label="Financeur"
              required
              placeholder="Choisir le financeur"
              value={form.funderId}
              onChange={(event) => setForm({ ...form, funderId: event.target.value })}
              options={funders.map((funder) => ({ value: String(funder.id), label: funder.name }))}
              error={errors.funder_id}
            />
            <fieldset className="pre-report-period">
              <legend className="form-legend">Période (facultative)</legend>
              <p className="form-hint" id="periode-aide">
                Sans dates, tout le projet{period ? ` (${period})` : ''}. Les dates sont celles des séances, bornes incluses.
              </p>
              <div className="form-row">
                <TextInput
                  label="Du"
                  type="date"
                  value={form.from}
                  onChange={(event) => setForm({ ...form, from: event.target.value })}
                  error={errors.from}
                  aria-describedby="periode-aide"
                />
                <TextInput
                  label="Au"
                  type="date"
                  value={form.to}
                  onChange={(event) => setForm({ ...form, to: event.target.value })}
                  error={errors.to}
                  aria-describedby="periode-aide"
                />
              </div>
            </fieldset>

            <p className="form-hint">
              Le document reprend les chiffres de l’écran Statistiques (participants, séances, moyennes), les groupes, la
              provenance et l’âge, puis les temps forts des fiches rendues. Aucun nom d’élève n’y figure. Chaque export est
              enregistré.
            </p>

            <div className="form-actions">
              <Button type="submit" disabled={busy}>
                {busy ? 'Préparation du document…' : 'Télécharger le pré-bilan'}
              </Button>
            </div>
          </>
        )}

        {message && (
          <p className={message.tone === 'success' ? 'form-success' : 'form-alert'} role={message.tone === 'error' ? 'alert' : 'status'}>
            {message.text}
          </p>
        )}
      </form>
    </section>
  );
}
