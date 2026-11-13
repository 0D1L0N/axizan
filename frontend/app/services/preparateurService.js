import api from './api';

const preparateurService = {
  getOrders: () => api.get('/preparateur/orders'),
  acceptOrder: (id) => api.post(`/preparateur/orders/${id}/accept`),
  startPreparing: (id) => api.post(`/preparateur/orders/${id}/start`),
  markReady: (id) => api.post(`/preparateur/orders/${id}/ready`),
  adjustStock: (data) => api.post('/preparateur/stock/adjust', data),
  getOrder: (id) => api.get(`/orders/${id}`),
  substitute: (orderId, data) => api.post(`/preparateur/orders/${orderId}/substitute`, data),
};

export default preparateurService;
