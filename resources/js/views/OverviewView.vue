<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    Award,
    BarChart3,
    CalendarCheck,
    CalendarDays,
    CircleCheck,
    Clock3,
    Crown,
    Flame,
    Gem,
    Hand,
    Lock,
    Plus,
    Star,
    Trophy,
} from 'lucide-vue-next'
import { computed, ref, type Component } from 'vue'
import CommandCenter from '../components/CommandCenter.vue'
import TaskModal from '../components/TaskModal.vue'
import { formatDate, isRtl, messages, t } from '../i18n'
import { useSiteCopy } from '../lib/content'
import { lastDays, parseDay, percent, today, ymd } from '../lib/dates'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { Task } from '../types/domain'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()
const adding = ref(false)
const { welcomeSub } = useSiteCopy()

const firstName = computed(() => (auth.user?.displayName ?? '').split(' ')[0])
const completionRate = computed(() => percent(workspace.completedCount, workspace.tasks.length))
const openCount = computed(
    () => workspace.tasks.filter((task) => task.status !== 'completed' && task.status !== 'cancelled').length,
)
const Arrow = computed(() => (isRtl.value ? ArrowLeft : ArrowRight))

const tiles = computed(() => [
    { icon: Trophy, value: workspace.tasks.length, label: t('dash.tTotal') },
    { icon: BarChart3, value: `${completionRate.value}%`, label: t('dash.tRate') },
    { icon: Flame, value: workspace.streak, label: t('dash.tStreak') },
    { icon: Star, value: auth.user?.points ?? 0, label: t('dash.tPoints') },
])

/** Open tasks with a due date from today onward, soonest first. */
const upcoming = computed(() =>
    workspace.tasks
        .filter(
            (task) =>
                task.dueDate && task.dueDate >= today() && task.status !== 'completed' && task.status !== 'cancelled',
        )
        .sort((a, b) => (a.dueDate! < b.dueDate! ? -1 : a.dueDate! > b.dueDate! ? 1 : 0))
        .slice(0, 4),
)

function dueLabel(task: Task): string {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    if (task.dueDate === today()) return t('dash.dueToday')
    if (task.dueDate === ymd(tomorrow)) return t('dash.dueTomorrow')
    return formatDate(parseDay(task.dueDate!), { weekday: 'short', day: 'numeric', month: 'short' })
}

const categoryColor = (task: Task) => workspace.categories.find((category) => category.id === task.categoryId)?.color

async function complete(task: Task): Promise<void> {
    try {
        await workspace.toggleTask(task)
    } catch (error) {
        ui.failure(error)
    }
}

/** Four headline badges; earned ones are lit, the rest stay locked. */
const badgeIcons: Record<string, Component> = {
    first_task: Star,
    streak_7: CalendarCheck,
    tasks_10: Crown,
    first_goal: Gem,
}
const badges = computed(() =>
    Object.keys(badgeIcons).map((key) => ({
        key,
        icon: badgeIcons[key],
        title: messages.value.achievements.keys[key as keyof typeof messages.value.achievements.keys].title,
        earned: workspace.achievements.some((badge) => badge.key === key),
    })),
)

// Seven-day completion chart: bars plus a trend line through their tops.
const week = computed(() => lastDays(workspace.tasks, 7))
const chart = computed(() => {
    const max = Math.max(1, ...week.value.map((day) => day.completed))
    const bars = week.value.map((day, index) => {
        const height = 8 + (day.completed / max) * 92
        return {
            x: 16 + index * 40,
            y: 120 - height,
            height,
            label: formatDate(day.date, { weekday: 'short' }),
            value: day.completed,
        }
    })
    return { bars, line: bars.map((bar) => `${bar.x + 13},${bar.y - 8}`).join(' ') }
})
</script>

<template>
    <section class="dash">
        <div class="dash__row dash__row--top">
            <div class="tiles">
                <div v-for="tile in tiles" :key="tile.label" class="tile">
                    <component :is="tile.icon" :size="34" class="tile__icon" aria-hidden="true" />
                    <strong>{{ tile.value }}</strong>
                    <span>{{ tile.label }}</span>
                </div>
            </div>

            <div class="dash-card welcome">
                <h2>
                    <Hand :size="34" class="welcome__hand" aria-hidden="true" />
                    {{ t('dash.welcome', { name: firstName }) }}
                </h2>
                <p>{{ welcomeSub }}</p>
                <i class="welcome__swoosh" aria-hidden="true" />
            </div>
        </div>

        <div class="dash__row">
            <div class="dash-card">
                <div class="dash-card__head">
                    <h3><Clock3 :size="26" aria-hidden="true" /> {{ t('dash.upcoming') }}</h3>
                    <RouterLink :to="{ name: 'tasks', params: { period: 'daily' } }" class="inline-link">
                        {{ t('dash.viewAll') }} <component :is="Arrow" :size="15" aria-hidden="true" />
                    </RouterLink>
                </div>
                <ul v-if="upcoming.length" class="upcoming">
                    <li v-for="task in upcoming" :key="task.id">
                        <button
                            type="button"
                            class="upcoming__check"
                            :aria-label="t('tasks.toggle')"
                            @click="complete(task)"
                        >
                            <CircleCheck :size="22" aria-hidden="true" />
                        </button>
                        <div>
                            <b>{{ task.title }}</b>
                            <small>
                                <i
                                    v-if="categoryColor(task)"
                                    class="dot"
                                    :style="{ background: categoryColor(task) }"
                                />
                                {{ dueLabel(task) }} · {{ t(`priority.${task.priority}`) }}
                            </small>
                        </div>
                        <CalendarDays :size="24" class="upcoming__icon" aria-hidden="true" />
                    </li>
                </ul>
                <p v-else class="hint-box">{{ t('dash.upcomingEmpty') }}</p>
            </div>

            <div class="dash-card today">
                <div class="dash-card__head">
                    <h3><CalendarDays :size="26" aria-hidden="true" /> {{ t('dash.today') }}</h3>
                </div>
                <div class="today__body">
                    <div class="today__ring" :style="{ '--pct': `${workspace.todayPct}%` }">
                        <div>
                            <b>{{ workspace.todayPct }}%</b>
                            <BarChart3 :size="22" aria-hidden="true" />
                        </div>
                    </div>
                    <div class="today__text">
                        <strong>
                            <span class="today__count">{{ workspace.todayDone }}</span>
                            {{ t('dash.todayCount', { done: '', total: workspace.todayTasks.length }).trim() }}
                        </strong>
                        <span><CircleCheck :size="20" aria-hidden="true" /> {{ t('dash.todayDone') }}</span>
                    </div>
                </div>
                <RouterLink
                    v-if="workspace.todayTasks.length > workspace.todayDone"
                    :to="{ name: 'tasks', params: { period: 'daily' } }"
                    class="btn-metal"
                >
                    <component :is="Arrow" :size="20" aria-hidden="true" /> <span>{{ t('dash.todayCta') }}</span>
                </RouterLink>
                <button v-else type="button" class="btn-metal" @click="adding = true">
                    <Plus :size="20" aria-hidden="true" /> <span>{{ t('dash.todayAdd') }}</span>
                </button>
            </div>
        </div>

        <div class="dash__row">
            <div class="dash-card">
                <div class="dash-card__head">
                    <h3><BarChart3 :size="26" aria-hidden="true" /> {{ t('dash.quick') }}</h3>
                    <RouterLink :to="{ name: 'profile' }" class="inline-link">
                        {{ t('dash.viewAll') }} <component :is="Arrow" :size="15" aria-hidden="true" />
                    </RouterLink>
                </div>
                <div class="quick">
                    <dl>
                        <div>
                            <dt>{{ t('dash.qDone') }}</dt>
                            <dd>{{ workspace.completedCount }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('dash.qLeft') }}</dt>
                            <dd>{{ openCount }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('dash.qRate') }}</dt>
                            <dd>{{ completionRate }}%</dd>
                        </div>
                    </dl>
                    <svg class="quick__chart" viewBox="0 0 300 150" role="img" :aria-label="t('dash.chartLabel')">
                        <line x1="6" y1="120" x2="294" y2="120" class="quick__axis" />
                        <g v-for="(bar, index) in chart.bars" :key="bar.label">
                            <rect
                                :x="bar.x"
                                :y="bar.y"
                                width="26"
                                :height="bar.height"
                                rx="3"
                                :class="index % 2 ? 'quick__bar--gold' : 'quick__bar--steel'"
                            />
                            <text :x="bar.x + 13" y="138" text-anchor="middle">{{ bar.label }}</text>
                            <title>{{ bar.label }}: {{ bar.value }}</title>
                        </g>
                        <polyline :points="chart.line" class="quick__trend" />
                    </svg>
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-card__head">
                    <h3><Trophy :size="26" aria-hidden="true" /> {{ t('dash.badges') }}</h3>
                    <RouterLink :to="{ name: 'achievements' }" class="inline-link">
                        {{ t('dash.viewAll') }} <component :is="Arrow" :size="15" aria-hidden="true" />
                    </RouterLink>
                </div>
                <div class="shields">
                    <div
                        v-for="badge in badges"
                        :key="badge.key"
                        class="shield"
                        :class="{ 'shield--locked': !badge.earned }"
                    >
                        <span class="shield__plate"
                            ><component :is="badge.earned ? badge.icon : Lock" :size="26" aria-hidden="true"
                        /></span>
                        <b>{{ badge.title }}</b>
                        <small v-if="!badge.earned">{{ t('achievements.locked') }}</small>
                        <Award v-else :size="14" class="gold" aria-hidden="true" />
                    </div>
                </div>
            </div>
        </div>

        <CommandCenter @add="adding = true" />

        <TaskModal v-if="adding" :task="null" default-period="daily" :anchor-day="today()" @close="adding = false" />
    </section>
</template>
