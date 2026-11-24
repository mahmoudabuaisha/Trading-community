import { ymd } from './dates'

export interface HabitDay {
    /** `YYYY-MM-DD` */
    key: string
    /** Day of the month, 1-based. */
    number: number
    date: Date
    /** Week of the month, 0-based: days 1–7 are week 0, 8–14 week 1, and so on. */
    week: number
}

/** Every day of the month containing `anchor`. */
export function monthDays(anchor: Date): HabitDay[] {
    const year = anchor.getFullYear()
    const month = anchor.getMonth()
    const count = new Date(year, month + 1, 0).getDate()
    return Array.from({ length: count }, (_, index) => {
        const date = new Date(year, month, index + 1, 12)
        return { key: ymd(date), number: index + 1, date, week: Math.floor(index / 7) }
    })
}

/** `YYYY-MM` for the month containing `date`. */
export function monthKey(date: Date): string {
    return ymd(date).slice(0, 7)
}

/** Key that identifies one habit on one day in the set of done-days. */
export const checkKey = (habitId: number, day: string): string => `${habitId}:${day}`

/** Per-day totals across all habits: how many were done and the percentage. */
export function dayStats(
    habitIds: number[],
    days: HabitDay[],
    checks: Set<string>,
): { done: number; notDone: number; pct: number }[] {
    return days.map((day) => {
        const done = habitIds.filter((id) => checks.has(checkKey(id, day.key))).length
        return {
            done,
            notDone: habitIds.length - done,
            pct: habitIds.length ? Math.round((done / habitIds.length) * 100) : 0,
        }
    })
}

/**
 * How one habit did over the month: days done, the share of the month, and
 * progress toward its goal (a number of days; no goal means every day).
 */
export function habitStats(
    habitId: number,
    targetDays: number | null,
    days: HabitDay[],
    checks: Set<string>,
): { done: number; goal: number; pct: number; goalPct: number } {
    const done = days.filter((day) => checks.has(checkKey(habitId, day.key))).length
    const goal = Math.min(targetDays ?? days.length, days.length)
    return {
        done,
        goal,
        pct: days.length ? Math.round((done / days.length) * 10000) / 100 : 0,
        goalPct: goal ? Math.min(100, Math.round((done / goal) * 100)) : 0,
    }
}

/** Average of the rated days in each week of the month, as a percentage of the 1–10 scale; null when nothing was rated. */
export function weeklyAverages(
    days: HabitDay[],
    ratings: Map<string, number | null>,
    scaleMax = 10,
): (number | null)[] {
    const weeks = Math.max(...days.map((day) => day.week)) + 1
    return Array.from({ length: weeks }, (_, week) => {
        const values = days
            .filter((day) => day.week === week)
            .map((day) => ratings.get(day.key))
            .filter((value): value is number => typeof value === 'number')
        return values.length
            ? Math.round((values.reduce((sum, value) => sum + value, 0) / values.length / scaleMax) * 10000) / 100
            : null
    })
}
