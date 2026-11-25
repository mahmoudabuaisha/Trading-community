<script setup lang="ts">
import { BellPlus } from 'lucide-vue-next'
import { reactive, ref } from 'vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import type { Preferences } from '../types/domain'

/** Which notifications this account receives. Saved on the account, so they follow the member across devices. */
const auth = useAuthStore()
const ui = useUiStore()

const form = reactive<Preferences>({ ...auth.user!.preferences })
const saving = ref(false)
const toggles = [
    { key: 'notifyReminders', label: 'reminders' },
    { key: 'notifyMorning', label: 'morning' },
    { key: 'notifyInactivity', label: 'inactivity' },
    { key: 'notifySleep', label: 'sleep' },
] as const

/** Every change saves straight away; a refused save puts the switch back. */
async function save(): Promise<void> {
    saving.value = true
    try {
        auth.setUser(
            await repository.savePreferences({
                ...form,
                sleepTime: form.sleepTime || null,
                wakeTime: form.wakeTime || null,
            }),
        )
        ui.success(t('prefs.saved'))
    } catch (error) {
        Object.assign(form, auth.user!.preferences)
        ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <h3>{{ t('prefs.head') }}</h3>
                <p>{{ t('prefs.sub') }}</p>
            </div>
            <BellPlus :size="18" class="gold" aria-hidden="true" />
        </div>

        <label v-for="item in toggles" :key="item.key" class="setting-row">
            <span>
                <b>{{ t(`prefs.${item.label}`) }}</b>
                <small>{{ t(`prefs.${item.label}Hint`) }}</small>
            </span>
            <input v-model="form[item.key]" type="checkbox" class="switch" :disabled="saving" @change="save" />
        </label>

        <div v-if="form.notifySleep" class="form-grid form-grid--two">
            <label class="field">
                <span>{{ t('prefs.sleepTime') }}</span>
                <input v-model="form.sleepTime" class="input" type="time" dir="ltr" @change="save" />
            </label>
            <label class="field">
                <span>{{ t('prefs.wakeTime') }}</span>
                <input v-model="form.wakeTime" class="input" type="time" dir="ltr" @change="save" />
            </label>
        </div>
        <p class="hint">{{ t('prefs.openNote') }}</p>
    </div>
</template>
