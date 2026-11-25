<script setup lang="ts">
import type { ChartConfiguration } from 'chart.js'
import { Activity, Camera, CircleCheckBig, Clock3, Pencil, Target } from 'lucide-vue-next'
import { computed, reactive, ref } from 'vue'
import ChartCanvas from '../components/ChartCanvas.vue'
import NotificationSettings from '../components/NotificationSettings.vue'
import StatCard from '../components/StatCard.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { ApiError } from '../data/http'
import { formatDate, locale, t } from '../i18n'
import { lastDays, percent, today } from '../lib/dates'
import { fileToAvatar } from '../lib/image'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import { PERIOD_TYPES, type Locale, type ProfileInput } from '../types/domain'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const user = computed(() => auth.user!)
const editing = ref(false)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})

function snapshot(): ProfileInput {
    const { displayName, surname, bio, timezone, language, weeklyGoal, dailyFocusMinutes, avatarUrl } = user.value
    return { displayName, surname, bio, timezone, language, weeklyGoal, dailyFocusMinutes, avatarUrl }
}
const form = reactive<ProfileInput>(snapshot())
const fullName = computed(() => `${form.displayName} ${form.surname}`.trim() || user.value.displayName)

const timezones: string[] = (() => {
    try {
        return Intl.supportedValuesOf('timeZone')
    } catch {
        return [user.value.timezone]
    }
})()

function cancel(): void {
    Object.assign(form, snapshot())
    fieldErrors.value = {}
    editing.value = false
}

async function pickAvatar(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    const result = await fileToAvatar(file)
    if (result.ok) form.avatarUrl = result.dataUrl
    else ui.failure(new ApiError(t(`profile.avatar${result.reason[0].toUpperCase()}${result.reason.slice(1)}`), 422))
}

async function save(): Promise<void> {
    saving.value = true
    fieldErrors.value = {}
    try {
        await workspace.saveProfile({ ...form, displayName: form.displayName.trim(), surname: form.surname.trim() })
        Object.assign(form, snapshot())
        editing.value = false
        ui.success(t('profile.saved'))
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) fieldErrors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}

// ── Analytics ────────────────────────────────────────────────────────────
const tasks = computed(() => workspace.tasks)
const goals = computed(() => workspace.goals)
const completed = computed(() => tasks.value.filter((task) => task.status === 'completed'))
const open = computed(() => tasks.value.filter((task) => task.status !== 'completed' && task.status !== 'cancelled'))
const overdue = computed(() => open.value.filter((task) => task.dueDate && task.dueDate < today()).length)
const completionPct = computed(() => percent(completed.value.length, tasks.value.length))
const week = computed(() => lastDays(tasks.value, 7))
const activeDays = computed(() => week.value.filter((day) => day.completed > 0).length)
const goalAvg = computed(() =>
    goals.value.length
        ? Math.round(
              goals.value.reduce(
                  (sum, goal) => sum + Math.min(100, (goal.currentValue / Math.max(1, goal.targetValue)) * 100),
                  0,
              ) / goals.value.length,
          )
        : 0,
)
const periodRows = computed(() =>
    PERIOD_TYPES.map((period) => ({
        period,
        tasks: tasks.value.filter((task) => task.periodType === period).length,
        completed: tasks.value.filter((task) => task.periodType === period && task.status === 'completed').length,
        goals: goals.value.filter((goal) => goal.periodType === period).length,
        goalsDone: goals.value.filter((goal) => goal.periodType === period && goal.status === 'completed').length,
    })),
)

const css = (name: string) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()
const countAxis = () => ({
    beginAtZero: true,
    ticks: { precision: 0 },
    title: { display: true, text: t('profile.axisCount') },
})

const weekChart = computed<ChartConfiguration>(() => ({
    type: 'bar',
    data: {
        labels: week.value.map((day) => formatDate(day.date, { weekday: 'short' })),
        datasets: [
            {
                label: t('profile.chart7Series'),
                data: week.value.map((day) => day.completed),
                backgroundColor: css('--gold-500'),
                borderRadius: 6,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: countAxis() },
    },
}))

const statusSlices = computed(() =>
    [
        { key: 'completed', value: completed.value.length, color: css('--green-500') },
        {
            key: 'in_progress',
            value: tasks.value.filter((task) => task.status === 'in_progress').length,
            color: css('--gold-500'),
        },
        {
            key: 'pending',
            value: tasks.value.filter((task) => task.status === 'pending').length,
            color: css('--neutral-500'),
        },
        {
            key: 'cancelled',
            value: tasks.value.filter((task) => task.status === 'cancelled').length,
            color: css('--red-500'),
        },
    ].filter((slice) => slice.value > 0),
)
const statusChart = computed<ChartConfiguration>(() => ({
    type: 'doughnut',
    data: {
        // Counts are in the labels so the chart never relies on colour alone.
        labels: statusSlices.value.map((slice) => `${t(`status.${slice.key}`)} (${slice.value})`),
        datasets: [
            {
                data: statusSlices.value.map((slice) => slice.value),
                backgroundColor: statusSlices.value.map((slice) => slice.color),
                borderWidth: 0,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { position: 'bottom' } },
    },
}))

const periodChart = computed<ChartConfiguration>(() => {
    const series = [
        { label: t('profile.serAllTasks'), key: 'tasks', color: css('--gold-700') },
        { label: t('profile.serDoneTasks'), key: 'completed', color: css('--gold-500') },
        { label: t('profile.serGoals'), key: 'goals', color: css('--neutral-400') },
        { label: t('profile.serGoalsDone'), key: 'goalsDone', color: css('--gold-200') },
    ] as const
    return {
        type: 'bar',
        data: {
            labels: periodRows.value.map((row) => t(`period.${row.period}`)),
            datasets: series.map((entry) => ({
                label: entry.label,
                data: periodRows.value.map((row) => row[entry.key]),
                backgroundColor: entry.color,
                borderRadius: 5,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: countAxis() },
        },
    }
})
// Re-read colours/labels when the language flips.
const chartKey = computed(() => locale.value)
const languages: Locale[] = ['ar', 'en']
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN TASKS / PERSONAL IDENTITY</span>
                <h2>{{ t('profile.head') }}</h2>
                <p>{{ t('profile.sub') }}</p>
            </div>
        </div>

        <form class="panel profile-form" @submit.prevent="save">
            <div class="profile-hero">
                <label class="profile-avatar" :title="t(editing ? 'profile.changeAvatar' : 'profile.avatar')">
                    <UserAvatar :name="fullName" :src="form.avatarUrl" size="lg" />
                    <template v-if="editing">
                        <input
                            class="sr-only"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/gif"
                            :aria-label="t('profile.changeAvatar')"
                            @change="pickAvatar"
                        />
                        <i class="profile-avatar__badge"><Camera :size="15" aria-hidden="true" /></i>
                    </template>
                </label>
                <div class="profile-identity">
                    <h3>{{ fullName }}</h3>
                    <bdi>{{ user.email }}</bdi>
                    <small class="count-pill">{{ t(`role.${user.role}`) }}</small>
                    <small v-if="editing" class="hint">{{ t('profile.avatarHint') }}</small>
                </div>
            </div>

            <div class="form-grid">
                <label class="field">
                    <span>{{ t('profile.firstName') }}</span>
                    <input
                        v-model="form.displayName"
                        class="input"
                        :disabled="!editing"
                        required
                        maxlength="80"
                        :placeholder="t('profile.firstNamePh')"
                    />
                    <small v-if="fieldErrors.displayName" class="field__error">{{ fieldErrors.displayName[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.surname') }}</span>
                    <input
                        v-model="form.surname"
                        class="input"
                        :disabled="!editing"
                        maxlength="80"
                        :placeholder="t('profile.surnamePh')"
                    />
                </label>
                <label class="field">
                    <span>{{ t('profile.email') }}</span>
                    <input class="input" :value="user.email" disabled dir="ltr" />
                </label>
                <label class="field">
                    <span>{{ t('profile.weeklyGoal') }}</span>
                    <input
                        v-model.number="form.weeklyGoal"
                        class="input"
                        :disabled="!editing"
                        type="number"
                        min="1"
                        max="500"
                        required
                    />
                    <small v-if="fieldErrors.weeklyGoal" class="field__error">{{ fieldErrors.weeklyGoal[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.focusMinutes') }}</span>
                    <input
                        v-model.number="form.dailyFocusMinutes"
                        class="input"
                        :disabled="!editing"
                        type="number"
                        min="0"
                        max="1440"
                        required
                    />
                    <small v-if="fieldErrors.dailyFocusMinutes" class="field__error">{{
                        fieldErrors.dailyFocusMinutes[0]
                    }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.timezone') }}</span>
                    <select v-model="form.timezone" class="input" :disabled="!editing" dir="ltr">
                        <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('profile.language') }}</span>
                    <select v-model="form.language" class="input" :disabled="!editing">
                        <option v-for="language in languages" :key="language" :value="language">
                            {{ language === 'ar' ? 'العربية' : 'English' }}
                        </option>
                    </select>
                </label>
                <label class="field field--wide">
                    <span>{{ t('profile.bio') }}</span>
                    <textarea
                        v-model="form.bio"
                        class="input"
                        :disabled="!editing"
                        rows="3"
                        maxlength="500"
                        :placeholder="t('profile.bioPh')"
                    />
                    <small class="hint">{{ t('profile.chars', { n: form.bio.length }) }}</small>
                </label>
            </div>

            <div class="actions">
                <template v-if="editing">
                    <button type="button" class="btn btn--outline" :disabled="saving" @click="cancel">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="submit" class="btn" :disabled="saving">
                        {{ saving ? t('common.saving') : t('profile.saveBtn') }}
                    </button>
                </template>
                <button v-else type="button" class="btn" @click="editing = true">
                    <Pencil :size="15" aria-hidden="true" /> {{ t('profile.editBtn') }}
                </button>
            </div>
        </form>

        <NotificationSettings />

        <div class="section-head">
            <div>
                <span class="eyebrow">YOUR PERFORMANCE / LIVE DATA</span>
                <h2>{{ t('profile.perfHead') }}</h2>
                <p>{{ t('profile.perfSub') }}</p>
            </div>
        </div>

        <div class="stat-grid">
            <StatCard
                :icon="CircleCheckBig"
                :label="t('profile.sCompleted')"
                :value="completed.length"
                :note="t('profile.sCompletedNote', { pct: completionPct })"
            />
            <StatCard
                :icon="Target"
                :label="t('profile.sGoals')"
                :value="goals.length"
                :note="t('profile.sGoalsNote', { pct: goalAvg })"
            />
            <StatCard
                :icon="Activity"
                :label="t('profile.sActive')"
                :value="`${activeDays}/7`"
                :note="t('profile.sActiveNote')"
            />
            <StatCard
                :icon="Clock3"
                :label="t('profile.sOverdue')"
                :value="overdue"
                :note="t('profile.sOverdueNote', { n: open.length })"
            />
        </div>

        <div class="two-col">
            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('profile.chart7') }}</h3>
                        <p>{{ t('profile.chart7Sub') }}</p>
                    </div>
                </div>
                <ChartCanvas :key="`week-${chartKey}`" :config="weekChart" :label="t('profile.chart7')" />
            </div>
            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('profile.chartStatus') }}</h3>
                        <p>{{ t('profile.chartStatusSub') }}</p>
                    </div>
                </div>
                <ChartCanvas
                    v-if="statusSlices.length"
                    :key="`status-${chartKey}`"
                    :config="statusChart"
                    :label="t('profile.chartStatus')"
                />
                <p v-else class="hint-box">{{ t('profile.chartEmpty') }}</p>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('profile.chartPeriods') }}</h3>
                    <p>{{ t('profile.chartPeriodsSub') }}</p>
                </div>
            </div>
            <ChartCanvas :key="`periods-${chartKey}`" :config="periodChart" :label="t('profile.chartPeriods')" />
            <div class="period-metrics">
                <div v-for="row in periodRows" :key="row.period">
                    <b>{{ t(`period.${row.period}`) }}</b>
                    <span>{{ t('profile.tasksDone', { done: row.completed, total: row.tasks }) }}</span>
                    <small>{{ t('profile.goalsDone', { done: row.goalsDone, total: row.goals }) }}</small>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('profile.behavior') }}</h3>
                    <p>{{ t('profile.behaviorSub') }}</p>
                </div>
                <Activity :size="17" class="gold" aria-hidden="true" />
            </div>
            <div class="behavior-grid">
                <div>
                    <b>{{ completionPct }}%</b>
                    <span>{{ t('profile.bRate') }}</span>
                    <small>{{
                        t(
                            completionPct >= 75
                                ? 'profile.bRateHigh'
                                : completionPct >= 40
                                  ? 'profile.bRateMid'
                                  : 'profile.bRateLow',
                        )
                    }}</small>
                </div>
                <div>
                    <b>{{ activeDays }}/7</b>
                    <span>{{ t('profile.bConsistency') }}</span>
                    <small>{{ t(activeDays >= 5 ? 'profile.bConsHigh' : 'profile.bConsLow') }}</small>
                </div>
                <div>
                    <b>{{ goalAvg }}%</b>
                    <span>{{ t('profile.bGoals') }}</span>
                    <small>{{ t(goals.length ? 'profile.bGoalsSome' : 'profile.bGoalsNone') }}</small>
                </div>
                <div>
                    <b>{{ workspace.achievements.length }}</b>
                    <span>{{ t('profile.bBadges') }}</span>
                    <small>{{ t('profile.bBadgesNote') }}</small>
                </div>
            </div>
        </div>
    </section>
</template>
