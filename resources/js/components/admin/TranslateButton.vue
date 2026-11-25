<script setup lang="ts">
import { Languages } from 'lucide-vue-next'
import { ref } from 'vue'
import { t } from '../../i18n'
import TranslateDialog from './TranslateDialog.vue'

/** The "Translate" pill shown beside a piece of admin-written content; it owns its dialog. */
defineProps<{ type: string; id: number }>()
const open = ref(false)
</script>

<template>
    <button type="button" class="pill" @click="open = true">
        <Languages :size="13" aria-hidden="true" /> {{ t('admin.translate') }}
    </button>
    <TranslateDialog v-if="open" :type="type" :id="id" @close="open = false" />
</template>
