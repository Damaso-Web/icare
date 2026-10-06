import axios from 'axios';

const API_ROOT = import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com';

// ---- Failed requests the pages can't explain on their own ----
// Most pages only log a failed load to the console, which leaves an empty
// screen that reads as "no records". This puts a message on screen for the
// cases no page can do anything about: the server unreachable, the server
// failing while loading data, or the request limit being hit.
let lastNoticeAt = 0;
function notifyFailure(error) {
    if (axios.isCancel(error)) return;

    const status = error.response?.status;
    const method = (error.config?.method || 'get').toLowerCase();
    const url    = error.config?.url || '';
    if (/\/(login|otp)/.test(url)) return;            // the login pages show their own message

    let message = null;
    if (status === 429) {
        message = 'Too many requests at once. Please wait a moment, then try again.';
    } else if (!error.response) {
        message = "Couldn't reach the server. Check your internet connection and try again.";
    } else if (status >= 500 && method === 'get') {
        message = 'The server had a problem loading this page. Please try again.';
    }
    if (!message) return;

    // A page fires several requests together - one message is enough.
    const now = Date.now();
    if (now - lastNoticeAt < 4000) return;
    lastNoticeAt = now;

    window.dispatchEvent(new CustomEvent('icare:notice', {
        detail: { message, type: status === 429 ? 'warning' : 'error' },
    }));
}

// Pages that call axios directly (not through `api`) get the same message.
axios.interceptors.response.use((response) => response, (error) => {
    notifyFailure(error);
    return Promise.reject(error);
});

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
        notifyFailure(error);
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);


// ---------------------------------------------------------------------------
// Request guard (applies to every save/confirm/upload/submit/delete action)
//
// 1. While any write request (POST/PUT/PATCH/DELETE) is in flight, <body>
//    gets the "is-busy" class. app.css greys out and disables every button
//    while it is set, so a second click (or a click on a different button
//    in the same modal) can't fire while the first request is processing.
// 2. As a backstop for things the CSS can't stop (pressing Enter twice, a
//    double-fired handler), an identical write request that is already in
//    flight is not sent again - the second caller just receives the same
//    response as the first.
// ---------------------------------------------------------------------------
const WRITE_METHODS = ['post', 'put', 'patch', 'delete'];
// Background housekeeping calls that shouldn't lock the screen.
const GUARD_IGNORE = [/notifications/i];
const BUSY_FAILSAFE_MS = 60000;

const inflightWrites = new Map();
const busyIds = new Set();
let busySeq = 0;

function setBusyClass() {
    if (typeof document === 'undefined') return;
    document.body.classList.toggle('is-busy', busyIds.size > 0);
}

function writeKey(config) {
    let body = '';
    try {
        if (typeof config.data === 'string') {
            body = config.data;
        } else if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
            body = [...config.data.entries()]
                .map(([k, v]) => `${k}=${typeof v === 'string' ? v : `${v.name}:${v.size}`}`)
                .join('&');
        } else if (config.data) {
            body = JSON.stringify(config.data);
        }
    } catch (e) {
        body = '';
    }
    return `${(config.method || 'get').toLowerCase()} ${config.baseURL || ''}${config.url || ''} ${body}`;
}

function installRequestGuard(instance) {
    instance.interceptors.request.use((config) => {
        const method = (config.method || 'get').toLowerCase();
        if (!WRITE_METHODS.includes(method)) return config;
        if (GUARD_IGNORE.some((re) => re.test(config.url || ''))) return config;

        const key = writeKey(config);
        const baseAdapter = axios.getAdapter(config.adapter || instance.defaults.adapter);

        config.adapter = (cfg) => {
            if (inflightWrites.has(key)) return inflightWrites.get(key);

            const id = ++busySeq;
            busyIds.add(id);
            setBusyClass();
            const release = () => {
                inflightWrites.delete(key);
                busyIds.delete(id);
                setBusyClass();
            };
            // Never leave the screen locked if a request hangs forever.
            const watchdog = setTimeout(release, BUSY_FAILSAFE_MS);

            const promise = baseAdapter(cfg).finally(() => {
                clearTimeout(watchdog);
                release();
            });
            inflightWrites.set(key, promise);
            return promise;
        };

        return config;
    });
}

installRequestGuard(api);

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

installRequestGuard(studentApi);

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
        notifyFailure(error);
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
    issueParentConferenceSlip: (id, data) => api.post(`/cases/${id}/parent-conference-slip`, data),
    saveParentConferenceNotes: (slipId, data) => api.post(`/parent-conference-slips/${slipId}/notes`, data),
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
    escalateNoShow: (id, action) => api.post(`/appointments/${id}/escalate-no-show`, { action }),
    availability:   (params)   => api.get('/appointments/availability', { params }),
    checkConflict:  (data)     => api.post('/appointments/check-conflict', data),
};

export const testingAPI = {
    index:           (params)   => api.get('/testing-records', { params }),
    show:            (id)       => api.get(`/testing-records/${id}`),
    update:          (id, data) => api.put(`/testing-records/${id}`, data),
    updateStatus:    (id, data) => api.patch(`/testing-records/${id}/status`, data),
    sendToGcu:       (id, data) => api.post(`/testing-records/${id}/send-to-gcu`, data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined),
    acknowledge:     (id)       => api.post(`/testing-records/${id}/acknowledge`),
    scheduleTesting: (id, data) => api.post(`/testing-records/${id}/schedule-testing`, data),
    // "Psychological Tests Administered" action on the Testing Record Details
    // page - saves the tests + date and moves status straight to Awaiting Results.
    administerTests: (id, data) => api.post(`/testing-records/${id}/administer-tests`, data),
    schedulePar:     (id, data) => api.post(`/testing-records/${id}/schedule-par`, data),
    orPhoto:         (id)       => api.get(`/testing-records/${id}/or-photo`, { responseType: 'blob' }),
    // Assigning the psychometrician (TMDU staff/head) who will handle a
    // record - required before any other action on it can proceed.
    assign:           (id, data) => api.post(`/testing-records/${id}/assign`, data),
    availableTesters: ()         => api.get('/testing-records/available-testers'),
    // PAR release appointment actions: released | on_hold | cancel
    parAction:        (appointmentId, action) => api.post(`/appointments/${appointmentId}/par-action`, { action }),
};

export const reportAPI = {
    referrals:    (params) => api.get('/reports/referrals', { params }),
    appointments: (params) => api.get('/reports/appointments', { params }),
    cases:        (params) => api.get('/reports/cases', { params }),
    recurringConcerns: (params) => api.get('/reports/recurring-concerns', { params }),
    services:     (params) => api.get('/reports/services', { params }),
    complaints:   (params) => api.get('/reports/complaints', { params }),
    dashboard:    ()       => api.get('/reports/dashboard'),
};

// Case Referrals (Internal) - GCU's read-only view of the Refer to TMDU forms.
export const caseReferralAPI = {
    index:     (params) => api.get('/case-referrals', { params }),
    show:      (id)     => api.get(`/case-referrals/${id}`),
    forSource: (id)     => api.get(`/case-referrals/by-source/${id}`),
    // PAR file download (blob), opened by the caller.
    downloadDocument: (id) => api.get(`/documents/${id}/download`, { responseType: 'blob' }),
};

// Faculty module (Dean = whole college, Dept Chair = own department; scoped on the server).
export const facultyAPI = {
    index:          (params)   => api.get('/faculty', { params }),
    store:          (data)     => api.post('/faculty', data),
    update:         (id, data) => api.put(`/faculty/${id}`, data),
    toggleActive:   (id)       => api.post(`/faculty/${id}/toggle-active`),
    resetPassword:  (id)       => api.post(`/faculty/${id}/reset-password`),
    assignChair:    (id, data) => api.post(`/faculty/${id}/assign-chair`, data),
    removeChair:    (id)       => api.post(`/faculty/${id}/remove-chair`),
    importPreview:  (formData) => api.post('/faculty/import-preview', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    importConfirm:  (data)     => api.post('/faculty/import-confirm', data),
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
    // Non-admin staff roster for the Reassign / Transfer dropdowns.
    roster:        (params)   => api.get('/staff-roster', { params }),
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