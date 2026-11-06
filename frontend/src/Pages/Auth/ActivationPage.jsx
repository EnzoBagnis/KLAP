import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import { activateInvitation, getInvitation } from '../../Services/InvitationService.js';

// Mêmes classes que l'API (Password::mixedCase, numbers, symbols) : lettres accentuées comprises.
const RULES = [
  ['au moins 8 caractères', (value) => [...value].length >= 8],
  ['une majuscule et une minuscule', (value) => /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value)],
  ['un chiffre', (value) => /\p{N}/u.test(value)],
  ['un caractère spécial (@, #, !…)', (value) => /[\p{S}\p{P}\p{Z}]/u.test(value)],
];

const loadError = (error) =>
  error.status === 0 || error.status === 404 || error.status === 410
    ? error.message
    : error.status === 429
      ? `Trop de tentatives. Réessayez dans ${error.retryAfter ?? 60} secondes.`
      : 'L’invitation n’a pas pu être lue. Réessayez dans un instant.';

/*
 * Activation d'un compte depuis le lien d'invitation (#127), hors connexion. Le nom et
 * l'adresse viennent de l'invitation ; seul le mot de passe se choisit (contexte client).
 */
export default function ActivationPage() {
  const { token } = useParams();
  const navigate = useNavigate();
  const [state, setState] = useState({ status: 'loading', invitation: null, error: '' });
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = 'Activer mon compte · KLAP';
    let cancelled = false;
    getInvitation(token)
      .then((invitation) => !cancelled && setState({ status: 'ready', invitation, error: '' }))
      .catch((error) => !cancelled && setState({ status: 'error', invitation: null, error: loadError(error) }));
    return () => {
      cancelled = true;
    };
  }, [token]);

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    const found = {};
    if (RULES.some(([, test]) => !test(password))) found.password = 'Le mot de passe ne respecte pas toutes les règles.';
    else if (password !== confirmation) found.password_confirmation = 'La confirmation ne correspond pas au mot de passe.';
    setErrors(found);
    const firstInvalid = Object.keys(found)[0];
    if (firstInvalid) {
      event.currentTarget.elements[firstInvalid]?.focus();
      return;
    }

    setSubmitting(true);
    try {
      const account = await activateInvitation(token, password, confirmation);
      navigate('/connexion', { replace: true, state: { email: account.email, activated: true } });
    } catch (error) {
      if (error.status === 422) {
        setErrors(Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]])));
      } else {
        setFormError(
          [0, 404, 409, 410].includes(error.status)
            ? error.message
            : error.status === 429
              ? `Trop de tentatives. Réessayez dans ${error.retryAfter ?? 60} secondes.`
              : 'Le compte n’a pas pu être activé. Réessayez dans un instant.',
        );
      }
      setSubmitting(false);
    }
  }

  if (state.status === 'loading') return <LoadingSpinner label="Lecture de l’invitation…" />;

  if (state.status === 'error') {
    return (
      <>
        <h1 className="auth-title">Activer mon compte</h1>
        <p className="form-alert" role="alert">
          {state.error}
        </p>
        <Link to="/connexion" className="btn btn-line">
          Aller à la connexion
        </Link>
      </>
    );
  }

  const { invitation } = state;
  return (
    <>
      <h1 className="auth-title">Activer mon compte</h1>
      <p className="auth-subtitle">Bienvenue {invitation.full_name}. Choisissez votre mot de passe.</p>

      <form className="auth-form" onSubmit={handleSubmit} noValidate>
        {formError && (
          <p className="form-alert" role="alert">
            {formError}
          </p>
        )}
        <dl className="activation-facts">
          <dt>Nom</dt>
          <dd>{invitation.full_name}</dd>
          <dt>Adresse mail</dt>
          <dd>{invitation.email}</dd>
        </dl>
        <TextInput
          label="Mot de passe"
          name="password"
          type="password"
          autoComplete="new-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={errors.password}
          aria-describedby="regles-mot-de-passe"
        />
        <ul id="regles-mot-de-passe" className="password-rules" aria-label="Le mot de passe doit contenir">
          {RULES.map(([label, test]) => (
            <li key={label} className={test(password) ? 'is-met' : undefined}>
              {label}
              <span className="visually-hidden">{test(password) ? ' : respecté' : ' : manquant'}</span>
            </li>
          ))}
        </ul>
        <TextInput
          label="Confirmer le mot de passe"
          name="password_confirmation"
          type="password"
          autoComplete="new-password"
          required
          value={confirmation}
          onChange={(event) => setConfirmation(event.target.value)}
          error={errors.password_confirmation}
        />
        <Button type="submit" className="btn-block" disabled={submitting}>
          {submitting ? 'Activation…' : 'Activer mon compte'}
        </Button>
      </form>
    </>
  );
}
