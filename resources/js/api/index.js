import axios from 'axios';

const API_ROOT = import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com';

const api = axios.create({
    baseURL: `${API_ROOT}/api`,
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

// Separate axios instance for student-authenticated requests, so a failed
// student request never wipes the STAFF token/session or vice versa.
export const studentApi = axios.create({
    baseURL: `${API_ROOT}/api`,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

studentApi.interceptors.request.use((config) => {
    const token = localStorage.getItem('student_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

studentApi.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('student_token');
            localStorage.removeItem('student');
            window.location.href = '/student/login';
        }
        return Promise.reject(error);
    }
);

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
    toggleActive:  (id) => api.post(`/students/${id}/toggle-active`),
    graduate: (id, payload) => api.post(`/students/${id}/graduate`, payload),
    import:        (formData) => api.post('/students/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    importPreview: (formData) => api.post('/students/import-preview', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    importConfirm: (data)     => api.post('/students/import-confirm', data),
    checkDuplicateName: (data) => api.post('/students/check-duplicate-name', data),
    viewTempPassword: (id)    => api.get(`/students/${id}/temp-password`),
    resetPassword:    (id)    => api.post(`/students/${id}/reset-password`),
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
    sendFeedback:     (id, data) => api.post(`/referrals/${id}/feedback`, data),
    saveAdmissionSlip:(id, data) => api.post(`/referrals/${id}/admission-slip`, data),
    archive:      (id)       => api.post(`/referrals/${id}/archive`),
    unarchive:    (id)       => api.post(`/referrals/${id}/unarchive`),
    archived:     (params)   => api.get('/referrals-archived', { params }),
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
    acknowledgeHandoff: (id, handoffId) => api.post(`/cases/${id}/handoffs/${handoffId}/acknowledge`),
    flagUnreachable: (id, data) => api.post(`/cases/${id}/flag-unreachable`, data),
    flagFollowUp:    (id, data) => api.post(`/cases/${id}/flag-follow-up`, data),
    resolveFollowUp: (id)       => api.post(`/cases/${id}/resolve-follow-up`),
    addIntervention: (id, data) => api.post(`/cases/${id}/interventions`, data),
    completeIntervention: (interventionId) => api.post(`/interventions/${interventionId}/complete`),
    deleteIntervention:   (interventionId) => api.delete(`/interventions/${interventionId}`),
};

export const caseHandoffAPI = {
    confirm: (id) => api.post(`/case-handoffs/${id}/confirm`),
};

export const caseInterventionAPI = {
    store: (caseId, data) => api.post(`/cases/${caseId}/interventions`, data),
    markCompleted: (id) => api.post(`/case-interventions/${id}/complete`),
};

export const sessionNoteAPI = {
    index:   (caseId)        => api.get(`/cases/${caseId}/session-notes`),
    store:   (caseId, data)  => api.post(`/cases/${caseId}/session-notes`, data),
    show:    (id)            => api.get(`/session-notes/${id}`),
    update:  (id, data)      => api.put(`/session-notes/${id}`, data),
    destroy: (id)            => api.delete(`/session-notes/${id}`),
    indexByReferral: (referralId)       => api.get(`/referrals/${referralId}/session-notes`),
    storeByReferral: (referralId, data) => api.post(`/referrals/${referralId}/session-notes`, data),
};

export const appointmentAPI = {
    index:          (params)   => api.get('/appointments', { params }),
    store:          (data)     => api.post('/appointments', data),
    show:           (id)       => api.get(`/appointments/${id}`),
    update:         (id, data) => api.put(`/appointments/${id}`, data),
    confirm:        (id, data) => api.post(`/appointments/${id}/confirm`, data),
    reschedule:     (id, data) => api.post(`/appointments/${id}/reschedule`, data),
    cancel:         (id, data) => api.post(`/appointments/${id}/cancel`, data),
    checkIn:        (id)       => api.post(`/appointments/${id}/check-in`),
    escalateNoShow: (id)       => api.post(`/appointments/${id}/escalate-no-show`),
    availability:   (params)   => api.get('/appointments/availability', { params }),
    checkConflict:  (data)     => api.post('/appointments/check-conflict', data),
};

export const testingAPI = {
    index:          (params)   => api.get('/testing-records', { params }),
    show:           (id)       => api.get(`/testing-records/${id}`),
    update:         (id, data) => api.put(`/testing-records/${id}`, data),
    updateStatus:   (id, data) => api.patch(`/testing-records/${id}/status`, data),
    sendToGcu:      (id, data) => api.post(`/testing-records/${id}/send-to-gcu`, data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined),
    acknowledge:    (id)       => api.post(`/testing-records/${id}/acknowledge`),
    scheduleTesting:(id, data) => api.post(`/testing-records/${id}/schedule-testing`, data),
    schedulePar:    (id, data) => api.post(`/testing-records/${id}/schedule-par`, data),
    orPhoto:        (id)       => api.get(`/testing-records/${id}/or-photo`, { responseType: 'blob' }),
};

export const reportAPI = {
    referrals:    (params) => api.get('/reports/referrals', { params }),
    appointments: (params) => api.get('/reports/appointments', { params }),
    cases:        (params) => api.get('/reports/cases', { params }),
    recurringConcerns: (params) => api.get('/reports/recurring-concerns', { params }),
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
    import:        (formData) => api.post('/users/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    viewTempPassword: (id)    => api.get(`/users/${id}/temp-password`),
};

export const callSlipAPI = {
    index:         (params)     => api.get('/call-slips', { params }),
    markContacted: (id, data)   => api.post(`/call-slips/${id}/contacted`, data),
    reschedule:    (id)         => api.post(`/call-slips/${id}/reschedule`),
    escalate:      (id, data)   => api.post(`/call-slips/${id}/escalate`, data),
};

export const notificationAPI = {
    index:       ()   => api.get('/notifications'),
    markRead:    (id) => api.post(`/notifications/${id}/read`),
    markAllRead: ()   => api.post('/notifications/read-all'),
    logs:        ()   => api.get('/notification-logs'),
};

export const studentNotificationAPI = {
    index:       ()   => studentApi.get('/student/notifications'),
    markRead:    (id) => studentApi.post(`/student/notifications/${id}/read`),
    markAllRead: ()   => studentApi.post('/student/notifications/read-all'),
};

export const monitoringAPI = {
    index: () => api.get('/monitoring'),
};

export const backupAPI = {
    index:         (params) => api.get('/backups', { params }),
    run:           (type)   => api.post('/backups/run', { type }),
    restoreData:   (confirm)=> api.post('/backups/restore-data', { confirm }),
    restoreConfig: (confirm)=> api.post('/backups/restore-config', { confirm }),
};

export const devAPI = {
    switchRole:      (role) => api.post('/dev/switch-role', { role }),
    switchToStudent: ()     => api.post('/dev/switch-to-student'),
};

export const auditAPI = {
    index: (params) => api.get('/audit-logs', { params }),
    show:  (id)     => api.get(`/audit-logs/${id}`),
};

export const studentAppointmentAPI = {
    index:         ()       => studentApi.get('/student/appointments'),
    store:         (data)   => studentApi.post('/student/appointments', data),
    show:          (id)     => studentApi.get(`/student/appointments/${id}`),
    checkConflict: (data)   => studentApi.post('/student/appointments/check-conflict', data),
};

// Testing (TMDU) - student side: view own testing record(s) and submit the
// OR photo to request a testing schedule.
export const studentTestingAPI = {
    index:          ()       => studentApi.get('/student/testing-records'),
    show:           (id)     => studentApi.get(`/student/testing-records/${id}`),
    requestTesting: (id, formData) => studentApi.post(`/student/testing-records/${id}/request-testing`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
};