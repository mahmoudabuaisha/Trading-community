<script setup lang="ts">
import {
    Activity,
    Brain,
    CalendarDays,
    Check,
    Clock3,
    Compass,
    Layers,
    Pause,
    Play,
    RotateCcw,
    ScanLine,
    Sparkles,
    Target,
    TrendingUp,
    Award,
} from 'lucide-vue-next'
import { computed, onBeforeUnmount, reactive, ref, watch, type Component } from 'vue'
import { formatDate, messages, t } from '../i18n'
import { dailyScore, lastDays, percent, today, ymd } from '../lib/dates'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { Task, TaskPriority } from '../types/domain'

const emit = defineEmits<{ add: [] }>()

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()

type TabId = 'daily' | 'focus' | 'review' | 'areas' | 'calendar' | 'week' | 'universe' | 'assistant'
const tabs: { id: TabId; label: string; icon: Component }[] = [
    { id: 'daily', label: 'tabDaily', icon: Target },
    { id: 'focus', label: 'tabFocus', icon: ScanLine },
    { id: 'review', label: 'tabReview', icon: Check },
    { id: 'areas', label: 'tabAreas', icon: Layers },
    { id: 'calendar', label: 'tabCalendar', icon: CalendarDays },
    { id: 'week', label: 'tabWeek', icon: Activity },
    { id: 'universe', label: 'tabUniverse', icon: Compass },
    { id: 'assistant', label: 'tabAssistant', icon: Brain },
]
const activeTab = ref<TabId>('daily')

// ── Today ────────────────────────────────────────────────────────────────
const PRIORITY_ORDER: Record<TaskPriority, number> = { urgent: 0, high: 1, medium: 2, low: 3 }
const openToday = computed(() =>
    workspace.todayTasks
        .filter((task) => task.status !== 'completed' && task.status !== 'cancelled')
        .sort((a, b) => PRIORITY_ORDER[a.priority] - PRIORITY_ORDER[b.priority]),
)
const topThree = computed(() => openToday.value.slice(0, 3))
const timeline = computed(() =>
    [...workspace.todayTasks].sort((a, b) => Number(a.status === 'completed') - Number(b.status === 'completed')),
)
const focusTarget = computed(() => auth.user?.dailyFocusMinutes ?? 120)
const reviewSaved = computed(() => Boolean(workspace.review?.savedAt) && !reviewDirty.value)
const score = computed(() =>
    dailyScore(workspace.todayPct, workspace.focus.todayMinutes, focusTarget.value, Boolean(workspace.review?.savedAt)),
)

function timelineNote(task: Task): string {
    if (task.status === 'completed') return t('center.tlDone')
    if (task.priority === 'urgent') return t('center.tlUrgent')
    if (task.priority === 'high') return t('center.tlHigh')
    return t('center.tlWaiting')
}

// ── Focus timer ──────────────────────────────────────────────────────────
// The end instant is stored, not a countdown, so a throttled background tab cannot drift.
const DURATIONS = [15, 25, 50, 90]
const duration = ref(25)
const remaining = ref(25 * 60)
const endsAt = ref<number | null>(null)
let ticker = 0

const running = computed(() => endsAt.value !== null)
const dialPct = computed(() =>
    Math.max(0, Math.min(100, ((duration.value * 60 - remaining.value) / (duration.value * 60)) * 100)),
)
const clockText = computed(
    () =>
        `${String(Math.floor(remaining.value / 60)).padStart(2, '0')}:${String(remaining.value % 60).padStart(2, '0')}`,
)

function chooseDuration(minutes: number): void {
    duration.value = minutes
    remaining.value = minutes * 60
}

function startTimer(): void {
    endsAt.value = Date.now() + remaining.value * 1000
    ticker = window.setInterval(tick, 300)
}

function tick(): void {
    if (endsAt.value === null) return
    remaining.value = Math.max(0, Math.ceil((endsAt.value - Date.now()) / 1000))
    if (remaining.value === 0) void finishSession()
}

function pauseTimer(): void {
    tick()
    stopTicker()
}

function resetTimer(): void {
    stopTicker()
    remaining.value = duration.value * 60
}

function stopTicker(): void {
    window.clearInterval(ticker)
    endsAt.value = null
}

async function finishSession(): Promise<void> {
    const minutes = duration.value
    resetTimer()
    try {
        await workspace.logFocusSession(minutes)
        ui.success(t('center.focusSaved', { n: minutes }))
    } catch (error) {
        ui.failure(error)
    }
}

onBeforeUnmount(() => window.clearInterval(ticker))

// ── Daily review ─────────────────────────────────────────────────────────
const reviewFields = ['win', 'unfinished', 'lesson', 'tomorrow'] as const
const reviewLabels = { win: 'qWin', unfinished: 'qUnfinished', lesson: 'qLesson', tomorrow: 'qTomorrow' } as const
const reviewForm = reactive({ win: '', unfinished: '', lesson: '', tomorrow: '' })
const reviewDirty = ref(false)
const savingReview = ref(false)

watch(
    () => workspace.review,
    (review) => {
        if (reviewDirty.value) return
        for (const field of reviewFields) reviewForm[field] = review?.[field] ?? ''
    },
    { immediate: true },
)

async function saveReview(): Promise<void> {
    savingReview.value = true
    try {
        await workspace.saveReview({ ...reviewForm })
        reviewDirty.value = false
    } catch (error) {
        ui.failure(error)
    } finally {
        savingReview.value = false
    }
}

// ── Life areas ───────────────────────────────────────────────────────────
const areas = computed(() =>
    workspace.categories.map((category) => {
        const owned = workspace.tasks.filter((task) => task.categoryId === category.id)
        const done = owned.filter((task) => task.status === 'completed').length
        return { ...category, total: owned.length, done, pct: percent(done, owned.length) }
    }),
)

// ── Calendar (weeks start on Monday) ─────────────────────────────────────
const calendarMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const todayKey = today()
const weekdayLabels = computed(() =>
    // 2024-01-01 is a Monday.
    Array.from({ length: 7 }, (_, i) => formatDate(new Date(2024, 0, 1 + i), { weekday: 'short' })),
)
const calendarCells = computed(() => {
    const year = calendarMonth.value.getFullYear()
    const month = calendarMonth.value.getMonth()
    const blanks = (new Date(year, month, 1).getDay() + 6) % 7
    const days = new Date(year, month + 1, 0).getDate()
    return {
        blanks,
        days: Array.from({ length: days }, (_, i) => {
            const key = ymd(new Date(year, month, i + 1))
            return { n: i + 1, key, due: workspace.tasks.filter((task) => task.dueDate === key).length }
        }),
    }
})
const shiftMonth = (step: number) =>
    (calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + step, 1))

// ── Weekly rhythm ────────────────────────────────────────────────────────
const week = computed(() => lastDays(workspace.tasks, 7))
const weekTotal = computed(() => week.value.reduce((sum, day) => sum + day.completed, 0))
const weekMax = computed(() => Math.max(1, ...week.value.map((day) => day.completed)))

// ── Universe ─────────────────────────────────────────────────────────────
const planets = computed(() => [
    { label: t('center.uTasks'), value: workspace.tasks.length, icon: Target },
    { label: t('center.uGoals'), value: workspace.goals.length, icon: TrendingUp },
    { label: t('center.uAchievements'), value: workspace.achievements.length, icon: Award },
    { label: t('center.uFocus'), value: `${workspace.focus.totalMinutes}${t('common.minutesShort')}`, icon: Clock3 },
])

// ── Assistant: rule-based suggestions computed locally from the member's own data ──
type Prompt = 'aPrioritize' | 'aAnalyze' | 'aTomorrow'
const prompts: Prompt[] = ['aPrioritize', 'aAnalyze', 'aTomorrow']
const answer = ref<{ intro: string; items: string[] } | null>(null)

function ask(prompt: Prompt): void {
    if (prompt === 'aPrioritize') {
        answer.value = openToday.value.length
            ? {
                  intro: t('center.aOrder'),
                  items: openToday.value.map((task) => `${task.title} — ${t(`priority.${task.priority}`)}`),
              }
            : { intro: t('center.aNoTasks'), items: [] }
    } else if (prompt === 'aAnalyze') {
        const best = [...week.value].sort((a, b) => b.completed - a.completed)[0]
        answer.value = weekTotal.value
            ? {
                  intro: t('center.aWeek', {
                      n: weekTotal.value,
                      days: week.value.filter((day) => day.completed > 0).length,
                      best: formatDate(best.date, { weekday: 'long' }),
                  }),
                  items: [],
              }
            : { intro: t('center.aWeekEmpty'), items: [] }
    } else {
        const items = openToday.value.map((task) => task.title)
        const note = workspace.review?.tomorrow?.trim()
        if (note) items.unshift(t('center.aTomorrowReview', { text: note }))
        answer.value = items.length
            ? { intro: t('center.aTomorrowCarry'), items }
            : { intro: t('center.aTomorrowEmpty'), items: [] }
    }
}

const growthCards = computed(() => workspace.motivations.slice(0, 3))
const growthLabel = (category: keyof typeof messages.value.growth.cat) => messages.value.growth.cat[category]
</script>

<template>
    <section class="suite">
        <div class="suite__head">
            <div>
                <span class="eyebrow"><Sparkles :size="13" aria-hidden="true" /> PERSONAL OPERATING SYSTEM</span>
                <h2>{{ t('center.title') }}</h2>
                <p>{{ t('center.sub') }}</p>
            </div>
            <div class="score-orb" :style="{ '--pct': `${score}%` }">
                <span>DAILY SCORE</span>
                <b>{{ score }}</b>
                <small>/ 100</small>
            </div>
        </div>

        <nav class="tabs" :aria-label="t('center.tools')">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                :class="{ tabs__active: activeTab === tab.id }"
                :aria-pressed="activeTab === tab.id"
                @click="activeTab = tab.id"
            >
                <component :is="tab.icon" :size="15" aria-hidden="true" />{{ t(`center.${tab.label}`) }}
            </button>
        </nav>

        <div v-if="activeTab === 'daily'" class="suite__content">
            <div class="metrics">
                <div>
                    <span>{{ t('center.doneToday') }}</span>
                    <b>{{ workspace.todayDone }}/{{ workspace.todayTasks.length }}</b>
                    <small>{{ t('center.pctDone', { pct: workspace.todayPct }) }}</small>
                </div>
                <div>
                    <span>{{ t('center.streak') }}</span>
                    <b>{{ workspace.streak }} {{ t('common.day') }}</b>
                    <small>{{ t('center.streakNote') }}</small>
                </div>
                <div>
                    <span>{{ t('center.focusTime') }}</span>
                    <b>{{ workspace.focus.todayMinutes }} {{ t('common.minutesShort') }}</b>
                    <small>{{ t('center.focusNote', { target: focusTarget }) }}</small>
                </div>
            </div>

            <div class="section-title">
                <div>
                    <h3>{{ t('center.top3') }}</h3>
                    <p>{{ t('center.top3Sub') }}</p>
                </div>
                <button type="button" class="btn btn--ghost" @click="emit('add')">{{ t('center.addTask') }}</button>
            </div>
            <div class="priority-list">
                <div v-for="(task, index) in topThree" :key="task.id">
                    <span class="priority-list__no">0{{ index + 1 }}</span>
                    <b>{{ task.title }}</b>
                    <small class="priority" :class="`priority--${task.priority}`">{{
                        t(`priority.${task.priority}`)
                    }}</small>
                </div>
                <p v-if="!topThree.length" class="hint-box">
                    {{ t(workspace.todayTasks.length ? 'center.noPending' : 'center.firstTask') }}
                </p>
            </div>
            <div class="track"><i :style="{ inlineSize: `${workspace.todayPct}%` }" /></div>

            <div class="timeline">
                <div class="section-title">
                    <div>
                        <h3>{{ t('center.timeline') }}</h3>
                        <p>{{ t('center.timelineSub') }}</p>
                    </div>
                    <span class="count-pill">{{ workspace.todayDone }} / {{ workspace.todayTasks.length }}</span>
                </div>
                <div class="timeline__list">
                    <div
                        v-for="(task, index) in timeline"
                        :key="task.id"
                        class="timeline__item"
                        :class="{ 'timeline__item--done': task.status === 'completed' }"
                    >
                        <span class="timeline__dot">
                            <Check v-if="task.status === 'completed'" :size="11" />
                            <template v-else>{{ String(index + 1).padStart(2, '0') }}</template>
                        </span>
                        <div>
                            <b>{{ task.title }}</b>
                            <small>{{ timelineNote(task) }}</small>
                        </div>
                    </div>
                    <p v-if="!timeline.length" class="hint-box">{{ t('center.timelineEmpty') }}</p>
                </div>
            </div>
        </div>

        <div v-else-if="activeTab === 'focus'" class="suite__content">
            <div class="focus-panel">
                <span class="eyebrow"><ScanLine :size="13" aria-hidden="true" /> DEEP WORK SESSION</span>
                <div class="timer-dial" :style="{ '--pct': `${dialPct}%` }">
                    <div class="timer-dial__time" role="timer" aria-live="off">{{ clockText }}</div>
                </div>
                <p>{{ t(running ? 'center.focusRunning' : 'center.focusIdle') }}</p>
                <div class="pill-row">
                    <button
                        v-for="minutes in DURATIONS"
                        :key="minutes"
                        type="button"
                        class="pill"
                        :class="{ 'pill--chosen': duration === minutes }"
                        :disabled="running"
                        @click="chooseDuration(minutes)"
                    >
                        {{ t('center.minutesOption', { n: minutes }) }}
                    </button>
                </div>
                <div class="actions actions--center">
                    <button v-if="running" type="button" class="btn" @click="pauseTimer">
                        <Pause :size="16" /> {{ t('center.pause') }}
                    </button>
                    <button v-else type="button" class="btn" @click="startTimer">
                        <Play :size="16" /> {{ t(remaining === duration * 60 ? 'center.start' : 'center.resume') }}
                    </button>
                    <button type="button" class="btn btn--outline" @click="resetTimer">
                        <RotateCcw :size="15" /> {{ t('center.reset') }}
                    </button>
                </div>
                <small>{{
                    t('center.focusTotal', { today: workspace.focus.todayMinutes, total: workspace.focus.totalMinutes })
                }}</small>
            </div>
        </div>

        <form v-else-if="activeTab === 'review'" class="suite__content" @submit.prevent="saveReview">
            <div class="section-title">
                <div>
                    <h3>{{ t('center.reviewTitle') }}</h3>
                    <p>{{ t('center.reviewSub') }}</p>
                </div>
                <span class="count-pill" :class="{ 'count-pill--ok': reviewSaved }">
                    {{ t(reviewSaved ? 'center.reviewSaved' : 'center.reviewUnsaved') }}
                </span>
            </div>
            <div class="review-grid">
                <label v-for="field in reviewFields" :key="field" class="field">
                    <span>{{ t(`center.${reviewLabels[field]}`) }}</span>
                    <textarea
                        v-model="reviewForm[field]"
                        class="input"
                        rows="4"
                        maxlength="2000"
                        :placeholder="t(`center.${reviewLabels[field]}Ph`)"
                        @input="reviewDirty = true"
                    />
                </label>
            </div>
            <button type="submit" class="btn" :disabled="savingReview">
                {{ savingReview ? t('common.saving') : t('center.saveReview') }}
            </button>
        </form>

        <div v-else-if="activeTab === 'areas'" class="suite__content">
            <div class="section-title">
                <div>
                    <h3>{{ t('center.areasTitle') }}</h3>
                    <p>{{ t('center.areasSub') }}</p>
                </div>
            </div>
            <div class="area-grid">
                <div v-for="area in areas" :key="area.id">
                    <span class="area-grid__bar" :style="{ background: area.color }" />
                    <b>{{ area.name }}</b>
                    <strong>{{ area.done }}/{{ area.total }}</strong>
                    <small>{{ t('center.pctAchieved', { pct: area.pct }) }}</small>
                    <div class="track track--thin">
                        <i :style="{ inlineSize: `${area.pct}%`, background: area.color }" />
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="activeTab === 'calendar'" class="suite__content">
            <div class="calendar-head">
                <button type="button" class="icon-btn" :aria-label="t('common.previous')" @click="shiftMonth(-1)">
                    ‹
                </button>
                <h3>{{ formatDate(calendarMonth, { month: 'long', year: 'numeric' }) }}</h3>
                <button type="button" class="icon-btn" :aria-label="t('common.next')" @click="shiftMonth(1)">›</button>
            </div>
            <div class="calendar-grid">
                <b v-for="label in weekdayLabels" :key="label">{{ label }}</b>
                <span v-for="blank in calendarCells.blanks" :key="`blank-${blank}`" class="calendar-grid__blank" />
                <span
                    v-for="cell in calendarCells.days"
                    :key="cell.key"
                    :class="{ 'calendar-grid__today': cell.key === todayKey }"
                >
                    {{ cell.n }}<i v-if="cell.due">{{ cell.due }}</i>
                </span>
            </div>
            <small class="hint">{{ t('center.calendarHint') }}</small>
        </div>

        <div v-else-if="activeTab === 'week'" class="suite__content">
            <div class="section-title">
                <div>
                    <h3>{{ t('center.weekTitle') }}</h3>
                    <p>{{ t('center.weekSub') }}</p>
                </div>
                <Activity :size="18" aria-hidden="true" />
            </div>
            <div class="week-bars">
                <div v-for="day in week" :key="day.day">
                    <b>{{ day.completed }}</b>
                    <span><i :style="{ blockSize: `${Math.max(4, (day.completed / weekMax) * 100)}%` }" /></span>
                    <small>{{ formatDate(day.date, { weekday: 'short' }) }}</small>
                </div>
            </div>
            <div class="insight">
                <Sparkles :size="17" aria-hidden="true" />
                <p>{{ weekTotal ? t('center.weekInsight', { n: weekTotal }) : t('center.weekEmpty') }}</p>
            </div>
        </div>

        <div v-else-if="activeTab === 'universe'" class="suite__content">
            <div class="universe">
                <div class="universe__core">
                    <Sparkles :size="25" aria-hidden="true" /><b>YOUR<br />UNIVERSE</b>
                </div>
                <div
                    v-for="(planet, index) in planets"
                    :key="planet.label"
                    class="universe__planet"
                    :class="`universe__planet--${index + 1}`"
                >
                    <component :is="planet.icon" :size="17" aria-hidden="true" />
                    <b>{{ planet.value }}</b>
                    <small>{{ planet.label }}</small>
                </div>
            </div>
            <p class="hint">{{ t('center.universeHint') }}</p>
        </div>

        <div v-else class="suite__content">
            <div class="assistant">
                <span class="assistant__icon"><Brain :size="25" aria-hidden="true" /></span>
                <h3>{{ t('center.assistantTitle') }}</h3>
                <p>{{ t('center.assistantSub') }}</p>
                <div class="pill-row">
                    <button v-for="prompt in prompts" :key="prompt" type="button" class="pill" @click="ask(prompt)">
                        {{ t(`center.${prompt}`) }}
                    </button>
                </div>
                <div v-if="answer" class="assistant__answer" aria-live="polite">
                    <p>{{ answer.intro }}</p>
                    <ol v-if="answer.items.length">
                        <li v-for="item in answer.items" :key="item">{{ item }}</li>
                    </ol>
                </div>
            </div>
        </div>

        <div v-if="growthCards.length" class="growth-strip">
            <div class="growth-strip__head">
                <span class="eyebrow"><Sparkles :size="14" aria-hidden="true" /> GROWTH &amp; MOTIVATION</span>
                <b>{{ t('center.growthKicker') }}</b>
            </div>
            <div class="growth-strip__cards">
                <article v-for="card in growthCards" :key="card.id">
                    <small>{{ growthLabel(card.category) }}</small>
                    <h3>{{ card.title }}</h3>
                    <p>{{ card.body }}</p>
                </article>
            </div>
        </div>
    </section>
</template>
