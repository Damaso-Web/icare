import { createRouter, createWebHistory } from 'vue-router';

// Layouts
import MainLayout from '../layouts/MainLayout.vue';

// Views
import Login from '../views/Login.vue';
import Dashboard from '../views/Dashboard.vue';
import Students from '../views/students/Index.vue';
import StudentShow from '../views/students/Show.vue';
import Referrals from '../views/referrals/Index.vue';
import ReferralCreate from '../views/referrals/Create.vue';
import ReferralShow from '../views/referrals/Show.vue';
import Cases from '../views/cases/Index.vue';
import CaseShow from '../views/cases/Show.vue';
import Appointments from '../views/appointments/Index.vue';
import TestingRecords from '../views/testing/Index.vue';
import Reports from '../views/reports/Index.vue';
import Users from '../views/users/Index.vue';
import AuditLogs from '../views/audit/Index.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { guest: true },
    },
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '',                 name: 'dashboard',      component: Dashboard },
            { path: 'students',         name: 'students',       component: Students },
            { path: 'students/:id',     name: 'student-show',   component: StudentShow },
            { path: 'referrals',        name: 'referrals',      component: Referrals },
            { path: 'referrals/create', name: 'referral-create',component: ReferralCreate },
            { path: 'referrals/:id',    name: 'referral-show',  component: ReferralShow },
            { path: 'cases',            name: 'cases',          component: Cases },
            { path: 'cases/:id',        name: 'case-show',      component: CaseShow },
            { path: 'appointments',     name: 'appointments',   component: Appointments },
            { path: 'testing',          name: 'testing',        component: TestingRecords },
            { path: 'reports',          name: 'reports',        component: Reports },
            { path: 'users',            name: 'users',          component: Users },
            { path: 'audit',            name: 'audit',          component: AuditLogs },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/login',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    if (to.meta.requiresAuth && !token) {
        next({ name: 'login' });
    } else if (to.meta.guest && token) {
        next({ name: 'dashboard' });
    } else {
        next();
    }
});

export default router;