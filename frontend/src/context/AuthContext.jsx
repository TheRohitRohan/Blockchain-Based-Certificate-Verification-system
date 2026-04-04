import React, {
  createContext,
  useContext,
  useReducer,
  useEffect,
  useCallback,
} from 'react';
import { login as apiLogin, register as apiRegister } from '../api/auth.api';

// ─────────────────────────────────────────────────────────────
//  AuthContext
//
//  Exposes: { user, token, role, isAuthenticated, isLoading }
//  Actions: { login, logout, register }
//
//  Token lifecycle:
//    • Login  → store token in localStorage['certiledger_token']
//    • Reload → read token from localStorage, decode JWT payload
//               to restore user (no extra API call)
//    • 401    → axiosInstance fires 'certiledger:logout' event
//               → this context listens and auto-clears state
// ─────────────────────────────────────────────────────────────

const TOKEN_KEY = 'certiledger_token';

// ── JWT payload decoder (no library needed) ────────────────
function decodeJWTPayload(token) {
  try {
    const base64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
    const json = atob(base64);
    return JSON.parse(json);
  } catch {
    return null;
  }
}

// ── Initial state from localStorage ───────────────────────
function buildInitialState() {
  const token = localStorage.getItem(TOKEN_KEY);
  if (!token) {
    return { user: null, token: null, isAuthenticated: false, isLoading: false };
  }
  const payload = decodeJWTPayload(token);
  // If token is expired, treat as logged out
  if (!payload || payload.exp * 1000 < Date.now()) {
    localStorage.removeItem(TOKEN_KEY);
    return { user: null, token: null, isAuthenticated: false, isLoading: false };
  }
  // Reconstruct user from JWT payload
  const user = {
    id: payload.user_id,
    email: payload.email,
    role: payload.role,
    university_id: payload.university_id ?? null,
    // University-admin fields (present in tokens issued by /auth/university/login)
    admin_name: payload.admin_name ?? null,
    university_name: payload.university_name ?? null,
    full_name: payload.full_name ?? payload.admin_name ?? null,
  };
  return { user, token, isAuthenticated: true, isLoading: false };
}

// ── Reducer ────────────────────────────────────────────────
function authReducer(state, action) {
  switch (action.type) {
    case 'SET_LOADING':
      return { ...state, isLoading: action.payload };
    case 'LOGIN_SUCCESS':
      return {
        ...state,
        user: action.payload.user,
        token: action.payload.token,
        isAuthenticated: true,
        isLoading: false,
      };
    case 'LOGOUT':
      return { user: null, token: null, isAuthenticated: false, isLoading: false };
    default:
      return state;
  }
}

// ── Context ────────────────────────────────────────────────
export const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [state, dispatch] = useReducer(authReducer, null, buildInitialState);

  // Listen for 401 auto-logout fired by axiosInstance interceptor
  useEffect(() => {
    const handleAutoLogout = () => {
      dispatch({ type: 'LOGOUT' });
    };
    window.addEventListener('certiledger:logout', handleAutoLogout);
    return () => window.removeEventListener('certiledger:logout', handleAutoLogout);
  }, []);

  // ── Actions ──────────────────────────────────────────────

  const login = useCallback(async (email, password) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const data = await apiLogin(email, password);
      console.log('Login API response:', { success: data.success, hasToken: !!data.token });
      if (data.success && data.token) {
        localStorage.setItem(TOKEN_KEY, data.token);
        console.log('✓ Token stored in localStorage:', TOKEN_KEY);
        console.log('Token payload:', data.token.split('.')[1]); // Log the payload part (for debugging)
        dispatch({
          type: 'LOGIN_SUCCESS',
          payload: { user: data.user, token: data.token },
        });
        return { success: true, user: data.user };
      }
      dispatch({ type: 'SET_LOADING', payload: false });
      console.warn('Login failed:', data.error);
      return { success: false, error: data.error ?? 'Login failed' };
    } catch (err) {
      dispatch({ type: 'SET_LOADING', payload: false });
      const message = err.response?.data?.error ?? err.message ?? 'Login failed';
      console.error('Login error:', { status: err.response?.status, error: message });
      return { success: false, error: message };
    }
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem(TOKEN_KEY);
    dispatch({ type: 'LOGOUT' });
  }, []);

  /**
   * Accepts a pre-fetched { token, user } object (e.g. from the university login
   * endpoint) and stores the token in localStorage and updates auth state —
   * without making a second API call.
   */
  const loginWithData = useCallback((tokenData) => {
    localStorage.setItem(TOKEN_KEY, tokenData.token);
    dispatch({
      type: 'LOGIN_SUCCESS',
      payload: { user: tokenData.user, token: tokenData.token },
    });
  }, []);

  const register = useCallback(async (data) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const result = await apiRegister(data);
      dispatch({ type: 'SET_LOADING', payload: false });
      return result;
    } catch (err) {
      dispatch({ type: 'SET_LOADING', payload: false });
      const message = err.response?.data?.error ?? err.message ?? 'Registration failed';
      return { success: false, error: message };
    }
  }, []);

  const value = {
    // State
    user: state.user,
    token: state.token,
    role: state.user?.role ?? null,
    isAuthenticated: state.isAuthenticated,
    isLoading: state.isLoading,
    // Actions
    login,
    logout,
    register,
    loginWithData,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// Internal hook — used by useAuth.js
export function useAuthContext() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuthContext must be used inside <AuthProvider>');
  return ctx;
}
