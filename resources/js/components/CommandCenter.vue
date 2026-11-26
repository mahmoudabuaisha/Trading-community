<script setup lang="ts">
import { useRoute } from 'vue-router'
import {
    Activity,
    Brain,
    CalendarDays,
    Check,
    Clock3,
    Compass,
    Layers,
    Pause,
    Pencil,
    Plus,
    Trash2,
    Play,
    RotateCcw,
    ScanLine,
    Sparkles,
    Target,
    TrendingUp,
    Award,
} from 'lucide-vue-next'
import { computed, reactive, ref, watch, type Component } from 'vue'
import DayEntryDialog from './DayEntryDialog.vue'
import ConfirmDialog from './ConfirmDialog.vue'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import { formatDate, messages, t } from '../i18n'
import { dailyScore, lastDays, percent, today, ymd } from '../lib/dates'
import { useAuthStore } from '../stores/auth'
import { PRESETS, MAX_MINUTES, useFocusStore } from '../stores/focus'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { DayEntry, Task, TaskPriority } from '../types/domain'

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
const route = useRoute()
const activeTab = ref<TabId>(route.query.tab === 'focus' ? 'focus' : 'daily')
watch(
    () => route.query.tab,
    (tab) => {
        if (tab === 'focus') activeTab.value = 'focus'
    },
)

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
// The timer lives in a store so it keeps running across pages and visits.
const focus = useFocusStore()
const customMinutes = ref<number | null>(null)
function chooseCustom(): void {
    if (customMinutes.value && customMinutes.value > 0) focus.choose(customMinutes.value)
}

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

// Adding, renaming and removing areas; a new one counts like the starter ones.
const AREA_COLORS = ['#70d6ad', '#a89bf5', '#f2c879', '#83d8f5', '#e58a7b', '#e79ac0', '#8fb6ff', '#d6ad62']
const areaForm = reactive({ id: null as number | null, name: '', color: AREA_COLORS[4] })
const areaErrors = ref<Record<string, string[]>>({})
const deletingArea = ref<{ id: number; name: string } | null>(null)

function editArea(area: { id: number; name: string; color: string }): void {
    Object.assign(areaForm, { id: area.id, name: area.name, color: area.color })
    areaErrors.value = {}
}
function resetArea(): void {
    Object.assign(areaForm, {
        id: null,
        name: '',
        color: AREA_COLORS[(workspace.categories.length + 4) % AREA_COLORS.length],
    })
    areaErrors.value = {}
}
async function saveArea(): Promise<void> {
    if (!areaForm.name.trim()) return
    try {
        await workspace.saveCategory(areaForm.id, { name: areaForm.name.trim(), color: areaForm.color })
        ui.success(t(areaForm.id === null ? 'center.areaAdded' : 'center.areaSaved'))
        resetArea()
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) areaErrors.value = error.errors
        else ui.failure(error)
    }
}
async function confirmDeleteArea(): Promise<void> {
    const area = deletingArea.value
    deletingArea.value = null
    if (!area) return
    try {
        await workspace.deleteCategory(area.id)
        if (areaForm.id === area.id) resetArea()
    } catch (error) {
        ui.failure(error)
    }
}

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
            return {
                n: i + 1,
                key,
                due: workspace.tasks.filter((task) => task.dueDate === key).length,
                entry: dayEntries.value.get(key) ?? null,
            }
        }),
    }
})
const shiftMonth = (step: number) =>
    (calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + step, 1))

// A picture and a note per day, the same entries the habit tracker shows.
const dayEntries = ref(new Map<string, DayEntry>())
const openDay = ref<string | null>(null)
const monthKey = computed(() => ymd(calendarMonth.value).slice(0, 7))
async function loadDayEntries(): Promise<void> {
    const month = monthKey.value
    try {
        const days = await repository.dayEntries(month)
        if (month === monthKey.value) dayEntries.value = new Map(days.map((entry) => [entry.day, entry]))
    } catch (error) {
        ui.failure(error)
    }
}
watch(
    () => [activeTab.value, monthKey.value],
    () => {
        if (activeTab.value === 'calendar') void loadDayEntries()
    },
)
function setDayEntry(day: string, entry: DayEntry | null): void {
    const next = new Map(dayEntries.value)
    if (entry) next.set(day, entry)
    else next.delete(day)
    dayEntries.value = next
}

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
                <div class="timer-dial" :style="{ '--pct': `${focus.pct}%` }">
                    <div class="timer-dial__time" role="timer" aria-live="off">{{ focus.clock }}</div>
                </div>
                <p>{{ t(focus.running ? 'center.focusRunning' : 'center.focusIdle') }}</p>
                <div class="pill-row">
                    <button
                        v-for="minutes in PRESETS"
                        :key="minutes"
                        type="button"
                        class="pill"
                        :class="{ 'pill--chosen': focus.duration === minutes }"
                        :disabled="focus.running"
                        @click="focus.choose(minutes)"
                    >
                        {{ t('center.minutesOption', { n: minutes }) }}
                    </button>
                </div>
                <form class="focus-custom" @submit.prevent="chooseCustom">
                    <label class="sr-only" for="focus-custom">{{ t('focus.custom') }}</label>
                    <input
                        id="focus-custom"
                        v-model.number="customMinutes"
                        class="input"
                        type="number"
                        min="1"
                        :max="MAX_MINUTES"
                        inputmode="numeric"
                        :placeholder="t('focus.custom')"
                        :disabled="focus.running"
                    />
                    <button type="submit" class="pill" :disabled="focus.running || !customMinutes">
                        {{ t('focus.set') }}
                    </button>
                </form>
                <div class="actions actions--center">
                    <button v-if="focus.running" type="button" class="btn" @click="focus.pause()">
                        <Pause :size="16" /> {{ t('center.pause') }}
                    </button>
                    <button v-else type="button" class="btn" @click="focus.start()">
                        <Play :size="16" /> {{ t(focus.active ? 'center.resume' : 'center.start') }}
                    </button>
                    <button type="button" class="btn btn--outline" @click="focus.reset()">
                        <RotateCcw :size="15" /> {{ t('center.reset') }}
                    </button>
                </div>
                <p class="hint">{{ t('focus.keepsRunning') }}</p>
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
                    <span class="area-grid__top">
                        <span class="area-grid__bar" :style="{ background: area.color }" />
                        <span class="area-grid__tools">
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :aria-label="t('center.areaEdit', { name: area.name })"
                                @click="editArea(area)"
                            >
                                <Pencil :size="13" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :aria-label="t('center.areaDelete', { name: area.name })"
                                @click="deletingArea = area"
                            >
                                <Trash2 :size="13" />
                            </button>
                        </span>
                    </span>
                    <b>{{ area.name }}</b>
                    <strong>{{ area.done }}/{{ area.total }}</strong>
                    <small>{{ t('center.pctAchieved', { pct: area.pct }) }}</small>
                    <div class="track track--thin">
                        <i :style="{ inlineSize: `${area.pct}%`, background: area.color }" />
                    </div>
                </div>
            </div>
            <form class="area-form" @submit.prevent="saveArea">
                <span class="area-form__swatches" role="radiogroup" :aria-label="t('center.areaColor')">
                    <button
                        v-for="color in AREA_COLORS"
                        :key="color"
                        type="button"
                        role="radio"
                        class="area-form__swatch"
                        :class="{ 'area-form__swatch--on': areaForm.color === color }"
                        :style="{ background: color }"
                        :aria-checked="areaForm.color === color"
                        :aria-label="color"
                        @click="areaForm.color = color"
                    />
                </span>
                <input
                    v-model="areaForm.name"
                    class="input"
                    required
                    maxlength="40"
                    :placeholder="t('center.areaPh')"
                    :aria-label="t('center.areaPh')"
                />
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" />
                    {{ t(areaForm.id === null ? 'center.areaAdd' : 'common.save') }}
                </button>
                <button v-if="areaForm.id !== null" type="button" class="btn btn--outline" @click="resetArea">
                    {{ t('common.cancel') }}
                </button>
                <small v-if="areaErrors.name" class="field__error">{{ areaErrors.name[0] }}</small>
            </form>
            <ConfirmDialog
                v-if="deletingArea"
                :message="t('center.areaConfirmDelete', { name: deletingArea.name })"
                :confirm-label="t('common.delete')"
                @confirm="confirmDeleteArea"
                @cancel="deletingArea = null"
            />
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
                <button
                    v-for="cell in calendarCells.days"
                    :key="cell.key"
                    type="button"
                    class="calendar-grid__day"
                    :class="{
                        'calendar-grid__today': cell.key === todayKey,
                        'calendar-grid__day--image': cell.entry?.imageUrl,
                        'calendar-grid__day--note': cell.entry?.note,
                    }"
                    :style="cell.entry?.imageUrl ? { backgroundImage: `url(${cell.entry.imageUrl})` } : undefined"
                    :title="cell.entry?.note || t('center.calendarOpen')"
                    :aria-label="
                        t('habits.dayOpen', { day: formatDate(`${cell.key}T00:00`, { day: 'numeric', month: 'long' }) })
                    "
                    @click="openDay = cell.key"
                >
                    <span>{{ cell.n }}</span><i v-if="cell.due">{{ cell.due }}</i>
                </button>
            </div>
            <small class="hint">{{ t('center.calendarHint') }} {{ t('center.calendarOpen') }}</small>
            <DayEntryDialog
                v-if="openDay"
                :day="openDay"
                :entry="dayEntries.get(openDay) ?? null"
                @saved="(entry) => setDayEntry(openDay!, entry)"
                @close="openDay = null"
            />
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
