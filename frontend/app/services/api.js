import axios from 'axios';

const API_URL = process.env.NEXT_PUBLIC_API_URL || '';

const getBaseURL = () => {
  if (API_URL) {
    return API_URL;
  }

  if (typeof window !== 'undefined') {
    const host = window.location.hostname;
    return `http://${host}:8000/api/v1`;
  }

  return 'http://localhost:8000/api/v1';
};

const api = axios.create({
  baseURL: getBaseURL(),
  headers: {
    'Content-Type': 'application/json',
  },
});

// Intercepteur pour ajouter le token
api.interceptors.request.use((config) => {
  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : null;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Intercepteur pour gérer les erreurs
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
