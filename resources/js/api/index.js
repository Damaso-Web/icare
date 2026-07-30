import axios from 'axios';

const api = axios.create({
    baseURL: 'https://icare-backend-5jwe.onrender.com/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

// Attach token to every request
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Handle 401 - redirect to login
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;

export const authAPI = {
    login:          (data) => api.post('/login', data),
    logout:         ()     => api.post('/logout'),
    me:             ()     => api.get('/me'),
    changePassword: (data) => api.put('/me/password', data),
};

export const studentAPI = {
    index:   (params)   => api.get('/students', { params }),
    store:   (data)     => api.post('/students', data),
    show:    (id)       => api.get(`/students/${id}`),
    update:  (id, data) => api.put(`/students/${id}`, data),
    destroy: (id)       => api.delete(`/students/${id}`),
    history: (id)       => api.get(`/students/${id}/history`),
    cases:   (id)       => api.get(`/students/${id}/cases`),
};

export const referralAPI = {
    index:        (params)   => api.get('/referrals', { params }),
    store:        (data)     => api.post('/referrals', data),
    show:         (id)       => api.get(`/referrals/${id}`),
    update:       (id, data) => api.put(`/referrals/${id}`, data),
    acknowledge:  (id)       => api.post(`/referrals/${id}/acknowledge`),
    assign:       (id, data) => api.post(`/referrals/${id}/assign`, data),
    updateStatus: (id, data) => api.patch(`/referrals/${id}/status`, data),
    tracking:     (id)       => api.get(`/referrals/${id}/tracking`),
};

export const caseAPI = {
    index:           (params)   => api.get('/cases', { params }),
    show:            (id)       => api.get(`/cases/${id}`),
    update:          (id, data) => api.put(`/cases/${id}`, data),
    updateStatus:    (id, data) => api.patch(`/cases/${id}/status`, data),
    close:           (id, data) => api.post(`/cases/${id}/close`, data),
    summary:         (id)       => api.get(`/cases/${id}/summary`),
    referToTmdu:     (id, data) => api.post(`/cases/${id}/refer-tmdu`, data),
    referExternal:   (id, data) => api.post(`/cases/${id}/refer-external`, data),
    handoff:         (id, data) => api.post(`/cases/${id}/handoff`, data),
    flagUnreachable: (id, data) => api.post(`/cases/${id}/flag-unreachable`, data),
};

export const sessionNoteAPI = {
    index:   (caseId)        => api.get(`/cases/${caseId}/session-notes`),
    store:   (caseId, data)  => api.post(`/cases/${caseId}/session-notes`, data),
    show:    (id)            => api.get(`/session-notes/${id}`),
    update:  (id, data)      => api.put(`/session-notes/${id}`, data),
    destroy: (id)            => api.delete(`/session-notes/${id}`),
};

export const appointmentAPI = {
    index:          (params)   => api.get('/appointments', { params }),
    store:          (data)     => api.post('/appointments', data),
    show:           (id)       => api.get(`/appointments/${id}`),
    update:         (id, data) => api.put(`/appointments/${id}`, data),
    confirm:        (id)       => api.post(`/appointments/${id}/confirm`),
    reschedule:     (id, data) => api.post(`/appointments/${id}/reschedule`, data),
    cancel:         (id, data) => api.post(`/appointments/${id}/cancel`, data),
    checkIn:        (id)       => api.post(`/appointments/${id}/check-in`),
    escalateNoShow: (id)       => api.post(`/appointments/${id}/escalate-no-show`),
    availability:   (params)   => api.get('/appointments/availability', { params }),
    checkConflict:  (data)     => api.post('/appointments/check-conflict', data),
};

export const testingAPI = {
    index:        (params)   => api.get('/testing-records', { params }),
    show:         (id)       => api.get(`/testing-records/${id}`),
    update:       (id, data) => api.put(`/testing-records/${id}`, data),
    updateStatus: (id, data) => api.patch(`/testing-records/${id}/status`, data),
    sendToGcu:    (id, data) => api.post(`/testing-records/${id}/send-to-gcu`, data),
};

export const reportAPI = {
    referrals:    (params) => api.get('/reports/referrals', { params }),
    appointments: (params) => api.get('/reports/appointments', { params }),
    cases:        (params) => api.get('/reports/cases', { params }),
    dashboard:    ()       => api.get('/reports/dashboard'),
};

export const userAPI = {
    index:         (params)   => api.get('/users', { params }),
    store:         (data)     => api.post('/users', data),
    show:          (id)       => api.get(`/users/${id}`),
    update:        (id, data) => api.put(`/users/${id}`, data),
    destroy:       (id)       => api.delete(`/users/${id}`),
    toggleActive:  (id)       => api.post(`/users/${id}/toggle-active`),
    resetPassword: (id, data) => api.post(`/users/${id}/reset-password`, data),
};

export const notificationAPI = {
    index:       ()   => api.get('/notifications'),
    markRead:    (id) => api.post(`/notifications/${id}/read`),
    markAllRead: ()   => api.post('/notifications/read-all'),
    logs:        ()   => api.get('/notification-logs'),
};

export const auditAPI = {
    index: (params) => api.get('/audit-logs', { params }),
    show:  (id)     => api.get(`/audit-logs/${id}`),
};