/**
 * The seam between the UI and persistence.
 *
 * Everything above this file talks to `repository`; nothing above it knows
 * that the data lives behind a Laravel HTTP API.
 */
import { request } from './http'
import type {
    AdminArea,
    DeviceSession,
    Preferences,
    AdminBroadcasts,
    Badge,
    BadgeInput,
    GoalStep,
    Insights,
    Leaderboard,
    Portfolio,
    PortfolioProject,
    PortfolioVisibility,
    PublicPortfolio,
    QuizQuestion,
    SkillPath,
    Timeline,
    VisionBoard,
    VisionItemKind,
    VisionSize,
    PointsHistory,
    StoreItem,
    StoreItemInput,
    StoreKind,
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
    changePassword: (currentPassword: string, password: string) =>
        request<void>('PUT', '/profile/password', { currentPassword, password }),
    signOutOthers: () => request<void>('POST', '/profile/sign-out-others'),
    sessions: () => request<{ sessions: DeviceSession[] }>('GET', '/profile/sessions').then((r) => r.sessions),
    savePreferences: (preferences: Preferences) =>
        request<{ user: User }>('PUT', '/profile/preferences', preferences).then((r) => r.user),
    pointsHistory: (day: string) => request<PointsHistory>('GET', `/profile/points?date=${day}`),
    store: () => request<{ items: StoreItem[]; user: User }>('GET', '/store'),
    buyItem: (id: number) => request<{ items: StoreItem[]; user: User }>('POST', `/store/${id}/buy`),
    equipItem: (kind: StoreKind, itemId: number | null) =>
        request<{ items: StoreItem[]; user: User }>('PUT', '/store/equip', { kind, itemId }),
    insights: (day: string) => request<Insights>('GET', `/insights?date=${day}`),
    timeline: () => request<Timeline>('GET', '/timeline'),
    saveTimelineNote: (key: string, note: string) => request<void>('PUT', `/timeline/notes/${key}`, { note }),
    addMilestone: (input: { title: string; day: string; note: string }) =>
        request<{ id: number }>('POST', '/timeline', input),
    updateMilestone: (id: number, patch: { title?: string; day?: string; note?: string }) =>
        request<void>('PATCH', `/timeline/${id}`, patch),
    deleteMilestone: (id: number) => request<void>('DELETE', `/timeline/${id}`),
    roadmap: (goalId: number) => request<{ steps: GoalStep[] }>('GET', `/goals/${goalId}/steps`).then((r) => r.steps),
    addStep: (goalId: number, input: { title: string; requirements?: string; parentId?: number | null }) =>
        request<{ step: GoalStep }>('POST', `/goals/${goalId}/steps`, input).then((r) => r.step),
    updateStep: (
        id: number,
        patch: { title?: string; requirements?: string; isDone?: boolean; move?: 'up' | 'down' },
    ) => request<{ steps: GoalStep[] }>('PATCH', `/goal-steps/${id}`, patch).then((r) => r.steps),
    deleteStep: (id: number) => request<void>('DELETE', `/goal-steps/${id}`),
    backupStatus: () =>
        request<{ lastBackupAt: string | null; ok: boolean | null; serverTime: string }>(
            'GET',
            '/profile/backup-status',
        ),

    visionBoards: () => request<{ boards: VisionBoard[] }>('GET', '/vision-boards').then((r) => r.boards),
    createBoard: (input: Pick<VisionBoard, 'title' | 'area' | 'color'>) =>
        request<{ board: VisionBoard }>('POST', '/vision-boards', input).then((r) => r.board),
    updateBoard: (id: number, patch: Partial<Pick<VisionBoard, 'title' | 'area' | 'color'>> & { order?: number[] }) =>
        request<{ board: VisionBoard }>('PATCH', `/vision-boards/${id}`, patch).then((r) => r.board),
    deleteBoard: (id: number) => request<void>('DELETE', `/vision-boards/${id}`),
    addVisionItem: (
        boardId: number,
        input: { kind: VisionItemKind; text?: string; goalId?: number | null; image?: Blob | null },
    ) => {
        const form = new FormData()
        form.set('kind', input.kind)
        if (input.text) form.set('text', input.text)
        if (input.goalId) form.set('goalId', String(input.goalId))
        if (input.image) form.set('image', input.image, 'vision.jpg')
        return request<{ board: VisionBoard }>('POST', `/vision-boards/${boardId}/items`, form).then((r) => r.board)
    },
    updateVisionItem: (id: number, patch: { text?: string; size?: VisionSize; goalId?: number | null }) =>
        request<{ board: VisionBoard }>('PATCH', `/vision-items/${id}`, patch).then((r) => r.board),
    deleteVisionItem: (id: number) => request<void>('DELETE', `/vision-items/${id}`),

    skills: () => request<{ paths: SkillPath[] }>('GET', '/skills').then((r) => r.paths),
    attemptLevel: (id: number, answers?: number[]) =>
        request<{ passed: boolean; score: number; needed: number; paths: SkillPath[] }>(
            'POST',
            `/skill-levels/${id}/attempt`,
            answers ? { answers } : {},
        ),
    adminSkillPaths: () => request<{ paths: SkillPath[] }>('GET', '/admin/skill-paths').then((r) => r.paths),
    adminSavePath: (id: number | null, input: { title?: string; description?: string; isActive?: boolean }) =>
        request<{ paths: SkillPath[] }>(
            id === null ? 'POST' : 'PATCH',
            id === null ? '/admin/skill-paths' : `/admin/skill-paths/${id}`,
            input,
        ).then((r) => r.paths),
    adminDeletePath: (id: number) => request<void>('DELETE', `/admin/skill-paths/${id}`),
    adminSaveLevel: (
        pathId: number,
        id: number | null,
        input: {
            title?: string
            description?: string
            challenge?: string
            lessonId?: number | null
            quiz?: QuizQuestion[]
            move?: 'up' | 'down'
        },
    ) =>
        request<{ paths: SkillPath[] }>(
            id === null ? 'POST' : 'PATCH',
            id === null ? `/admin/skill-paths/${pathId}/levels` : `/admin/skill-paths/${pathId}/levels/${id}`,
            input,
        ).then((r) => r.paths),
    adminDeleteLevel: (pathId: number, id: number) =>
        request<void>('DELETE', `/admin/skill-paths/${pathId}/levels/${id}`),

    portfolio: () => request<Portfolio>('GET', '/portfolio'),
    saveProject: (
        id: number | null,
        input: Partial<Omit<PortfolioProject, 'id' | 'imageUrl'>> & { image?: Blob | null; removeImage?: boolean },
    ) => {
        const form = new FormData()
        for (const key of ['title', 'description', 'category', 'link', 'completedOn'] as const) {
            if (input[key] !== undefined) form.set(key, input[key] ?? '')
        }
        input.skills?.forEach((skill, index) => form.set(`skills[${index}]`, skill))
        if (input.removeImage) form.set('removeImage', '1')
        if (input.image) form.set('image', input.image, 'project.jpg')
        return request<Portfolio>('POST', id === null ? '/portfolio' : `/portfolio/${id}`, form)
    },
    deleteProject: (id: number) => request<void>('DELETE', `/portfolio/${id}`),
    portfolioSettings: (patch: { visibility?: PortfolioVisibility; newLink?: boolean }) =>
        request<Portfolio>('PUT', '/portfolio-settings', patch),
    publicPortfolio: (slug: string) => request<PublicPortfolio>('GET', `/public/portfolio/${slug}`),
    leaderboard: (day: string) => request<Leaderboard>('GET', `/leaderboard?date=${day}`),
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
    adminUpdateUser: (
        id: number,
        patch: { displayName?: string; email?: string; role?: Role; isPremium?: boolean; permissions?: AdminArea[] },
    ) => request<{ user: AdminUser }>('PATCH', `/admin/users/${id}`, patch).then((r) => r.user),
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
    adminStoreItems: () => request<{ items: StoreItem[] }>('GET', '/admin/store-items').then((r) => r.items),
    adminSaveStoreItem: (id: number | null, input: Partial<StoreItemInput>) =>
        request<{ item: StoreItem }>(
            'POST',
            id === null ? '/admin/store-items' : `/admin/store-items/${id}`,
            storeItemForm(input),
        ).then((r) => r.item),
    adminDeleteStoreItem: (id: number) => request<void>('DELETE', `/admin/store-items/${id}`),
    adminBroadcasts: (from: string) => request<AdminBroadcasts>('GET', `/admin/broadcasts?from=${from}`),
    adminScheduleBroadcast: (day: string, body: string) =>
        request<void>('PUT', `/admin/broadcasts/schedule/${day}`, { body }),
    adminSendBroadcast: (body: string, day: string) =>
        request<{ id: number }>('POST', '/admin/broadcasts', { body, day }),
    adminDeleteBroadcast: (id: number) => request<void>('DELETE', `/admin/broadcasts/${id}`),
    adminLessons: () => request<{ lessons: Lesson[] }>('GET', '/admin/lessons').then((r) => r.lessons),
    adminSaveLesson: (id: number | null, input: Partial<LessonInput>) =>
        request<{ lesson: Lesson }>(
            'POST',
            id === null ? '/admin/lessons' : `/admin/lessons/${id}`,
            lessonForm(input),
        ).then((r) => r.lesson),
    adminDeleteLesson: (id: number) => request<void>('DELETE', `/admin/lessons/${id}`),
}

/** Store items travel as multipart form data so the picture can go with the details. */
function storeItemForm(input: Partial<StoreItemInput>): FormData {
    const form = new FormData()
    for (const key of ['name', 'description', 'kind', 'value'] as const) {
        if (input[key] !== undefined) form.set(key, input[key])
    }
    if (input.price !== undefined) form.set('price', String(input.price))
    if (input.isActive !== undefined) form.set('isActive', input.isActive ? '1' : '0')
    if (input.removeImage) form.set('removeImage', '1')
    if (input.image) form.set('image', input.image, 'item.png')
    return form
}

/** Lessons travel as multipart form data so the image can go with the text. */
function lessonForm(input: Partial<LessonInput>): FormData {
    const form = new FormData()
    if (input.title !== undefined) form.set('title', input.title)
    if (input.body !== undefined) form.set('body', input.body)
    if (input.isPublished !== undefined) form.set('isPublished', input.isPublished ? '1' : '0')
    if (input.isPremium !== undefined) form.set('isPremium', input.isPremium ? '1' : '0')
    if (input.removeImage) form.set('removeImage', '1')
    if (input.image) form.set('image', input.image, 'lesson.jpg')
    return form
}
