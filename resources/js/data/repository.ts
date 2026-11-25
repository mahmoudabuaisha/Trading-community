/**
 * The seam between the UI and persistence.
 *
 * Everything above this file talks to `repository`; nothing above it knows
 * that the data lives behind a Laravel HTTP API.
 */
import { request } from './http'
import type {
    Badge,
    BadgeInput,
    Habit,
    HabitMonth,
    MoodEntry,
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
    Lesson,
    LessonInput,
    Message,
    SiteContent,
    Thread,
    ThreadUser,
    UnreadCounts,
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
    dismissReminder: (id: number) =>
        request<{ task: Task }>('POST', `/tasks/${id}/dismiss-reminder`).then((r) => r.task),
    createGoal: (input: GoalInput) => request<{ goal: Goal }>('POST', '/goals', input).then((r) => r.goal),
    updateGoal: (id: number, patch: { currentValue: number }) =>
        request<{ goal: Goal; newAchievements: Achievement[] }>('PATCH', `/goals/${id}`, patch),
    deleteGoal: (id: number) => request<void>('DELETE', `/goals/${id}`),
    saveProfile: (input: ProfileInput) => request<{ user: User }>('PUT', '/profile', input).then((r) => r.user),
    logFocusSession: (minutes: number, day: string) =>
        request<{ focus: FocusTotals; newAchievements: Achievement[] }>('POST', '/focus-sessions', { minutes, day }),
    saveReview: (review: Pick<DailyReview, 'date' | 'win' | 'unfinished' | 'lesson' | 'tomorrow'>) =>
        request<{ review: DailyReview }>('PUT', `/reviews/${review.date}`, review).then((r) => r.review),

    loadHabits: (month: string) => request<HabitMonth>('GET', `/habits?month=${month}`),
    createHabit: (name: string, targetDays: number | null) =>
        request<{ habit: Habit }>('POST', '/habits', { name, targetDays }).then((r) => r.habit),
    updateHabit: (id: number, patch: { name?: string; targetDays?: number | null }) =>
        request<{ habit: Habit }>('PATCH', `/habits/${id}`, patch).then((r) => r.habit),
    deleteHabit: (id: number) => request<void>('DELETE', `/habits/${id}`),
    setHabitCheck: (id: number, day: string, done: boolean) =>
        request<void>('PUT', `/habits/${id}/checks/${day}`, { done }),
    saveMood: (entry: MoodEntry) =>
        request<MoodEntry>('PUT', `/moods/${entry.day}`, { mood: entry.mood, motivation: entry.motivation }),
    saveHabitNote: (month: string, body: string) =>
        request<{ note: string }>('PUT', `/habit-notes/${month}`, { body }).then((r) => r.note),
    loadLessons: () => request<{ lessons: Lesson[] }>('GET', '/lessons').then((r) => r.lessons),
    loadMessages: () => request<{ messages: Message[] }>('GET', '/messages').then((r) => r.messages),
    sendMessage: (body: string) => request<{ message: Message }>('POST', '/messages', { body }).then((r) => r.message),
    unreadCounts: () => request<UnreadCounts>('GET', '/messages/unread'),

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
    adminBadges: () => request<{ badges: Badge[] }>('GET', '/admin/badges').then((r) => r.badges),
    adminSaveBadge: (id: number | null, input: Partial<BadgeInput>) =>
        request<{ badge: Badge }>(
            id === null ? 'POST' : 'PATCH',
            id === null ? '/admin/badges' : `/admin/badges/${id}`,
            input,
        ).then((r) => r.badge),
    adminDeleteBadge: (id: number) => request<void>('DELETE', `/admin/badges/${id}`),
    adminContent: () => request<{ content: SiteContent }>('GET', '/admin/settings').then((r) => r.content),
    adminSaveContent: (content: SiteContent) =>
        request<{ content: SiteContent }>('PUT', '/admin/settings', content).then((r) => r.content),
    adminThreads: () => request<{ threads: Thread[] }>('GET', '/admin/threads').then((r) => r.threads),
    adminThread: (userId: number) =>
        request<{ user: ThreadUser; messages: Message[] }>('GET', `/admin/threads/${userId}`),
    adminSendMessage: (userId: number, body: string) =>
        request<{ message: Message }>('POST', `/admin/threads/${userId}`, { body }).then((r) => r.message),
    adminLessons: () => request<{ lessons: Lesson[] }>('GET', '/admin/lessons').then((r) => r.lessons),
    adminSaveLesson: (id: number | null, input: Partial<LessonInput>) =>
        request<{ lesson: Lesson }>(
            'POST',
            id === null ? '/admin/lessons' : `/admin/lessons/${id}`,
            lessonForm(input),
        ).then((r) => r.lesson),
    adminDeleteLesson: (id: number) => request<void>('DELETE', `/admin/lessons/${id}`),
}

/** Lessons travel as multipart form data so the image can go with the text. */
function lessonForm(input: Partial<LessonInput>): FormData {
    const form = new FormData()
    if (input.title !== undefined) form.set('title', input.title)
    if (input.body !== undefined) form.set('body', input.body)
    if (input.isPublished !== undefined) form.set('isPublished', input.isPublished ? '1' : '0')
    if (input.removeImage) form.set('removeImage', '1')
    if (input.image) form.set('image', input.image, 'lesson.jpg')
    return form
}
