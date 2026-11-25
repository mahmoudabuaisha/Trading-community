<script setup lang="ts">
import { ArrowLeft, ArrowRight, BookOpen, Crown, GraduationCap, Lock } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import EmptyState from '../components/EmptyState.vue'
import { isRtl, t } from '../i18n'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'

const workspace = useWorkspaceStore()
const ui = useUiStore()
const loading = ref(!workspace.lessons.length)
const Arrow = computed(() => (isRtl.value ? ArrowLeft : ArrowRight))

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
        <div v-else-if="workspace.lessons.length" class="lesson-grid">
            <RouterLink
                v-for="(lesson, index) in workspace.lessons"
                :key="lesson.id"
                :to="{ name: 'lesson', params: { id: lesson.id } }"
                class="lesson-card"
                :class="{ 'lesson-card--locked': lesson.locked }"
            >
                <div class="lesson-card__media">
                    <img v-if="lesson.imageUrl" :src="lesson.imageUrl" alt="" loading="lazy" />
                    <component :is="lesson.locked ? Lock : BookOpen" v-else :size="40" aria-hidden="true" />
                    <span class="lesson-card__no">{{ String(index + 1).padStart(2, '0') }}</span>
                    <span v-if="lesson.isPremium" class="lesson-card__premium"
                        ><Crown :size="13" aria-hidden="true" /> {{ t('education.premium') }}</span
                    >
                </div>
                <div class="lesson-card__body">
                    <h3>{{ lesson.title }}</h3>
                    <p>{{ lesson.locked ? t('education.lockedCard') : lesson.body }}</p>
                    <span class="lesson-card__more">
                        {{ t('education.open') }} <component :is="Arrow" :size="16" aria-hidden="true" />
                    </span>
                </div>
            </RouterLink>
        </div>
        <EmptyState v-else :title="t('education.emptyTitle')" :text="t('education.emptyText')" />
    </section>
</template>
