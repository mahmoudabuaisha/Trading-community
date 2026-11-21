<script setup lang="ts">
import { Check, X } from 'lucide-vue-next'
import { t } from './i18n'
import { useAuthStore } from './stores/auth'
import { useUiStore } from './stores/ui'

const auth = useAuthStore()
const ui = useUiStore()

function runUndo(id: number, undo: () => void): void {
    ui.dismiss(id)
    undo()
}
</script>

<template>
    <div v-if="!auth.ready" class="boot">
        <div class="loader" aria-hidden="true" />
        <p>{{ t('common.preparing') }}</p>
    </div>
    <RouterView v-else />

    <div class="toasts" aria-live="polite">
        <div v-for="toast in ui.toasts" :key="toast.id" class="toast" :class="`toast--${toast.kind}`" role="status">
            <component :is="toast.kind === 'success' ? Check : X" :size="16" aria-hidden="true" />
            <span>{{ toast.text }}</span>
            <button v-if="toast.undo" type="button" class="toast__undo" @click="runUndo(toast.id, toast.undo)">
                {{ t('common.undo') }}
            </button>
            <button type="button" class="toast__close" :aria-label="t('common.close')" @click="ui.dismiss(toast.id)">
                <X :size="14" />
            </button>
        </div>
    </div>
</template>
