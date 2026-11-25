<script setup lang="ts">
import { Award, Plus } from 'lucide-vue-next'
import { reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { t } from '../../i18n'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import { BADGE_METRICS, type Badge, type BadgeMetric } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'

const admin = useAdminStore()
const ui = useUiStore()

const draft = reactive({
    id: null as number | null,
    title: '',
    description: '',
    metric: 'tasks_completed' as BadgeMetric,
    threshold: 10,
    isActive: true,
})
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const deleting = ref<Badge | null>(null)

function reset(): void {
    Object.assign(draft, {
        id: null,
        title: '',
        description: '',
        metric: 'tasks_completed',
        threshold: 10,
        isActive: true,
    })
    errors.value = {}
}

function edit(badge: Badge): void {
    Object.assign(draft, {
        id: badge.id,
        title: badge.title,
        description: badge.description,
        metric: badge.metric,
        threshold: badge.threshold,
        isActive: badge.isActive,
    })
    errors.value = {}
}

async function save(): Promise<void> {
    if (!draft.title.trim() || saving.value) return
    saving.value = true
    errors.value = {}
    const { id, ...input } = draft
    try {
        await admin.saveBadge(id, {
            ...input,
            title: input.title.trim(),
            description: input.description.trim(),
            threshold: Math.round(Number(input.threshold)),
        })
        ui.success(t('admin.bSaved'))
        reset()
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}

async function toggle(badge: Badge): Promise<void> {
    try {
        await admin.saveBadge(badge.id, { isActive: !badge.isActive })
        ui.success(t('admin.bSaved'))
    } catch (error) {
        ui.failure(error)
    }
}

async function confirmDelete(): Promise<void> {
    const badge = deleting.value
    deleting.value = null
    if (!badge) return
    try {
        await admin.deleteBadge(badge.id)
        if (draft.id === badge.id) reset()
        ui.success(t('admin.bDeleted'))
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">MILESTONES / CUSTOM BADGES</span>
                <h3>{{ t('admin.badgesHead') }}</h3>
                <p>{{ t('admin.badgesSub') }}</p>
            </div>
            <Award :size="19" class="gold" aria-hidden="true" />
        </div>

        <form class="stack stack--tight" @submit.prevent="save">
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('admin.bTitle') }}</span>
                    <input
                        v-model="draft.title"
                        class="input"
                        required
                        maxlength="80"
                        :placeholder="t('admin.bTitlePh')"
                    />
                    <small v-if="errors.title" class="field__error">{{ errors.title[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.bDescription') }}</span>
                    <input
                        v-model="draft.description"
                        class="input"
                        maxlength="240"
                        :placeholder="t('admin.bDescriptionPh')"
                    />
                </label>
                <label class="field">
                    <span>{{ t('admin.bMetric') }}</span>
                    <select v-model="draft.metric" class="input">
                        <option v-for="metric in BADGE_METRICS" :key="metric" :value="metric">
                            {{ t(`admin.metric.${metric}`) }}
                        </option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('admin.bThreshold') }}</span>
                    <input
                        v-model.number="draft.threshold"
                        class="input"
                        type="number"
                        min="1"
                        max="1000000"
                        required
                    />
                    <small v-if="errors.threshold" class="field__error">{{ errors.threshold[0] }}</small>
                </label>
            </div>
            <div class="head-actions">
                <label class="check"><input v-model="draft.isActive" type="checkbox" /> {{ t('admin.bActive') }}</label>
                <button type="submit" class="btn" :disabled="saving">
                    <Plus :size="15" aria-hidden="true" />
                    {{ saving ? t('common.saving') : t(draft.id === null ? 'admin.bAdd' : 'admin.saveEdit') }}
                </button>
                <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="reset">
                    {{ t('admin.cancelEdit') }}
                </button>
            </div>
        </form>

        <div class="motivation-list">
            <article v-for="badge in admin.badges" :key="badge.id" class="motivation-item">
                <div>
                    <span class="count-pill" :class="{ 'count-pill--ok': badge.isActive }">
                        {{ t(`admin.metric.${badge.metric}`) }} ≥ {{ badge.threshold }} ·
                        {{ t(badge.isActive ? 'admin.published' : 'admin.draft') }}
                    </span>
                    <h4>{{ badge.title }}</h4>
                    <p v-if="badge.description">{{ badge.description }}</p>
                </div>
                <div class="row-actions">
                    <button type="button" class="pill" @click="edit(badge)">{{ t('common.edit') }}</button>
                    <button type="button" class="pill" @click="toggle(badge)">
                        {{ t(badge.isActive ? 'admin.hide' : 'admin.publish') }}
                    </button>
                    <button type="button" class="pill" @click="deleting = badge">{{ t('common.delete') }}</button>
                </div>
            </article>
            <EmptyState v-if="!admin.badges.length" :title="t('admin.bEmptyTitle')" :text="t('admin.bEmptyText')" />
        </div>

        <ConfirmDialog
            v-if="deleting"
            :message="t('admin.bConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
