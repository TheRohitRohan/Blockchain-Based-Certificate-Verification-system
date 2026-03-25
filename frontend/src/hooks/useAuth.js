import { useAuthContext } from '../context/AuthContext';

/**
 * useAuth — access authentication state and actions.
 *
 * @returns {{
 *   user: object|null,
 *   token: string|null,
 *   role: 'admin'|'university'|'student'|null,
 *   isAuthenticated: boolean,
 *   isLoading: boolean,
 *   login: (email: string, password: string) => Promise<{success, user?, error?}>,
 *   logout: () => void,
 *   register: (data: object) => Promise<{success, message?, error?}>,
 * }}
 *
 * Usage:
 *   const { user, login, logout, isAuthenticated, role } = useAuth();
 */
export function useAuth() {
  return useAuthContext();
}
