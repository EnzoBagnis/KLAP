import { useContext } from 'react';
import { LogbookContext } from '../Context/LogbookContext.jsx';

/**
 * La fiche en cours de saisie et ses actions (voir LogbookProvider).
 *
 * @throws {Error} si on l'appelle hors de <LogbookProvider>
 */
export default function useLogbook() {
  const context = useContext(LogbookContext);
  if (context === null) {
    throw new Error('useLogbook doit être utilisé à l’intérieur de <LogbookProvider>.');
  }
  return context;
}
