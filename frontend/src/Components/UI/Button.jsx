// Bouton de la maquette (composant « Btn ») : primaire rouge, ligne ou fantôme.
export default function Button({ variant = 'primary', type = 'button', className = '', ...props }) {
  return <button type={type} className={`btn btn-${variant} ${className}`.trim()} {...props} />;
}
