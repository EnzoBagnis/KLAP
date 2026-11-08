import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import { requestPasswordReset } from '../../Services/PasswordService.js';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/*
 * Mot de passe oublié (#81), hors connexion : on demande un lien par mail. La réponse est
 * la même que l'adresse existe ou non (l'API ne révèle pas quels comptes existent).
 */
export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [formError, setFormError] = useState('');
  const [sent, setSent] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = 'Mot de passe oublié · KLAP';
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    const value = email.trim();
    const found = !value ? 'Indiquez votre adresse mail.' : !EMAIL_PATTERN.test(value) ? 'L’adresse mail n’est pas valide.' : '';
    setError(found);
    if (found) {
      event.currentTarget.elements.email?.focus();
      return;
    }
    setSubmitting(true);
    try {
      setSent(await requestPasswordReset(value));
    } catch (failure) {
      if (failure.status === 422) setError(failure.errors?.email?.[0] ?? 'L’adresse mail n’est pas valide.');
      else
        setFormError(
          failure.status === 429
            ? `Trop de demandes. Réessayez dans ${failure.retryAfter ?? 60} secondes.`
            : failure.status === 0
              ? failure.message
              : 'La demande n’a pas pu être envoyée. Réessayez dans un instant.',
        );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <h1 className="auth-title">Mot de passe oublié</h1>
      <p className="auth-subtitle">Indiquez l’adresse de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
      {sent ? (
        <p className="form-success" role="status">
          {sent}
        </p>
      ) : (
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
            error={error}
          />
          <Button type="submit" className="btn-block" disabled={submitting}>
            {submitting ? 'Envoi…' : 'Recevoir un lien'}
          </Button>
        </form>
      )}
      <p className="auth-link">
        <Link to="/connexion">Retour à la connexion</Link>
      </p>
    </>
  );
}
