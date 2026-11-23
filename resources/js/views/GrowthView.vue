<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'
import EmptyState from '../components/EmptyState.vue'
import { formatDate, messages, t } from '../i18n'
import { useWorkspaceStore } from '../stores/workspace'

const workspace = useWorkspaceStore()
</script>

<template>
    <section class="stack">
        <div class="growth-hero">
            <span class="eyebrow">SN TASKS / PERSONAL GROWTH</span>
            <div class="growth-hero__icon"><Sparkles :size="26" aria-hidden="true" /></div>
            <h2>{{ t('growth.head') }}</h2>
            <p>{{ t('growth.sub') }}</p>
            <span class="growth-hero__foot">READ • REFLECT • GROW</span>
        </div>

        <div v-if="workspace.motivations.length" class="growth-library">
            <article
                v-for="item in workspace.motivations"
                :key="item.id"
                class="growth-reading"
                :class="`growth-reading--${item.category}`"
            >
                <span class="count-pill">{{ messages.growth.cat[item.category] }}</span>
                <h3>{{ item.title }}</h3>
                <p>{{ item.body }}</p>
                <small>{{ formatDate(item.createdAt, { dateStyle: 'medium' }) }}</small>
            </article>
        </div>
        <EmptyState v-else :title="t('growth.emptyTitle')" :text="t('growth.emptyText')" />
    </section>
</template>
