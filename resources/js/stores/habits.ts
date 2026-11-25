import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { repository } from '../data/repository'
import { checkKey, monthKey } from '../lib/habits'
import type { DayEntry, Habit, MoodEntry } from '../types/domain'

/** One month of the habit tracker: habits, done-days, mood ratings and the month's note. */
export const useHabitsStore = defineStore('habits', () => {
    /** Any day inside the month on screen. */
    const anchor = ref(new Date())
    const habits = ref<Habit[]>([])
    const checks = ref(new Set<string>())
    const moods = ref(new Map<string, MoodEntry>())
    const note = ref('')
    /** Pictures and notes pinned to days of this month, by day. */
    const dayEntries = ref(new Map<string, DayEntry>())
    const loading = ref(false)
    const error = ref<unknown>(null)

    const month = computed(() => monthKey(anchor.value))

    async function load(): Promise<void> {
        const requested = month.value
        loading.value = true
        error.value = null
        try {
            const data = await repository.loadHabits(requested)
            // The member may have moved to another month while this was in flight.
            if (requested !== month.value) return
            habits.value = data.habits
            checks.value = new Set(data.checks.map((check) => checkKey(check.habitId, check.day)))
            moods.value = new Map(data.moods.map((entry) => [entry.day, entry]))
            note.value = data.note
            dayEntries.value = new Map(data.days.map((entry) => [entry.day, entry]))
        } catch (e) {
            error.value = e
        } finally {
            if (requested === month.value) loading.value = false
        }
    }

    function shiftMonth(step: number): Promise<void> {
        anchor.value = new Date(anchor.value.getFullYear(), anchor.value.getMonth() + step, 1)
        return load()
    }

    function goToToday(): Promise<void> {
        anchor.value = new Date()
        return load()
    }

    async function saveDay(
        day: string,
        input: { note: string; image?: Blob | null; removeImage?: boolean },
    ): Promise<void> {
        const entry = await repository.saveDayEntry(day, input)
        const next = new Map(dayEntries.value)
        if (entry) next.set(day, entry)
        else next.delete(day)
        dayEntries.value = next
    }

    async function addHabit(name: string, targetDays: number | null): Promise<void> {
        habits.value.push(await repository.createHabit(name, targetDays))
    }

    async function updateHabit(id: number, patch: { name?: string; targetDays?: number | null }): Promise<void> {
        const updated = await repository.updateHabit(id, patch)
        habits.value = habits.value.map((habit) => (habit.id === id ? updated : habit))
    }

    async function deleteHabit(id: number): Promise<void> {
        await repository.deleteHabit(id)
        habits.value = habits.value.filter((habit) => habit.id !== id)
    }

    /** Flip a day immediately and roll back if the server refuses. */
    async function toggle(habitId: number, day: string): Promise<void> {
        const key = checkKey(habitId, day)
        const done = !checks.value.has(key)
        const apply = (state: boolean) => {
            const next = new Set(checks.value)
            if (state) next.add(key)
            else next.delete(key)
            checks.value = next
        }
        apply(done)
        try {
            await repository.setHabitCheck(habitId, day, done)
        } catch (e) {
            apply(!done)
            throw e
        }
    }

    async function rate(day: string, field: 'mood' | 'motivation', value: number | null): Promise<void> {
        const previous = moods.value.get(day)
        const entry: MoodEntry = {
            day,
            mood: previous?.mood ?? null,
            motivation: previous?.motivation ?? null,
            [field]: value,
        }
        const apply = (next: MoodEntry | undefined) => {
            const map = new Map(moods.value)
            if (next && (next.mood !== null || next.motivation !== null)) map.set(day, next)
            else map.delete(day)
            moods.value = map
        }
        apply(entry)
        try {
            await repository.saveMood(entry)
        } catch (e) {
            apply(previous)
            throw e
        }
    }

    async function saveNote(body: string): Promise<void> {
        note.value = await repository.saveHabitNote(month.value, body)
    }

    return {
        dayEntries,
        saveDay,
        anchor,
        habits,
        checks,
        moods,
        note,
        loading,
        error,
        month,
        load,
        shiftMonth,
        goToToday,
        addHabit,
        updateHabit,
        deleteHabit,
        toggle,
        rate,
        saveNote,
    }
})
