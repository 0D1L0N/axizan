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
  /**
   * Transmet l'identifiant rendu par le module FedaPay. Le serveur interroge
   * l'operateur avant de croire quoi que ce soit — ce n'est pas une
   * declaration, c'est une demande de verification.
   */
  confirmPayment: (id, transactionId) =>
    api.post(`/orders/${id}/confirm-payment`, { transaction_id: transactionId }),
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
  initiate: (orderId, phoneNumber) =>
    api.post('/payments/initiate', { order_id: orderId, phone_number: phoneNumber }),
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
  /**
   * Marquer déposée au dépôt, avec une photo du panier.
   *
   * PATCH n'accepte pas de fichier en multipart : on passe par POST avec
   * `_method`, la convention Laravel pour les envois de formulaire.
   */
  ready: (orderId, { notes, photo } = {}) => {
    if (!photo) {
      return api.patch(`/preparateur/orders/${orderId}/ready`, { notes });
    }

    const form = new FormData();
    form.append('_method', 'PATCH');
    form.append('photo', photo);
    if (notes) form.append('notes', notes);

    return api.post(`/preparateur/orders/${orderId}/ready`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  /** Fournisseurs partenaires du marché de rattachement. */
  getSuppliers: () => api.get('/preparateur/suppliers'),
  /**
   * Rendre une commande qu'on ne peut pas honorer : elle retourne au pool du
   * marché et les lignes déjà pointées sont remises à zéro.
   */
  release: (orderId, reason) => api.patch(`/preparateur/orders/${orderId}/release`, { reason }),
  /**
   * Enregistre le sort d'une ligne : récupérée, remplacée ou retirée.
   * Aucun prix : le préparateur ne paie pas, il retire en compte.
   */
  updateItem: (orderId, itemId, data) =>
    api.patch(`/preparateur/orders/${orderId}/items/${itemId}`, data),
};

export const adminService = {
  /** Marchés actifs, pour rattacher un préparateur à la création. */
  getMarkets: () => api.get('/admin/markets'),
  getPendingPayments: (params) => api.get('/admin/payments/pending', { params }),
  /** `reference_number` est obligatoire : sans référence, le paiement n'est pas traçable. */
  validatePayment: (orderId, proof) => api.post(`/admin/orders/${orderId}/validate-payment`, proof),
  createStaff: (data) => api.post('/admin/staff', data),
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
