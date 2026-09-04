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

// Role definitions
const ALL_ROLES = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'];
const STAFF_ROLES = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'];
const GCU_ROLES = ['admin', 'gcu_staff'];
const ADMIN_ONLY = ['admin'];
const REFERRAL_SUBMITTERS = ['admin', 'gcu_staff', 'sdu_head', 'faculty', 'dean_secretary'];

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
            {
                path: '',
                name: 'dashboard',
                component: Dashboard,
                meta: { roles: ALL_ROLES },
            },
            {
                path: 'students',
                name: 'students',
                component: Students,
                meta: { roles: STAFF_ROLES },
            },
            {
                path: 'students/:id',
                name: 'student-show',
                component: StudentShow,
                meta: { roles: STAFF_ROLES },
            },
            {
                path: 'referrals',
                name: 'referrals',
                component: Referrals,
                meta: { roles: ['admin', 'gcu_staff', 'sdu_head'] },
            },
            {
                path: 'referrals/create',
                name: 'referral-create',
                component: ReferralCreate,
                meta: { roles: REFERRAL_SUBMITTERS },
            },
            {
                path: 'referrals/:id',
                name: 'referral-show',
                component: ReferralShow,
                meta: { roles: [...REFERRAL_SUBMITTERS, 'tmdu_staff'] },
            },
            {
                path: 'cases',
                name: 'cases',
                component: Cases,
                meta: { roles: STAFF_ROLES },
            },
            {
                path: 'cases/:id',
                name: 'case-show',
                component: CaseShow,
                meta: { roles: STAFF_ROLES },
            },
            {
                path: 'appointments',
                name: 'appointments',
                component: Appointments,
                meta: { roles: STAFF_ROLES },
            },
            {
                path: 'testing',
                name: 'testing',
                component: TestingRecords,
                meta: { roles: ['admin', 'gcu_staff', 'tmdu_staff'] },
            },
            {
                path: 'reports',
                name: 'reports',
                component: Reports,
                meta: { roles: GCU_ROLES },
            },
            {
                path: 'users',
                name: 'users',
                component: Users,
                meta: { roles: ADMIN_ONLY },
            },
            {
                path: 'faculty',
                name: 'faculty-directory',
                component: Users,
                meta: { roles: ADMIN_ONLY },
            },
            {
                path: 'audit',
                name: 'audit',
                component: AuditLogs,
                meta: { roles: ADMIN_ONLY },
            },
        ],
    },
    {
        path: '/unauthorized',
        name: 'unauthorized',
        component: {
            template: `
                <div style="display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:16px;background:var(--cloud)">
                    <div style="font-size:48px">🔒</div>
                    <div style="font-size:20px;font-weight:600;color:var(--forest)">Access Denied</div>
                    <div style="font-size:14px;color:var(--stone)">You don't have permission to view this page.</div>
                    <button onclick="history.back()" style="padding:10px 20px;background:var(--moss);color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px">Go Back</button>
                </div>
            `,
        },
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
    const user  = JSON.parse(localStorage.getItem('user') || '{}');
    const role  = user?.role;

    // Guest routes (login)
    if (to.meta.guest) {
        if (token) return next({ name: 'dashboard' });
        return next();
    }

    // Auth required
    if (to.meta.requiresAuth || to.meta.roles) {
        if (!token) return next({ name: 'login' });

        // Check role access
        if (to.meta.roles && !to.meta.roles.includes(role)) {
            return next({ name: 'unauthorized' });
        }
    }

    next();
});

export default router;