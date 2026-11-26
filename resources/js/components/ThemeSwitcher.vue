<script setup lang="ts">
import { Check, Palette } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

/** Switch between the platform's own look and the themes the member bought. Shown once they own one. */
const auth = useAuthStore()
const ui = useUiStore()
const open = ref(false)
const busy = ref(false)
const root = ref<HTMLElement | null>(null)

async function choose(id: number | null): Promise<void> {
    if (busy.value) return
    busy.value = true
    try {
        auth.setUser((await repository.equipItem('theme', id)).user)
        open.value = false
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}

function onDocument(event: MouseEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('click', onDocument))
onBeforeUnmount(() => document.removeEventListener('click', onDocument))
</script>

<template>
    <div v-if="auth.user?.ownedThemes.length" ref="root" class="theme-switcher">
        <button
            type="button"
            class="chip-btn"
            :aria-label="t('themes.switch')"
            :title="t('themes.switch')"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Palette :size="17" aria-hidden="true" />
        </button>
        <div v-if="open" class="theme-switcher__menu" role="menu">
            <button
                type="button"
                role="menuitemradio"
                :aria-checked="!auth.user.theme"
                :disabled="busy"
                @click="choose(null)"
            >
                <span class="theme-switcher__dot theme-switcher__dot--base" />
                {{ t('themes.base') }}
                <Check v-if="!auth.user.theme" :size="15" aria-hidden="true" />
            </button>
            <button
                v-for="theme in auth.user.ownedThemes"
                :key="theme.id"
                type="button"
                role="menuitemradio"
                :aria-checked="auth.user.theme?.id === theme.id"
                :disabled="busy"
                @click="choose(theme.id)"
            >
                <span
                    class="theme-switcher__dot"
                    :style="{ background: theme.accent, borderColor: theme.background ?? undefined }"
                />
                {{ theme.name }}
                <Check v-if="auth.user.theme?.id === theme.id" :size="15" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
