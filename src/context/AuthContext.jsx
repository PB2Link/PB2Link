import { createContext, useContext, useState, useEffect } from 'react';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null); // Citizen state
  const [adminUser, setAdminUser] = useState(null); // Admin state
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check for both independently on app load
    const storedUser = localStorage.getItem('citizen_user');
    const storedAdmin = localStorage.getItem('admin_user');
    
    if (storedUser) setUser(JSON.parse(storedUser));
    if (storedAdmin) setAdminUser(JSON.parse(storedAdmin));
    
    setLoading(false);
  }, []);

  // Citizen Authentication
  const login = (userData) => {
    setUser(userData);
    localStorage.setItem('citizen_user', JSON.stringify(userData));
  };

  const logout = () => {
    setUser(null);
    localStorage.removeItem('citizen_user');
  };

  // Admin Authentication
  const adminLogin = (adminData) => {
    // adminData must include { role: 'Super' } or { role: 'Admin' } from PHP
    setAdminUser(adminData);
    localStorage.setItem('admin_user', JSON.stringify(adminData));
  };

  const adminLogout = () => {
    setAdminUser(null);
    localStorage.removeItem('admin_user');
  };

  return (
    <AuthContext.Provider value={{ 
      user, login, logout, 
      adminUser, adminLogin, adminLogout, 
      loading 
    }}>
      {!loading && children}
    </AuthContext.Provider>
  );
};

export default AuthContext;