/**
 * The seam between the UI and persistence.
 *
 * Everything above this file talks to `repository`; nothing above it knows
 * that the data lives behind a Laravel HTTP API.
 */
import type { WorldData } from '../world/summitScene'
import { request } from './http'
import type {
    AdminArea,
    DeviceSession,
    Preferences,
    AdminBroadcasts,
    AssistantStatus,
    Design,
    DesignTarget,
    Book,
    GrantType,
    SubscriptionSheet,
    Category,
    DailyTipItem,
    DailyTipState,
    BroadcastSchedule,
    UserDetails,
    DayEntry,
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
    StoreCategory,
    StoreItem,
    StoreItemInput,
    StoreKind,
    TranslationSheet,
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

/** What the store answers with: the items, the admin-made sections, and the member's fresh balance. */
type StoreView = { items: StoreItem[]; categories: StoreCategory[]; user: User }

type DesignsView = { designs: Design[]; selected: Record<DesignTarget, number | null>; user: User }
type AdminDesignsView = { designs: Design[]; layouts: Record<DesignTarget, string[]>; nodeShapes: string[] }

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
    store: () => request<StoreView>('GET', '/store'),
    buyItem: (id: number) => request<StoreView>('POST', `/store/${id}/buy`),
    equipItem: (kind: StoreKind, itemId: number | null) => request<StoreView>('PUT', '/store/equip', { kind, itemId }),
    dailyTip: (day: string) => request<DailyTipState>('GET', `/daily-tip?date=${day}`),
    drawDailyTip: (day: string) => request<DailyTipState & { reel: string[] }>('POST', '/daily-tip', { date: day }),
    dayEntries: (month: string) =>
        request<{ days: DayEntry[] }>('GET', `/habit-days?month=${month}`).then((r) => r.days),
    createCategory: (input: { name: string; color: string }) =>
        request<{ category: Category }>('POST', '/categories', input).then((r) => r.category),
    updateCategory: (id: number, input: { name: string; color: string }) =>
        request<{ category: Category }>('PATCH', `/categories/${id}`, input).then((r) => r.category),
    deleteCategory: (id: number) => request<void>('DELETE', `/categories/${id}`),
    adminDailyTips: () => request<{ tips: DailyTipItem[] }>('GET', '/admin/daily-tips').then((r) => r.tips),
    adminAddDailyTips: (bodies: string[]) =>
        request<{ tips: DailyTipItem[] }>('POST', '/admin/daily-tips', { bodies }).then((r) => r.tips),
    adminSaveDailyTip: (id: number, patch: { body?: string; isActive?: boolean }) =>
        request<{ tips: DailyTipItem[] }>('PATCH', `/admin/daily-tips/${id}`, patch).then((r) => r.tips),
    adminDeleteDailyTip: (id: number) => request<void>('DELETE', `/admin/daily-tips/${id}`),
    designs: () => request<DesignsView>('GET', '/designs'),
    buyDesign: (id: number) => request<DesignsView>('POST', `/designs/${id}/buy`),
    selectDesign: (id: number) => request<DesignsView>('PUT', '/designs/select', { designId: id }),
    adminDesigns: () => request<AdminDesignsView>('GET', '/admin/designs'),
    adminSaveDesign: (
        id: number | null,
        input: Partial<Omit<Design, 'id' | 'backgroundUrl' | 'owned'>> & {
            background?: File | null
            removeBackground?: boolean
        },
    ) => {
        const form = new FormData()
        for (const key of ['target', 'layout', 'name', 'description', 'accent', 'nodeShape'] as const) {
            if (input[key] !== undefined) form.set(key, input[key]!)
        }
        if (input.price !== undefined) form.set('price', String(input.price))
        if (input.isActive !== undefined) form.set('isActive', input.isActive ? '1' : '0')
        if (input.removeBackground) form.set('removeBackground', '1')
        if (input.background) form.set('background', input.background, input.background.name)
        return request<AdminDesignsView>('POST', id === null ? '/admin/designs' : `/admin/designs/${id}`, form)
    },
    adminDeleteDesign: (id: number) => request<void>('DELETE', `/admin/designs/${id}`),
    world: () => request<Omit<WorldData, 'accent'>>('GET', '/world'),
    assistantStatus: () => request<AssistantStatus>('GET', '/assistant'),
    askAssistant: (messages: { role: 'user' | 'assistant'; content: string }[]) =>
        request<AssistantStatus & { reply: string }>('POST', '/assistant', { messages }),
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
    saveDayEntry: (day: string, input: { note: string; image?: Blob | null; removeImage?: boolean }) => {
        const form = new FormData()
        form.set('note', input.note)
        if (input.removeImage) form.set('removeImage', '1')
        if (input.image) form.set('image', input.image, 'day.jpg')
        return request<{ entry: DayEntry | null }>('POST', `/habit-days/${day}`, form).then((r) => r.entry)
    },
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
    loadMessages: () => request<{ messages: Message[]; notice: string | null }>('GET', '/messages'),
    sendMessage: (body: string) => request<{ message: Message }>('POST', '/messages', { body }).then((r) => r.message),
    unreadCounts: () => request<UnreadCounts>('GET', '/messages/unread'),

    // Admin
    adminOverview: () => request<{ stats: AdminStats; audit: AuditEntry[] }>('GET', '/admin/overview'),
    adminUsers: (search: string, page: number) =>
        request<Paginated<AdminUser>>('GET', `/admin/users?search=${encodeURIComponent(search)}&page=${page}`),
    adminUpdateUser: (id: number, patch: { role?: Role; isPremium?: boolean; permissions?: AdminArea[] }) =>
        request<{ user: AdminUser }>('PATCH', `/admin/users/${id}`, patch).then((r) => r.user),
    books: () => request<{ books: Book[] }>('GET', '/books').then((r) => r.books),
    adminBooks: () => request<{ books: Book[] }>('GET', '/admin/books').then((r) => r.books),
    adminSaveBook: (
        id: number | null,
        input: {
            title?: string
            author?: string
            description?: string
            isPublished?: boolean
            cover?: File | null
            file?: File | null
        },
    ) => {
        const form = new FormData()
        for (const key of ['title', 'author', 'description'] as const) {
            if (input[key] !== undefined) form.set(key, input[key]!)
        }
        if (input.isPublished !== undefined) form.set('isPublished', input.isPublished ? '1' : '0')
        if (input.cover) form.set('cover', input.cover, input.cover.name)
        if (input.file) form.set('file', input.file, input.file.name)
        return request<{ books: Book[] }>('POST', id === null ? '/admin/books' : `/admin/books/${id}`, form).then(
            (r) => r.books,
        )
    },
    adminDeleteBook: (id: number) => request<void>('DELETE', `/admin/books/${id}`),
    adminSubscription: (userId: number) => request<SubscriptionSheet>('GET', `/admin/users/${userId}/subscription`),
    adminSaveSubscription: (
        userId: number,
        input: { isPremium: boolean; premiumUntil: string | null; grants: Record<GrantType, number[]> },
    ) => request<SubscriptionSheet>('PUT', `/admin/users/${userId}/subscription`, input),
    adminSubscriptionNotice: () =>
        request<{ notice: string }>('GET', '/admin/subscription-notice').then((r) => r.notice),
    adminSaveSubscriptionNotice: (notice: string) =>
        request<{ notice: string }>('PUT', '/admin/subscription-notice', { notice }).then((r) => r.notice),
    adminUserDetails: (id: number) => request<UserDetails>('GET', `/admin/users/${id}`),
    adminBroadcastSchedules: () =>
        request<{ timezone: string; schedules: BroadcastSchedule[] }>('GET', '/admin/broadcast-schedules'),
    adminSaveBroadcastSchedule: (id: number | null, input: Partial<Omit<BroadcastSchedule, 'id'>>) =>
        request<{ timezone: string; schedules: BroadcastSchedule[] }>(
            id === null ? 'POST' : 'PATCH',
            id === null ? '/admin/broadcast-schedules' : `/admin/broadcast-schedules/${id}`,
            input,
        ),
    adminDeleteBroadcastSchedule: (id: number) => request<void>('DELETE', `/admin/broadcast-schedules/${id}`),
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
    adminStoreCategories: () =>
        request<{ categories: StoreCategory[] }>('GET', '/admin/store-categories').then((r) => r.categories),
    adminSaveStoreCategory: (id: number | null, name: string) =>
        request<{ categories: StoreCategory[] }>(
            id === null ? 'POST' : 'PATCH',
            id === null ? '/admin/store-categories' : `/admin/store-categories/${id}`,
            { name },
        ).then((r) => r.categories),
    adminDeleteStoreCategory: (id: number) => request<void>('DELETE', `/admin/store-categories/${id}`),
    adminTranslations: (type: string, id: number) =>
        request<TranslationSheet>('GET', `/admin/translations/${type}/${id}`),
    adminSaveTranslations: (type: string, id: number, translations: Record<string, Record<string, string>>) =>
        request<TranslationSheet>('PUT', `/admin/translations/${type}/${id}`, { translations }),
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
    if (input.themeAccent !== undefined) form.set('themeAccent', input.themeAccent)
    if (input.themeBackground !== undefined) form.set('themeBackground', input.themeBackground ?? '')
    if (input.themeImage) form.set('themeImage', input.themeImage, input.themeImage.name)
    if (input.removeThemeImage) form.set('removeThemeImage', '1')
    if (input.isExclusive !== undefined) form.set('isExclusive', input.isExclusive ? '1' : '0')
    if (input.categoryId !== undefined)
        form.set('categoryId', input.categoryId === null ? '' : String(input.categoryId))
    if (input.link !== undefined) form.set('link', input.link)
    if (input.file) form.set('file', input.file, input.file.name)
    if (input.removeFile) form.set('removeFile', '1')
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
