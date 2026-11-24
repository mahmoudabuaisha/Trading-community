<script setup lang="ts">
import { Mail } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { formatDate, t } from '../../i18n'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import MessageThread from '../MessageThread.vue'
import UserAvatar from '../UserAvatar.vue'

const admin = useAdminStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()
const sending = ref(false)
let poll = 0

async function open(userId: number): Promise<void> {
    try {
        await admin.openThread(userId)
        void workspace.refreshUnread()
    } catch (error) {
        ui.failure(error)
    }
}

async function send(body: string): Promise<void> {
    sending.value = true
    try {
        await admin.sendMessage(body)
    } catch (error) {
        ui.failure(error)
    } finally {
        sending.value = false
    }
}

/** Keep the inbox (and the open conversation) fresh while the tab is on screen. */
async function refresh(): Promise<void> {
    try {
        if (admin.activeThread) await admin.openThread(admin.activeThread.user.id)
        else await admin.loadThreads()
    } catch {
        /* a failed background refresh keeps what is on screen */
    }
}

onMounted(() => (poll = window.setInterval(() => void refresh(), 20_000)))
onBeforeUnmount(() => window.clearInterval(poll))
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">DIRECT LINE / INBOX</span>
                <h3>{{ t('admin.inboxHead') }}</h3>
                <p>{{ t('admin.inboxSub') }}</p>
            </div>
            <Mail :size="19" class="gold" aria-hidden="true" />
        </div>

        <div class="inbox">
            <div class="inbox__list">
                <button
                    v-for="thread in admin.threads"
                    :key="thread.user.id"
                    type="button"
                    class="inbox__item"
                    :class="{ 'inbox__item--active': admin.activeThread?.user.id === thread.user.id }"
                    @click="open(thread.user.id)"
                >
                    <UserAvatar :name="thread.user.displayName" size="sm" />
                    <span class="inbox__text">
                        <b>{{ thread.user.displayName }}</b>
                        <small
                            >{{ thread.lastMessage.fromAdmin ? t('admin.you') : ''
                            }}{{ thread.lastMessage.body }}</small
                        >
                    </span>
                    <span class="inbox__meta">
                        <time :datetime="thread.lastMessage.createdAt">{{
                            formatDate(thread.lastMessage.createdAt, { day: 'numeric', month: 'short' })
                        }}</time>
                        <i v-if="thread.unread" class="nav-item__count nav-item__count--alert">{{ thread.unread }}</i>
                    </span>
                </button>
                <p v-if="!admin.threads.length" class="hint-box">{{ t('admin.inboxEmpty') }}</p>
            </div>

            <div class="inbox__thread">
                <template v-if="admin.activeThread">
                    <div class="inbox__who">
                        <b>{{ admin.activeThread.user.displayName }}</b>
                        <small
                            ><bdi>{{ admin.activeThread.user.email }}</bdi></small
                        >
                    </div>
                    <MessageThread
                        :messages="admin.activeThread.messages"
                        mine="admin"
                        :sending="sending"
                        :empty-text="t('admin.threadEmpty')"
                        @send="send"
                    />
                </template>
                <p v-else class="hint-box">{{ t('admin.threadPick') }}</p>
            </div>
        </div>
    </div>
</template>
