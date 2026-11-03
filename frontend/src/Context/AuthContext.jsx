import React, { createContext, useContext, useState, useEffect } from 'react';
import AuthService, { defaultFallbackUser } from '../Services/AuthService';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(defaultFallbackUser);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;
    AuthService.getCurrentUser()
      .then((userData) => {
        if (isMounted && userData) {
          setUser(userData);
        }
      })
      .catch((err) => {
        console.warn('Backend non disponible, utilisation du profil local:', err);
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const login = (userData) => {
    setUser(userData);
  };

  const logout = () => {
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, setUser, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
