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

// ── Request interceptor — attach Bearer token ──────────────
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('certiledger_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
      console.log('✓ Bearer token attached to request:', config.url);
    } else {
      console.warn('✗ No token found in localStorage for URL:', config.url);
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
      // Clear persisted credentials and notify AuthContext
      localStorage.removeItem('certiledger_token');
      window.dispatchEvent(new CustomEvent('certiledger:logout'));
    }
    return Promise.reject(error);
  }
);

export default axiosInstance;
