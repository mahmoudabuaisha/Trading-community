import { describe, expect, it } from 'vitest'
import { completionStreak, dailyScore, percent, periodRange, shiftPeriod, ymd } from './dates'

const at = (day: string) => new Date(`${day}T12:00:00`)
const done = (day: string) => ({ status: 'completed' as const, completedAt: at(day).toISOString() })

describe('periodRange', () => {
    it('daily is a single day', () => {
        expect(periodRange('daily', at('2026-11-21'))).toEqual({ start: '2026-11-21', end: '2026-11-21' })
    })
    it('weekly runs Monday to Sunday', () => {
        expect(periodRange('weekly', at('2026-11-21'))).toEqual({ start: '2026-11-16', end: '2026-11-22' })
        expect(periodRange('weekly', at('2026-11-16'))).toEqual({ start: '2026-11-16', end: '2026-11-22' })
        expect(periodRange('weekly', at('2026-11-22'))).toEqual({ start: '2026-11-16', end: '2026-11-22' })
    })
    it('monthly handles month lengths and leap years', () => {
        expect(periodRange('monthly', at('2026-11-21'))).toEqual({ start: '2026-11-01', end: '2026-11-30' })
        expect(periodRange('monthly', at('2028-02-10'))).toEqual({ start: '2028-02-01', end: '2028-02-29' })
    })
    it('yearly covers the calendar year', () => {
        expect(periodRange('yearly', at('2026-11-21'))).toEqual({ start: '2026-01-01', end: '2026-12-31' })
    })
})

describe('shiftPeriod', () => {
    it('moves by whole periods', () => {
        expect(ymd(shiftPeriod('daily', at('2026-12-31'), 1))).toBe('2027-01-01')
        expect(ymd(shiftPeriod('weekly', at('2026-11-21'), -1))).toBe('2026-11-14')
        expect(ymd(shiftPeriod('monthly', at('2026-01-31'), 1))).toBe('2026-02-01')
        expect(ymd(shiftPeriod('yearly', at('2026-11-21'), 1))).toBe('2027-11-21')
    })
})

describe('completionStreak', () => {
    const now = at('2026-11-21')
    it('is zero with no completions', () => {
        expect(completionStreak([], now)).toBe(0)
    })
    it('counts consecutive days ending today', () => {
        expect(completionStreak([done('2026-11-21'), done('2026-11-20'), done('2026-11-19')], now)).toBe(3)
    })
    it('keeps the streak alive when today has no completion yet', () => {
        expect(completionStreak([done('2026-11-20'), done('2026-11-19')], now)).toBe(2)
    })
    it('breaks on a gap', () => {
        expect(completionStreak([done('2026-11-21'), done('2026-11-19')], now)).toBe(1)
        expect(completionStreak([done('2026-11-18')], now)).toBe(0)
    })
})

describe('dailyScore', () => {
    it('weights completion, focus and review', () => {
        expect(dailyScore(0, 0, 120, false)).toBe(0)
        expect(dailyScore(100, 120, 120, true)).toBe(100)
        expect(dailyScore(100, 0, 120, false)).toBe(65)
        expect(dailyScore(50, 60, 120, true)).toBe(58)
    })
    it('caps focus credit and tolerates a zero target', () => {
        expect(dailyScore(0, 999, 120, false)).toBe(20)
        expect(dailyScore(0, 50, 0, false)).toBe(0)
    })
})

describe('percent', () => {
    it('avoids division by zero', () => {
        expect(percent(0, 0)).toBe(0)
        expect(percent(1, 3)).toBe(33)
    })
})
