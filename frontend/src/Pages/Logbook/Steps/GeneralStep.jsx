import { useEffect, useState } from 'react';
import LocationSelect from '../../../Components/Form/LocationSelect.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';
import { listLocations } from '../../../Services/ReferenceService.js';
import { dayTitle, sessionTypeLabel } from '../../Planning/sessionLabels.js';

/**
 * Étape 1 : les généralités, qu'on reprend de la séance programmée. Seul le lieu se corrige ici
 * (#215) : « le lieu est modifiable par tout utilisateur » (projets en tournée). On l'enregistre
 * avec le brouillon, et il s'applique à la séance. Le reste ne se modifie plus une fois la
 * fiche ouverte (le planning refuse de modifier une séance qui a une fiche).
 */
export default function GeneralStep() {
  const { entry, form, set, errors, readOnlyReason } = useLogbook();
  const session = entry.session;
  const editable = entry.editable && !readOnlyReason;
  const [locations, setLocations] = useState([]);

  useEffect(() => {
    if (!editable) return undefined;
    let current = true;
    // En cas d'échec, on garde au moins le lieu actuel dans le menu : la fiche reste utilisable.
    listLocations()
      .then((list) => current && setLocations(list))
      .catch(() => {});
    return () => {
      current = false;
    };
  }, [editable]);

  // On propose toujours le lieu actuel, même s'il n'est plus dans la liste des lieux actifs.
  const choices = locations.some((place) => place.id === session.location.id) ? locations : [session.location, ...locations];

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
        {!editable && (
          <div>
            <dt>Lieu</dt>
            <dd>{session.location.city ? `${session.location.name} (${session.location.city})` : session.location.name}</dd>
          </div>
        )}
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
      {editable && (
        <LocationSelect
          label="Lieu de la séance"
          name="location_id"
          required
          value={form.location_id}
          onChange={(value) => set('location_id', value)}
          onCreated={(place) => setLocations((list) => [...list, place].sort((a, b) => a.name.localeCompare(b.name, 'fr')))}
          error={errors.location_id}
          locations={choices}
        />
      )}
      <p className="form-hint">
        {editable
          ? 'Le lieu se choisit ici, ou se crée avec « Nouveau lieu… » (un projet en tournée, par exemple) : il change aussi au planning. Les autres informations ne se modifient plus une fois la fiche ouverte.'
          : 'Ces informations viennent du planning.'}
      </p>
    </>
  );
}
