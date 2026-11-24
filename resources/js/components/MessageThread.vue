<script setup lang="ts">
import { Send } from 'lucide-vue-next'
import { nextTick, ref, watch } from 'vue'
import { formatDate, t } from '../i18n'
import type { Message } from '../types/domain'

/**
 * A conversation: bubbles plus a composer. `mine` says which side the viewer
 * is on, so their own messages sit on one side and the other party's opposite.
 */
const props = defineProps<{ messages: Message[]; mine: 'member' | 'admin'; sending: boolean; emptyText: string }>()
const emit = defineEmits<{ send: [body: string] }>()

const draft = ref('')
const list = ref<HTMLElement | null>(null)

const isMine = (message: Message) => message.fromAdmin === (props.mine === 'admin')

watch(
    () => props.messages.length,
    async () => {
        await nextTick()
        list.value?.scrollTo({ top: list.value.scrollHeight })
    },
    { immediate: true },
)

function submit(): void {
    const body = draft.value.trim()
    if (!body || props.sending) return
    emit('send', body)
    draft.value = ''
}

function onKeydown(event: KeyboardEvent): void {
    // Enter sends; Shift+Enter makes a new line.
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault()
        submit()
    }
}
</script>

<template>
    <div class="thread">
        <div ref="list" class="thread__list" role="log" aria-live="polite">
            <p v-if="!messages.length" class="hint-box">{{ emptyText }}</p>
            <div
                v-for="message in messages"
                :key="message.id"
                class="bubble"
                :class="{ 'bubble--mine': isMine(message) }"
            >
                <small class="bubble__who">{{ message.fromAdmin ? t('messages.admin') : message.senderName }}</small>
                <p>{{ message.body }}</p>
                <time :datetime="message.createdAt">{{
                    formatDate(message.createdAt, { dateStyle: 'medium', timeStyle: 'short' })
                }}</time>
            </div>
        </div>
        <form class="thread__composer" @submit.prevent="submit">
            <textarea
                v-model="draft"
                class="input"
                rows="2"
                maxlength="4000"
                :placeholder="t('messages.placeholder')"
                :aria-label="t('messages.placeholder')"
                @keydown="onKeydown"
            />
            <button type="submit" class="btn" :disabled="sending || !draft.trim()">
                <Send :size="16" aria-hidden="true" /> {{ t('messages.send') }}
            </button>
        </form>
    </div>
</template>
