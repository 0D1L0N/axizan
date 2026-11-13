import api from './api';

export const authService = {
  register: (data) => api.post('/auth/register', data),
  login: (email, password) => api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  getProfile: () => api.get('/auth/me'),
  updateProfile: (data) => api.patch('/auth/profile', data),
  refreshToken: () => api.post('/auth/refresh'),
  forgotPassword: (email) => api.post('/auth/forgot-password', { email }),
  resetPassword: (data) => api.post('/auth/reset-password', data),
  changePassword: (data) => api.post('/auth/change-password', data),
};

export const notificationService = {
  list: (params) => api.get('/notifications', { params }),
  unreadCount: () => api.get('/notifications/unread-count'),
  markRead: (id) => api.patch(`/notifications/${id}/read`),
  markAllRead: () => api.post('/notifications/read-all'),
};

export const productService = {
  getProducts: (params) => api.get('/products', { params }),
  getProduct: (id) => api.get(`/products/${id}`),
  getCategories: () => api.get('/categories'),
};

export const orderService = {
  /**
   * `idempotencyKey` : protege du double envoi. Sur un reseau mobile
   * instable, le client qui ne voit pas la reponse arriver retouche le
   * bouton — la meme cle renvoie alors la commande deja creee au lieu d'en
   * creer une seconde.
   */
  createOrder: (data, idempotencyKey) =>
    api.post('/orders', data, idempotencyKey ? { headers: { 'Idempotency-Key': idempotencyKey } } : undefined),
  getOrders: (params) => api.get('/orders', { params }),
  getOrder: (id) => api.get(`/orders/${id}`),
  cancelOrder: (id, reason) => api.delete(`/orders/${id}`, { data: { reason } }),
  /** Recharge le panier avec les articles d'une commande passee, aux prix du jour. */
  reorder: (id) => api.post(`/orders/${id}/reorder`),
  /** Le client declare avoir paye. Ne valide rien : un admin verifie ensuite. */
  declarePayment: (id, data) => api.post(`/orders/${id}/declare-payment`, data),
  updateOrderStatus: (id, status) => api.patch(`/orders/${id}/status`, { status }),
};

export const deliveryService = {
  getReadyOrders: (params) => api.get('/deliveries/ready-orders', { params }),
  getMine: () => api.get('/deliveries/mine'),
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
  checkStatus: (paymentId) => api.get(`/payments/${paymentId}/status`),
  getHistory: (params) => api.get('/payments/history', { params }),
};

export const addressService = {
  list: () => api.get('/addresses'),
  create: (data) => api.post('/addresses', data),
  update: (id, data) => api.patch(`/addresses/${id}`, data),
  remove: (id) => api.delete(`/addresses/${id}`),
};

export const logisticsService = {
  getZones: () => api.get('/delivery-zones'),
  // La date compte autant que la zone : un creneau n'est reservable que s'il
  // commence au moins une heure apres maintenant.
  getSlots: (zoneId, date) =>
    api.get('/delivery-slots', {
      params: { ...(zoneId ? { zone_id: zoneId } : {}), ...(date ? { date } : {}) },
    }),
};

export const preparatorService = {
  getAvailable: (params) => api.get('/preparateur/orders', { params }),
  getMine: () => api.get('/preparateur/orders/mine'),
  accept: (orderId) => api.patch(`/preparateur/orders/${orderId}/accept`),
  start: (orderId, notes) => api.patch(`/preparateur/orders/${orderId}/start`, { notes }),
  ready: (orderId, notes) => api.patch(`/preparateur/orders/${orderId}/ready`, { notes }),
  getSuppliers: () => api.get('/preparateur/suppliers'),
  /** collection_status : collected | replaced | removed | pending */
  updateItem: (orderId, itemId, data) => api.patch(`/preparateur/orders/${orderId}/items/${itemId}`, data),
};

export const adminService = {
  getPendingPayments: (params) => api.get('/admin/payments/pending', { params }),
  /** `reference_number` est obligatoire : sans référence, le paiement n'est pas traçable. */
  validatePayment: (orderId, proof) => api.post(`/admin/orders/${orderId}/validate-payment`, proof),
  createStaff: (data) => api.post('/admin/staff', data),

  getZones: () => api.get('/admin/delivery-zones'),
  createZone: (data) => api.post('/admin/delivery-zones', data),
  updateZone: (id, data) => api.put(`/admin/delivery-zones/${id}`, data),
  deleteZone: (id) => api.delete(`/admin/delivery-zones/${id}`),

  getSlots: () => api.get('/admin/delivery-slots'),
  createSlot: (data) => api.post('/admin/delivery-slots', data),
  updateSlot: (id, data) => api.put(`/admin/delivery-slots/${id}`, data),
  deleteSlot: (id) => api.delete(`/admin/delivery-slots/${id}`),

  getPendingKyc: () => api.get('/admin/kyc/pending'),
  /** Le document est prive : on le recupere avec le jeton, pas via un lien public. */
  getKycDocument: (id) => api.get(`/admin/kyc/${id}/document`, { responseType: 'blob' }),
  approveKyc: (id) => api.post(`/admin/kyc/${id}/approve`),
  rejectKyc: (id, reason) => api.post(`/admin/kyc/${id}/reject`, { reason }),
};

export const promotionService = {
  /** Apercu indicatif : la reduction reelle est recalculee a la commande. */
  check: (code, subtotal) => api.post('/promotions/check', { code, subtotal }),
};

export const kycService = {
  getStatus: () => api.get('/livreur/kyc'),
  submit: (formData) => api.post('/livreur/kyc', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
};

export const cartService = {
  list: () => api.get('/cart'),
  put: (productId, quantity) => api.post('/cart', { product_id: productId, quantity }),
  remove: (productId) => api.delete(`/cart/${productId}`),
  clear: () => api.delete('/cart'),
  /** Pousse le panier local a la connexion. Voir CartController::merge. */
  merge: (items) => api.post('/cart/merge', { items }),
};

export const recurringBasketService = {
  list: () => api.get('/recurring-baskets'),
  create: (data) => api.post('/recurring-baskets', data),
  update: (id, data) => api.patch(`/recurring-baskets/${id}`, data),
  remove: (id) => api.delete(`/recurring-baskets/${id}`),
  load: (id) => api.post(`/recurring-baskets/${id}/load`),
};

export const paymentMethodService = {
  list: () => api.get('/payment-methods'),
};
