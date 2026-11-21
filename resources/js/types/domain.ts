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

export type Role = 'admin' | 'user'
export type Locale = 'ar' | 'en'

export interface User {
    id: number
    email: string
    displayName: string
    surname: string
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
    id: number
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
