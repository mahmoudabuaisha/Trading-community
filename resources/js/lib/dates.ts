import type { PeriodType, Task } from '../types/domain'

/** Local calendar day as `YYYY-MM-DD`. */
export function ymd(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function today(): string {
    return ymd(new Date())
}

/** Parse a `YYYY-MM-DD` day at local noon so DST shifts cannot move it to a neighbouring day. */
export function parseDay(day: string): Date {
    return new Date(`${day}T12:00:00`)
}

/** Inclusive start/end days of the period containing `anchor`. Weeks start on Monday. */
export function periodRange(type: PeriodType, anchor: Date): { start: string; end: string } {
    const start = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate())
    if (type === 'weekly') start.setDate(start.getDate() - ((start.getDay() + 6) % 7))
    if (type === 'monthly') start.setDate(1)
    if (type === 'yearly') start.setMonth(0, 1)
    const end = new Date(start)
    if (type === 'weekly') end.setDate(end.getDate() + 6)
    if (type === 'monthly') end.setMonth(end.getMonth() + 1, 0)
    if (type === 'yearly') end.setMonth(11, 31)
    return { start: ymd(start), end: ymd(end) }
}

/** Move an anchor day forward/backward by whole periods. */
export function shiftPeriod(type: PeriodType, anchor: Date, step: number): Date {
    const d = new Date(anchor)
    if (type === 'daily') d.setDate(d.getDate() + step)
    else if (type === 'weekly') d.setDate(d.getDate() + step * 7)
    else if (type === 'monthly') {
        d.setDate(1)
        d.setMonth(d.getMonth() + step)
    } else d.setFullYear(d.getFullYear() + step)
    return d
}

/** Local day on which a task was completed, or '' when it is not completed. */
export function completedDay(task: Pick<Task, 'status' | 'completedAt'>): string {
    return task.status === 'completed' && task.completedAt ? ymd(new Date(task.completedAt)) : ''
}

/**
 * Consecutive days (ending today, or yesterday if nothing is done yet today)
 * with at least one completed task.
 */
export function completionStreak(tasks: Pick<Task, 'status' | 'completedAt'>[], now = new Date()): number {
    const days = new Set(tasks.map(completedDay).filter(Boolean))
    const cursor = new Date(now)
    if (!days.has(ymd(cursor))) cursor.setDate(cursor.getDate() - 1)
    let streak = 0
    while (days.has(ymd(cursor)) && streak < 3650) {
        streak++
        cursor.setDate(cursor.getDate() - 1)
    }
    return streak
}

/** The last `count` days ending today, oldest first, with completed-task counts. */
export function lastDays(
    tasks: Pick<Task, 'status' | 'completedAt'>[],
    count = 7,
    now = new Date(),
): { day: string; date: Date; completed: number }[] {
    return Array.from({ length: count }, (_, i) => {
        const date = new Date(now)
        date.setDate(date.getDate() - (count - 1 - i))
        const day = ymd(date)
        return { day, date, completed: tasks.filter((t) => completedDay(t) === day).length }
    })
}

/**
 * Daily score out of 100: 65% today's task completion, 20% focus time
 * against the user's daily focus target, 15% for saving the daily review.
 */
export function dailyScore(
    completionPct: number,
    focusMinutes: number,
    focusTarget: number,
    reviewSaved: boolean,
): number {
    const focusPct = focusTarget > 0 ? Math.min(100, (focusMinutes / focusTarget) * 100) : 0
    return Math.min(100, Math.round(completionPct * 0.65 + focusPct * 0.2 + (reviewSaved ? 15 : 0)))
}

export function percent(done: number, total: number): number {
    return total ? Math.round((done / total) * 100) : 0
}
