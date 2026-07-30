import { defineStore } from 'pinia';
import axios from 'axios';

axios.defaults.baseURL = 'https://icare-backend-5jwe.onrender.com';
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
        isFaculty:       (state) => state.user?.role === 'faculty',
        isDeanSecretary: (state) => state.user?.role === 'dean_secretary',
        canCounsel:      (state) => ['admin', 'gcu_staff'].includes(state.user?.role),
        canViewCases:    (state) => !['faculty', 'dean_secretary'].includes(state.user?.role),
    },

    actions: {
        async login(email, password) {
            const response = await axios.post('/api/login', { email, password });
            this.token = response.data.token;
            this.user  = response.data.user;
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
    },
});