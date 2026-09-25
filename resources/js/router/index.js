import { createRouter, createWebHistory } from 'vue-router';

// Layouts
import MainLayout from '../layouts/MainLayout.vue';

// Views
import Login from '../views/Login.vue';
import Dashboard from '../views/Dashboard.vue';
import Students from '../views/students/Index.vue';
import StudentShow from '../views/students/Show.vue';
import Referrals from '../views/referrals/Index.vue';
import ReferChoice from '../views/referrals/ReferChoice.vue';
import ReferralCreate from '../views/referrals/Create.vue';
import ReferralShow from '../views/referrals/Show.vue';
import ComplaintCreate from '../views/complaints/Create.vue';
import Complaints from '../views/complaints/Index.vue';
import Cases from '../views/cases/Index.vue';
import Appointments from '../views/appointments/Index.vue';
import TestingRecords from '../views/testing/Index.vue';
import Reports from '../views/reports/Index.vue';
import Users from '../views/users/Index.vue';
import AuditLogs from '../views/audit/Index.vue';
import MyAccount from '../views/MyAccount.vue';
import CallSlips from '../views/callslips/Index.vue';
import Management from '../views/management/Index.vue';

// Role definitions
const ALL_ROLES = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'];
const STAFF_ROLES = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'];
const GCU_ROLES = ['admin', 'gcu_staff'];
const ADMIN_ONLY = ['admin'];
const SYSTEM_ADMIN_ONLY = ['system_admin'];
// Management page: Admin and System Admin both get write access (Colleges,
// Programs, Departments, Referral Form Options). System Admin still has no
// access to students/referrals/cases/Users - only Admin has both.
const MANAGEMENT_ROLES = ['admin', 'system_admin'];
const REFERRAL_SUBMITTERS = ['admin', 'gcu_staff', 'sdu_head', 'faculty', 'dean_secretary'];

const routes = [
    {
        path: '/welcome',
        name: 'login-choice',
        component: () => import('../views/LoginChoice.vue'),
        meta: { public: true },
    },
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { guest: true },
    },
    {
        path: '/schedule/:token',
        name: 'public-schedule',
        component: () => import('../views/public/Schedule.vue'),
        meta: { public: true },
    },
    {
        path: '/student/login',
        name: 'student-login',
        component: () => import('../views/StudentLogin.vue'),
        meta: { public: true },
    },
    {
        path: '/student',
        component: () => import('../layouts/StudentLayout.vue'),
        meta: { public: true },
        children: [
            {
                path: 'dashboard',
                name: 'student-dashboard',
                component: () => import('../views/student/StudentDashboard.vue'),
            },
            {
                path: 'appointments',
                name: 'student-appointments',
                component: () => import('../views/student/Appointments.vue'),
            },
            {
            path: 'schedule/new',
            name: 'student-schedule-new',
            component: () => import('../views/student/ScheduleNew.vue'),
            },
            {
                path: 'appointments/:id',
                name: 'student-appointment-show',
                component: () => import('../views/student/AppointmentShow.vue'),
            },
            {
                path: 'referrals',
                name: 'student-referrals',
                component: () => import('../views/student/Referrals.vue'),
            },
            {
                path: 'referrals/:id',
                name: 'student-referral-show',
                component: () => import('../views/student/ReferralShow.vue'),
            },
            {
                path: 'testing',
                name: 'student-testing',
                component: () => import('../views/student/Testing.vue'),
            },
            {
                path: 'account',
                name: 'student-account',
                component: () => import('../views/student/Account.vue'),
            },
        ],
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
                meta: { roles: ['admin', 'gcu_staff', 'sdu_head', 'faculty'] },
            },
            {
                path: 'referrals/create',
                name: 'referral-create',
                component: ReferChoice,
                meta: { roles: REFERRAL_SUBMITTERS },
            },
            {
                path: 'referrals/create/student',
                name: 'referral-create-form',
                component: ReferralCreate,
                meta: { roles: REFERRAL_SUBMITTERS },
            },
            {
                path: 'referrals/create/complaint',
                name: 'complaint-create',
                component: ComplaintCreate,
                meta: { roles: REFERRAL_SUBMITTERS },
            },
            {
                path: 'complaints',
                name: 'complaints',
                component: Complaints,
                meta: { roles: ['sdu_head'] },
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
                path: 'my-account',
                name: 'my-account',
                component: MyAccount,
                meta: { roles: ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'] },
            },
            {
                path: 'audit',
                name: 'audit',
                component: AuditLogs,
                meta: { roles: ADMIN_ONLY },
            },
            {
                path: 'call-slips',
                name: 'call-slips',
                component: CallSlips,
                meta: { roles: ['dean_secretary'] },
            },
            {
                path: 'backup',
                name: 'backup',
                component: () => import('../views/backup/Index.vue'),
                meta: { roles: ADMIN_ONLY },
            },
            {
                path: 'management',
                name: 'management',
                component: Management,
                meta: { roles: MANAGEMENT_ROLES },
            },
        ],
    },
    {
        path: '/cases/:id/study-report',
        name: 'case-study-report',
        component: () => import('../views/cases/StudyReport.vue'),
        meta: { requiresAuth: true, roles: STAFF_ROLES },
    },
    {
        path: '/call-slips/:id/print',
        name: 'call-slip-print',
        component: () => import('../views/callslips/CallSlipForm.vue'),
        meta: { requiresAuth: true, roles: ['dean_secretary', 'admin'] },
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
        redirect: '/welcome',
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

    // Public routes (no auth needed, e.g. student scheduling link, login choice)
    if (to.meta.public) {
        return next();
    }

    // Guest routes (login)
    if (to.meta.guest) {
        if (token) return next({ name: 'dashboard' });
        return next();
    }

    // Auth required
    if (to.meta.requiresAuth || to.meta.roles) {
        if (!token) return next({ name: 'login-choice' });

        // System Admin has no Dashboard (it's student/referral/case data, which
        // System Admin is not permitted to view) - land them on Management instead
        // of falling through to the default 'dashboard' redirect.
        if (role === 'system_admin' && to.name === 'dashboard') {
            return next({ name: 'management' });
        }

        // Check role access
        if (to.meta.roles && !to.meta.roles.includes(role)) {
            return next({ name: 'unauthorized' });
        }
    }

    next();
});

export default router;