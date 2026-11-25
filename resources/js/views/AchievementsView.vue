<script setup lang="ts">
import { Award, Lock } from 'lucide-vue-next'
import { computed } from 'vue'
import { formatDate, messages, t } from '../i18n'
import { conditionsText } from '../lib/badges'
import { useWorkspaceStore } from '../stores/workspace'

const workspace = useWorkspaceStore()

/** Every known badge, earned ones first, so members can see what is still ahead. */
/** Every badge there is — built-in ones, then those defined by the administrators — earned ones first. */
const badges = computed(() => {
    const builtIn = Object.entries(messages.value.achievements.keys).map(([key, copy]) => ({
        key,
        title: copy.title,
        text: copy.text,
        points: 0,
        period: 'once',
    }))
    const custom = workspace.badges.map((badge) => ({
        key: badge.key,
        title: badge.title,
        text: badge.description || conditionsText(badge),
        points: badge.points,
        period: badge.period,
    }))
    return [...builtIn, ...custom]
        .map((badge) => ({ ...badge, earned: workspace.achievements.find((earned) => earned.key === badge.key) }))
        .sort((x, y) => Number(Boolean(y.earned)) - Number(Boolean(x.earned)))
})
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">MILESTONES / YOUR PROGRESS</span>
                <h2>{{ t('achievements.head') }}</h2>
                <p>{{ workspace.achievements.length ? t('achievements.sub') : t('achievements.emptyText') }}</p>
            </div>
            <span class="count-pill">{{ workspace.achievements.length }} / {{ badges.length }}</span>
        </div>

        <div class="achievement-grid">
            <div
                v-for="badge in badges"
                :key="badge.key"
                class="achievement-card"
                :class="{ 'achievement-card--locked': !badge.earned }"
            >
                <div class="achievement-card__icon">
                    <component :is="badge.earned ? Award : Lock" :size="25" aria-hidden="true" />
                </div>
                <div>
                    <h3>{{ badge.title }}</h3>
                    <p>{{ badge.text }}</p>
                    <span v-if="badge.points" class="count-pill count-pill--gold">
                        {{ t('rewards.count', { n: badge.points }) }} · {{ t(`admin.period.${badge.period}`) }}
                    </span>
                    <small>{{
                        badge.earned
                            ? formatDate(badge.earned.earnedAt, { dateStyle: 'medium' })
                            : t('achievements.locked')
                    }}</small>
                </div>
            </div>
        </div>
    </section>
</template>
