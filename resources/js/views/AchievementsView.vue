<script setup lang="ts">
import { Award, Lock } from 'lucide-vue-next'
import { computed } from 'vue'
import { formatDate, messages, t } from '../i18n'
import { useWorkspaceStore } from '../stores/workspace'

const workspace = useWorkspaceStore()

/** Every known badge, earned ones first, so members can see what is still ahead. */
const badges = computed(() => {
    const catalogue = messages.value.achievements.keys
    return Object.entries(catalogue)
        .map(([key, copy]) => ({ key, ...copy, earned: workspace.achievements.find((badge) => badge.key === key) }))
        .sort((a, b) => Number(Boolean(b.earned)) - Number(Boolean(a.earned)))
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
