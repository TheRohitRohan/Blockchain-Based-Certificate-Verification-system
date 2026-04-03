import { Navigate } from 'react-router';
import { useAuthContext } from '../context/AuthContext';

const ROLE_HOME = {
  admin: '/admin',
  university: '/university',
  student: '/student',
};

/**
 * Role-based access control.
 * @param {string[]} allowedRoles  — e.g. ['admin'] or ['university', 'admin']
 */
export default function RoleRoute({ children, allowedRoles }) {
  const { role } = useAuthContext();

  if (!role || !allowedRoles.includes(role)) {
    const fallback = ROLE_HOME[role] ?? '/login';
    return <Navigate to={fallback} replace />;
  }

  return children;
}
