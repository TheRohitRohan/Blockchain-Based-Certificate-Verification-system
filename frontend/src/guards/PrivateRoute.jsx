import { Navigate, useLocation } from 'react-router';
import { useAuthContext } from '../context/AuthContext';

/**
 * Wraps any route that requires the user to be logged in.
 * If not authenticated → redirect to /login, preserving the current path.
 */
export default function PrivateRoute({ children }) {
  const { isAuthenticated, isLoading } = useAuthContext();
  const location = useLocation();

  if (isLoading) return null; // avoid flash before state hydrates

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return children;
}
