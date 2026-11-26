<script setup lang="ts">
import { Gift, Plus, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import type { DailyTipItem } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import TranslateButton from './TranslateButton.vue'

/** The lines members draw as their tip of the day. Many can be pasted at once, one per line. */
const ui = useUiStore()
const tips = ref<DailyTipItem[]>([])
const paste = ref('')
const search = ref('')
const editingId = ref<number | null>(null)
const editText = ref('')
const deleting = ref<DailyTipItem | null>(null)

const lines = computed(() =>
    paste.value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean),
)
const shown = computed(() => {
    const query = search.value.trim()
    return query ? tips.value.filter((tip) => tip.body.includes(query)) : tips.value
})
const activeCount = computed(() => tips.value.filter((tip) => tip.isActive).length)

async function run(action: () => Promise<DailyTipItem[] | void>, successKey?: string): Promise<boolean> {
    try {
        tips.value = (await action()) ?? (await repository.adminDailyTips())
        if (successKey) ui.success(t(successKey))
        return true
    } catch (error) {
        ui.failure(error)
        return false
    }
}
onMounted(() => run(() => repository.adminDailyTips()))

async function add(): Promise<void> {
    if (lines.value.length && (await run(() => repository.adminAddDailyTips(lines.value), 'admin.dtAdded')))
        paste.value = ''
}
function edit(tip: DailyTipItem): void {
    editingId.value = tip.id
    editText.value = tip.body
}
async function saveEdit(tip: DailyTipItem): Promise<void> {
    const body = editText.value.trim()
    if (body && (await run(() => repository.adminSaveDailyTip(tip.id, { body }), 'admin.dtSaved')))
        editingId.value = null
}
const toggle = (tip: DailyTipItem) => run(() => repository.adminSaveDailyTip(tip.id, { isActive: !tip.isActive }))
async function confirmDelete(): Promise<void> {
    const tip = deleting.value
    deleting.value = null
    if (tip) await run(() => repository.adminDeleteDailyTip(tip.id))
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">TIP OF THE DAY</span>
                <h3>{{ t('admin.dtHead') }}</h3>
                <p>{{ t('admin.dtSub') }}</p>
            </div>
            <span class="count-pill count-pill--gold"
                ><Gift :size="12" aria-hidden="true" /> {{ t('admin.dtCount', { n: activeCount }) }}</span
            >
        </div>

        <form class="stack stack--tight" @submit.prevent="add">
            <textarea
                v-model="paste"
                class="input"
                rows="4"
                maxlength="60000"
                :placeholder="t('admin.dtPh')"
                :aria-label="t('admin.dtPh')"
            />
            <div class="head-actions">
                <button type="submit" class="btn" :disabled="!lines.length">
                    <Plus :size="15" aria-hidden="true" />
                    {{ lines.length > 1 ? t('admin.dtAddMany', { n: lines.length }) : t('admin.dtAdd') }}
                </button>
                <small class="hint">{{ t('admin.dtHint') }}</small>
            </div>
        </form>

        <input
            v-if="tips.length > 8"
            v-model="search"
            class="input"
            type="search"
            :placeholder="t('admin.dtSearch')"
            :aria-label="t('admin.dtSearch')"
        />
        <ul class="device-list daily-tips-list">
            <li v-for="tip in shown" :key="tip.id" class="device-row" :class="{ 'device-row--muted': !tip.isActive }">
                <form v-if="editingId === tip.id" class="broadcast-now" @submit.prevent="saveEdit(tip)">
                    <input v-model="editText" class="input" maxlength="300" :aria-label="t('admin.dtEdit')" />
                    <button type="submit" class="btn">{{ t('common.save') }}</button>
                    <button type="button" class="btn btn--outline" @click="editingId = null">
                        {{ t('common.cancel') }}
                    </button>
                </form>
                <template v-else>
                    <span
                        ><b>{{ tip.body }}</b></span
                    >
                    <button type="button" class="pill" @click="edit(tip)">{{ t('common.edit') }}</button>
                    <TranslateButton type="daily-tip" :id="tip.id" />
                    <button type="button" class="pill" @click="toggle(tip)">
                        {{ t(tip.isActive ? 'admin.hide' : 'admin.publish') }}
                    </button>
                    <button
                        type="button"
                        class="icon-btn icon-btn--sm"
                        :aria-label="t('common.delete')"
                        @click="deleting = tip"
                    >
                        <Trash2 :size="14" />
                    </button>
                </template>
            </li>
        </ul>
        <p v-if="!tips.length" class="hint">{{ t('admin.dtEmpty') }}</p>

        <ConfirmDialog
            v-if="deleting"
            :message="t('admin.dtConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
