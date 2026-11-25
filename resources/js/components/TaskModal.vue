<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { ApiError } from '../data/http'
import { t } from '../i18n'
import { fromLocalInput, parseDay, periodRange, toLocalInput, today } from '../lib/dates'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import {
    PERIOD_TYPES,
    TASK_PRIORITIES,
    TASK_STATUSES,
    type PeriodType,
    type Task,
    type TaskStatus,
} from '../types/domain'

const props = defineProps<{
    /** Task being edited, or null to create a new one. */
    task: Task | null
    defaultPeriod: PeriodType
    /** Anchor day (`YYYY-MM-DD`) that decides which period a new task lands in. */
    anchorDay: string
}>()
const emit = defineEmits<{ close: [] }>()

const workspace = useWorkspaceStore()
const ui = useUiStore()
const dialog = ref<HTMLDialogElement | null>(null)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})

const form = reactive({
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    periodType: props.task?.periodType ?? props.defaultPeriod,
    priority: props.task?.priority ?? 'medium',
    status: (props.task?.status ?? 'pending') as TaskStatus,
    dueDate: props.task ? (props.task.dueDate ?? '') : props.anchorDay || today(),
    categoryId: props.task?.categoryId ?? null,
    remindAt: toLocalInput(props.task?.remindAt ?? null),
})

onMounted(() => dialog.value?.showModal())

async function submit(): Promise<void> {
    if (!form.title.trim() || saving.value) return
    saving.value = true
    fieldErrors.value = {}
    // Editing keeps the task in its original period unless the period type changed.
    const anchor = props.task && props.task.periodType === form.periodType ? props.task.periodStart : props.anchorDay
    const range = periodRange(form.periodType, parseDay(anchor))
    const input = {
        title: form.title.trim(),
        description: form.description.trim(),
        periodType: form.periodType,
        periodStart: range.start,
        periodEnd: range.end,
        dueDate: form.dueDate || null,
        priority: form.priority,
        categoryId: form.categoryId,
        remindAt: fromLocalInput(form.remindAt),
    }
    try {
        if (props.task) await workspace.updateTask(props.task.id, { ...input, status: form.status })
        else await workspace.createTask(input)
        ui.success(t(props.task ? 'tasks.updated' : 'tasks.created'))
        emit('close')
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) fieldErrors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <dialog ref="dialog" class="dialog" @cancel.prevent="emit('close')" @click.self="emit('close')">
        <form class="dialog__body" @submit.prevent="submit">
            <div class="dialog__head">
                <div>
                    <span class="eyebrow">{{ task ? 'EDIT TASK' : 'NEW TASK' }}</span>
                    <h2>{{ t(task ? 'tasks.editTask' : 'tasks.newTask') }}</h2>
                </div>
                <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </div>

            <label class="field">
                <span>{{ t('tasks.fTitle') }}</span>
                <input
                    v-model="form.title"
                    class="input"
                    required
                    maxlength="180"
                    autofocus
                    :placeholder="t('tasks.fTitlePh')"
                />
                <small v-if="fieldErrors.title" class="field__error">{{ fieldErrors.title[0] }}</small>
            </label>
            <label class="field">
                <span>{{ t('tasks.fDetails') }}</span>
                <textarea
                    v-model="form.description"
                    class="input"
                    rows="3"
                    maxlength="2000"
                    :placeholder="t('tasks.fDetailsPh')"
                />
                <small v-if="fieldErrors.description" class="field__error">{{ fieldErrors.description[0] }}</small>
            </label>

            <div class="form-grid">
                <label class="field">
                    <span>{{ t('tasks.fPeriod') }}</span>
                    <select v-model="form.periodType" class="input">
                        <option v-for="period in PERIOD_TYPES" :key="period" :value="period">
                            {{ t(`period.${period}`) }}
                        </option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('tasks.fPriority') }}</span>
                    <select v-model="form.priority" class="input">
                        <option v-for="priority in TASK_PRIORITIES" :key="priority" :value="priority">
                            {{ t(`priority.${priority}`) }}
                        </option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('tasks.fCategory') }}</span>
                    <select v-model="form.categoryId" class="input">
                        <option :value="null">{{ t('tasks.noCategory') }}</option>
                        <option v-for="category in workspace.categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <small v-if="fieldErrors.categoryId" class="field__error">{{ fieldErrors.categoryId[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('tasks.fDue') }}</span>
                    <input v-model="form.dueDate" class="input" type="date" />
                    <small v-if="fieldErrors.dueDate" class="field__error">{{ fieldErrors.dueDate[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('tasks.fRemind') }}</span>
                    <input v-model="form.remindAt" class="input" type="datetime-local" />
                    <small v-if="fieldErrors.remindAt" class="field__error">{{ fieldErrors.remindAt[0] }}</small>
                    <small v-else class="hint">{{ t('tasks.fRemindHint') }}</small>
                </label>
                <label v-if="task" class="field">
                    <span>{{ t('tasks.fStatus') }}</span>
                    <select v-model="form.status" class="input">
                        <option v-for="status in TASK_STATUSES" :key="status" :value="status">
                            {{ t(`status.${status}`) }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="actions">
                <button type="button" class="btn btn--outline" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="btn" :disabled="saving">
                    {{ saving ? t('common.saving') : t(task ? 'tasks.saveEdit' : 'tasks.saveTask') }}
                </button>
            </div>
        </form>
    </dialog>
</template>
