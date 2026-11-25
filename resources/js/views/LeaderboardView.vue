<script setup lang="ts">
import { CalendarCheck, CircleCheckBig, Crown, Flame, Star, Trophy } from 'lucide-vue-next'
import { onMounted, ref, type Component } from 'vue'
import EmptyState from '../components/EmptyState.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { today } from '../lib/dates'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import type { Leaderboard } from '../types/domain'

const auth = useAuthStore()
const ui = useUiStore()

const board = ref<Leaderboard | null>(null)
const boards: { id: keyof Leaderboard; icon: Component; unit: string }[] = [
    { id: 'today', icon: CircleCheckBig, unit: 'unitTasks' },
    { id: 'week', icon: Flame, unit: 'unitDays' },
    { id: 'points', icon: Star, unit: 'unitPoints' },
    { id: 'month', icon: CalendarCheck, unit: 'unitPoints' },
]

onMounted(async () => {
    try {
        board.value = await repository.leaderboard(today())
    } catch (error) {
        ui.failure(error)
    }
})
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">HALL OF HONOUR</span>
                <h2>{{ t('leaders.head') }}</h2>
                <p>{{ t('leaders.sub') }}</p>
            </div>
            <Trophy :size="22" class="gold" aria-hidden="true" />
        </div>

        <div v-if="board" class="leader-grid">
            <article v-for="item in boards" :key="item.id" class="dash-card leader-card">
                <header class="leader-card__head">
                    <component :is="item.icon" :size="22" aria-hidden="true" />
                    <div>
                        <h3>{{ t(`leaders.${item.id}`) }}</h3>
                        <p>{{ t(`leaders.${item.id}Sub`) }}</p>
                    </div>
                </header>
                <ol v-if="board[item.id].length" class="leader-list">
                    <li
                        v-for="(row, index) in board[item.id]"
                        :key="row.userId"
                        class="leader-row"
                        :class="{ 'leader-row--top': index === 0, 'leader-row--me': row.userId === auth.user?.id }"
                    >
                        <span class="leader-row__rank">
                            <Crown v-if="index === 0" :size="18" :aria-label="t('leaders.first')" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <UserAvatar :name="row.displayName" :src="row.avatarUrl" :frame="row.frame" size="sm" />
                        <span class="leader-row__name">
                            <b>{{ row.displayName }}</b>
                            <small v-if="row.title">{{ row.title }}</small>
                        </span>
                        <span v-if="row.allDone" class="count-pill count-pill--ok">{{ t('leaders.allDone') }}</span>
                        <span class="leader-row__value">{{ t(`leaders.${item.unit}`, { n: row.value }) }}</span>
                    </li>
                </ol>
                <EmptyState v-else :title="t('leaders.emptyTitle')" :text="t(`leaders.${item.id}Empty`)" />
            </article>
        </div>
    </section>
</template>
