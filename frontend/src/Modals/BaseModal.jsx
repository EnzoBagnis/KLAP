import { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import '../assets/css/ModalStyles.css';

/**
 * La fenêtre modale de base, qu'on rend hors de l'application (dans <body>). Tant qu'elle
 * est ouverte, on rend l'application (#root) inerte : ni clic ni tabulation derrière, menu
 * latéral compris. Échap ferme (sauf si `closable` vaut false) ; le focus entre dans la
 * fenêtre, et on le rend à l'élément d'origine à la fermeture.
 *
 * @param {object} props
 * @param {boolean} props.open
 * @param {string} props.title
 * @param {() => void} props.onClose
 * @param {React.ReactNode} props.children
 * @param {React.ReactNode} [props.actions]  les boutons du bas
 * @param {boolean} [props.closable=true]  faux pendant un enregistrement, pour ne pas le perdre
 */
export default function BaseModal({ open, title, onClose, children, actions, closable = true }) {
  const titleId = useId();
  const dialogRef = useRef(null);
  const closableRef = useRef(closable);
  // On garde la dernière valeur de `closable` sans relancer l'effet d'ouverture.
  useEffect(() => {
    closableRef.current = closable;
  }, [closable]);

  useEffect(() => {
    if (!open) return undefined;
    const previouslyFocused = document.activeElement;
    const appRoot = document.getElementById('root');
    if (appRoot) appRoot.inert = true;
    focusFirst(dialogRef.current);

    const onKeyDown = (event) => {
      if (event.key === 'Escape' && closableRef.current) onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      window.removeEventListener('keydown', onKeyDown);
      if (appRoot) appRoot.inert = false;
      previouslyFocused?.focus?.();
    };
  }, [open, onClose]);

  if (!open) return null;

  return createPortal(
    <div className="modal-backdrop">
      <div ref={dialogRef} className="modal" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <h2 id={titleId} className="modal-title">
          {title}
        </h2>
        <div className="modal-body">{children}</div>
        {actions && <div className="modal-actions">{actions}</div>}
      </div>
    </div>,
    document.body,
  );
}

/**
 * On place le focus sur le premier élément interactif d'un conteneur.
 *
 * @param {HTMLElement|null} container
 */
export function focusFirst(container) {
  container?.querySelector('button:not([disabled]), a[href], input, select, textarea')?.focus();
}
