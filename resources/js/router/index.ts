import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { PERIOD_TYPES } from '../types/domain'

const routes: RouteRecordRaw[] = [
    { path: '/', name: 'auth', component: () => import('../views/AuthView.vue'), meta: { guest: true } },
    {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: () => import('../views/ResetPasswordView.vue'),
        meta: { guest: true },
    },
    {
        path: '/app',
        component: () => import('../layouts/WorkspaceLayout.vue'),
        meta: { auth: true },
        children: [
            { path: '', redirect: { name: 'overview' } },
            { path: 'overview', name: 'overview', component: () => import('../views/OverviewView.vue') },
            {
                path: `tasks/:period(${PERIOD_TYPES.join('|')})`,
                name: 'tasks',
                component: () => import('../views/TasksView.vue'),
            },
            { path: 'goals', name: 'goals', component: () => import('../views/GoalsView.vue') },
            { path: 'habits', name: 'habits', component: () => import('../views/HabitsView.vue') },
            { path: 'achievements', name: 'achievements', component: () => import('../views/AchievementsView.vue') },
            { path: 'growth', name: 'growth', component: () => import('../views/GrowthView.vue') },
            {
                path: 'profile/:section(settings|security|stats|rewards)?',
                name: 'profile',
                component: () => import('../views/ProfileView.vue'),
            },
            { path: 'education', name: 'education', component: () => import('../views/EducationView.vue') },
            { path: 'education/:id(\\d+)', name: 'lesson', component: () => import('../views/LessonView.vue') },
            { path: 'store', name: 'store', component: () => import('../views/StoreView.vue') },
            { path: 'leaderboard', name: 'leaderboard', component: () => import('../views/LeaderboardView.vue') },
            { path: 'messages', name: 'messages', component: () => import('../views/MessagesView.vue') },
            // Admin has its own route and guard; the server enforces the role again on every request.
            {
                path: 'admin',
                name: 'admin',
                component: () => import('../views/admin/AdminView.vue'),
                meta: { admin: true },
            },
        ],
    },
    { path: '/dashboard', redirect: { name: 'overview' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()
    await auth.ensureLoaded()
    if (to.meta.auth && !auth.user) return { name: 'auth', query: { next: to.fullPath } }
    if (to.meta.admin && !auth.isStaff) return { name: 'overview' }
    if (to.meta.guest && auth.user) return { name: 'overview' }
    return true
})
