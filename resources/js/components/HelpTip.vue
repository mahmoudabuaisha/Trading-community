<script setup lang="ts">
import { CircleHelp, X } from 'lucide-vue-next'
import { onBeforeUnmount, ref, watch } from 'vue'
import { messages, t } from '../i18n'

/**
 * A small "?" beside a tool. Tapping it explains what the tool is for and
 * how to use it, in the member's language.
 */
const props = defineProps<{ topic: string }>()
const open = ref(false)
const root = ref<HTMLElement | null>(null)

const has = (topic: string): boolean => topic in (messages.value.help as Record<string, unknown>)

function onOutside(event: Event): void {
    if (root.value && !root.value.contains(event.target as Node)) open.value = false
}
function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') open.value = false
}
watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('pointerdown', onOutside)
        document.addEventListener('keydown', onKey)
    } else {
        document.removeEventListener('pointerdown', onOutside)
        document.removeEventListener('keydown', onKey)
    }
})
watch(
    () => props.topic,
    () => (open.value = false),
)
onBeforeUnmount(() => (open.value = false))
</script>

<template>
    <span v-if="has(topic)" ref="root" class="help-tip">
        <button
            type="button"
            class="help-tip__btn"
            :aria-expanded="open"
            :aria-label="t('help.label', { name: t(`help.${topic}.title`) })"
            @click="open = !open"
        >
            <CircleHelp :size="16" aria-hidden="true" />
        </button>
        <span v-if="open" class="help-tip__bubble" role="dialog" :aria-label="t(`help.${topic}.title`)">
            <span class="help-tip__head">
                <b>{{ t(`help.${topic}.title`) }}</b>
                <button
                    type="button"
                    class="icon-btn icon-btn--sm"
                    :aria-label="t('common.close')"
                    @click="open = false"
                >
                    <X :size="14" />
                </button>
            </span>
            <span class="help-tip__text">{{ t(`help.${topic}.text`) }}</span>
        </span>
    </span>
</template>
