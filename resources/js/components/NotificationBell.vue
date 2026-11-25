<script setup lang="ts">
import { Bell, BellRing, Check, Mail, Moon, Sparkles, Sun, X } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useNudges } from '../composables/useNudges'
import { useReminders } from '../composables/useReminders'
import { formatDate, t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useNoticesStore, type NoticeKind } from '../stores/notices'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { Task } from '../types/domain'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()
const notices = useNoticesStore()
const { due } = useReminders()
useNudges()

const noticeIcons: Record<NoticeKind, typeof Sun> = { morning: Sun, absence: Sparkles, sleep: Moon, wake: Sun }

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const count = computed(() => notices.items.length + due.value.length + workspace.unread.member + workspace.unread.admin)

async function attempt(action: () => Promise<void>): Promise<void> {
    try {
        await action()
    } catch (error) {
        ui.failure(error)
    }
}
const complete = (task: Task) => attempt(() => workspace.toggleTask(task))
const dismiss = (task: Task) => attempt(() => workspace.dismissReminder(task.id))

function onPointerDown(event: PointerEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false
}
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') open.value = false
}
onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown)
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <div ref="root" class="bell">
        <button
            type="button"
            class="icon-btn bell__button"
            :class="{ 'bell__button--active': count > 0 }"
            :aria-expanded="open"
            :aria-label="t('notify.open', { n: count })"
            @click="open = !open"
        >
            <component :is="count ? BellRing : Bell" :size="20" aria-hidden="true" />
            <i v-if="count" class="bell__count">{{ count > 99 ? '99+' : count }}</i>
        </button>

        <div v-if="open" class="bell__panel" role="dialog" :aria-label="t('notify.title')">
            <div class="bell__head">
                <b>{{ t('notify.title') }}</b>
                <RouterLink
                    :to="{ name: 'profile', params: { section: 'settings' }, hash: '#notifications' }"
                    class="inline-link"
                    @click="open = false"
                    >{{ t('notify.settings') }}</RouterLink
                >
            </div>

            <RouterLink
                v-if="workspace.unread.member"
                :to="{ name: 'messages' }"
                class="bell__item bell__item--link"
                @click="open = false"
            >
                <Mail :size="18" aria-hidden="true" />
                <span>{{ t('notify.newMessages', { n: workspace.unread.member }) }}</span>
            </RouterLink>
            <RouterLink
                v-if="auth.isAdmin && workspace.unread.admin"
                :to="{ name: 'admin' }"
                class="bell__item bell__item--link"
                @click="open = false"
            >
                <Mail :size="18" aria-hidden="true" />
                <span>{{ t('notify.memberMessages', { n: workspace.unread.admin }) }}</span>
            </RouterLink>

            <div v-for="notice in notices.items" :key="`notice-${notice.id}`" class="bell__item">
                <component :is="noticeIcons[notice.kind]" :size="18" aria-hidden="true" />

                <span>
                    <b>{{ notice.title }}</b>

                    <small class="bell__text">{{ notice.text }}</small>
                </span>

                <button
                    type="button"
                    class="icon-btn icon-btn--sm"
                    :title="t('common.close')"
                    :aria-label="`${t('common.close')}: ${notice.title}`"
                    @click="notices.dismiss(notice.id)"
                >
                    <X :size="14" />
                </button>
            </div>

            <div v-for="task in due" :key="task.id" class="bell__item">
                <BellRing :size="18" aria-hidden="true" />
                <span>
                    <b>{{ task.title }}</b>
                    <small>{{ formatDate(task.remindAt!, { dateStyle: 'medium', timeStyle: 'short' }) }}</small>
                </span>
                <button
                    type="button"
                    class="icon-btn icon-btn--sm"
                    :title="t('notify.done')"
                    :aria-label="`${t('notify.done')}: ${task.title}`"
                    @click="complete(task)"
                >
                    <Check :size="14" />
                </button>
                <button
                    type="button"
                    class="icon-btn icon-btn--sm"
                    :title="t('notify.dismiss')"
                    :aria-label="`${t('notify.dismiss')}: ${task.title}`"
                    @click="dismiss(task)"
                >
                    <X :size="14" />
                </button>
            </div>

            <p v-if="!count" class="bell__empty">{{ t('notify.empty') }}</p>
        </div>
    </div>
</template>
