/**
 * Domain contract shared by every layer of the front end.
 *
 * Conventions
 * - Instants are ISO 8601 strings; calendar days are `YYYY-MM-DD` strings.
 *   Never a pre-formatted display string.
 * - Durations are whole minutes.
 * - Statuses, priorities, periods and achievement keys are machine values;
 *   Arabic / English labels live in the i18n layer only.
 */

export const PERIOD_TYPES = ['daily', 'weekly', 'monthly', 'yearly'] as const
export type PeriodType = (typeof PERIOD_TYPES)[number]

export const TASK_PRIORITIES = ['low', 'medium', 'high', 'urgent'] as const
export type TaskPriority = (typeof TASK_PRIORITIES)[number]

export const TASK_STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'] as const
export type TaskStatus = (typeof TASK_STATUSES)[number]

export const MOTIVATION_CATEGORIES = ['growth', 'motivation', 'announcement'] as const
export type MotivationCategory = (typeof MOTIVATION_CATEGORIES)[number]

export type Role = 'admin' | 'moderator' | 'user'

/** Areas of the admin center a moderator can be granted. Administrators have all of them. */
export const ADMIN_AREAS = ['users', 'content', 'growth', 'education', 'badges', 'store', 'messages', 'audit'] as const
export type AdminArea = (typeof ADMIN_AREAS)[number]
/** Areas a moderator can be given. Members' private messages stay with full administrators. */
export const GRANTABLE_AREAS = ADMIN_AREAS.filter((area) => area !== 'messages')
export const LOCALES = ['ar', 'en', 'fr'] as const
export type Locale = (typeof LOCALES)[number]

export interface User {
    id: number
    email: string
    displayName: string
    surname: string
    nickname: string
    /** A subscription switched on by an administrator and not past its end. */
    isPremium: boolean
    /** The membership name while subscribed: a preset tier key or the admin's own name. */
    premiumTier: string | null
    /** Its end, if it has one. */
    premiumUntil: string | null
    permissions: AdminArea[]
    preferences: Preferences
    privacy: Privacy
    /** Points left to spend in the store. */
    points: number
    /** Every point earned so far; this is what the honour board ranks. */
    pointsEarned: number
    /** What the member wears, from the store items they put to use. */
    title: string | null
    frame: string
    /** An emoji or picture worn next to the name. */
    emblem: string | null
    /** The theme in use, or null for the platform's own gold. */
    theme: ThemeData | null
    /** Themes the member bought, for the switcher in the top bar. */
    ownedThemes: ThemeData[]
    avatarUrl: string | null
    role: Role
    bio: string
    specialties: Specialty[]
    timezone: string
    language: Locale
    weeklyGoal: number
    dailyFocusMinutes: number
    createdAt: string
}

export interface Category {
    id: number
    name: string
    color: string
}

export interface Task {
    id: number
    title: string
    description: string
    periodType: PeriodType
    periodStart: string
    periodEnd: string
    dueDate: string | null
    priority: TaskPriority
    status: TaskStatus
    categoryId: number | null
    /** Instant at which the owner wants a reminder; null when none is set. */
    remindAt: string | null
    reminderDismissedAt: string | null
    completedAt: string | null
    createdAt: string
}

export interface Goal {
    id: number
    title: string
    description: string
    periodType: PeriodType
    periodStart: string
    periodEnd: string
    targetValue: number
    currentValue: number
    status: 'active' | 'completed'
}

export interface Achievement {
    /** Null for an admin-defined badge announced in the response that earned it. */
    id: number | null
    /** For repeatable badges: how many periods it was earned in. */
    timesEarned?: number
    key: string
    earnedAt: string
}

export interface Motivation {
    id: number
    title: string
    body: string
    category: MotivationCategory
    isActive: boolean
    createdAt: string
}

export interface DailyReview {
    date: string
    win: string
    unfinished: string
    lesson: string
    tomorrow: string
    savedAt: string | null
}

export interface FocusTotals {
    totalMinutes: number
    todayMinutes: number
}

export interface DashboardPayload {
    user: User
    tasks: Task[]
    categories: Category[]
    goals: Goal[]
    achievements: Achievement[]
    motivations: Motivation[]
    focus: FocusTotals
    review: DailyReview | null
    content: SiteContent
    badges: Badge[]
    /** Whole days the member was away before this visit; 0 when there is nothing to mention. */
    absenceDays: number
    /** Today's messages from the administrators. */
    broadcasts: Broadcast[]
}

export interface TaskInput {
    title: string
    description: string
    periodType: PeriodType
    periodStart: string
    periodEnd: string
    dueDate: string | null
    priority: TaskPriority
    categoryId: number | null
    remindAt?: string | null
}

export interface GoalInput {
    title: string
    periodType: PeriodType
    periodStart: string
    periodEnd: string
    targetValue: number
}

export interface ProfileInput {
    specialties?: Specialty[]
    displayName: string
    surname: string
    nickname: string
    bio: string
    timezone: string
    language: Locale
    weeklyGoal: number
    dailyFocusMinutes: number
    avatarUrl: string | null
}

export interface AdminStats {
    users: number
    tasks: number
    completed: number
    activeToday: number
}

export interface AuditEntry {
    id: number
    action: string
    adminName: string
    targetUserId: number | null
    details: Record<string, unknown> | null
    createdAt: string
}

export interface AdminUser {
    id: number
    displayName: string
    email: string
    role: Role
    isPremium: boolean
    premiumUntil: string | null
    permissions: AdminArea[]
    createdAt: string
}

export interface Paginated<T> {
    data: T[]
    meta: { currentPage: number; lastPage: number; total: number }
}

export interface MotivationInput {
    title: string
    body: string
    category: MotivationCategory
    isActive: boolean
}

/** Admin-editable interface copy; null means "use the built-in text". */
export interface SiteContent {
    taglineAr: string | null
    taglineEn: string | null
    welcomeSubAr: string | null
    welcomeSubEn: string | null
}

/** One message in a member's private thread with the administrators. */
export interface Message {
    id: number
    body: string
    fromAdmin: boolean
    senderName: string
    readAt: string | null
    createdAt: string
}

export interface ThreadUser {
    id: number
    displayName: string
    email: string
}

export interface Thread {
    user: ThreadUser
    lastMessage: Message
    unread: number
}

export interface UnreadCounts {
    broadcasts: Broadcast[]
    /** Messages from the administrators this member has not opened. */
    member: number
    /** Messages from members waiting on an administrator (0 for members). */
    admin: number
}

export interface Lesson {
    id: number
    title: string
    body: string
    imageUrl: string | null
    position: number
    isPublished: boolean
    isPremium: boolean
    /** True when this reader may not open the lesson; its text and picture are then withheld. */
    locked: boolean
    createdAt: string
}

export interface LessonInput {
    title: string
    body: string
    isPublished: boolean
    isPremium: boolean
    /** A new image to upload, if any. */
    image: Blob | null
    removeImage: boolean
}

export interface Habit {
    id: number
    name: string
    /** Monthly goal in days; null means every day. */
    targetDays: number | null
    position: number
}

/** A daily self-rating; each value is 1–10 or null when not rated. */
export interface MoodEntry {
    day: string
    mood: number | null
    motivation: number | null
}

/** A picture and a note the member pinned to one calendar day. */
export interface DayEntry {
    day: string
    note: string
    imageUrl: string | null
}

export interface HabitMonth {
    days: DayEntry[]
    habits: Habit[]
    checks: { habitId: number; day: string }[]
    moods: MoodEntry[]
    note: string
}

export const BADGE_METRICS = [
    'tasks_completed',
    'perfect_days',
    'streak_days',
    'focus_minutes',
    'goals_completed',
    'habit_checks',
] as const
export type BadgeMetric = (typeof BADGE_METRICS)[number]

export const BADGE_PERIODS = ['once', 'daily', 'weekly', 'monthly', 'yearly'] as const
export type BadgePeriod = (typeof BADGE_PERIODS)[number]

export interface BadgeCondition {
    metric: BadgeMetric
    threshold: number
}

/**
 * An achievement defined by an administrator. It is earned when every
 * condition holds within its period, and awards its points each time.
 */
export interface Badge {
    id: number
    key: string
    title: string
    description: string
    points: number
    period: BadgePeriod
    conditions: BadgeCondition[]
    isActive: boolean
    /** A store item handed over free with the achievement. */
    rewardItemId: number | null
    reward: { name: string; kind: StoreKind } | null
}

export type BadgeInput = Omit<Badge, 'id' | 'key' | 'reward'>

/** Per-account notification choices. Times are `HH:MM` in the member's own clock, or null. */
/** What other members see of an account. */
export interface Privacy {
    showName: boolean
    showAvatar: boolean
    showTitle: boolean
    showFrame: boolean
    showEmblem: boolean
    showOnLeaderboard: boolean
}

export interface Preferences {
    notifyReminders: boolean
    notifyMorning: boolean
    notifyInactivity: boolean
    notifySleep: boolean
    sleepTime: string | null
    wakeTime: string | null
}

/** Preset membership names, from the lightest subscription to the fullest. */
export const TIERS = ['premium', 'silver', 'gold', 'platinum', 'elite'] as const

export const STORE_KINDS = ['title', 'frame', 'emblem', 'theme', 'avatar', 'product'] as const
export type StoreKind = (typeof STORE_KINDS)[number]
/** Built-in looks an item can apply; the CSS for each lives in app.css. */
export const FRAME_STYLES = ['bronze', 'silver', 'gold', 'royal', 'diamond'] as const

export interface StoreItem {
    id: number
    name: string
    description: string
    kind: StoreKind
    /** The title's text, or the key of a frame style or theme. */
    value: string | null
    imageUrl: string | null
    price: number
    isActive: boolean
    owned?: boolean
    equipped?: boolean
    /** Reserved to the subscribers it was opened for. */
    isExclusive: boolean
    /** A theme: its accent colour, optional dark base and optional picture. */
    themeAccent: string | null
    themeBackground: string | null
    themeImageUrl: string | null
    /** On the member's store: whether this member may buy it. */
    allowed?: boolean
    /** The section a product sits in. */
    categoryId: number | null
    /** A product may hand over a file and a link; the link is sent to its owner only. */
    hasFile: boolean
    fileName: string | null
    link: string | null
}

/** A section of the store added by the administrators, such as "Books". */
export interface StoreCategory {
    id: number
    name: string
}

/** One item's text in every language, for the admin's translation dialog. */
export interface TranslationSheet {
    locales: Locale[]
    fields: { field: string; original: string; translations: Record<string, string>; auto: string[]; stale: string[] }[]
}

export interface StoreItemInput {
    name: string
    description: string
    kind: StoreKind
    value: string
    price: number
    isActive: boolean
    image?: Blob | null
    removeImage?: boolean
    categoryId?: number | null
    isExclusive?: boolean
    themeAccent?: string
    themeBackground?: string | null
    themeImage?: File | null
    removeThemeImage?: boolean
    link?: string
    file?: File | null
    removeFile?: boolean
}

export interface LeaderboardRow {
    userId: number
    /** Null when the member hides their name. */
    displayName: string | null
    avatarUrl: string | null
    title: string | null
    frame: string
    emblem?: string | null
    value: number
    /** Today's board: every daily task of the day is done. */
    allDone?: boolean
}

export interface Leaderboard {
    today: LeaderboardRow[]
    week: LeaderboardRow[]
    weekPoints: LeaderboardRow[]
    points: LeaderboardRow[]
    month: LeaderboardRow[]
}

export interface PointsHistory {
    earned: number
    balance: number
    history: { day: string; points: number }[]
}

/** A message from the administrators to every member. */
export interface Broadcast {
    /** A number for a dated message; a string unique to the day for an occurrence of a repeating one. */
    id: number | string
    kind: 'scheduled' | 'instant' | 'recurring'
    body: string
}

/** A message that repeats every day, or every week on a weekday (1 = Monday … 7 = Sunday), at a set time. */
export interface BroadcastSchedule {
    id: number
    frequency: 'daily' | 'weekly'
    weekday: number | null
    time: string
    body: string
    isActive: boolean
}

interface DetailTask {
    id: number
    title: string
    periodType: PeriodType
    status: TaskStatus
    createdAt: string
    completedAt: string | null
}

/** Everything the administrators see about one account. */
export interface UserDetails {
    user: AdminUser & {
        surname: string
        nickname: string
        bio: string
        specialties: Specialty[]
        timezone: string
        language: Locale
        lastSeenAt: string | null
        title: string | null
        frame: string
        theme: ThemeData | null
    }
    points: { balance: number; earned: number }
    counts: Record<
        | 'tasks'
        | 'tasksCompleted'
        | 'goals'
        | 'goalsCompleted'
        | 'habits'
        | 'focusMinutes'
        | 'streak'
        | 'skillLevels'
        | 'projects'
        | 'visionBoards',
        number
    >
    tasks: DetailTask[]
    completedTasks: DetailTask[]
    goals: { id: number; title: string; status: string; currentValue: number; targetValue: number }[]
    achievements: { key: string; title: string | null; points: number; times: number; earnedAt: string }[]
    badges: { key: string; title: string | null; points: number; times: number; earnedAt: string }[]
    purchases: { name: string; kind: StoreKind; price: number; boughtAt: string }[]
}

export interface DailyTipItem {
    id: number
    body: string
    isActive: boolean
}

export interface DailyTipState {
    tip: string | null
    available: boolean
    day: string
}

export interface AdminBroadcasts {
    /** Day (YYYY-MM-DD) → message, for the requested week. */
    scheduled: Record<string, string>
    scheduledIds: Record<string, number>
    instant: { id: number; body: string; day: string; createdAt: string }[]
}

export interface DeviceSession {
    device: string
    ip: string | null
    lastActiveAt: string
    current: boolean
}

// ── Growth journey ──────────────────────────────────────────────────────

/** What a member can say they do (machine keys; labels live in i18n under `specialty`). */
export const SPECIALTIES = [
    'student_school',
    'student_university',
    'trader_learning',
    'trader_beginner',
    'trader_pro',
    'merchant',
    'freelancer',
    'employee',
    'business_owner',
    'content_creator',
    'other',
] as const
export type Specialty = (typeof SPECIALTIES)[number]

export type DayPart = 'morning' | 'afternoon' | 'evening' | 'night'

export interface PeriodTotals {
    tasksCompleted: number
    completionRate: number
    activeDays: number
    focusMinutes: number
    habitChecks: number
}

/** "Performance DNA": how the member works, as numbers and machine keys. Weekday 0 is Monday. */
export interface Insights {
    windowDays: number
    enoughData: boolean
    totalCompleted: number
    byWeekday: number[]
    byHour: number[]
    byPart: Record<DayPart, number>
    bestWeekday: number | null
    weakWeekday: number | null
    bestPart: DayPart | null
    byPriority: Record<string, { total: number; done: number }>
    byCategory: { name: string; total: number; done: number }[]
    onTime: { total: number; onTime: number }
    current: PeriodTotals
    previous: PeriodTotals
    weeks: { start: string; completed: number }[]
    mood: { highDays: number; lowDays: number } | null
    streak: number
    tips: { key: string; params: Record<string, string | number> }[]
}

export type TimelineKind = 'joined' | 'achievement' | 'badge' | 'goal' | 'skill' | 'project' | 'purchase' | 'custom'

export interface TimelineEvent {
    key: string
    kind: TimelineKind
    day: string
    title: string | null
    note: string
    /** Built-in achievement key, for its translated title. */
    ref?: string
    /** Set on milestones the member added themselves. */
    entryId?: number
}

export interface Timeline {
    events: TimelineEvent[]
    stages: { month: string; tasks: number; points: number }[]
}

/** A stage of a goal's roadmap (parentId null) or a step inside a stage. */
export interface GoalStep {
    id: number
    parentId: number | null
    title: string
    requirements: string
    isDone: boolean
    position: number
}

export const VISION_AREAS = [
    'personal',
    'career',
    'finance',
    'health',
    'family',
    'learning',
    'travel',
    'other',
] as const
export const VISION_COLORS = ['gold', 'emerald', 'sapphire', 'ruby', 'amethyst', 'silver'] as const
export type VisionItemKind = 'image' | 'quote' | 'dream'
export type VisionSize = 'sm' | 'md' | 'lg'

export interface VisionItem {
    id: number
    kind: VisionItemKind
    text: string
    size: VisionSize
    goalId: number | null
    imageUrl: string | null
}

export interface VisionBoard {
    id: number
    title: string
    area: (typeof VISION_AREAS)[number]
    color: (typeof VISION_COLORS)[number]
    items: VisionItem[]
}

export interface QuizQuestion {
    question: string
    options: string[]
    /** Index of the right option; only administrators receive it. */
    answer?: number
}

export interface SkillLevel {
    id: number
    title: string
    description: string
    challenge: string
    lessonId: number | null
    quiz: QuizQuestion[]
    passed: boolean
    unlocked: boolean
}

export interface SkillPath {
    id: number
    title: string
    description: string
    isActive: boolean
    /** The member's own path (skills of their choosing, ticked in any order). */
    own?: boolean
    levels: SkillLevel[]
}

export const PORTFOLIO_CATEGORIES = [
    'trading',
    'business',
    'design',
    'programming',
    'writing',
    'marketing',
    'study',
    'other',
] as const
export const PORTFOLIO_VISIBILITIES = ['private', 'members', 'public'] as const
export type PortfolioVisibility = (typeof PORTFOLIO_VISIBILITIES)[number]

export interface PortfolioProject {
    id: number
    title: string
    description: string
    category: (typeof PORTFOLIO_CATEGORIES)[number]
    skills: string[]
    link: string | null
    imageUrl: string | null
    completedOn: string | null
}

export interface Portfolio {
    projects: PortfolioProject[]
    visibility: PortfolioVisibility
    slug: string | null
}

export interface PublicPortfolio {
    owner: { displayName: string; avatarUrl: string | null; bio: string; specialties: Specialty[] }
    projects: PortfolioProject[]
}

// ── Paid subscriptions and books ────────────────────────────────────────

/** What a subscription can open for a member. */
export type GrantType = 'lesson' | 'book' | 'store_item' | 'assistant'

export interface SubscriptionSheet {
    isPremium: boolean
    tier: string | null
    active: boolean
    /** Last day (YYYY-MM-DD) in the platform's timezone; null means no end. */
    premiumUntil: string | null
    grants: Record<GrantType, number[]>
    options: Record<GrantType, { id: number; title: string }[]>
}

export interface Book {
    id: number
    title: string
    author: string
    description: string
    coverUrl: string | null
    hasFile: boolean
    fileName: string | null
    position: number
    isPublished: boolean
    /** Opened for this member (always for staff). */
    unlocked: boolean
}

// ── Designs of the goal map and the skill tree ──────────────────────────

export type DesignTarget = 'goal_map' | 'skill_tree'

/** A built-in layout dressed by the administrators; price 0 is free for everyone. */
export interface Design {
    id: number
    target: DesignTarget
    layout: string
    name: string
    description: string
    price: number
    accent: string
    nodeShape: string
    backgroundUrl: string | null
    isActive: boolean
    owned?: boolean
}

/** A theme as the app applies it. */
export interface ThemeData {
    id: number
    name: string
    accent: string
    background: string | null
    imageUrl: string | null
}

export interface AssistantStatus {
    /** An API key is set on the server. */
    configured: boolean
    /** The AI assistant is opened in this member's subscription (always for staff). */
    allowed: boolean
    limit: number
    remaining: number
}
