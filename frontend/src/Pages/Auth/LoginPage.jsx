import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import useAuth from '../../Hooks/useAuth.js';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/*
 * Traduit une erreur de l'API en message pour l'utilisateur.
 * Identifiants faux et compte désactivé arrivent tous deux en 422 avec un message
 * déjà en français dans `errors.email` : on l'affiche tel quel, sans l'interpréter.
 */
export function loginErrorMessage(error) {
  if (error.status === 0) return error.message;
  if (error.status === 422) {
    return error.errors?.email?.[0] ?? error.errors?.password?.[0] ?? 'Connexion refusée.';
  }
  if (error.status === 429) {
    return error.retryAfter
      ? `Trop de tentatives. Réessayez dans ${error.retryAfter} secondes.`
      : 'Trop de tentatives. Réessayez dans une minute.';
  }
  return 'Une erreur est survenue. Réessayez plus tard.';
}

// Contrôles faits avant l'envoi : réponse immédiate, sans aller-retour avec le serveur.
function validate(email, password) {
  const errors = {};
  if (!email.trim()) errors.email = 'Saisissez votre adresse e-mail.';
  else if (!EMAIL_PATTERN.test(email.trim())) errors.email = 'Cette adresse e-mail n’est pas valide.';
  if (!password) errors.password = 'Saisissez votre mot de passe.';
  return errors;
}

export default function LoginPage() {
  const { status, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Déjà connecté : rien à faire ici.
  if (status === 'authenticated') {
    return <Navigate to="/" replace />;
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');

    const errors = validate(email, password);
    setFieldErrors(errors);
    const firstInvalid = Object.keys(errors)[0];
    if (firstInvalid) {
      // Le focus sur le champ en erreur fait lire son message par les lecteurs d'écran.
      event.currentTarget.elements[firstInvalid]?.focus();
      return;
    }

    setSubmitting(true);
    try {
      await login(email.trim(), password);
      navigate('/', { replace: true });
    } catch (error) {
      setFormError(loginErrorMessage(error));
      setPassword('');
      setSubmitting(false);
    }
  }

  return (
    <>
      <h1 className="auth-title">Connexion</h1>
      <p className="auth-subtitle">Accédez à votre espace de suivi.</p>

      <form className="auth-form" onSubmit={handleSubmit} noValidate>
        {formError && (
          <p className="form-alert" role="alert">
            {formError}
          </p>
        )}

        <TextInput
          label="Adresse e-mail"
          type="email"
          name="email"
          autoComplete="username"
          required
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          error={fieldErrors.email}
        />
        <TextInput
          label="Mot de passe"
          type="password"
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={fieldErrors.password}
        />

        <Button type="submit" className="btn-block" disabled={submitting}>
          {submitting ? 'Connexion…' : 'Se connecter'}
        </Button>
      </form>
    </>
  );
}
