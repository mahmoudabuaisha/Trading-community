<script setup lang="ts">
import { BellRing, ChevronLeft, ChevronRight, Send } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { repository } from '../../data/repository'
import { formatDate, isRtl, t } from '../../i18n'
import { addDays, periodRange, today } from '../../lib/dates'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import type { AdminBroadcasts } from '../../types/domain'
import TranslateButton from './TranslateButton.vue'

/**
 * Messages to every member: one planned per day of the week shown, and a
 * box to send one right now. Members receive them as notifications.
 */
const admin = useAdminStore()
const ui = useUiStore()

const weekStart = ref(periodRange('weekly', new Date()).start)
const days = computed(() => Array.from({ length: 7 }, (_, index) => addDays(weekStart.value, index)))
const drafts = reactive<Record<string, string>>({})
const saved = ref<Record<string, string>>({})
const ids = ref<Record<string, number>>({})
const instant = ref<AdminBroadcasts['instant']>([])
const now = ref('')
const busy = ref(false)

const PrevIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))
const NextIcon = computed(() => (isRtl.value ? ChevronLeft : ChevronRight))
const dirty = computed(() => days.value.filter((day) => (drafts[day] ?? '').trim() !== (saved.value[day] ?? '')))

async function load(): Promise<void> {
    try {
        const result = await repository.adminBroadcasts(weekStart.value)
        saved.value = result.scheduled
        ids.value = result.scheduledIds
        instant.value = result.instant
        for (const day of days.value) drafts[day] = result.scheduled[day] ?? ''
    } catch (error) {
        ui.failure(error)
    }
}
onMounted(load)

function shift(weeks: number): void {
    weekStart.value = addDays(weekStart.value, weeks * 7)
    void load()
}

async function saveWeek(): Promise<void> {
    if (busy.value) return
    busy.value = true
    try {
        for (const day of dirty.value) await repository.adminScheduleBroadcast(day, (drafts[day] ?? '').trim())
        await Promise.all([load(), admin.loadOverview()])
        ui.success(t('admin.bcSaved'))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}

async function sendNow(): Promise<void> {
    const body = now.value.trim()
    if (!body || busy.value) return
    busy.value = true
    try {
        await repository.adminSendBroadcast(body, today())
        now.value = ''
        await Promise.all([load(), admin.loadOverview()])
        ui.success(t('admin.bcSent'))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}

async function remove(id: number): Promise<void> {
    try {
        await repository.adminDeleteBroadcast(id)
        await load()
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">NOTIFICATIONS TO ALL MEMBERS</span>
                <h3>{{ t('admin.bcHead') }}</h3>
                <p>{{ t('admin.bcSub') }}</p>
            </div>
            <BellRing :size="19" class="gold" aria-hidden="true" />
        </div>

        <form class="broadcast-now" @submit.prevent="sendNow">
            <input
                v-model="now"
                class="input"
                maxlength="500"
                :placeholder="t('admin.bcNowPh')"
                :aria-label="t('admin.bcNow')"
            />
            <button type="submit" class="btn" :disabled="busy || !now.trim()">
                <Send :size="15" aria-hidden="true" /> {{ t('admin.bcNow') }}
            </button>
        </form>
        <ul v-if="instant.length" class="device-list">
            <li v-for="item in instant" :key="item.id" class="device-row">
                <Send :size="16" aria-hidden="true" />
                <span>
                    <b>{{ item.body }}</b>
                    <small>{{ formatDate(item.createdAt, { dateStyle: 'medium', timeStyle: 'short' }) }}</small>
                </span>
                <TranslateButton type="broadcast" :id="item.id" />
                <button type="button" class="pill" @click="remove(item.id)">{{ t('common.delete') }}</button>
            </li>
        </ul>

        <div class="section-head">
            <div>
                <h3>{{ t('admin.bcWeek') }}</h3>
                <p>{{ t('admin.bcWeekSub') }}</p>
            </div>
            <div class="head-actions">
                <button type="button" class="icon-btn" :aria-label="t('common.previous')" @click="shift(-1)">
                    <component :is="PrevIcon" :size="18" />
                </button>
                <span class="count-pill"
                    >{{ formatDate(`${days[0]}T00:00`, { day: 'numeric', month: 'short' }) }} –
                    {{ formatDate(`${days[6]}T00:00`, { day: 'numeric', month: 'short' }) }}</span
                >
                <button type="button" class="icon-btn" :aria-label="t('common.next')" @click="shift(1)">
                    <component :is="NextIcon" :size="18" />
                </button>
            </div>
        </div>
        <div class="broadcast-week">
            <label v-for="day in days" :key="day" class="field" :class="{ 'broadcast-week__today': day === today() }">
                <span>{{ formatDate(`${day}T00:00`, { weekday: 'long', day: 'numeric', month: 'short' }) }}</span>
                <textarea
                    v-model="drafts[day]"
                    class="input"
                    rows="2"
                    maxlength="500"
                    :placeholder="t('admin.bcDayPh')"
                />
                <TranslateButton
                    v-if="ids[day] && (drafts[day] ?? '').trim() === saved[day]"
                    type="broadcast"
                    :id="ids[day]"
                />
            </label>
        </div>
        <div class="actions">
            <button type="button" class="btn" :disabled="busy || !dirty.length" @click="saveWeek">
                {{ busy ? t('common.saving') : t('admin.bcSaveWeek') }}
            </button>
        </div>
    </div>
</template>
