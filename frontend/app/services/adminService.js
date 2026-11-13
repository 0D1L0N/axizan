import api from './api';

const adminService = {
  getZones: () => api.get('/admin/delivery-zones'),
  createZone: (data) => api.post('/admin/delivery-zones', data),
  updateZone: (id, data) => api.put(`/admin/delivery-zones/${id}`, data),
  deleteZone: (id) => api.delete(`/admin/delivery-zones/${id}`),

  getSlots: () => api.get('/admin/delivery-slots'),
  createSlot: (data) => api.post('/admin/delivery-slots', data),
  updateSlot: (id, data) => api.put(`/admin/delivery-slots/${id}`, data),
  deleteSlot: (id) => api.delete(`/admin/delivery-slots/${id}`),
  // KYC
  getPendingKyc: () => api.get('/admin/kyc/pending'),
  approveKyc: (id) => api.post(`/admin/kyc/${id}/approve`),
  rejectKyc: (id, data) => api.post(`/admin/kyc/${id}/reject`, data),
};

export default adminService;
