<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '../i18n'

defineProps<{ message: string; confirmLabel: string }>()
const emit = defineEmits<{ confirm: []; cancel: [] }>()

const dialog = ref<HTMLDialogElement | null>(null)
onMounted(() => dialog.value?.showModal())
</script>

<template>
    <dialog ref="dialog" class="dialog" @cancel.prevent="emit('cancel')" @click.self="emit('cancel')">
        <div class="dialog__body">
            <p>{{ message }}</p>
            <div class="actions">
                <button type="button" class="btn btn--outline" @click="emit('cancel')">{{ t('common.cancel') }}</button>
                <button type="button" class="btn" @click="emit('confirm')">{{ confirmLabel }}</button>
            </div>
        </div>
    </dialog>
</template>
