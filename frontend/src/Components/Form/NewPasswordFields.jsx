import TextInput from './TextInput.jsx';

/**
 * Les règles du mot de passe : [libellé, test]. On reprend les mêmes classes que l'API
 * (Password::mixedCase, numbers, symbols), lettres accentuées comprises.
 */
export const PASSWORD_RULES = [
  ['au moins 8 caractères', (value) => [...value].length >= 8],
  ['une majuscule et une minuscule', (value) => /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value)],
  ['un chiffre', (value) => /\p{N}/u.test(value)],
  ['un caractère spécial (@, #, !…)', (value) => /[\p{S}\p{P}\p{Z}]/u.test(value)],
];

/**
 * Les erreurs qu'on détecte avant d'appeler l'API, avec les mêmes clés qu'elle.
 *
 * @param {string} password
 * @param {string} confirmation
 * @returns {{password?: string, password_confirmation?: string}}
 */
export function newPasswordErrors(password, confirmation) {
  if (PASSWORD_RULES.some(([, test]) => !test(password))) return { password: 'Le mot de passe ne respecte pas toutes les règles.' };
  if (password !== confirmation) return { password_confirmation: 'La confirmation ne correspond pas au mot de passe.' };
  return {};
}

/**
 * Les deux champs pour choisir un mot de passe : activation d'un compte (#127) et mot de passe
 * oublié (#81). Les règles se cochent pendant la saisie, et les lecteurs d'écran les lisent.
 *
 * @param {object} props
 * @param {string} props.password
 * @param {string} props.confirmation
 * @param {(value: string) => void} props.onPassword
 * @param {(value: string) => void} props.onConfirmation
 * @param {{password?: string, password_confirmation?: string}} [props.errors]
 */
export default function NewPasswordFields({ password, confirmation, onPassword, onConfirmation, errors = {} }) {
  return (
    <>
      <TextInput
        label="Mot de passe"
        name="password"
        type="password"
        autoComplete="new-password"
        required
        value={password}
        onChange={(event) => onPassword(event.target.value)}
        error={errors.password}
        aria-describedby="regles-mot-de-passe"
      />
      <ul id="regles-mot-de-passe" className="password-rules" aria-label="Le mot de passe doit contenir">
        {PASSWORD_RULES.map(([label, test]) => (
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
        onChange={(event) => onConfirmation(event.target.value)}
        error={errors.password_confirmation}
      />
    </>
  );
}
