import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { repository } from '../data/repository'
import { messages, setLocale, t } from '../i18n'
import { completionStreak, percent, today } from '../lib/dates'
import type {
    Achievement,
    Category,
    DailyReview,
    FocusTotals,
    Goal,
    GoalInput,
    Motivation,
    ProfileInput,
    Task,
    TaskInput,
    TaskStatus,
} from '../types/domain'
import { useAuthStore } from './auth'
import { useUiStore } from './ui'

/** Everything a signed-in member owns: tasks, goals, badges, focus time and the daily review. */
export const useWorkspaceStore = defineStore('workspace', () => {
    const tasks = ref<Task[]>([])
    const categories = ref<Category[]>([])
    const goals = ref<Goal[]>([])
    const achievements = ref<Achievement[]>([])
    const motivations = ref<Motivation[]>([])
    const focus = ref<FocusTotals>({ totalMinutes: 0, todayMinutes: 0 })
    const review = ref<DailyReview | null>(null)
    const loading = ref(false)
    const loaded = ref(false)
    const error = ref<unknown>(null)

    const todayTasks = computed(() => {
        const day = today()
        return tasks.value.filter((task) => task.periodType === 'daily' && task.periodStart === day)
    })
    const todayDone = computed(() => todayTasks.value.filter((task) => task.status === 'completed').length)
    const todayPct = computed(() => percent(todayDone.value, todayTasks.value.length))
    const completedCount = computed(() => tasks.value.filter((task) => task.status === 'completed').length)
    const streak = computed(() => completionStreak(tasks.value))

    async function load(force = false): Promise<void> {
        if (loaded.value && !force) return
        loading.value = !loaded.value
        error.value = null
        try {
            const payload = await repository.loadDashboard(today())
            tasks.value = payload.tasks
            categories.value = payload.categories
            goals.value = payload.goals
            achievements.value = payload.achievements
            motivations.value = payload.motivations
            focus.value = payload.focus
            review.value = payload.review
            useAuthStore().setUser(payload.user)
            loaded.value = true
        } catch (e) {
            error.value = e
        } finally {
            loading.value = false
        }
    }

    function reset(): void {
        tasks.value = []
        categories.value = []
        goals.value = []
        achievements.value = []
        motivations.value = []
        focus.value = { totalMinutes: 0, todayMinutes: 0 }
        review.value = null
        loaded.value = false
    }

    function celebrate(earned: Achievement[]): void {
        for (const badge of earned) {
            if (achievements.value.some((existing) => existing.id === badge.id)) continue
            achievements.value.unshift(badge)
            const title =
                messages.value.achievements.keys[badge.key as keyof typeof messages.value.achievements.keys]?.title
            useUiStore().success(t('achievements.earned', { title: title ?? badge.key }))
        }
    }

    function replaceTask(task: Task): void {
        const index = tasks.value.findIndex((existing) => existing.id === task.id)
        if (index >= 0) tasks.value[index] = task
        else tasks.value.unshift(task)
    }

    async function createTask(input: TaskInput): Promise<Task> {
        const task = await repository.createTask(input)
        tasks.value.unshift(task)
        return task
    }

    async function updateTask(id: number, patch: Partial<TaskInput> & { status?: TaskStatus }): Promise<void> {
        const result = await repository.updateTask(id, patch)
        replaceTask(result.task)
        celebrate(result.newAchievements)
    }

    const toggleTask = (task: Task) =>
        updateTask(task.id, { status: task.status === 'completed' ? 'pending' : 'completed' })

    /** Delete immediately; the returned function re-creates the task (undo). */
    async function deleteTask(task: Task): Promise<() => Promise<void>> {
        await repository.deleteTask(task.id)
        tasks.value = tasks.value.filter((existing) => existing.id !== task.id)
        return async () => {
            const restored = await createTask({
                title: task.title,
                description: task.description,
                periodType: task.periodType,
                periodStart: task.periodStart,
                periodEnd: task.periodEnd,
                dueDate: task.dueDate,
                priority: task.priority,
                categoryId: task.categoryId,
            })
            if (task.status !== 'pending') await updateTask(restored.id, { status: task.status })
        }
    }

    async function createGoal(input: GoalInput): Promise<Goal> {
        const goal = await repository.createGoal(input)
        goals.value.unshift(goal)
        return goal
    }

    async function setGoalProgress(goal: Goal, value: number): Promise<void> {
        const currentValue = Math.max(0, Math.min(goal.targetValue, value))
        const result = await repository.updateGoal(goal.id, { currentValue })
        goals.value = goals.value.map((existing) => (existing.id === goal.id ? result.goal : existing))
        celebrate(result.newAchievements)
    }

    async function deleteGoal(goal: Goal): Promise<() => Promise<void>> {
        await repository.deleteGoal(goal.id)
        goals.value = goals.value.filter((existing) => existing.id !== goal.id)
        return async () => {
            const restored = await createGoal({
                title: goal.title,
                periodType: goal.periodType,
                periodStart: goal.periodStart,
                periodEnd: goal.periodEnd,
                targetValue: goal.targetValue,
            })
            if (goal.currentValue > 0) await setGoalProgress(restored, goal.currentValue)
        }
    }

    async function saveProfile(input: ProfileInput): Promise<void> {
        const user = await repository.saveProfile(input)
        useAuthStore().setUser(user)
        setLocale(user.language)
    }

    async function logFocusSession(minutes: number): Promise<void> {
        const result = await repository.logFocusSession(minutes, today())
        focus.value = result.focus
        celebrate(result.newAchievements)
    }

    async function saveReview(fields: Pick<DailyReview, 'win' | 'unfinished' | 'lesson' | 'tomorrow'>): Promise<void> {
        review.value = await repository.saveReview({ date: today(), ...fields })
    }

    return {
        tasks,
        categories,
        goals,
        achievements,
        motivations,
        focus,
        review,
        loading,
        loaded,
        error,
        todayTasks,
        todayDone,
        todayPct,
        completedCount,
        streak,
        load,
        reset,
        createTask,
        updateTask,
        toggleTask,
        deleteTask,
        createGoal,
        setGoalProgress,
        deleteGoal,
        saveProfile,
        logFocusSession,
        saveReview,
    }
})
