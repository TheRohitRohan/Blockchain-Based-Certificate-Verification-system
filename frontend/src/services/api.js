import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost/backend/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const authAPI = {
  login: (email, password) => api.post('/auth/login', { email, password }),
  register: (data) => api.post('/auth/register', data),
};

export const certificateAPI = {
  create: (data) => api.post('/certificates/create', data),
  verify: (certificateId, certificateHash) => 
    api.post('/certificates/verify', { certificate_id: certificateId, certificate_hash: certificateHash }),
  getAll: () => api.get('/certificates'),
  revoke: (certificateId) => api.post('/certificates/revoke', { certificate_id: certificateId }),
};

export const universityAPI = {
  getAll: () => api.get('/universities'),
  create: (data) => api.post('/universities', data),
};

export const studentAPI = {
  getAll: () => api.get('/students'),
  create: (data) => api.post('/students', data),
};

export default api;

