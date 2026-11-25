<script setup lang="ts">
import {
    Award,
    BriefcaseBusiness,
    Flag,
    GitBranch,
    NotebookPen,
    Plus,
    ShoppingBag,
    Sparkles,
    Star,
    Target,
    Trash2,
} from 'lucide-vue-next'
import { computed, onMounted, reactive, ref, type Component } from 'vue'
import EmptyState from '../../components/EmptyState.vue'
import JourneyHead from '../../components/JourneyHead.vue'
import { repository } from '../../data/repository'
import { formatDate, messages, t } from '../../i18n'
import { today } from '../../lib/dates'
import { useUiStore } from '../../stores/ui'
import type { Timeline, TimelineEvent, TimelineKind } from '../../types/domain'

const ui = useUiStore()
const timeline = ref<Timeline>({ events: [], stages: [] })
const loaded = ref(false)
const icons: Record<TimelineKind, Component> = {
    joined: Sparkles,
    achievement: Award,
    badge: Star,
    goal: Target,
    skill: GitBranch,
    project: BriefcaseBusiness,
    purchase: ShoppingBag,
    custom: Flag,
}

async function load(): Promise<void> {
    try {
        timeline.value = await repository.timeline()
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
}
onMounted(load)

/** Stage filter: a month (YYYY-MM) to look back at, or everything. */
const month = ref('')
const events = computed(() =>
    timeline.value.events.filter((event) => !month.value || event.day.startsWith(month.value)),
)
const monthLabel = (value: string) => formatDate(`${value}-01T00:00`, { month: 'long', year: 'numeric' })

function title(event: TimelineEvent): string {
    if (event.kind === 'achievement' && event.ref) {
        const keys = messages.value.achievements.keys as Record<string, { title: string }>
        return t('journey.timeline.kinds.achievement', { title: keys[event.ref]?.title ?? event.ref })
    }
    return t(`journey.timeline.kinds.${event.kind}`, { title: event.title ?? '' })
}

// Notes: one open editor at a time.
const editing = ref<string | null>(null)
const draft = ref('')
function openNote(event: TimelineEvent): void {
    editing.value = event.key
    draft.value = event.note
}
async function saveNote(event: TimelineEvent): Promise<void> {
    try {
        if (event.entryId) await repository.updateMilestone(event.entryId, { note: draft.value.trim() })
        else await repository.saveTimelineNote(event.key, draft.value.trim())
        editing.value = null
        await load()
        ui.success(t('journey.timeline.noteSaved'))
    } catch (error) {
        ui.failure(error)
    }
}

const form = reactive({ title: '', day: today(), note: '' })
async function add(): Promise<void> {
    if (!form.title.trim()) return
    try {
        await repository.addMilestone({ title: form.title.trim(), day: form.day, note: form.note.trim() })
        Object.assign(form, { title: '', day: today(), note: '' })
        await load()
        ui.success(t('journey.timeline.added'))
    } catch (error) {
        ui.failure(error)
    }
}
async function remove(event: TimelineEvent): Promise<void> {
    if (!event.entryId) return
    try {
        await repository.deleteMilestone(event.entryId)
        await load()
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <section class="stack">
        <JourneyHead
            eyebrow="GROWTH TIMELINE"
            :title="t('journey.tiles.timeline.title')"
            :text="t('journey.timeline.sub')"
        />

        <div v-if="timeline.stages.length" class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('journey.timeline.stagesHead') }}</h3>
                    <p>{{ t('journey.timeline.stagesSub') }}</p>
                </div>
            </div>
            <div class="stage-row">
                <button
                    type="button"
                    class="stage-card"
                    :class="{ 'stage-card--on': !month }"
                    :aria-pressed="!month"
                    @click="month = ''"
                >
                    <b>{{ t('journey.timeline.all') }}</b>
                    <small>{{ t('journey.timeline.events', { n: timeline.events.length }) }}</small>
                </button>
                <button
                    v-for="stage in timeline.stages"
                    :key="stage.month"
                    type="button"
                    class="stage-card"
                    :class="{ 'stage-card--on': month === stage.month }"
                    :aria-pressed="month === stage.month"
                    @click="month = stage.month"
                >
                    <b>{{ monthLabel(stage.month) }}</b>
                    <small>{{ t('journey.timeline.stage', { tasks: stage.tasks, points: stage.points }) }}</small>
                </button>
            </div>
        </div>

        <form class="panel stack stack--tight" @submit.prevent="add">
            <h3>{{ t('journey.timeline.addHead') }}</h3>
            <div class="form-grid form-grid--two">
                <input
                    v-model="form.title"
                    class="input"
                    required
                    maxlength="180"
                    :placeholder="t('journey.timeline.titlePh')"
                    :aria-label="t('journey.timeline.titlePh')"
                />
                <input
                    v-model="form.day"
                    class="input"
                    type="date"
                    required
                    :max="today()"
                    :aria-label="t('journey.timeline.day')"
                />
                <textarea
                    v-model="form.note"
                    class="input field--wide"
                    rows="2"
                    maxlength="1000"
                    :placeholder="t('journey.timeline.notePh')"
                    :aria-label="t('journey.timeline.notePh')"
                />
            </div>
            <div class="actions">
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" /> {{ t('journey.timeline.add') }}
                </button>
            </div>
        </form>

        <ol v-if="events.length" class="timeline">
            <li v-for="event in events" :key="event.key" class="timeline__item">
                <span class="timeline__dot"><component :is="icons[event.kind]" :size="16" aria-hidden="true" /></span>
                <div class="panel timeline__card">
                    <time :datetime="event.day">{{ formatDate(`${event.day}T00:00`, { dateStyle: 'long' }) }}</time>
                    <h3>{{ title(event) }}</h3>
                    <template v-if="editing === event.key">
                        <textarea
                            v-model="draft"
                            class="input"
                            rows="3"
                            maxlength="1000"
                            :placeholder="t('journey.timeline.notePh')"
                            :aria-label="t('journey.timeline.notePh')"
                        />
                        <div class="actions">
                            <button type="button" class="btn btn--outline" @click="editing = null">
                                {{ t('common.cancel') }}
                            </button>
                            <button type="button" class="btn" @click="saveNote(event)">{{ t('common.save') }}</button>
                        </div>
                    </template>
                    <template v-else>
                        <p v-if="event.note" class="timeline__note">{{ event.note }}</p>
                        <div class="row-actions">
                            <button type="button" class="pill" @click="openNote(event)">
                                <NotebookPen :size="13" aria-hidden="true" />
                                {{ t(event.note ? 'journey.timeline.editNote' : 'journey.timeline.addNote') }}
                            </button>
                            <button v-if="event.entryId" type="button" class="pill" @click="remove(event)">
                                <Trash2 :size="13" aria-hidden="true" /> {{ t('common.delete') }}
                            </button>
                        </div>
                    </template>
                </div>
            </li>
        </ol>
        <EmptyState
            v-else-if="loaded"
            :title="t('journey.timeline.emptyTitle')"
            :text="t('journey.timeline.emptyText')"
        />
    </section>
</template>
