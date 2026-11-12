/**
 * Le bouton de la maquette (composant « Btn »). Par défaut, c'est un `type="button"` : il
 * n'envoie un formulaire que si on le demande.
 *
 * @param {object} props
 * @param {'primary'|'line'|'ghost'} [props.variant='primary']  rouge plein, contour ou fantôme
 * @param {'button'|'submit'} [props.type='button']
 * @param {string} [props.className]
 */
export default function Button({ variant = 'primary', type = 'button', className = '', ...props }) {
  return <button type={type} className={`btn btn-${variant} ${className}`.trim()} {...props} />;
}
