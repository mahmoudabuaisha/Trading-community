<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { LOCALE_NAMES, t } from '../../i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import { LOCALES, SPECIALTIES } from '../../types/domain'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const user = auth.user!
const form = reactive({
    language: user.language,
    timezone: user.timezone,
    weeklyGoal: user.weeklyGoal,
    dailyFocusMinutes: user.dailyFocusMinutes,
    bio: user.bio,
    specialties: [...user.specialties],
})
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const timezones: string[] = (() => {
    try {
        return Intl.supportedValuesOf('timeZone')
    } catch {
        return [user.timezone]
    }
})()

async function save(): Promise<void> {
    saving.value = true
    errors.value = {}
    try {
        await workspace.patchProfile({ ...form })
        ui.success(t('profile.saved'))
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="stack">
        <form class="panel" @submit.prevent="save">
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('profile.language') }}</span>
                    <select v-model="form.language" class="input">
                        <option v-for="language in LOCALES" :key="language" :value="language">
                            {{ LOCALE_NAMES[language] }}
                        </option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('profile.timezone') }}</span>
                    <select v-model="form.timezone" class="input" dir="ltr">
                        <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                    </select>
                    <small v-if="errors.timezone" class="field__error">{{ errors.timezone[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.weeklyGoal') }}</span>
                    <input v-model.number="form.weeklyGoal" class="input" type="number" min="1" max="500" required />
                    <small v-if="errors.weeklyGoal" class="field__error">{{ errors.weeklyGoal[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.focusMinutes') }}</span>
                    <input
                        v-model.number="form.dailyFocusMinutes"
                        class="input"
                        type="number"
                        min="0"
                        max="1440"
                        required
                    />
                    <small v-if="errors.dailyFocusMinutes" class="field__error">{{
                        errors.dailyFocusMinutes[0]
                    }}</small>
                </label>
                <fieldset class="perm-list field--wide">
                    <legend>{{ t('specialty.legend') }}</legend>
                    <label v-for="key in SPECIALTIES" :key="key" class="check">
                        <input
                            v-model="form.specialties"
                            type="checkbox"
                            :value="key"
                            :disabled="form.specialties.length >= 4 && !form.specialties.includes(key)"
                        />
                        {{ t(`specialty.${key}`) }}
                    </label>
                    <small v-if="errors.specialties" class="field__error">{{ errors.specialties[0] }}</small>
                </fieldset>
                <label class="field field--wide">
                    <span>{{ t('profile.bio') }}</span>
                    <textarea
                        v-model="form.bio"
                        class="input"
                        rows="3"
                        maxlength="500"
                        :placeholder="t('profile.bioPh')"
                    />
                    <small class="hint">{{ t('profile.chars', { n: form.bio.length }) }}</small>
                </label>
            </div>
            <div class="actions">
                <button type="submit" class="btn" :disabled="saving">
                    {{ saving ? t('common.saving') : t('profile.saveBtn') }}
                </button>
            </div>
        </form>
    </div>
</template>
