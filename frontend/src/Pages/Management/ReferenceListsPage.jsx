import ManagementTabs from '../../Components/Management/ManagementTabs.jsx';
import ReferenceList from '../../Components/Management/ReferenceList.jsx';
import { createProjectType, listProjectTypes, updateProjectType } from '../../Services/ProjectService.js';
import { createFunder, createLocation, listAllLocations, listFunders, updateFunder, updateLocation } from '../../Services/ReferenceService.js';

/*
 * Gestion › Listes (#249) : les listes que l'administratrice tient à jour elle-même.
 * Financeurs (destinataires du pré-bilan), types de projet et lieux.
 */
export const FUNDER_TYPES = [
  { value: 'state', label: 'État' },
  { value: 'local_authority', label: 'Collectivité' },
  { value: 'foundation', label: 'Fondation' },
  { value: 'other', label: 'Autre' },
];
const typeLabel = (value) => FUNDER_TYPES.find((t) => t.value === value)?.label ?? 'Autre';
const projects = (n) => (n > 0 ? `${n} projet${n > 1 ? 's' : ''}` : 'Aucun projet');

const FUNDER_FIELDS = [
  { key: 'name', label: 'Nom du financeur', required: true, maxLength: 150, requiredMessage: 'Indiquez le nom du financeur.' },
  { key: 'type', label: 'Catégorie', required: true, options: FUNDER_TYPES, requiredMessage: 'Choisissez la catégorie du financeur.' },
];
const TYPE_FIELDS = [{ key: 'name', label: 'Nom du type de projet', required: true, maxLength: 100, requiredMessage: 'Indiquez le nom du type de projet.' }];
const LOCATION_FIELDS = [
  { key: 'name', label: 'Nom du lieu', required: true, maxLength: 150, requiredMessage: 'Indiquez le nom du lieu.' },
  { key: 'address', label: 'Adresse', maxLength: 255 },
  { key: 'postal_code', label: 'Code postal', required: true, inputMode: 'numeric', pattern: /^\d{5}$/, patternMessage: 'Le code postal doit compter 5 chiffres.', requiredMessage: 'Indiquez le code postal.' },
  { key: 'city', label: 'Ville', required: true, maxLength: 100, requiredMessage: 'Indiquez la ville.' },
];

export default function ReferenceListsPage() {
  return (
    <section className="page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Gestion</h1>
          <p className="page-subtitle">Listes</p>
        </div>
      </div>
      <ManagementTabs />
      <p className="form-hint">
        Les listes proposées dans les formulaires. Rien ne se supprime : un élément désactivé n’est plus proposé, mais les projets et séances qui
        l’utilisent le gardent.
      </p>

      <ReferenceList
        title="Financeurs"
        intro="Les destinataires du pré-bilan. Cochez-les ensuite sur chaque projet qu’ils financent."
        noun="Financeur"
        addLabel="Ajouter un financeur"
        fields={FUNDER_FIELDS}
        load={listFunders}
        create={createFunder}
        update={updateFunder}
        meta={(f) => `${typeLabel(f.type)} · ${projects(f.projects_count ?? 0)}`}
      />
      <ReferenceList
        title="Types de projet"
        intro="Par exemple : partenariat lycée, résidence, parcours collège."
        noun="Type de projet"
        addLabel="Ajouter un type"
        fields={TYPE_FIELDS}
        load={listProjectTypes}
        create={createProjectType}
        update={updateProjectType}
        meta={(t) => projects(t.projects_count ?? 0)}
      />
      <ReferenceList
        title="Lieux"
        intro="Les lieux des séances. Les intervenant·e·s peuvent aussi en ajouter depuis une fiche (projets en tournée)."
        noun="Lieu"
        addLabel="Ajouter un lieu"
        fields={LOCATION_FIELDS}
        load={listAllLocations}
        create={createLocation}
        update={updateLocation}
        meta={(l) => [l.address, `${l.postal_code} ${l.city}`].filter(Boolean).join(' · ') + (l.is_klap ? ' · lieu de KLAP' : '')}
      />
    </section>
  );
}
