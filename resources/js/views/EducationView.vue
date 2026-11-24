<script setup lang="ts">
import { GraduationCap } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import EmptyState from '../components/EmptyState.vue'
import { t } from '../i18n'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'

const workspace = useWorkspaceStore()
const ui = useUiStore()
const loading = ref(true)

onMounted(async () => {
    try {
        await workspace.loadLessons()
    } catch (error) {
        ui.failure(error)
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <section class="stack">
        <div class="growth-hero">
            <span class="eyebrow">SN TASKS / ACADEMY</span>
            <div class="growth-hero__icon"><GraduationCap :size="28" aria-hidden="true" /></div>
            <h2>{{ t('education.head') }}</h2>
            <p>{{ t('education.sub') }}</p>
        </div>

        <div v-if="loading" class="loading-panel"><div class="loader" aria-hidden="true" /></div>
        <div v-else-if="workspace.lessons.length" class="lessons">
            <article v-for="(lesson, index) in workspace.lessons" :key="lesson.id" class="panel lesson">
                <span class="lesson__no">{{ String(index + 1).padStart(2, '0') }}</span>
                <h3>{{ lesson.title }}</h3>
                <a v-if="lesson.imageUrl" :href="lesson.imageUrl" target="_blank" rel="noopener" class="lesson__image">
                    <img :src="lesson.imageUrl" :alt="lesson.title" loading="lazy" />
                </a>
                <p>{{ lesson.body }}</p>
            </article>
        </div>
        <EmptyState v-else :title="t('education.emptyTitle')" :text="t('education.emptyText')" />
    </section>
</template>
