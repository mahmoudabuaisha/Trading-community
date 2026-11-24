<script setup lang="ts">
import {
    BarChart3,
    Brain,
    Check,
    ChevronLeft,
    ChevronRight,
    ListChecks,
    NotebookPen,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-vue-next'
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import EmptyState from '../components/EmptyState.vue'
import StatCard from '../components/StatCard.vue'
import { ApiError } from '../data/http'
import { formatDate, isRtl, t } from '../i18n'
import { today } from '../lib/dates'
import { checkKey, dayStats, habitStats, monthDays, weeklyAverages } from '../lib/habits'
import { useHabitsStore } from '../stores/habits'
import { describeError, useUiStore } from '../stores/ui'
import type { Habit } from '../types/domain'

const store = useHabitsStore()
const ui = useUiStore()

const todayKey = today()
const days = computed(() => monthDays(store.anchor))
const isFuture = (key: string) => key > todayKey
/** Days that have happened — the only ones charts and percentages are based on. */
const elapsed = computed(() => days.value.filter((day) => !isFuture(day.key)))
const weeks = computed(() => {
    const groups: { index: number; span: number }[] = []
    for (const day of days.value) {
        if (groups[day.week]) groups[day.week].span++
        else groups[day.week] = { index: day.week, span: 1 }
    }
    return groups
})
const PrevIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))
const NextIcon = computed(() => (isRtl.value ? ChevronLeft : ChevronRight))

// ── Habit grid ───────────────────────────────────────────────────────────
const habitIds = computed(() => store.habits.map((habit) => habit.id))
const perDay = computed(() => dayStats(habitIds.value, days.value, store.checks))
const totalDone = computed(() => perDay.value.reduce((sum, day) => sum + day.done, 0))
const possible = computed(() => elapsed.value.length * store.habits.length)
const overallPct = computed(() => (possible.value ? Math.round((totalDone.value / possible.value) * 10000) / 100 : 0))
/** Done out of possible; the mark keeps the order left-to-right inside Arabic text. */
const fraction = computed(() => `\u200E${totalDone.value} / ${possible.value}`)
const bestDay = computed(() => {
    const best = days.value.map((day, index) => ({ day, ...perDay.value[index] })).sort((a, b) => b.done - a.done)[0]
    return best && best.done > 0 ? formatDate(best.day.date, { day: 'numeric', month: 'short' }) : '—'
})
const analysis = computed(() =>
    store.habits.map((habit) => ({ habit, ...habitStats(habit.id, habit.targetDays, days.value, store.checks) })),
)

async function attempt(action: () => Promise<void>): Promise<boolean> {
    try {
        await action()
        return true
    } catch (error) {
        ui.failure(error)
        return false
    }
}

const toggle = (habit: Habit, day: string) => attempt(() => store.toggle(habit.id, day))

// ── Add / edit / delete ──────────────────────────────────────────────────
const draft = reactive({ name: '', targetDays: '' as number | '' })
const adding = ref(false)
const addError = ref('')

async function addHabit(): Promise<void> {
    if (!draft.name.trim() || adding.value) return
    adding.value = true
    addError.value = ''
    try {
        await store.addHabit(draft.name.trim(), draft.targetDays === '' ? null : Number(draft.targetDays))
        draft.name = ''
        draft.targetDays = ''
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) addError.value = describeError(error)
        else ui.failure(error)
    } finally {
        adding.value = false
    }
}

const editing = ref<Habit | null>(null)
const editForm = reactive({ name: '', targetDays: '' as number | '' })
const editDialog = ref<HTMLDialogElement | null>(null)
const deleting = ref<Habit | null>(null)

async function openEdit(habit: Habit): Promise<void> {
    editing.value = habit
    editForm.name = habit.name
    editForm.targetDays = habit.targetDays ?? ''
    await nextTick()
    editDialog.value?.showModal()
}

async function saveEdit(): Promise<void> {
    const habit = editing.value
    if (!habit || !editForm.name.trim()) return
    const targetDays = editForm.targetDays === '' ? null : Number(editForm.targetDays)
    if (await attempt(() => store.updateHabit(habit.id, { name: editForm.name.trim(), targetDays })))
        editing.value = null
}

async function confirmDelete(): Promise<void> {
    const habit = deleting.value
    deleting.value = null
    if (habit && (await attempt(() => store.deleteHabit(habit.id))))
        ui.success(t('habits.deleted', { name: habit.name }))
}

// ── Trend chart (daily % of habits done) ─────────────────────────────────
const CHART = { width: 620, height: 170, top: 12, bottom: 150, left: 36, right: 10 }
const trend = computed(() => {
    const count = days.value.length
    // Day 1 sits on the reading side: left in English, right in Arabic — the same way the grid runs.
    const x = (index: number) => {
        const share = index / Math.max(1, count - 1)
        return CHART.left + (isRtl.value ? 1 - share : share) * (CHART.width - CHART.left - CHART.right)
    }
    const y = (pct: number) => CHART.bottom - (pct / 100) * (CHART.bottom - CHART.top)
    const points = elapsed.value.map((_, index) => `${x(index).toFixed(1)},${y(perDay.value[index].pct).toFixed(1)}`)
    const last = Math.max(0, elapsed.value.length - 1)
    return {
        line: points.join(' '),
        area: points.length
            ? `M${x(0).toFixed(1)},${CHART.bottom} L${points.join(' L')} L${x(last).toFixed(1)},${CHART.bottom} Z`
            : '',
        ticks: days.value
            .filter((day) => day.number === 1 || day.number % 5 === 0)
            .map((day) => ({ x: x(day.number - 1), label: day.number })),
        x,
        y,
    }
})

// ── Mental state ─────────────────────────────────────────────────────────
type Field = 'mood' | 'motivation'
const fields: Field[] = ['mood', 'motivation']
const SCALE = Array.from({ length: 10 }, (_, index) => index + 1)

const ratingOf = (day: string, field: Field) => store.moods.get(day)?.[field] ?? null

function rate(day: string, field: Field, event: Event): void {
    const raw = (event.target as HTMLSelectElement).value
    void attempt(() => store.rate(day, field, raw === '' ? null : Number(raw)))
}

/** A day's combined score as a percentage, from whichever ratings exist. */
function dayScore(day: string): number | null {
    const values = fields.map((field) => ratingOf(day, field)).filter((value): value is number => value !== null)
    return values.length ? Math.round((values.reduce((sum, value) => sum + value, 0) / values.length) * 10) : null
}

const moodLines = computed(() =>
    fields.map((field) => ({
        field,
        points: elapsed.value
            .map((day, index) => ({ index, value: ratingOf(day.key, field) }))
            .filter((point): point is { index: number; value: number } => point.value !== null)
            .map((point) => `${trend.value.x(point.index).toFixed(1)},${trend.value.y(point.value * 10).toFixed(1)}`)
            .join(' '),
    })),
)
const weekly = computed(() =>
    fields.map((field) => ({
        field,
        weeks: weeklyAverages(days.value, new Map(days.value.map((day) => [day.key, ratingOf(day.key, field)]))),
    })),
)

// ── Notes ────────────────────────────────────────────────────────────────
const noteDraft = ref('')
const savingNote = ref(false)
watch(
    () => store.note,
    (note) => (noteDraft.value = note),
    { immediate: true },
)

async function saveNote(): Promise<void> {
    savingNote.value = true
    if (await attempt(() => store.saveNote(noteDraft.value))) ui.success(t('habits.notesSaved'))
    savingNote.value = false
}

onMounted(() => void store.load())
</script>

<template>
    <section class="stack habits">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN TASKS / HABIT TRACKER</span>
                <h2>{{ t('habits.head') }}</h2>
                <p>{{ t('habits.sub') }}</p>
            </div>
            <div class="date-control">
                <button
                    type="button"
                    class="icon-btn"
                    :aria-label="t('habits.prevMonth')"
                    @click="store.shiftMonth(-1)"
                >
                    <component :is="PrevIcon" :size="16" />
                </button>
                <button
                    type="button"
                    class="chip-btn habits__month"
                    :title="t('habits.thisMonth')"
                    @click="store.goToToday()"
                >
                    {{ formatDate(store.anchor, { month: 'long', year: 'numeric' }) }}
                </button>
                <button type="button" class="icon-btn" :aria-label="t('habits.nextMonth')" @click="store.shiftMonth(1)">
                    <component :is="NextIcon" :size="16" />
                </button>
            </div>
        </div>

        <div class="stat-grid">
            <StatCard
                :icon="ListChecks"
                :label="t('habits.count')"
                :value="store.habits.length"
                :note="t('habits.myHabits')"
            />
            <StatCard :icon="Check" :label="t('habits.completed')" :value="totalDone" :note="fraction" />
            <div class="stat-card">
                <span class="stat-card__icon"><BarChart3 :size="18" aria-hidden="true" /></span>
                <small>{{ t('habits.progress') }}</small>
                <strong>{{ overallPct }}%</strong>
                <div class="track"><i :style="{ inlineSize: `${overallPct}%` }" /></div>
            </div>
            <StatCard :icon="BarChart3" :label="t('habits.best')" :value="bestDay" :note="t('habits.trend')" />
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('habits.myHabits') }}</h3>
                </div>
                <form class="inline-form habits__add" @submit.prevent="addHabit">
                    <input
                        v-model="draft.name"
                        class="input"
                        required
                        maxlength="80"
                        :placeholder="t('habits.addPh')"
                        :aria-label="t('habits.name')"
                    />
                    <input
                        v-model.number="draft.targetDays"
                        class="input input--narrow"
                        type="number"
                        min="1"
                        max="31"
                        :placeholder="t('habits.goalPh')"
                        :aria-label="t('habits.goalLabel')"
                    />
                    <button type="submit" class="btn" :disabled="adding">
                        <Plus :size="16" aria-hidden="true" /> {{ t('habits.add') }}
                    </button>
                </form>
            </div>
            <p v-if="addError" class="field__error" role="alert">{{ addError }}</p>

            <div v-if="store.habits.length" class="grid-scroll" tabindex="0">
                <table class="habit-grid">
                    <thead>
                        <tr>
                            <th class="habit-grid__corner" rowspan="3" scope="col">{{ t('habits.myHabits') }}</th>
                            <th
                                v-for="week in weeks"
                                :key="week.index"
                                :colspan="week.span"
                                scope="colgroup"
                                class="habit-grid__week"
                                :class="`week-${week.index}`"
                            >
                                {{ t('habits.week', { n: week.index + 1 }) }}
                            </th>
                        </tr>
                        <tr>
                            <th
                                v-for="day in days"
                                :key="day.key"
                                scope="col"
                                class="habit-grid__dow"
                                :class="`week-${day.week}`"
                            >
                                {{ formatDate(day.date, { weekday: 'narrow' }) }}
                            </th>
                        </tr>
                        <tr>
                            <th
                                v-for="day in days"
                                :key="day.key"
                                scope="col"
                                class="habit-grid__num"
                                :class="[`week-${day.week}`, { 'is-today': day.key === todayKey }]"
                            >
                                {{ day.number }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="habit in store.habits" :key="habit.id">
                            <th scope="row" class="habit-grid__name">
                                <span>{{ habit.name }}</span>
                                <span class="habit-grid__tools">
                                    <button
                                        type="button"
                                        :aria-label="`${t('common.edit')}: ${habit.name}`"
                                        @click="openEdit(habit)"
                                    >
                                        <Pencil :size="13" />
                                    </button>
                                    <button
                                        type="button"
                                        :aria-label="`${t('common.delete')}: ${habit.name}`"
                                        @click="deleting = habit"
                                    >
                                        <Trash2 :size="13" />
                                    </button>
                                </span>
                            </th>
                            <td
                                v-for="day in days"
                                :key="day.key"
                                :class="[`week-${day.week}`, { 'is-today': day.key === todayKey }]"
                            >
                                <button
                                    type="button"
                                    class="habit-box"
                                    :class="{ 'habit-box--on': store.checks.has(checkKey(habit.id, day.key)) }"
                                    role="checkbox"
                                    :aria-checked="store.checks.has(checkKey(habit.id, day.key))"
                                    :aria-label="
                                        t('habits.mark', {
                                            habit: habit.name,
                                            day: formatDate(day.date, { day: 'numeric', month: 'long' }),
                                        })
                                    "
                                    :disabled="isFuture(day.key)"
                                    @click="toggle(habit, day.key)"
                                >
                                    <Check
                                        v-if="store.checks.has(checkKey(habit.id, day.key))"
                                        :size="12"
                                        aria-hidden="true"
                                    />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ t('habits.rowProgress') }}</th>
                            <td v-for="(day, index) in days" :key="day.key" class="habit-grid__stat">
                                {{ isFuture(day.key) ? '' : `${perDay[index].pct}%` }}
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">{{ t('habits.rowDone') }}</th>
                            <td v-for="(day, index) in days" :key="day.key" class="habit-grid__stat">
                                {{ isFuture(day.key) ? '' : perDay[index].done }}
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">{{ t('habits.rowNotDone') }}</th>
                            <td v-for="(day, index) in days" :key="day.key" class="habit-grid__stat">
                                {{ isFuture(day.key) ? '' : perDay[index].notDone }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <EmptyState v-else-if="!store.loading" :title="t('habits.emptyTitle')" :text="t('habits.emptyText')" />
        </div>

        <div v-if="store.habits.length" class="two-col">
            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('habits.trend') }}</h3>
                        <p>{{ t('habits.trendSub') }}</p>
                    </div>
                </div>
                <svg
                    class="line-chart"
                    :viewBox="`0 0 ${CHART.width} ${CHART.height}`"
                    role="img"
                    :aria-label="t('habits.trend')"
                >
                    <g v-for="level in [0, 25, 50, 75, 100]" :key="level">
                        <line
                            :x1="CHART.left"
                            :x2="CHART.width - CHART.right"
                            :y1="trend.y(level)"
                            :y2="trend.y(level)"
                            class="line-chart__grid"
                        />
                        <text :x="CHART.left - 6" :y="trend.y(level) + 3" text-anchor="end" class="line-chart__label">
                            {{ level }}%
                        </text>
                    </g>
                    <text
                        v-for="tick in trend.ticks"
                        :key="tick.label"
                        :x="tick.x"
                        :y="CHART.height - 4"
                        text-anchor="middle"
                        class="line-chart__label"
                    >
                        {{ tick.label }}
                    </text>
                    <path :d="trend.area" class="line-chart__area" />
                    <polyline :points="trend.line" class="line-chart__line" />
                </svg>
            </div>

            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('habits.analysis') }}</h3>
                        <p>{{ t('habits.analysisSub') }}</p>
                    </div>
                    <BarChart3 :size="18" class="gold" aria-hidden="true" />
                </div>
                <ul class="analysis">
                    <li v-for="row in analysis" :key="row.habit.id">
                        <span class="analysis__name">{{ row.habit.name }}</span>
                        <span class="analysis__bar" :class="{ 'analysis__bar--met': row.goalPct >= 100 }"
                            ><i :style="{ inlineSize: `${row.goalPct}%` }"
                        /></span>
                        <b>{{ row.pct.toFixed(2) }}%</b>
                        <small>{{ t('habits.ofGoal', { done: row.done, goal: row.goal }) }}</small>
                    </li>
                </ul>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('habits.mental') }}</h3>
                    <p>{{ t('habits.mentalSub') }}</p>
                </div>
                <Brain :size="18" class="gold" aria-hidden="true" />
            </div>
            <div class="grid-scroll" tabindex="0">
                <table class="habit-grid habit-grid--mood">
                    <thead>
                        <tr>
                            <th class="habit-grid__corner" rowspan="2" scope="col">{{ t('habits.mental') }}</th>
                            <th
                                v-for="week in weeks"
                                :key="week.index"
                                :colspan="week.span"
                                scope="colgroup"
                                class="habit-grid__week"
                                :class="`week-${week.index}`"
                            >
                                {{ t('habits.week', { n: week.index + 1 }) }}
                            </th>
                        </tr>
                        <tr>
                            <th
                                v-for="day in days"
                                :key="day.key"
                                scope="col"
                                class="habit-grid__num"
                                :class="[`week-${day.week}`, { 'is-today': day.key === todayKey }]"
                            >
                                {{ day.number }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="field in fields" :key="field">
                            <th scope="row" class="habit-grid__name">{{ t(`habits.${field}`) }}</th>
                            <td v-for="day in days" :key="day.key" :class="`week-${day.week}`">
                                <select
                                    class="mood-pick"
                                    :value="ratingOf(day.key, field) ?? ''"
                                    :disabled="isFuture(day.key)"
                                    :aria-label="
                                        t('habits.rate', {
                                            what: t(`habits.${field}`),
                                            day: formatDate(day.date, { day: 'numeric', month: 'long' }),
                                        })
                                    "
                                    @change="rate(day.key, field, $event)"
                                >
                                    <option value="">·</option>
                                    <option v-for="value in SCALE" :key="value" :value="value">{{ value }}</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ t('habits.score') }}</th>
                            <td v-for="day in days" :key="day.key" class="habit-grid__stat">
                                {{ dayScore(day.key) === null ? '' : `${dayScore(day.key)}%` }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="two-col">
                <div>
                    <svg
                        class="line-chart"
                        :viewBox="`0 0 ${CHART.width} ${CHART.height}`"
                        role="img"
                        :aria-label="t('habits.mental')"
                    >
                        <g v-for="level in [0, 25, 50, 75, 100]" :key="level">
                            <line
                                :x1="CHART.left"
                                :x2="CHART.width - CHART.right"
                                :y1="trend.y(level)"
                                :y2="trend.y(level)"
                                class="line-chart__grid"
                            />
                            <text
                                :x="CHART.left - 6"
                                :y="trend.y(level) + 3"
                                text-anchor="end"
                                class="line-chart__label"
                            >
                                {{ level }}%
                            </text>
                        </g>
                        <polyline
                            v-for="line in moodLines"
                            :key="line.field"
                            :points="line.points"
                            class="line-chart__line"
                            :class="`line-chart__line--${line.field}`"
                        />
                    </svg>
                    <div class="legend">
                        <span class="legend__mood">{{ t('habits.mood') }}</span>
                        <span class="legend__motivation">{{ t('habits.motivation') }}</span>
                    </div>
                </div>
                <div class="mindset">
                    <b class="eyebrow">{{ t('habits.mindset') }}</b>
                    <div v-for="row in weekly" :key="row.field" class="mindset__group">
                        <strong>{{ t(`habits.${row.field}`) }}</strong>
                        <div v-for="(value, index) in row.weeks" :key="index" class="mindset__row">
                            <span>{{ t('habits.week', { n: index + 1 }) }}</span>
                            <span class="analysis__bar" :class="`analysis__bar--${row.field}`"
                                ><i :style="{ inlineSize: `${value ?? 0}%` }"
                            /></span>
                            <b>{{ value === null ? t('habits.notRated') : `${value.toFixed(2)}%` }}</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form class="panel" @submit.prevent="saveNote">
            <div class="section-head">
                <div>
                    <h3>{{ t('habits.notes') }}</h3>
                </div>
                <NotebookPen :size="18" class="gold" aria-hidden="true" />
            </div>
            <textarea
                v-model="noteDraft"
                class="input"
                rows="4"
                maxlength="5000"
                :placeholder="t('habits.notesPh')"
                :aria-label="t('habits.notes')"
            />
            <div class="actions">
                <button type="submit" class="btn" :disabled="savingNote || noteDraft === store.note">
                    {{ savingNote ? t('common.saving') : t('habits.saveNotes') }}
                </button>
            </div>
        </form>

        <ConfirmDialog
            v-if="deleting"
            :message="`${t('common.delete')}: ${deleting.name}?`"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />

        <dialog
            v-if="editing"
            ref="editDialog"
            class="dialog"
            @cancel.prevent="editing = null"
            @click.self="editing = null"
        >
            <form class="dialog__body" @submit.prevent="saveEdit">
                <div class="dialog__head">
                    <h2>{{ t('habits.editHabit') }}</h2>
                    <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="editing = null">
                        <X :size="18" />
                    </button>
                </div>
                <label class="field">
                    <span>{{ t('habits.name') }}</span>
                    <input v-model="editForm.name" class="input" required maxlength="80" />
                </label>
                <label class="field">
                    <span>{{ t('habits.goalLabel') }}</span>
                    <input v-model.number="editForm.targetDays" class="input" type="number" min="1" max="31" />
                </label>
                <div class="actions">
                    <button type="button" class="btn btn--outline" @click="editing = null">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="submit" class="btn">{{ t('common.save') }}</button>
                </div>
            </form>
        </dialog>
    </section>
</template>
