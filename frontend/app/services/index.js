import api from './api';

export const authService = {
  register: (data) => api.post('/auth/register', data),
  login: (email, password) => api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  getProfile: () => api.get('/auth/me'),
  updateProfile: (data) => api.patch('/auth/profile', data),
  refreshToken: () => api.post('/auth/refresh'),
};

export const productService = {
  getProducts: (params) => api.get('/products', { params }),
  getProduct: (id) => api.get(`/products/${id}`),
  getCategories: () => api.get('/categories'),
};

export const orderService = {
  createOrder: (data) => api.post('/orders', data),
  getOrders: (params) => api.get('/orders', { params }),
  getOrder: (id) => api.get(`/orders/${id}`),
  cancelOrder: (id, reason) => api.delete(`/orders/${id}`, { data: { reason } }),
  updateOrderStatus: (id, status) => api.patch(`/orders/${id}/status`, { status }),
};

export const deliveryService = {
  getReadyOrders: (params) => api.get('/deliveries/ready-orders', { params }),
  acceptDelivery: (orderId) => api.post(`/deliveries/${orderId}/accept`),
  updateLocation: (deliveryId, latitude, longitude) =>
    api.post(`/deliveries/${deliveryId}/location`, { latitude, longitude }),
  startDelivery: (deliveryId) => api.patch(`/deliveries/${deliveryId}/start`),
  confirmDelivery: (deliveryId, data) =>
    api.post(`/deliveries/${deliveryId}/confirm`, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  getStats: () => api.get('/deliveries/stats'),
};

export const paymentService = {
  initiate: (orderId, phoneNumber) =>
    api.post('/payments/initiate', { order_id: orderId, phone_number: phoneNumber }),
  checkStatus: (paymentId) => api.get(`/payments/${paymentId}/status`),
  getHistory: (params) => api.get('/payments/history', { params }),
};
