<script setup lang="ts">
import { CalendarCheck, CircleCheckBig, Crown, Flame, Star, Trophy, Zap } from 'lucide-vue-next'
import { computed, onMounted, ref, type Component } from 'vue'
import UserAvatar from '../components/UserAvatar.vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { today } from '../lib/dates'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import type { Leaderboard, LeaderboardRow } from '../types/domain'

const auth = useAuthStore()
const ui = useUiStore()

/** Each table always shows ten places, so an open spot invites members to claim it. */
const SIZE = 10
const board = ref<Leaderboard | null>(null)
type TableId = Exclude<keyof Leaderboard, 'today'>
const tables: { id: TableId; icon: Component; unit: string }[] = [
    { id: 'points', icon: Star, unit: 'unitPoints' },
    { id: 'week', icon: Flame, unit: 'unitDays' },
    { id: 'weekPoints', icon: Zap, unit: 'unitPoints' },
    { id: 'month', icon: CalendarCheck, unit: 'unitPoints' },
]
const featured = computed(() => board.value?.today[0] ?? null)
const MEDALS = ['🥇', '🥈', '🥉']

function slots(rows: LeaderboardRow[]): (LeaderboardRow | null)[] {
    return Array.from({ length: SIZE }, (_, index) => rows[index] ?? null)
}
const nameOf = (row: LeaderboardRow): string => row.displayName ?? t('privacy.anonymous')

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

        <template v-if="board">
            <article class="dash-card leader-star" :class="{ 'leader-star--me': featured?.userId === auth.user?.id }">
                <div class="leader-star__crown"><Crown :size="26" aria-hidden="true" /></div>
                <template v-if="featured">
                    <UserAvatar :name="nameOf(featured)" :src="featured.avatarUrl" :frame="featured.frame" size="lg" />
                    <div class="leader-star__text">
                        <span class="eyebrow">{{ t('leaders.featured') }}</span>
                        <h3>{{ nameOf(featured) }}</h3>
                        <small v-if="featured.title">{{ featured.title }}</small>
                        <p>
                            <CircleCheckBig :size="16" aria-hidden="true" />
                            {{ t('leaders.unitTasks', { n: featured.value }) }}
                            <span v-if="featured.allDone" class="count-pill count-pill--ok">{{
                                t('leaders.allDone')
                            }}</span>
                        </p>
                    </div>
                </template>
                <div v-else class="leader-star__text">
                    <span class="eyebrow">{{ t('leaders.featured') }}</span>
                    <p>{{ t('leaders.featuredEmpty') }}</p>
                </div>
            </article>

            <div class="leader-grid">
                <article v-for="table in tables" :key="table.id" class="dash-card leader-card">
                    <header class="leader-card__head">
                        <component :is="table.icon" :size="22" aria-hidden="true" />
                        <div>
                            <h3>{{ t(`leaders.${table.id}`) }}</h3>
                            <p>{{ t(`leaders.${table.id}Sub`) }}</p>
                        </div>
                    </header>
                    <table class="leader-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ t('leaders.rank') }}</th>
                                <th scope="col">{{ t('leaders.member') }}</th>
                                <th scope="col">{{ t('leaders.score') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in slots(board[table.id])"
                                :key="index"
                                :class="{
                                    'leader-table__row--podium': row && index < 3,
                                    'leader-table__row--me': row?.userId === auth.user?.id,
                                    'leader-table__row--open': !row,
                                }"
                            >
                                <td class="leader-table__rank">
                                    <span v-if="row && index < 3" :aria-label="String(index + 1)">{{
                                        MEDALS[index]
                                    }}</span>
                                    <template v-else>{{ index + 1 }}</template>
                                </td>
                                <td>
                                    <span v-if="row" class="leader-table__who">
                                        <UserAvatar
                                            :name="nameOf(row)"
                                            :src="row.avatarUrl"
                                            :frame="row.frame"
                                            size="sm"
                                        />
                                        <span class="leader-row__name">
                                            <b>{{ nameOf(row) }}</b>
                                            <small v-if="row.title">{{ row.title }}</small>
                                        </span>
                                    </span>
                                    <span v-else class="hint">{{ t('leaders.open') }}</span>
                                </td>
                                <td class="leader-table__score">
                                    {{ row ? t(`leaders.${table.unit}`, { n: row.value }) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </article>
            </div>
        </template>
    </section>
</template>
