<script setup lang="ts">
import { Eye, Lock } from 'lucide-vue-next'
import { computed, reactive, ref } from 'vue'
import UserAvatar from '../UserAvatar.vue'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import type { Privacy } from '../../types/domain'

/** What other members see of this account, with a live preview of the honour-board row. */
const auth = useAuthStore()
const ui = useUiStore()
const user = computed(() => auth.user!)
const form = reactive<Privacy>({ ...auth.user!.privacy })
const saving = ref(false)
const toggles = ['showOnLeaderboard', 'showName', 'showAvatar', 'showTitle', 'showFrame'] as const

const preview = computed(() => ({
    name: form.showName ? user.value.displayName : t('privacy.anonymous'),
    avatar: form.showAvatar ? user.value.avatarUrl : null,
    title: form.showTitle ? user.value.title : null,
    frame: form.showFrame ? user.value.frame : 'none',
}))

/** Every switch saves straight away; a refused save puts it back. */
async function save(): Promise<void> {
    saving.value = true
    try {
        auth.setUser(await repository.savePrivacy({ ...form }))
        ui.success(t('privacy.saved'))
    } catch (error) {
        Object.assign(form, auth.user!.privacy)
        ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="stack">
        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('privacy.head') }}</h3>
                    <p>{{ t('privacy.sub') }}</p>
                </div>
                <Lock :size="18" class="gold" aria-hidden="true" />
            </div>
            <label
                v-for="key in toggles"
                :key="key"
                class="setting-row"
                :class="{ 'setting-row--off': key !== 'showOnLeaderboard' && !form.showOnLeaderboard }"
            >
                <span>
                    <b>{{ t(`privacy.${key}`) }}</b>
                    <small>{{ t(`privacy.${key}Hint`) }}</small>
                </span>
                <input v-model="form[key]" type="checkbox" class="switch" :disabled="saving" @change="save" />
            </label>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('privacy.previewHead') }}</h3>
                    <p>{{ t(form.showOnLeaderboard ? 'privacy.previewSub' : 'privacy.previewHidden') }}</p>
                </div>
                <Eye :size="18" class="gold" aria-hidden="true" />
            </div>
            <div v-if="form.showOnLeaderboard" class="leader-row privacy-preview">
                <span class="leader-row__rank">1</span>
                <UserAvatar :name="preview.name" :src="preview.avatar" :frame="preview.frame" size="sm" />
                <span class="leader-row__name">
                    <b>{{ preview.name }}</b>
                    <small v-if="preview.title">{{ preview.title }}</small>
                </span>
                <span class="leader-row__value">{{ t('leaders.unitPoints', { n: user.points }) }}</span>
            </div>
            <p class="hint">{{ t('privacy.adminNote') }}</p>
        </div>
    </div>
</template>
