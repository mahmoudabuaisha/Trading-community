<script setup lang="ts">
import { ArrowDown, ArrowUp, CalendarDays, Clock, Flame, Lightbulb, Minus, Target } from 'lucide-vue-next'
import type { ChartConfiguration } from 'chart.js'
import { computed, onMounted, ref } from 'vue'
import ChartCanvas from '../../components/ChartCanvas.vue'
import EmptyState from '../../components/EmptyState.vue'
import JourneyHead from '../../components/JourneyHead.vue'
import StatCard from '../../components/StatCard.vue'
import { repository } from '../../data/repository'
import { formatDate, messages, t } from '../../i18n'
import { percent, today } from '../../lib/dates'
import { useUiStore } from '../../stores/ui'
import type { Insights, PeriodTotals } from '../../types/domain'

const ui = useUiStore()
const data = ref<Insights | null>(null)

onMounted(async () => {
    try {
        data.value = await repository.insights(today())
    } catch (error) {
        ui.failure(error)
    }
})

const weekdays = computed(() => messages.value.journey.weekdays)
const accent = () => getComputedStyle(document.documentElement).getPropertyValue('--gold-500').trim()
const bars = (labels: string[], values: number[]): ChartConfiguration => ({
    type: 'bar',
    data: { labels, datasets: [{ data: values, backgroundColor: accent(), borderRadius: 4 }] },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
    },
})
const weekdayChart = computed(() => bars([...weekdays.value], data.value?.byWeekday ?? []))
const hourChart = computed(() =>
    bars(
        Array.from({ length: 24 }, (_, hour) => String(hour).padStart(2, '0')),
        data.value?.byHour ?? [],
    ),
)
const weeksChart = computed(() =>
    bars(
        data.value?.weeks.map((week) => formatDate(`${week.start}T00:00`, { day: 'numeric', month: 'short' })) ?? [],
        data.value?.weeks.map((week) => week.completed) ?? [],
    ),
)

const comparisons = computed(() => {
    if (!data.value) return []
    const { current, previous } = data.value
    return (
        ['tasksCompleted', 'completionRate', 'activeDays', 'focusMinutes', 'habitChecks'] as (keyof PeriodTotals)[]
    ).map((key) => ({
        key,
        now: current[key],
        before: previous[key],
        suffix: key === 'completionRate' ? '%' : '',
    }))
})
const rates = computed(() => {
    if (!data.value) return []
    const priorities = Object.entries(data.value.byPriority).map(([key, row]) => ({
        label: t(`priority.${key}`),
        ...row,
    }))
    return [
        ...priorities,
        ...data.value.byCategory.map((row) => ({ label: row.name, total: row.total, done: row.done })),
    ]
})
const tipText = (tip: Insights['tips'][number]) =>
    t(`journey.tips.${tip.key}`, {
        ...tip.params,
        ...(typeof tip.params.weekday === 'number' ? { day: weekdays.value[tip.params.weekday] } : {}),
        ...(typeof tip.params.part === 'string' ? { part: t(`journey.parts.${tip.params.part}`) } : {}),
    })
</script>

<template>
    <section class="stack">
        <JourneyHead
            eyebrow="PERFORMANCE DNA"
            :title="t('journey.tiles.insights.title')"
            :text="t('journey.insights.sub', { n: data?.windowDays ?? 90 })"
        />

        <template v-if="data">
            <EmptyState
                v-if="!data.enoughData"
                :title="t('journey.insights.emptyTitle')"
                :text="t('journey.insights.emptyText')"
            />

            <div class="stat-grid">
                <StatCard
                    :icon="CalendarDays"
                    :label="t('journey.insights.bestDay')"
                    :value="data.bestWeekday === null ? '—' : weekdays[data.bestWeekday]"
                    :note="t('journey.insights.bestDayNote')"
                />
                <StatCard
                    :icon="Clock"
                    :label="t('journey.insights.bestPart')"
                    :value="data.bestPart ? t(`journey.parts.${data.bestPart}`) : '—'"
                    :note="t('journey.insights.bestPartNote')"
                />
                <StatCard
                    :icon="Target"
                    :label="t('journey.insights.onTime')"
                    :value="data.onTime.total ? `${percent(data.onTime.onTime, data.onTime.total)}%` : '—'"
                    :note="t('journey.insights.onTimeNote')"
                />
                <StatCard
                    :icon="Flame"
                    :label="t('journey.insights.streak')"
                    :value="data.streak"
                    :note="t('journey.insights.streakNote')"
                />
            </div>

            <div v-if="data.tips.length" class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('journey.insights.tipsHead') }}</h3>
                        <p>{{ t('journey.insights.tipsSub') }}</p>
                    </div>
                    <Lightbulb :size="18" class="gold" aria-hidden="true" />
                </div>
                <ul class="device-list">
                    <li v-for="tip in data.tips" :key="tip.key" class="device-row">
                        <Lightbulb :size="18" aria-hidden="true" />
                        <span>{{ tipText(tip) }}</span>
                    </li>
                </ul>
            </div>

            <div class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('journey.insights.compareHead') }}</h3>
                        <p>{{ t('journey.insights.compareSub') }}</p>
                    </div>
                </div>
                <div class="compare-grid">
                    <div v-for="row in comparisons" :key="row.key" class="compare-card">
                        <small>{{ t(`journey.insights.metrics.${row.key}`) }}</small>
                        <b>{{ row.now }}{{ row.suffix }}</b>
                        <span
                            :class="
                                row.now > row.before
                                    ? 'trend trend--up'
                                    : row.now < row.before
                                      ? 'trend trend--down'
                                      : 'trend'
                            "
                        >
                            <component
                                :is="row.now > row.before ? ArrowUp : row.now < row.before ? ArrowDown : Minus"
                                :size="14"
                                aria-hidden="true"
                            />
                            {{ t('journey.insights.before', { n: `${row.before}${row.suffix}` }) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="chart-pair">
                <div class="panel">
                    <h3>{{ t('journey.insights.weekdayHead') }}</h3>
                    <div class="chart-box">
                        <ChartCanvas :config="weekdayChart" :label="t('journey.insights.weekdayHead')" />
                    </div>
                </div>
                <div class="panel">
                    <h3>{{ t('journey.insights.hourHead') }}</h3>
                    <div class="chart-box">
                        <ChartCanvas :config="hourChart" :label="t('journey.insights.hourHead')" />
                    </div>
                </div>
            </div>
            <div class="panel">
                <h3>{{ t('journey.insights.weeksHead') }}</h3>
                <div class="chart-box">
                    <ChartCanvas :config="weeksChart" :label="t('journey.insights.weeksHead')" />
                </div>
            </div>

            <div v-if="rates.length" class="panel">
                <div class="section-head">
                    <div>
                        <h3>{{ t('journey.insights.ratesHead') }}</h3>
                        <p>{{ t('journey.insights.ratesSub') }}</p>
                    </div>
                </div>
                <div class="rate-list">
                    <div v-for="row in rates" :key="row.label" class="rate-row">
                        <span>{{ row.label }}</span>
                        <div class="goal-card__bar">
                            <i :style="{ inlineSize: `${percent(row.done, row.total)}%` }" />
                        </div>
                        <b>{{ percent(row.done, row.total) }}%</b>
                        <small>{{ row.done }} / {{ row.total }}</small>
                    </div>
                </div>
            </div>

            <div v-if="data.mood" class="panel">
                <h3>{{ t('journey.insights.moodHead') }}</h3>
                <p>{{ t('journey.insights.moodText', { high: data.mood.highDays, low: data.mood.lowDays }) }}</p>
            </div>
        </template>
    </section>
</template>
