<script setup lang="ts">
import { ArrowLeft, ArrowRight, Expand, X } from 'lucide-vue-next'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import EmptyState from '../components/EmptyState.vue'
import { formatDate, isRtl, t } from '../i18n'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'

const route = useRoute()
const workspace = useWorkspaceStore()
const ui = useUiStore()
const loading = ref(!workspace.lessons.length)

const index = computed(() => workspace.lessons.findIndex((lesson) => lesson.id === Number(route.params.id)))
const lesson = computed(() => workspace.lessons[index.value] ?? null)
const previous = computed(() => workspace.lessons[index.value - 1] ?? null)
const next = computed(() => workspace.lessons[index.value + 1] ?? null)
/** Minutes to read at roughly 180 words a minute, never less than one. */
const readMinutes = computed(() => Math.max(1, Math.round((lesson.value?.body.trim().split(/\s+/).length ?? 0) / 180)))
const BackArrow = computed(() => (isRtl.value ? ArrowRight : ArrowLeft))
const ForwardArrow = computed(() => (isRtl.value ? ArrowLeft : ArrowRight))

// Full-size image viewer
const viewer = ref<HTMLDialogElement | null>(null)
const viewing = ref(false)
async function openViewer(): Promise<void> {
    viewing.value = true
    await nextTick()
    viewer.value?.showModal()
}

watch(lesson, (current) => {
    if (current) document.title = `${current.title} · SN Tasks`
})

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
        <RouterLink :to="{ name: 'education' }" class="inline-link">
            <component :is="BackArrow" :size="16" aria-hidden="true" /> {{ t('education.back') }}
        </RouterLink>

        <div v-if="loading" class="loading-panel"><div class="loader" aria-hidden="true" /></div>

        <article v-else-if="lesson" class="panel lesson-page">
            <header class="lesson-page__head">
                <span class="eyebrow">{{
                    t('education.lessonNo', { n: index + 1, total: workspace.lessons.length })
                }}</span>
                <h2>{{ lesson.title }}</h2>
                <p class="muted">
                    {{ formatDate(lesson.createdAt, { dateStyle: 'long' }) }} ·
                    {{ t('education.readTime', { n: readMinutes }) }}
                </p>
            </header>

            <button
                v-if="lesson.imageUrl"
                type="button"
                class="lesson-page__image"
                :aria-label="t('education.enlarge')"
                @click="openViewer"
            >
                <img :src="lesson.imageUrl" :alt="lesson.title" />
                <span class="lesson-page__zoom"
                    ><Expand :size="16" aria-hidden="true" /> {{ t('education.enlarge') }}</span
                >
            </button>

            <div class="lesson-page__text">{{ lesson.body }}</div>

            <nav class="lesson-page__nav" :aria-label="t('education.head')">
                <RouterLink
                    v-if="previous"
                    :to="{ name: 'lesson', params: { id: previous.id } }"
                    class="lesson-page__step"
                >
                    <component :is="BackArrow" :size="18" aria-hidden="true" />
                    <span
                        ><small>{{ t('education.previous') }}</small
                        ><b>{{ previous.title }}</b></span
                    >
                </RouterLink>
                <span v-else />
                <RouterLink
                    v-if="next"
                    :to="{ name: 'lesson', params: { id: next.id } }"
                    class="lesson-page__step lesson-page__step--next"
                >
                    <span
                        ><small>{{ t('education.next') }}</small
                        ><b>{{ next.title }}</b></span
                    >
                    <component :is="ForwardArrow" :size="18" aria-hidden="true" />
                </RouterLink>
            </nav>
        </article>

        <EmptyState v-else :title="t('education.missingTitle')" :text="t('education.missingText')" />

        <dialog
            v-if="viewing && lesson?.imageUrl"
            ref="viewer"
            class="image-viewer"
            @close="viewing = false"
            @click.self="viewer?.close()"
        >
            <button
                type="button"
                class="icon-btn image-viewer__close"
                :aria-label="t('common.close')"
                @click="viewer?.close()"
            >
                <X :size="18" />
            </button>
            <img :src="lesson.imageUrl" :alt="lesson.title" />
        </dialog>
    </section>
</template>
