<script setup lang="ts">
import type { ChartConfiguration } from 'chart.js'
import { Activity, CircleCheckBig, Clock3, Target } from 'lucide-vue-next'
import { computed } from 'vue'
import ChartCanvas from '../ChartCanvas.vue'
import StatCard from '../StatCard.vue'
import { formatDate, locale, t } from '../../i18n'
import { lastDays, percent, today } from '../../lib/dates'
import { useWorkspaceStore } from '../../stores/workspace'
import { PERIOD_TYPES } from '../../types/domain'

/** Account statistics: totals, charts and a plain-language reading of the member's activity. */
const workspace = useWorkspaceStore()

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
</script>

<template>
    <div class="stack">
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
    </div>
</template>
