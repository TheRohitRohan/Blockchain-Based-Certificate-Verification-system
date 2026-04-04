import axios from 'axios';

// ─────────────────────────────────────────────────────────────
//  Configured Axios instance
//  • baseURL points to /api which Vite proxies to localhost:8000
//  • Request interceptor injects the JWT from localStorage
//  • Response interceptor fires a logout event on 401
// ─────────────────────────────────────────────────────────────

const BASE_URL = import.meta.env.VITE_API_URL ?? '/api';

const axiosInstance = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  timeout: 15000,
});

/** Paths where we must not send JWT (wrong-password 401 is expected; sending token caused global logout). */
function isCredentialOrPublicRequest(config) {
  const key = `${config.baseURL || ''}${config.url || ''}`.toLowerCase();
  return (
    key.includes('auth/login') ||
    key.includes('auth/register') ||
    key.includes('auth/university/login') ||
    key.includes('auth/university/register') ||
    key.includes('auth/forgot-password') ||
    key.includes('auth/reset-password') ||
    key.includes('/public/')
  );
}

// ── Request interceptor — attach Bearer token ──────────────
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('certiledger_token');
    if (token && !isCredentialOrPublicRequest(config)) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// ── Response interceptor — handle 401 globally ────────────
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const cfg = error.config;
      // Wrong password on login/register must not wipe an existing session
      if (cfg && !isCredentialOrPublicRequest(cfg)) {
        localStorage.removeItem('certiledger_token');
        window.dispatchEvent(new CustomEvent('certiledger:logout'));
      }
    }
    return Promise.reject(error);
  }
);

export default axiosInstance;
