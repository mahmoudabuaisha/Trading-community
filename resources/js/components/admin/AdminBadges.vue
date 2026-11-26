<script setup lang="ts">
import { Award, Plus, Star, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { t } from '../../i18n'
import { conditionsText } from '../../lib/badges'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import {
    BADGE_METRICS,
    BADGE_PERIODS,
    type Badge,
    type BadgeCondition,
    type BadgeMetric,
    type BadgePeriod,
} from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'
import TranslateButton from './TranslateButton.vue'

const admin = useAdminStore()
const ui = useUiStore()

const blank = () => ({
    id: null as number | null,
    title: '',
    description: '',
    period: 'daily' as BadgePeriod,
    points: 10,
    conditions: [{ metric: 'perfect_days', threshold: 1 }] as BadgeCondition[],
    isActive: true,
    rewardItemId: null as number | null,
})
const draft = reactive(blank())
/** Items an achievement can hand over (anything worn or shown; products are bought). */
const rewardItems = computed(() => admin.storeItems.filter((item) => item.kind !== 'product'))
onMounted(() => {
    if (!admin.storeItems.length) void admin.loadStoreItems().catch(() => undefined)
})
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const deleting = ref<Badge | null>(null)

/** A metric can appear once per achievement, so offer only the ones not used by the other conditions. */
const metricsFor = (index: number) =>
    BADGE_METRICS.filter(
        (metric) => !draft.conditions.some((condition, other) => other !== index && condition.metric === metric),
    )
const nextMetric = computed<BadgeMetric | undefined>(() =>
    BADGE_METRICS.find((metric) => !draft.conditions.some((condition) => condition.metric === metric)),
)

function reset(): void {
    Object.assign(draft, blank())
    errors.value = {}
}

function edit(badge: Badge): void {
    const { reward: _reward, ...rest } = badge
    void _reward
    Object.assign(draft, { ...rest, conditions: badge.conditions.map((condition) => ({ ...condition })) })
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
            points: Math.max(0, Math.round(Number(input.points))),
            conditions: input.conditions.map((condition) => ({
                ...condition,
                threshold: Math.round(Number(condition.threshold)),
            })),
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
                <span class="eyebrow">ACHIEVEMENTS &amp; POINTS</span>
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
                    <span>{{ t('admin.bPeriod') }}</span>
                    <select v-model="draft.period" class="input">
                        <option v-for="period in BADGE_PERIODS" :key="period" :value="period">
                            {{ t(`admin.period.${period}`) }}
                        </option>
                    </select>
                    <small class="hint">{{ t(`admin.periodHint.${draft.period}`) }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.bPoints') }}</span>
                    <input v-model.number="draft.points" class="input" type="number" min="0" max="100000" required />
                    <small v-if="errors.points" class="field__error">{{ errors.points[0] }}</small>
                </label>
            </div>

            <fieldset class="perm-list condition-list">
                <legend>{{ t('admin.bConditions') }}</legend>
                <div v-for="(condition, index) in draft.conditions" :key="index" class="condition-row">
                    <select v-model="condition.metric" class="input" :aria-label="t('admin.bMetric')">
                        <option v-for="metric in metricsFor(index)" :key="metric" :value="metric">
                            {{ t(`admin.metric.${metric}`) }}
                        </option>
                    </select>
                    <input
                        v-model.number="condition.threshold"
                        class="input"
                        type="number"
                        min="1"
                        max="1000000"
                        required
                        :aria-label="t('admin.bThreshold')"
                    />
                    <button
                        type="button"
                        class="icon-btn"
                        :disabled="draft.conditions.length === 1"
                        :aria-label="t('admin.bRemoveCondition')"
                        :title="t('admin.bRemoveCondition')"
                        @click="draft.conditions.splice(index, 1)"
                    >
                        <Trash2 :size="16" />
                    </button>
                </div>
                <button
                    v-if="nextMetric && draft.conditions.length < 5"
                    type="button"
                    class="pill"
                    @click="draft.conditions.push({ metric: nextMetric, threshold: 1 })"
                >
                    <Plus :size="14" aria-hidden="true" /> {{ t('admin.bAddCondition') }}
                </button>
                <small v-if="errors.conditions" class="field__error">{{ errors.conditions[0] }}</small>
            </fieldset>

            <label class="field">
                <span>{{ t('admin.bReward') }}</span>
                <select v-model="draft.rewardItemId" class="input">
                    <option :value="null">{{ t('admin.bRewardNone') }}</option>
                    <option v-for="item in rewardItems" :key="item.id" :value="item.id">
                        {{ t(`store.kind.${item.kind}`) }} · {{ item.name }}
                    </option>
                </select>
                <small class="hint">{{ t('admin.bRewardHint') }}</small>
            </label>

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
                        {{ t(`admin.period.${badge.period}`) }} ·
                        {{ t(badge.isActive ? 'admin.published' : 'admin.draft') }}
                    </span>
                    <span class="count-pill count-pill--gold"
                        ><Star :size="12" aria-hidden="true" /> {{ badge.points }}</span
                    >
                    <h4>{{ badge.title }}</h4>
                    <p>{{ conditionsText(badge) }}</p>
                    <p v-if="badge.reward" class="hint">
                        🎁 {{ t('admin.bRewardShown', { name: badge.reward.name }) }}
                    </p>
                    <p v-if="badge.description" class="muted">{{ badge.description }}</p>
                </div>
                <div class="row-actions">
                    <button type="button" class="pill" @click="edit(badge)">{{ t('common.edit') }}</button>
                    <TranslateButton type="badge" :id="badge.id" />
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
