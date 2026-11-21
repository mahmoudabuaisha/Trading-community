<script setup lang="ts">
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import TaskList from '../components/TaskList.vue'
import TaskModal from '../components/TaskModal.vue'
import { formatDate, isRtl, t } from '../i18n'
import { parseDay, percent, periodRange, shiftPeriod, today, ymd } from '../lib/dates'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { PeriodType, Task } from '../types/domain'

const route = useRoute()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const period = computed(() => route.params.period as PeriodType)
const anchorDay = ref(today())
const filter = ref<'all' | 'open' | 'done'>('all')
const modal = ref<{ task: Task | null } | null>(null)

const range = computed(() => periodRange(period.value, parseDay(anchorDay.value)))
/** Tasks of this period type whose period overlaps the selected range (inclusive days). */
const periodTasks = computed(() =>
    workspace.tasks.filter(
        (task) =>
            task.periodType === period.value &&
            task.periodStart <= range.value.end &&
            task.periodEnd >= range.value.start,
    ),
)
const done = computed(() => periodTasks.value.filter((task) => task.status === 'completed').length)
const pct = computed(() => percent(done.value, periodTasks.value.length))
const visibleTasks = computed(() =>
    periodTasks.value.filter((task) =>
        filter.value === 'all'
            ? true
            : filter.value === 'done'
              ? task.status === 'completed'
              : task.status !== 'completed',
    ),
)
const heading = computed(() => t(`tasks.head${period.value[0].toUpperCase()}${period.value.slice(1)}`))
const longDate = computed(() =>
    formatDate(parseDay(anchorDay.value), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
)
const PrevIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))
const NextIcon = computed(() => (isRtl.value ? ChevronLeft : ChevronRight))

function shift(step: number): void {
    anchorDay.value = ymd(shiftPeriod(period.value, parseDay(anchorDay.value), step))
}

async function toggle(task: Task): Promise<void> {
    try {
        await workspace.toggleTask(task)
    } catch (error) {
        ui.failure(error)
    }
}

async function remove(task: Task): Promise<void> {
    try {
        const undo = await workspace.deleteTask(task)
        ui.success(t('tasks.deleted', { title: task.title }), () => void undo().catch(ui.failure))
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <section class="stack">
        <div class="period-header">
            <div>
                <span class="eyebrow">PLAN / {{ period.toUpperCase() }}</span>
                <h2>{{ heading }}</h2>
                <p>
                    {{ longDate }} <span class="gold">•</span> <bdi>{{ range.start }} — {{ range.end }}</bdi>
                </p>
            </div>
            <div class="date-control">
                <button type="button" class="icon-btn" :aria-label="t('common.previous')" @click="shift(-1)">
                    <component :is="PrevIcon" :size="16" />
                </button>
                <input v-model="anchorDay" class="input" type="date" required :aria-label="t('tasks.fDue')" />
                <button type="button" class="icon-btn" :aria-label="t('common.next')" @click="shift(1)">
                    <component :is="NextIcon" :size="16" />
                </button>
            </div>
        </div>

        <div class="progress-panel">
            <div>
                <span>{{ t('tasks.progress') }}</span>
                <strong>{{ pct }}%</strong>
            </div>
            <div class="track track--big"><i :style="{ inlineSize: `${pct}%` }" /></div>
            <small>{{ t('tasks.doneOf', { done, total: periodTasks.length }) }}</small>
        </div>

        <div class="section-head">
            <div>
                <h3>{{ t('tasks.list') }}</h3>
                <p>{{ t('tasks.listSub') }}</p>
            </div>
            <div class="head-actions">
                <div class="segmented" role="group">
                    <button
                        v-for="option in ['all', 'open', 'done'] as const"
                        :key="option"
                        type="button"
                        :class="{ segmented__active: filter === option }"
                        :aria-pressed="filter === option"
                        @click="filter = option"
                    >
                        {{ t(`tasks.filter${option[0].toUpperCase()}${option.slice(1)}`) }}
                    </button>
                </div>
                <button type="button" class="btn" @click="modal = { task: null }">
                    <Plus :size="16" aria-hidden="true" /> {{ t('tasks.add') }}
                </button>
            </div>
        </div>

        <TaskList
            :tasks="visibleTasks"
            :categories="workspace.categories"
            @toggle="toggle"
            @edit="modal = { task: $event }"
            @remove="remove"
        />

        <TaskModal
            v-if="modal"
            :task="modal.task"
            :default-period="period"
            :anchor-day="anchorDay"
            @close="modal = null"
        />
    </section>
</template>
