import { useEffect, useRef, useState } from 'react';
import useAuth from '../../Hooks/useAuth.js';
import { createLocation } from '../../Services/ReferenceService.js';
import Button from '../UI/Button.jsx';
import SelectInput from './SelectInput.jsx';
import TextInput from './TextInput.jsx';

const NEW = '__nouveau__';
const EMPTY = { name: '', postal_code: '', city: '', address: '' };
const label = (place) => `${place.name} (${place.city})`;

function validate(draft) {
  const errors = {};
  if (!draft.name.trim()) errors.name = 'Indiquez le nom du lieu.';
  if (!/^\d{5}$/.test(draft.postal_code.trim())) errors.postal_code = 'Le code postal doit compter 5 chiffres.';
  if (!draft.city.trim()) errors.city = 'Indiquez la ville.';
  return errors;
}

/*
 * Menu des lieux avec « Nouveau lieu… » (#135) : « chacun peut créer un nouveau lieu (projets
 * en tournée) ; nom, code postal et ville obligatoires » (contexte client). Les champs se
 * déplient dans le formulaire parent (pas de formulaire imbriqué) ; le lieu créé est ajouté
 * à la liste par `onCreated` et sélectionné.
 */
export default function LocationSelect({ locations, value, onChange, onCreated, error, label: fieldLabel = 'Lieu', name, required }) {
  const { logout } = useAuth();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [failure, setFailure] = useState('');
  const [saving, setSaving] = useState(false);
  const panelRef = useRef(null);
  const selectRef = useRef(null);

  useEffect(() => {
    if (open) panelRef.current?.querySelector('input')?.focus();
  }, [open]);

  function choose(event) {
    if (event.target.value === NEW) {
      setOpen(true);
      return;
    }
    // Un lieu existant choisi pendant la saisie d'un nouveau : on abandonne le nouveau.
    if (open) {
      setOpen(false);
      setDraft(EMPTY);
      setErrors({});
      setFailure('');
    }
    onChange(event.target.value);
  }

  function close() {
    setOpen(false);
    setDraft(EMPTY);
    setErrors({});
    setFailure('');
    selectRef.current?.focus();
  }

  async function add() {
    if (saving) return;
    setFailure('');
    const found = validate(draft);
    setErrors(found);
    const first = Object.keys(found)[0];
    if (first) {
      panelRef.current?.querySelector(`[name="nouveau_lieu_${first}"]`)?.focus();
      return;
    }
    setSaving(true);
    try {
      const place = await createLocation({
        name: draft.name.trim(),
        postal_code: draft.postal_code.trim(),
        city: draft.city.trim(),
        address: draft.address.trim() || null,
      });
      onCreated(place);
      onChange(String(place.id));
      close();
    } catch (failed) {
      if (failed.status === 401) {
        logout();
      } else if (failed.status === 422 && Object.keys(failed.errors ?? {}).some((key) => key in EMPTY)) {
        const fieldErrors = Object.fromEntries(Object.entries(failed.errors).map(([key, messages]) => [key, messages[0]]));
        setErrors(fieldErrors);
        // Le focus sur le champ refusé fait lire son message par les lecteurs d'écran.
        const first = Object.keys(EMPTY).find((key) => fieldErrors[key]);
        requestAnimationFrame(() => panelRef.current?.querySelector(`[name="nouveau_lieu_${first}"]`)?.focus());
      } else if (failed.status === 422) {
        setFailure(failed.message || 'Le lieu n’a pas pu être créé.');
      } else {
        setFailure(failed.status === 0 ? failed.message : 'Le lieu n’a pas pu être créé. Réessayez dans un instant.');
      }
    } finally {
      setSaving(false);
    }
  }

  // Entrée dans un champ du lieu : on crée le lieu au lieu d'envoyer le formulaire parent.
  // Sur un bouton, Entrée garde son effet (Annuler annule).
  function onKeyDown(event) {
    if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
      event.preventDefault();
      add();
    }
    if (event.key === 'Escape') {
      event.stopPropagation();
      close();
    }
  }

  const set = (key) => (event) => setDraft({ ...draft, [key]: event.target.value });

  return (
    <div className="location-select">
      <SelectInput
        ref={selectRef}
        label={fieldLabel}
        name={name}
        required={required}
        value={value}
        onChange={choose}
        error={error}
        options={[
          ...locations.map((place) => ({ value: String(place.id), label: label(place) })),
          { value: NEW, label: 'Nouveau lieu…' },
        ]}
      />
      {open && (
        <div ref={panelRef} className="location-new" role="group" aria-label="Nouveau lieu" onKeyDown={onKeyDown}>
          {failure && (
            <p className="form-alert" role="alert">
              {failure}
            </p>
          )}
          <TextInput label="Nom du lieu" name="nouveau_lieu_name" required maxLength={150} value={draft.name} onChange={set('name')} error={errors.name} />
          <div className="form-row">
            <TextInput
              label="Code postal"
              name="nouveau_lieu_postal_code"
              required
              inputMode="numeric"
              maxLength={5}
              value={draft.postal_code}
              onChange={set('postal_code')}
              error={errors.postal_code}
            />
            <TextInput label="Ville" name="nouveau_lieu_city" required maxLength={100} value={draft.city} onChange={set('city')} error={errors.city} />
          </div>
          <TextInput label="Adresse" name="nouveau_lieu_address" maxLength={255} value={draft.address} onChange={set('address')} error={errors.address} />
          <div className="location-new-actions">
            <Button variant="ghost" onClick={close} disabled={saving}>
              Annuler
            </Button>
            <Button variant="line" onClick={add} disabled={saving}>
              {saving ? 'Ajout…' : 'Ajouter le lieu'}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
