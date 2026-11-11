import { useContext } from 'react';
import { LogbookContext } from '../Context/LogbookContext.jsx';

export default function useLogbook() {
  const context = useContext(LogbookContext);
  if (context === null) {
    throw new Error('useLogbook doit être utilisé à l’intérieur de <LogbookProvider>.');
  }
  return context;
}
