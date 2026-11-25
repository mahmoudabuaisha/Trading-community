<script setup lang="ts">
import { Check, ShoppingBag, Star, TrendingUp, Trophy } from 'lucide-vue-next'
import type { ChartConfiguration } from 'chart.js'
import { computed, onMounted, ref } from 'vue'
import ChartCanvas from '../ChartCanvas.vue'
import StoreArt from '../StoreArt.vue'
import { repository } from '../../data/repository'
import { formatDate, t } from '../../i18n'
import { conditionsText } from '../../lib/badges'
import { today } from '../../lib/dates'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import type { PointsHistory, StoreItem } from '../../types/domain'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const user = computed(() => auth.user!)
const history = ref<PointsHistory['history']>([])
const owned = ref<StoreItem[]>([])
const busy = ref(false)

onMounted(async () => {
    try {
        const [points, store] = await Promise.all([repository.pointsHistory(today()), repository.store()])
        history.value = points.history
        owned.value = store.items.filter((item) => item.owned && item.kind !== 'product')
        auth.setUser(store.user)
    } catch (error) {
        ui.failure(error)
    }
})

const monthTotal = computed(() => history.value.reduce((sum, entry) => sum + entry.points, 0))
const chart = computed<ChartConfiguration>(() => {
    const styles = getComputedStyle(document.documentElement)
    return {
        type: 'bar',
        data: {
            labels: history.value.map((entry) => formatDate(`${entry.day}T00:00`, { day: 'numeric', month: 'short' })),
            datasets: [
                {
                    label: t('rewards.points'),
                    data: history.value.map((entry) => entry.points),
                    backgroundColor: styles.getPropertyValue('--gold-500').trim(),
                    borderRadius: 4,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
            },
        },
    }
})

/** Achievements that pay points, with whether the member already holds each in its current period. */
const earning = computed(() =>
    workspace.badges
        .filter((badge) => badge.points > 0)
        .map((badge) => ({ ...badge, done: workspace.achievements.some((earned) => earned.key === badge.key) })),
)

async function toggleUse(item: StoreItem): Promise<void> {
    if (busy.value) return
    busy.value = true
    try {
        const takeOff = Boolean(item.equipped) && item.kind !== 'avatar'
        const result = await repository.equipItem(item.kind, takeOff ? null : item.id)
        owned.value = result.items.filter((entry) => entry.owned && entry.kind !== 'product')
        auth.setUser(result.user)
        ui.success(t(takeOff ? 'store.removed' : 'store.applied'))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <div class="stack">
        <div class="stat-grid">
            <div class="panel rewards-summary">
                <span class="rewards-summary__points"><Star :size="26" aria-hidden="true" /> {{ user.points }}</span>
                <div>
                    <h3>{{ t('rewards.balance') }}</h3>
                    <p>{{ t('rewards.balanceSub') }}</p>
                </div>
            </div>
            <div class="panel rewards-summary">
                <span class="rewards-summary__points"
                    ><Trophy :size="26" aria-hidden="true" /> {{ user.pointsEarned }}</span
                >
                <div>
                    <h3>{{ t('rewards.earned') }}</h3>
                    <p>{{ t('rewards.earnedSub') }}</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('rewards.chartHead') }}</h3>
                    <p>{{ t('rewards.chartSub', { n: monthTotal }) }}</p>
                </div>
                <TrendingUp :size="18" class="gold" aria-hidden="true" />
            </div>
            <div class="chart-box"><ChartCanvas :config="chart" :label="t('rewards.chartHead')" /></div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('rewards.howHead') }}</h3>
                    <p>{{ t('rewards.howSub') }}</p>
                </div>
            </div>
            <ul v-if="earning.length" class="device-list">
                <li v-for="badge in earning" :key="badge.id" class="device-row">
                    <component :is="badge.done ? Check : Star" :size="18" aria-hidden="true" />
                    <span>
                        <b>{{ badge.title }}</b>
                        <small>{{ t(`admin.period.${badge.period}`) }} · {{ conditionsText(badge) }}</small>
                    </span>
                    <span class="count-pill" :class="badge.done ? 'count-pill--ok' : 'count-pill--gold'">
                        {{ badge.done ? t('rewards.doneNow') : t('rewards.count', { n: badge.points }) }}
                    </span>
                </li>
            </ul>
            <p v-else class="hint">{{ t('rewards.howEmpty') }}</p>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('rewards.ownedHead') }}</h3>
                    <p>{{ t('rewards.ownedSub') }}</p>
                </div>
                <RouterLink :to="{ name: 'store' }" class="btn btn--outline"
                    ><ShoppingBag :size="15" aria-hidden="true" /> {{ t('nav.store') }}</RouterLink
                >
            </div>
            <div v-if="owned.length" class="reward-grid">
                <button
                    v-for="item in owned"
                    :key="item.id"
                    type="button"
                    class="reward-card"
                    :class="{ 'reward-card--on': item.equipped }"
                    :disabled="busy"
                    :aria-pressed="Boolean(item.equipped)"
                    @click="toggleUse(item)"
                >
                    <StoreArt :item="item" />
                    <b>{{ item.name }}</b>
                    <small v-if="item.equipped"
                        ><Check :size="13" aria-hidden="true" /> {{ t('rewards.equipped') }}</small
                    >
                    <small v-else>{{ t('rewards.equip') }}</small>
                </button>
            </div>
            <p v-else class="hint">{{ t('rewards.ownedEmpty') }}</p>
        </div>
    </div>
</template>
