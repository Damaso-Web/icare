import { createRouter, createWebHistory } from 'vue-router';
import { roleIn } from '../utils/roles';
import { SELF_REFERRAL_ENABLED } from '../utils/features';

// Layouts
import RootEntry from '../views/RootEntry.vue';   // eager: it renders the landing at "/"

// Views
const Login = () => import('../views/Login.vue');
// Imported up front (not lazy) so their styles ship with the main bundle and a
// stale/failed chunk can never leave these pages blank or unstyled.
const StudentLogin = () => import('../views/StudentLogin.vue');
const CaseStudyReport = () => import('../views/cases/StudyReport.vue');
const Dashboard = () => import('../views/Dashboard.vue');
const Students = () => import('../views/students/Index.vue');
const StudentShow = () => import('../views/students/Show.vue');
const Referrals = () => import('../views/referrals/Index.vue');
const ReferChoice = () => import('../views/referrals/ReferChoice.vue');
const ReferralCreate = () => import('../views/referrals/Create.vue');
const ReferralShow = () => import('../views/referrals/Show.vue');
const ComplaintCreate = () => import('../views/complaints/Create.vue');
const Complaints = () => import('../views/complaints/Index.vue');
const IncidentReports = () => import('../views/incident-reports/Index.vue');
const IncidentReportShow = () => import('../views/incident-reports/Show.vue');
const Cases = () => import('../views/cases/Index.vue');
const Appointments = () => import('../views/appointments/Index.vue');
const TestingRecords = () => import('../views/testing/Index.vue');
const TestingRecordShow = () => import('../views/testing/Show.vue');
const TmduAppointments = () => import('../views/testing/Appointments.vue');
const Reports = () => import('../views/reports/Index.vue');
const Users = () => import('../views/users/Index.vue');
const AuditLogs = () => import('../views/audit/Index.vue');
const MyAccount = () => import('../views/MyAccount.vue');
const Notifications = () => import('../views/Notifications.vue');
const CallSlips = () => import('../views/callslips/Index.vue');
const Management = () => import('../views/management/Index.vue');
const Faculty = () => import('../views/faculty/Index.vue');
const CaseReferrals = () => import('../views/caseReferrals/Index.vue');
const CaseReferralShow = () => import('../views/caseReferrals/Show.vue');

// Role definitions
const ALL_ROLES = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary'];
const STAFF_ROLES = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'];
const GCU_ROLES = ['admin', 'gcu_staff'];
const ADMIN_ONLY = ['admin'];
const SYSTEM_ADMIN_ONLY = ['system_admin'];
// Management page: Admin and System Admin both get write access (Colleges,
// Programs, Departments, Referral Form Options). System Admin still has no
// access to students/referrals/cases/Users - only Admin has both.
const MANAGEMENT_ROLES = ['admin', 'system_admin'];
// Refer Student, File a Complaint and the Sent lists are open to every staff account.
const REFERRAL_SUBMITTERS = [...ALL_ROLES, 'system_admin'];
// Faculty page: Dean (whole college) and Dept Chair (own department).
const FACULTY_ROLES = ['dean', 'dept_chair'];   // the Admin manages accounts in User Management instead
// Testing Records (TMDU): shared with the GCU staff who referred the
// student, since the Testing Record Details page displays their original
// referral information too (a "shared case" the referring GCU staff can
// also view, per the Testing module requirements).
const TESTING_ROLES = ['tmdu_staff'];
// TMDU Appointments (calendar/queue) - unlike Testing Record Details, this
// isn't a shared-case view, so GCU staff don't need access here.
const TMDU_ROLES = ['tmdu_staff'];

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
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../views/ForgotPassword.vue'),
        meta: { public: true },
    },
    {
        // Resetting now uses an emailed 6-digit code on the Forgot Password page, not a link.
        path: '/reset-password',
        name: 'reset-password',
        redirect: (to) => ({ name: 'forgot-password', query: { type: to.query.type === 'student' ? 'student' : 'staff' } }),
        meta: { public: true },
    },
    {
        path: '/student/login',
        name: 'student-login',
        component: StudentLogin,
        meta: { public: true },
    },
    {
        path: '/register',
        name: 'staff-register',
        component: () => import('../views/PersonnelRegister.vue'),
        meta: { public: true },
    },
    {
        path: '/student/register',
        name: 'student-register',
        component: () => import('../views/StudentRegister.vue'),
        meta: { public: true },
    },
    {
        path: '/student',
        component: () => import('../layouts/StudentLayout.vue'),
        // B2 (security test): this was `meta: { public: true }`, which the global
        // guard below (`if (to.meta.public) return next();`) let through with no
        // auth check at all - so every page in here (dashboard, appointments,
        // account, referrals...) was reachable with no student_token whatsoever,
        // including by pressing Back after logging out. `studentArea` is checked
        // explicitly instead, so it can require student_token (see beforeEach).
        meta: { studentArea: true },
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
                path: 'refer',
                name: 'student-refer',
                component: () => import('../views/student/ReferSelf.vue'),
                // Off until the live database allows self-referrals (utils/features.js).
                beforeEnter: () => (SELF_REFERRAL_ENABLED ? true : { name: 'student-referrals' }),
            },
            {
                path: 'refer-student',
                name: 'student-refer-choice',
                component: () => import('../views/student/ReferChoice.vue'),
            },
            {
                path: 'refer-student/referral',
                name: 'student-refer-other',
                component: () => import('../views/student/ReferOther.vue'),
            },
            {
                path: 'refer-student/complaint',
                name: 'student-file-complaint',
                component: () => import('../views/student/FileComplaint.vue'),
            },
            {
                path: 'sent-referrals',
                name: 'student-sent-referrals',
                component: () => import('../views/sent/SentReferrals.vue'),
                meta: { student: true },
            },
            {
                path: 'sent-complaints',
                name: 'student-sent-complaints',
                component: () => import('../views/sent/SentComplaints.vue'),
                meta: { student: true },
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
            {
                path: 'notifications',
                name: 'student-notifications',
                component: Notifications,
                meta: { student: true },
            },
        ],
    },
    {
        path: '/',
        component: RootEntry,
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
                meta: { roles: GCU_ROLES },
            },
            {
                path: 'students/:id',
                name: 'student-show',
                component: StudentShow,
                // TMDU opens this from a psychological-testing referral; the API
                // scopes what it can see to that student's TMDU records only.
                meta: { roles: [...GCU_ROLES, 'tmdu_staff'] },
            },
            {
                path: 'referrals',
                name: 'referrals',
                component: Referrals,
                // B596/B605: a Dean or Dept Chair uses Sent Referrals instead -
                // the Referral Queue nav item (and this page) is no longer theirs.
                // Bug (10/25/2026, B626): same reasoning for plain Faculty - they
                // refer out, they don't process an incoming queue, so Sent
                // Referrals is their only status view too.
                meta: { roles: ['admin', 'gcu_staff', 'tmdu_staff'] },
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
                path: 'sent-referrals',
                name: 'sent-referrals',
                component: () => import('../views/sent/SentReferrals.vue'),
                meta: { roles: REFERRAL_SUBMITTERS },
            },
            {
                path: 'sent-complaints',
                name: 'sent-complaints',
                component: () => import('../views/sent/SentComplaints.vue'),
                meta: { roles: REFERRAL_SUBMITTERS },
            },
            {
                path: 'complaints',
                name: 'complaints',
                component: Complaints,
                meta: { roles: ['sdu_head'] },
            },
            {
                path: 'incident-reports',
                name: 'incident-reports',
                component: IncidentReports,
                meta: { roles: ['sdu_head'] },
            },
            {
                path: 'incident-reports/:id',
                name: 'incident-report-show',
                component: IncidentReportShow,
                meta: { roles: ['sdu_head'] },
            },
            {
                path: 'referrals/:id',
                name: 'referral-show',
                component: ReferralShow,
                meta: { roles: [...REFERRAL_SUBMITTERS, 'tmdu_staff'] },
            },
            {
                path: 'case-referrals',
                name: 'case-referrals',
                component: CaseReferrals,
                meta: { roles: GCU_ROLES },
            },
            {
                path: 'case-referrals/:id',
                name: 'case-referral-show',
                component: CaseReferralShow,
                meta: { roles: GCU_ROLES },
            },
            {
                path: 'cases',
                name: 'cases',
                component: Cases,
                meta: { roles: GCU_ROLES },
            },
            {
                path: 'appointments',
                name: 'appointments',
                component: Appointments,
                meta: { roles: ['admin', 'gcu_staff'] },
            },
            {
                path: 'testing',
                name: 'testing',
                component: TestingRecords,
                meta: { roles: TESTING_ROLES },
            },
            {
                // Must come before 'testing/:id' - otherwise the dynamic
                // route below would swallow this path, treating
                // "appointments" as a testing record id.
                path: 'testing/appointments',
                name: 'testing-appointments',
                component: TmduAppointments,
                meta: { roles: TMDU_ROLES },
            },
            {
                path: 'testing/:id',
                name: 'testing-show',
                component: TestingRecordShow,
                meta: { roles: TESTING_ROLES },
            },
            {
                path: 'college-report',
                name: 'college-report',
                component: () => import('../views/dean/CollegeReport.vue'),
                meta: { roles: ['dean'] },
            },
            {
                path: 'reports',
                name: 'reports',
                component: Reports,
                meta: { roles: STAFF_ROLES },
            },
            {
                path: 'users',
                name: 'users',
                component: Users,
                // GCU Head: every account. TMDU Head: TMDU Staff. Dean: their college's faculty and chairs.
                meta: { roles: ['admin', 'tmdu_head', 'dean'] },
            },
            {
                path: 'faculty',
                name: 'faculty-directory',
                component: Faculty,
                meta: { roles: FACULTY_ROLES },
            },
            {
                path: 'notifications',
                name: 'notifications',
                component: Notifications,
                meta: { roles: ALL_ROLES },
            },
            {
                path: 'my-account',
                name: 'my-account',
                component: MyAccount,
                meta: { roles: ALL_ROLES },
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
        component: CaseStudyReport,
        meta: { requiresAuth: true, roles: GCU_ROLES },
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
        // A real component: the built app has no runtime template compiler, so
        // an inline `template` string rendered as a blank page.
        component: () => import('../views/Unauthorized.vue'),
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

    // A student who must change their password can only use the Account page (the server refuses everything else).
    // (Not gated on to.meta.public/studentArea - this is about which PAGE is being
    // visited, not whether that page itself needs a token.)
    if (to.path.startsWith('/student') && localStorage.getItem('student_token')) {
        const stu = JSON.parse(localStorage.getItem('student') || '{}');
        if (stu?.must_change_password && to.name !== 'student-account') {
            return next({ name: 'student-account' });
        }
    }

    // Staff who must change their password (temporary password, admin reset, reactivation) can only use My Account.
    if (token && user?.must_change_password && to.name !== 'my-account' && !to.meta.guest && !to.meta.public) {
        return next({ name: 'my-account' });
    }

    // The student portal (dashboard, appointments, account, referrals...) - distinct
    // from the public student-login/student-register pages, which are separate,
    // genuinely `meta: { public: true }` routes outside this group.
    if (to.meta.studentArea) {
        if (!localStorage.getItem('student_token')) return next({ name: 'login-choice' });
        return next();
    }

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
        // The main address shows the landing page to visitors who are not signed in
        // (RootEntry renders it, so the address stays "/").
        if (!token && to.name === 'dashboard') return next();
        if (!token) return next({ name: 'login-choice' });

        // System Admin has no Dashboard (it's student/referral/case data, which
        // System Admin is not permitted to view) - land them on Management instead
        // of falling through to the default 'dashboard' redirect.
        if (role === 'system_admin' && to.name === 'dashboard') {
            return next({ name: 'management' });
        }

        // Check role access
        if (to.meta.roles && !roleIn(role, to.meta.roles)) {
            return next({ name: 'unauthorized' });
        }
    }

    next();
});

export default router;