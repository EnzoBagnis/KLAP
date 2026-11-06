import { useEffect, useState } from 'react';
import SelectInput from '../../Components/Form/SelectInput.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import ManagementTabs from '../../Components/Management/ManagementTabs.jsx';
import Button from '../../Components/UI/Button.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { createInvitation } from '../../Services/InvitationService.js';
import { formatInstant } from '../Logbook/logbookModel.js';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';

const ROLE_OPTIONS = [
  { value: 'contributor', label: 'Intervenant·e' },
  { value: 'super_user', label: 'Super-utilisatrice' },
];
const EMPTY = { full_name: '', email: '', role: 'contributor' };
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validate(form) {
  const errors = {};
  if (!form.full_name.trim()) errors.full_name = 'Indiquez le nom de la personne invitée.';
  if (!form.email.trim()) errors.email = 'Indiquez l’adresse mail.';
  else if (!EMAIL_PATTERN.test(form.email.trim())) errors.email = 'L’adresse mail n’est pas valide.';
  return errors;
}

/*
 * Gestion, onglet Utilisateurs (maquette, écran 08) : inviter une personne (#127). Elle reçoit
 * un mail avec un lien pour choisir son mot de passe ; son nom et son adresse sont ceux saisis ici.
 * La liste des comptes et des invitations en cours viendra avec la gestion des utilisateurs.
 */
export default function UserManagementPage() {
  const { logout } = useAuth();
  const [form, setForm] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [sent, setSent] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = 'Utilisateurs · Gestion · KLAP';
  }, []);

  const set = (key) => (event) => setForm({ ...form, [key]: event.target.value });

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    setSent(null);
    const found = validate(form);
    setErrors(found);
    const firstInvalid = Object.keys(found)[0];
    if (firstInvalid) {
      event.currentTarget.elements[firstInvalid]?.focus();
      return;
    }

    setSubmitting(true);
    try {
      const invitation = await createInvitation({ ...form, full_name: form.full_name.trim(), email: form.email.trim() });
      setSent(invitation);
      setForm(EMPTY);
      setErrors({});
    } catch (error) {
      if (error.status === 401) {
        logout();
      } else if (error.status === 422) {
        setErrors(Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]])));
      } else {
        setFormError(
          error.status === 0 || error.status === 503 || error.status === 403
            ? error.message
            : 'L’invitation n’a pas pu être envoyée. Réessayez dans un instant.',
        );
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="page">
      <div className="page-heading">
        <div>
          <h1 className="page-title">Gestion</h1>
          <p className="page-subtitle">Utilisateurs</p>
        </div>
      </div>
      <ManagementTabs />

      <h2 className="section-title">Inviter une personne</h2>
      <p className="form-hint">
        La personne reçoit un mail avec un lien pour choisir son mot de passe. Son nom et son adresse ne pourront pas
        être modifiés à l’activation. Réinviter la même adresse remplace l’invitation précédente.
      </p>

      {sent && (
        <p className="form-success" role="status">
          Invitation envoyée à {sent.full_name} ({sent.email}). Le lien est valable jusqu’au {formatInstant(sent.expires_at)}.
          {sent.replaced && ' Elle remplace l’invitation précédente, dont le lien ne fonctionne plus.'}
        </p>
      )}
      {formError && (
        <p className="form-alert" role="alert">
          {formError}
        </p>
      )}

      <form className="project-form invitation-form" onSubmit={handleSubmit} noValidate>
        <TextInput
          label="Nom et prénom"
          name="full_name"
          required
          maxLength={160}
          autoComplete="off"
          value={form.full_name}
          onChange={set('full_name')}
          error={errors.full_name}
        />
        <TextInput
          label="Adresse mail"
          name="email"
          type="email"
          required
          maxLength={190}
          autoComplete="off"
          value={form.email}
          onChange={set('email')}
          error={errors.email}
        />
        <SelectInput
          label="Rôle"
          name="role"
          required
          placeholder="Choisir un rôle"
          options={ROLE_OPTIONS}
          value={form.role}
          onChange={set('role')}
          error={errors.role}
        />
        <div>
          <Button type="submit" disabled={submitting}>
            {submitting ? 'Envoi…' : 'Envoyer l’invitation'}
          </Button>
        </div>
      </form>
    </section>
  );
}
