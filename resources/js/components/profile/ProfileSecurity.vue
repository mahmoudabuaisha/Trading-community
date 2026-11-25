<script setup lang="ts">
import { KeyRound, MonitorSmartphone } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { formatDate, t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import type { DeviceSession } from '../../types/domain'

const ui = useUiStore()

const form = reactive({ current: '', next: '' })
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const endingSessions = ref(false)
const devices = ref<DeviceSession[]>([])

async function loadDevices(): Promise<void> {
    try {
        devices.value = await repository.sessions()
    } catch (error) {
        ui.failure(error)
    }
}
onMounted(loadDevices)

async function changePassword(): Promise<void> {
    saving.value = true
    errors.value = {}
    try {
        await repository.changePassword(form.current, form.next)
        form.current = ''
        form.next = ''
        ui.success(t('security.passwordChanged'))
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}

async function signOutOthers(): Promise<void> {
    endingSessions.value = true
    try {
        await repository.signOutOthers()
        await loadDevices()
        ui.success(t('security.othersSignedOut'))
    } catch (error) {
        ui.failure(error)
    } finally {
        endingSessions.value = false
    }
}
</script>

<template>
    <div class="stack">
        <form class="panel" @submit.prevent="changePassword">
            <div class="section-head">
                <div>
                    <h3>{{ t('security.passwordHead') }}</h3>
                    <p>{{ t('security.passwordSub') }}</p>
                </div>
                <KeyRound :size="18" class="gold" aria-hidden="true" />
            </div>
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('security.current') }}</span>
                    <input
                        v-model="form.current"
                        class="input"
                        type="password"
                        autocomplete="current-password"
                        required
                        dir="ltr"
                    />
                    <small v-if="errors.currentPassword" class="field__error">{{ errors.currentPassword[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('security.new') }}</span>
                    <input
                        v-model="form.next"
                        class="input"
                        type="password"
                        autocomplete="new-password"
                        required
                        minlength="8"
                        dir="ltr"
                    />
                    <small v-if="errors.password" class="field__error">{{ errors.password[0] }}</small>
                    <small v-else class="hint">{{ t('security.newHint') }}</small>
                </label>
            </div>
            <div class="actions">
                <button type="submit" class="btn" :disabled="saving">
                    {{ saving ? t('common.saving') : t('security.change') }}
                </button>
            </div>
        </form>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('security.devicesHead') }}</h3>
                    <p>{{ t('security.devicesSub') }}</p>
                </div>
                <MonitorSmartphone :size="18" class="gold" aria-hidden="true" />
            </div>
            <ul class="device-list">
                <li v-for="(device, index) in devices" :key="index" class="device-row">
                    <MonitorSmartphone :size="20" aria-hidden="true" />
                    <span>
                        <b>{{ device.device || t('security.unknownDevice') }}</b>
                        <small>
                            <bdi v-if="device.ip">{{ device.ip }}</bdi>
                            {{
                                t('security.lastActive', {
                                    when: formatDate(device.lastActiveAt, { dateStyle: 'medium', timeStyle: 'short' }),
                                })
                            }}
                        </small>
                    </span>
                    <span v-if="device.current" class="count-pill count-pill--ok">{{ t('security.thisDevice') }}</span>
                </li>
            </ul>
            <div v-if="devices.length > 1" class="actions">
                <button type="button" class="btn btn--outline" :disabled="endingSessions" @click="signOutOthers">
                    {{ t('security.signOutOthers') }}
                </button>
            </div>
            <p class="hint">{{ t('security.privacyNote') }}</p>
        </div>
    </div>
</template>
