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
export const LOCALES = ['ar', 'en', 'fr'] as const
export type Locale = (typeof LOCALES)[number]

export interface User {
    id: number
    email: string
    displayName: string
    surname: string
    nickname: string
    /** Granted by an administrator; opens subscriber-only lessons. */
    isPremium: boolean
    permissions: AdminArea[]
    preferences: Preferences
    /** Points left to spend in the store. */
    points: number
    /** Every point earned so far; this is what the honour board ranks. */
    pointsEarned: number
    /** What the member wears, from the store items they put to use. */
    title: string | null
    frame: string
    theme: string
    avatarUrl: string | null
    role: Role
    bio: string
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

export interface HabitMonth {
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
}

export type BadgeInput = Omit<Badge, 'id' | 'key'>

/** Per-account notification choices. Times are `HH:MM` in the member's own clock, or null. */
export interface Preferences {
    notifyReminders: boolean
    notifyMorning: boolean
    notifyInactivity: boolean
    notifySleep: boolean
    sleepTime: string | null
    wakeTime: string | null
}

export const STORE_KINDS = ['title', 'frame', 'theme', 'avatar'] as const
export type StoreKind = (typeof STORE_KINDS)[number]
/** Built-in looks an item can apply; the CSS for each lives in app.css. */
export const FRAME_STYLES = ['bronze', 'silver', 'gold', 'royal', 'diamond'] as const
export const THEMES = ['emerald', 'sapphire', 'ruby', 'amethyst', 'silver'] as const

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
}

export interface LeaderboardRow {
    userId: number
    displayName: string
    avatarUrl: string | null
    title: string | null
    frame: string
    value: number
    /** Today's board: every daily task of the day is done. */
    allDone?: boolean
}

export interface Leaderboard {
    today: LeaderboardRow[]
    week: LeaderboardRow[]
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
    id: number
    kind: 'scheduled' | 'instant'
    body: string
}

export interface AdminBroadcasts {
    /** Day (YYYY-MM-DD) → message, for the requested week. */
    scheduled: Record<string, string>
    instant: { id: number; body: string; day: string; createdAt: string }[]
}

export interface DeviceSession {
    device: string
    ip: string | null
    lastActiveAt: string
    current: boolean
}
