<script setup lang="ts">
import {
    Activity,
    Award,
    BarChart3,
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    Circle,
    CircleCheckBig,
    Flag,
    Plus,
    Sparkles,
    Target,
    TrendingUp,
    Zap,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
import CommandCenter from '../components/CommandCenter.vue'
import EmptyState from '../components/EmptyState.vue'
import StatCard from '../components/StatCard.vue'
import TaskModal from '../components/TaskModal.vue'
import { isRtl, messages, t } from '../i18n'
import { completedDay, parseDay, percent, periodRange, today } from '../lib/dates'
import { useAuthStore } from '../stores/auth'
import { useWorkspaceStore } from '../stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const adding = ref(false)

const greeting = computed(() => {
    const hour = new Date().getHours()
    return t(hour < 12 ? 'overview.morning' : hour < 18 ? 'overview.afternoon' : 'overview.evening')
})
const firstName = computed(() => (auth.user?.displayName ?? '').split(' ')[0])
const quote = computed(() => {
    const quotes = messages.value.quotes
    return quotes[new Date().getDate() % quotes.length]
})
const completionRate = computed(() => percent(workspace.completedCount, workspace.tasks.length))
const Chevron = computed(() => (isRtl.value ? ChevronLeft : ChevronRight))

/** Tasks completed during the current Monday–Sunday week, against the member's weekly goal. */
const weeklyDone = computed(() => {
    const range = periodRange('weekly', parseDay(today()))
    return workspace.tasks.filter((task) => {
        const day = completedDay(task)
        return day >= range.start && day <= range.end
    }).length
})
const weeklyGoal = computed(() => auth.user?.weeklyGoal ?? 20)

const quickLinks = [
    { to: { name: 'tasks', params: { period: 'weekly' } }, label: 'qWeekly', icon: BarChart3 },
    { to: { name: 'tasks', params: { period: 'monthly' } }, label: 'qMonthly', icon: Target },
    { to: { name: 'tasks', params: { period: 'yearly' } }, label: 'qYearly', icon: Flag },
    { to: { name: 'goals' }, label: 'qGoals', icon: TrendingUp },
]
</script>

<template>
    <section class="stack">
        <div class="banner">
            <div>
                <span class="eyebrow">SN TASKS / YOUR PERSONAL SPACE</span>
                <h2>{{ greeting }}{{ isRtl ? '،' : ',' }} {{ firstName }} <span class="gold">✦</span></h2>
                <p>{{ t('overview.bannerSub') }}</p>
                <div class="banner__quote"><Sparkles :size="15" aria-hidden="true" />{{ quote }}</div>
            </div>
            <div class="banner__orb" aria-hidden="true">
                <div class="banner__orbit banner__orbit--one" />
                <div class="banner__orbit banner__orbit--two" />
                <Target :size="40" />
            </div>
        </div>

        <CommandCenter @add="adding = true" />

        <div class="stat-grid">
            <StatCard
                :icon="CircleCheckBig"
                :label="t('overview.totalCompleted')"
                :value="workspace.completedCount"
                :note="t('overview.everyStep')"
            />
            <StatCard
                :icon="CalendarDays"
                :label="t('overview.todayTasks')"
                :value="`${workspace.todayDone}/${workspace.todayTasks.length}`"
                :note="t('overview.todayDone')"
            />
            <StatCard
                :icon="TrendingUp"
                :label="t('overview.completionRate')"
                :value="`${completionRate}%`"
                :note="t('overview.ofAllTasks')"
            />
            <StatCard
                :icon="Award"
                :label="t('overview.badges')"
                :value="workspace.achievements.length"
                :note="t('overview.milestones')"
            />
        </div>

        <div class="two-col">
            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('overview.todayTasks') }}</h3>
                        <p>{{ t('overview.todaySub') }}</p>
                    </div>
                    <RouterLink :to="{ name: 'tasks', params: { period: 'daily' } }" class="inline-link">
                        {{ t('overview.viewAll') }} <component :is="Chevron" :size="14" aria-hidden="true" />
                    </RouterLink>
                </div>
                <div class="today-progress">
                    <div class="ring" :style="{ '--pct': `${workspace.todayPct}%` }">
                        <b>{{ workspace.todayPct }}%</b>
                    </div>
                    <div>
                        <strong>{{
                            t('overview.doneOf', { done: workspace.todayDone, total: workspace.todayTasks.length })
                        }}</strong>
                        <span>{{ t('overview.keepGoing') }}</span>
                    </div>
                </div>
                <button
                    v-for="task in workspace.todayTasks.slice(0, 4)"
                    :key="task.id"
                    type="button"
                    class="mini-task"
                    :aria-pressed="task.status === 'completed'"
                    @click="workspace.toggleTask(task)"
                >
                    <span class="mini-task__mark" :class="{ 'mini-task__mark--done': task.status === 'completed' }">
                        <component :is="task.status === 'completed' ? Check : Circle" :size="12" aria-hidden="true" />
                    </span>
                    <span :class="{ strike: task.status === 'completed' }">{{ task.title }}</span>
                </button>
                <EmptyState
                    v-if="!workspace.todayTasks.length"
                    :title="t('overview.startTitle')"
                    :text="t('overview.startText')"
                />
                <button type="button" class="btn btn--outline btn--block" @click="adding = true">
                    <Plus :size="15" aria-hidden="true" /> {{ t('overview.addToday') }}
                </button>
            </div>

            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('overview.quick') }}</h3>
                        <p>{{ t('overview.quickSub') }}</p>
                    </div>
                    <Zap :size="17" class="gold" aria-hidden="true" />
                </div>
                <div class="quick-grid">
                    <RouterLink v-for="link in quickLinks" :key="link.label" :to="link.to" class="quick-item">
                        <span class="quick-item__icon"
                            ><component :is="link.icon" :size="17" aria-hidden="true"
                        /></span>
                        <div>
                            <b>{{ t(`overview.${link.label}`) }}</b>
                            <small>{{ t(`overview.${link.label}Sub`) }}</small>
                        </div>
                        <component :is="Chevron" :size="15" aria-hidden="true" />
                    </RouterLink>
                </div>
                <div class="weekly-goal">
                    <div class="weekly-goal__row">
                        <span><Activity :size="15" aria-hidden="true" /> {{ t('overview.weeklyGoal') }}</span>
                        <b>{{ t('overview.weeklyGoalNote', { done: weeklyDone, goal: weeklyGoal }) }}</b>
                    </div>
                    <div class="track">
                        <i :style="{ inlineSize: `${Math.min(100, percent(weeklyDone, weeklyGoal))}%` }" />
                    </div>
                </div>
            </div>
        </div>

        <TaskModal v-if="adding" :task="null" default-period="daily" :anchor-day="today()" @close="adding = false" />
    </section>
</template>
