import useLogbook from '../../../Hooks/useLogbook.js';
import { dayTitle, sessionTypeLabel } from '../../Planning/sessionLabels.js';

// Étape 1 : généralités, héritées de la séance programmée (non modifiables depuis la fiche).
export default function GeneralStep() {
  const { entry, errors } = useLogbook();
  const session = entry.session;

  return (
    <>
      {errors.session && <p className="form-alert" role="alert">{errors.session}</p>}
      <dl className="logbook-facts">
        <div>
          <dt>Projet</dt>
          <dd>{session.project.name}</dd>
        </div>
        <div>
          <dt>Date</dt>
          <dd>{dayTitle(session.session_date)}</dd>
        </div>
        <div>
          <dt>Horaire</dt>
          <dd>
            {session.start_time} – {session.end_time}
          </dd>
        </div>
        <div>
          <dt>Lieu</dt>
          <dd>{session.location.name}</dd>
        </div>
        <div>
          <dt>Type de séance</dt>
          <dd>{sessionTypeLabel(session)}</dd>
        </div>
        <div>
          <dt>Groupes concernés</dt>
          <dd>{session.sub_projects.map((group) => group.name).join(', ')}</dd>
        </div>
        <div>
          <dt>Intervenant·e</dt>
          <dd>{entry.author.name}</dd>
        </div>
      </dl>
      <p className="form-hint">Ces informations viennent du planning. Pour les corriger, voyez avec l’administratrice.</p>
    </>
  );
}
