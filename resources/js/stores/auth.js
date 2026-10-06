import { defineStore } from 'pinia';
import axios from 'axios';

axios.defaults.baseURL = import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user:  JSON.parse(localStorage.getItem('user'))  || null,
        token: localStorage.getItem('token') || null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        isAdmin:         (state) => state.user?.role === 'admin',
        isGCUStaff:      (state) => state.user?.role === 'gcu_staff',
        isSDUHead:       (state) => state.user?.role === 'sdu_head',
        isTMDUStaff:     (state) => state.user?.role === 'tmdu_staff',
        isFaculty:       (state) => ['faculty', 'dean', 'dept_chair'].includes(state.user?.role),
        isDean:          (state) => state.user?.role === 'dean',
        isDeptChair:     (state) => state.user?.role === 'dept_chair',
        isDeanSecretary: (state) => state.user?.role === 'dean_secretary',
        canCounsel:      (state) => ['admin', 'gcu_staff'].includes(state.user?.role),
        canViewCases:    (state) => !['faculty', 'dean', 'dept_chair', 'dean_secretary'].includes(state.user?.role),
    },

    actions: {
        // Returns { otpRequired, otpToken, emailHint } when the server wants the
        // emailed 6-digit code; otherwise logs in and returns { otpRequired: false }.
        async login(email, password) {
            const response = await axios.post('/api/login', { email, password });
            if (response.data.otp_required) {
                return { otpRequired: true, otpToken: response.data.otp_token, emailHint: response.data.email_hint };
            }
            this.setSession(response.data);
            return { otpRequired: false };
        },

        async verifyOtp(otpToken, otp) {
            const response = await axios.post('/api/login/verify-otp', { otp_token: otpToken, otp });
            this.setSession(response.data);
        },

        setSession(data) {
            this.token = data.token;
            this.user  = data.user;
            localStorage.setItem('token', this.token);
            localStorage.setItem('user', JSON.stringify(this.user));
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        },

        async logout() {
            try {
                await axios.post('/api/logout');
            } catch (e) {}
            this.token = null;
            this.user  = null;
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            delete axios.defaults.headers.common['Authorization'];
        },

        initAuth() {
            if (this.token) {
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
            }
        },

        setUser(user) {
            this.user = user;
            localStorage.setItem('user', JSON.stringify(user));
        },
    },
});