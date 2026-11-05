import { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import '../assets/css/ModalStyles.css';

/*
 * Fenêtre modale de base, rendue hors de l'application (dans <body>).
 * Pendant qu'elle est ouverte, l'application (#root) est inerte : ni clic ni tabulation
 * derrière, menu latéral compris. Échap ferme (sauf si `closable` vaut false), le focus
 * entre dans la fenêtre et revient à l'élément d'origine à la fermeture.
 */
export default function BaseModal({ open, title, onClose, children, actions, closable = true }) {
  const titleId = useId();
  const dialogRef = useRef(null);
  const closableRef = useRef(closable);
  closableRef.current = closable;

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

/** Place le focus sur le premier élément interactif de la fenêtre. */
export function focusFirst(container) {
  container?.querySelector('button:not([disabled]), a[href], input, select, textarea')?.focus();
}
