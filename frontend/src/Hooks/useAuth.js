import { useContext } from 'react';
import { AuthContext } from '../Context/AuthContext.jsx';

export default function useAuth() {
  const context = useContext(AuthContext);
  if (context === null) {
    throw new Error('useAuth doit être utilisé à l’intérieur de <AuthProvider>.');
  }
  return context;
}
