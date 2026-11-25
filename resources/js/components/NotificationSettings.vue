<script setup lang="ts">
import { BellRing } from 'lucide-vue-next'
import { ref } from 'vue'
import { t } from '../i18n'
import {
    browserNotificationsSupported,
    chime,
    enableBrowserNotifications,
    notifyBrowser,
    notifySound,
} from '../lib/notifyPrefs'

const supported = browserNotificationsSupported()
const blocked = ref(supported && Notification.permission === 'denied')

async function toggleBrowser(event: Event): Promise<void> {
    const wanted = (event.target as HTMLInputElement).checked
    if (!wanted) {
        notifyBrowser.value = false
        return
    }
    const granted = await enableBrowserNotifications()
    blocked.value = !granted
    ;(event.target as HTMLInputElement).checked = granted
}

function toggleSound(): void {
    // Play the chime when turning sound on, so the member hears what they chose.
    if (notifySound.value) chime()
}
</script>

<template>
    <div id="notifications" class="panel">
        <div class="section-head">
            <div>
                <h3>{{ t('notify.settingsHead') }}</h3>
                <p>{{ t('notify.settingsSub') }}</p>
            </div>
            <BellRing :size="18" class="gold" aria-hidden="true" />
        </div>

        <label class="setting-row">
            <span>
                <b>{{ t('notify.sound') }}</b>
                <small>{{ t('notify.soundHint') }}</small>
            </span>
            <input v-model="notifySound" type="checkbox" class="switch" @change="toggleSound" />
        </label>

        <label class="setting-row">
            <span>
                <b>{{ t('notify.browser') }}</b>
                <small>{{
                    t(
                        !supported
                            ? 'notify.browserNeedsHttps'
                            : blocked
                              ? 'notify.browserBlocked'
                              : 'notify.browserHint',
                    )
                }}</small>
            </span>
            <input
                type="checkbox"
                class="switch"
                :checked="notifyBrowser"
                :disabled="!supported"
                @change="toggleBrowser"
            />
        </label>

        <p class="hint">{{ t('notify.deviceNote') }}</p>
    </div>
</template>
