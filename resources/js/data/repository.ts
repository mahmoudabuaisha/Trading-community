/**
 * The seam between the UI and persistence.
 *
 * Everything above this file talks to `repository`; nothing above it knows
 * that the data lives behind a Laravel HTTP API.
 */
import { request } from './http'
import type {
    Achievement,
    AdminStats,
    AdminUser,
    AuditEntry,
    DailyReview,
    DashboardPayload,
    FocusTotals,
    Goal,
    GoalInput,
    Motivation,
    MotivationInput,
    Paginated,
    ProfileInput,
    Role,
    Task,
    TaskInput,
    TaskStatus,
    User,
} from '../types/domain'

export const repository = {
    // Auth
    me: () => request<{ user: User }>('GET', '/auth/me').then((r) => r.user),
    login: (email: string, password: string, remember: boolean) =>
        request<{ user: User }>('POST', '/auth/login', { email, password, remember }).then((r) => r.user),
    register: (displayName: string, email: string, password: string) =>
        request<{ user: User }>('POST', '/auth/register', { displayName, email, password }).then((r) => r.user),
    logout: () => request<void>('POST', '/auth/logout'),
    forgotPassword: (email: string) => request<{ message: string }>('POST', '/auth/forgot-password', { email }),
    resetPassword: (token: string, email: string, password: string) =>
        request<{ message: string }>('POST', '/auth/reset-password', { token, email, password }),

    // Workspace
    loadDashboard: (day: string) => request<DashboardPayload>('GET', `/dashboard?date=${day}`),
    createTask: (input: TaskInput) => request<{ task: Task }>('POST', '/tasks', input).then((r) => r.task),
    updateTask: (id: number, patch: Partial<TaskInput> & { status?: TaskStatus }) =>
        request<{ task: Task; newAchievements: Achievement[] }>('PATCH', `/tasks/${id}`, patch),
    deleteTask: (id: number) => request<void>('DELETE', `/tasks/${id}`),
    createGoal: (input: GoalInput) => request<{ goal: Goal }>('POST', '/goals', input).then((r) => r.goal),
    updateGoal: (id: number, patch: { currentValue: number }) =>
        request<{ goal: Goal; newAchievements: Achievement[] }>('PATCH', `/goals/${id}`, patch),
    deleteGoal: (id: number) => request<void>('DELETE', `/goals/${id}`),
    saveProfile: (input: ProfileInput) => request<{ user: User }>('PUT', '/profile', input).then((r) => r.user),
    logFocusSession: (minutes: number, day: string) =>
        request<{ focus: FocusTotals; newAchievements: Achievement[] }>('POST', '/focus-sessions', { minutes, day }),
    saveReview: (review: Pick<DailyReview, 'date' | 'win' | 'unfinished' | 'lesson' | 'tomorrow'>) =>
        request<{ review: DailyReview }>('PUT', `/reviews/${review.date}`, review).then((r) => r.review),

    // Admin
    adminOverview: () => request<{ stats: AdminStats; audit: AuditEntry[] }>('GET', '/admin/overview'),
    adminUsers: (search: string, page: number) =>
        request<Paginated<AdminUser>>('GET', `/admin/users?search=${encodeURIComponent(search)}&page=${page}`),
    adminUpdateUser: (id: number, patch: { displayName?: string; email?: string; role?: Role }) =>
        request<{ user: AdminUser }>('PATCH', `/admin/users/${id}`, patch).then((r) => r.user),
    adminRevokeSessions: (id: number) => request<void>('POST', `/admin/users/${id}/revoke-sessions`),
    adminMotivations: () =>
        request<{ motivations: Motivation[] }>('GET', '/admin/motivations').then((r) => r.motivations),
    adminCreateMotivation: (input: MotivationInput) =>
        request<{ motivation: Motivation }>('POST', '/admin/motivations', input).then((r) => r.motivation),
    adminUpdateMotivation: (id: number, patch: Partial<MotivationInput>) =>
        request<{ motivation: Motivation }>('PATCH', `/admin/motivations/${id}`, patch).then((r) => r.motivation),
    adminDeleteMotivation: (id: number) => request<void>('DELETE', `/admin/motivations/${id}`),
}
