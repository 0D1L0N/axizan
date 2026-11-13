import api from './api';

const livreurService = {
  getDeliveries: () => api.get('/livreur/deliveries'),
  acceptDelivery: (id) => api.post(`/livreur/deliveries/${id}/accept`),
  updateLocation: (id, lat, lon) => api.post(`/livreur/deliveries/${id}/location`, { latitude: lat, longitude: lon }),
  confirmDelivery: (id, data) => {
    const form = new FormData();
    if (data.photo) form.append('photo', data.photo);
    if (data.signature) form.append('signature', data.signature);
    if (data.notes) form.append('notes', data.notes);
    return api.post(`/livreur/deliveries/${id}/confirm`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
  submitKyc: (formData) => api.post('/livreur/kyc', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
};

export default livreurService;
