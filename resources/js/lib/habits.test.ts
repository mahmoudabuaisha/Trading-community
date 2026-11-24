import { describe, expect, it } from 'vitest'
import { checkKey, dayStats, habitStats, monthDays, monthKey, weeklyAverages } from './habits'

const november = monthDays(new Date(2026, 10, 15))

describe('monthDays', () => {
    it('lists every day with its week of the month', () => {
        expect(november).toHaveLength(30)
        expect(november[0]).toMatchObject({ key: '2026-11-01', number: 1, week: 0 })
        expect(november[6].week).toBe(0)
        expect(november[7].week).toBe(1)
        expect(november[29]).toMatchObject({ key: '2026-11-30', week: 4 })
    })
    it('handles leap-year February', () => {
        expect(monthDays(new Date(2028, 1, 1))).toHaveLength(29)
        expect(monthKey(new Date(2028, 1, 29))).toBe('2028-02')
    })
})

describe('dayStats', () => {
    it('counts done and not done per day', () => {
        const checks = new Set([checkKey(1, '2026-11-01'), checkKey(2, '2026-11-01'), checkKey(1, '2026-11-02')])
        const stats = dayStats([1, 2, 3], november, checks)
        expect(stats[0]).toEqual({ done: 2, notDone: 1, pct: 67 })
        expect(stats[1]).toEqual({ done: 1, notDone: 2, pct: 33 })
        expect(stats[2]).toEqual({ done: 0, notDone: 3, pct: 0 })
    })
    it('is all zeros with no habits', () => {
        expect(dayStats([], november, new Set())[0]).toEqual({ done: 0, notDone: 0, pct: 0 })
    })
})

describe('habitStats', () => {
    const checks = new Set(
        ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06'].map((day) =>
            checkKey(7, day),
        ),
    )
    it('measures against the whole month when there is no goal', () => {
        expect(habitStats(7, null, november, checks)).toEqual({ done: 6, goal: 30, pct: 20, goalPct: 20 })
    })
    it('measures against a goal in days and caps at 100%', () => {
        expect(habitStats(7, 12, november, checks)).toMatchObject({ goal: 12, goalPct: 50 })
        expect(habitStats(7, 4, november, checks)).toMatchObject({ goal: 4, goalPct: 100 })
    })
    it('never lets a goal exceed the days in the month', () => {
        expect(habitStats(7, 31, november, checks).goal).toBe(30)
    })
})

describe('weeklyAverages', () => {
    it('averages only the rated days of each week', () => {
        const ratings = new Map<string, number | null>([
            ['2026-11-01', 8],
            ['2026-11-02', 6],
            ['2026-11-03', null],
            ['2026-11-09', 10],
        ])
        expect(weeklyAverages(november, ratings)).toEqual([70, 100, null, null, null])
    })
})
