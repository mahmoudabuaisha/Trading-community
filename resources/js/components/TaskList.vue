<script setup lang="ts">
import { CalendarDays, Check, Pencil, Trash2 } from 'lucide-vue-next'
import { t } from '../i18n'
import type { Category, Task } from '../types/domain'
import EmptyState from './EmptyState.vue'

const props = defineProps<{ tasks: Task[]; categories: Category[] }>()
const emit = defineEmits<{ toggle: [task: Task]; edit: [task: Task]; remove: [task: Task] }>()

const categoryOf = (task: Task) => props.categories.find((category) => category.id === task.categoryId)
</script>

<template>
    <div v-if="tasks.length" class="task-list">
        <article
            v-for="task in tasks"
            :key="task.id"
            class="task-row"
            :class="{ 'task-row--done': task.status === 'completed' }"
        >
            <button
                type="button"
                class="task-check"
                :aria-label="t('tasks.toggle')"
                :aria-pressed="task.status === 'completed'"
                @click="emit('toggle', task)"
            >
                <Check v-if="task.status === 'completed'" :size="15" />
            </button>
            <div class="task-info">
                <strong>{{ task.title }}</strong>
                <p v-if="task.description">{{ task.description }}</p>
                <div class="task-meta">
                    <span v-if="categoryOf(task)">
                        <i class="dot" :style="{ background: categoryOf(task)!.color }" />{{ categoryOf(task)!.name }}
                    </span>
                    <span class="priority" :class="`priority--${task.priority}`">{{
                        t(`priority.${task.priority}`)
                    }}</span>
                    <span v-if="task.dueDate"
                        ><CalendarDays :size="12" aria-hidden="true" /><bdi>{{ task.dueDate }}</bdi></span
                    >
                </div>
            </div>
            <span class="status-pill" :class="`status-pill--${task.status}`">{{ t(`status.${task.status}`) }}</span>
            <div class="task-actions">
                <button
                    type="button"
                    :title="t('common.edit')"
                    :aria-label="t('common.edit')"
                    @click="emit('edit', task)"
                >
                    <Pencil :size="15" />
                </button>
                <button
                    type="button"
                    :title="t('common.delete')"
                    :aria-label="t('common.delete')"
                    @click="emit('remove', task)"
                >
                    <Trash2 :size="15" />
                </button>
            </div>
        </article>
    </div>
    <EmptyState v-else :title="t('tasks.emptyTitle')" :text="t('tasks.emptyText')" />
</template>
