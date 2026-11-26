<script setup lang="ts">
import { Mail, Pin } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import MessageThread from '../components/MessageThread.vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { Message } from '../types/domain'

const workspace = useWorkspaceStore()
const ui = useUiStore()

const messages = ref<Message[]>([])
/** A note from the administration shown above the conversation (how to subscribe, …). */
const notice = ref<string | null>(null)
const sending = ref(false)
let poll = 0

/** Loading the thread also marks the administrators' messages as read. */
async function load(quiet = false): Promise<void> {
    try {
        const result = await repository.loadMessages()
        messages.value = result.messages
        notice.value = result.notice
        workspace.unread.member = 0
    } catch (error) {
        if (!quiet) ui.failure(error)
    }
}

async function send(body: string): Promise<void> {
    sending.value = true
    try {
        messages.value.push(await repository.sendMessage(body))
    } catch (error) {
        ui.failure(error)
    } finally {
        sending.value = false
    }
}

onMounted(() => {
    void load()
    poll = window.setInterval(() => void load(true), 20_000)
})
onBeforeUnmount(() => window.clearInterval(poll))
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN TASKS / DIRECT LINE</span>
                <h2>{{ t('messages.head') }}</h2>
                <p>{{ t('messages.sub') }}</p>
            </div>
            <Mail :size="20" class="gold" aria-hidden="true" />
        </div>
        <div v-if="notice" class="panel message-notice">
            <Pin :size="18" aria-hidden="true" />
            <div>
                <b>{{ t('messages.noticeTitle') }}</b>
                <p>{{ notice }}</p>
            </div>
        </div>
        <div class="panel">
            <MessageThread
                :messages="messages"
                mine="member"
                :sending="sending"
                :empty-text="t('messages.empty')"
                @send="send"
            />
        </div>
    </section>
</template>
