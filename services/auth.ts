import axios from 'axios';

const API_URL = 'http://127.0.0.1:8000/api';

// Create axios instance
const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add token to requests if it exists
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const authService = {
  async register(userData: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) {
    const response = await api.post('/register', userData);
    if (response.data.access_token) {
      localStorage.setItem('token', response.data.access_token);
    }
    return response.data;
  },

  async login(credentials: { email: string; password: string }) {
    const response = await api.post('/login', credentials);
    if (response.data.access_token) {
      localStorage.setItem('token', response.data.access_token);
    }
    return response.data;
  },

  async logout() {
    const response = await api.post('/logout');
    localStorage.removeItem('token');
    return response.data;
  },

  async getProfile() {
    const response = await api.get('/user-profile');
    return response.data;
  },

  isAuthenticated() {
    return !!localStorage.getItem('token');
  },

  getToken() {
    return localStorage.getItem('token');
  },
}; 