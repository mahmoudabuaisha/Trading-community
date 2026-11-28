<script setup lang="ts">
import { Link2, Plus, Save, Trash2 } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import { ApiError, request } from '../../data/http'
import { t } from '../../i18n'
import { loadOfficialLinks, officialLinks, type OfficialLink } from '../../lib/content'
import { useUiStore } from '../../stores/ui'

/** Edit the official Instagram and Telegram accounts shown to members and visitors. */
const ui = useUiStore()
const rows = ref<OfficialLink[]>([])
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

onMounted(async () => {
    await loadOfficialLinks()
    rows.value = officialLinks.value.map((link) => ({ ...link }))
})

async function save(): Promise<void> {
    saving.value = true
    errors.value = {}
    try {
        const result = await request<{ links: OfficialLink[] }>('PUT', '/admin/official-links', {
            links: rows.value.map((row) => ({ ...row, label: row.label.trim(), url: row.url.trim() })),
        })
        officialLinks.value = result.links
        ui.success(t('admin.linksSaved'))
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form class="panel stack" @submit.prevent="save">
        <div class="section-head">
            <div>
                <h3>{{ t('admin.linksHead') }}</h3>
                <p>{{ t('admin.linksSub') }}</p>
            </div>
            <Link2 :size="18" class="gold" aria-hidden="true" />
        </div>
        <div v-for="(row, index) in rows" :key="index" class="links-row">
            <select v-model="row.kind" class="input input--auto" :aria-label="t('links.kinds.other')">
                <option value="instagram">{{ t('links.kinds.instagram') }}</option>
                <option value="telegram">{{ t('links.kinds.telegram') }}</option>
                <option value="other">{{ t('links.kinds.other') }}</option>
            </select>
            <input v-model="row.label" class="input" maxlength="40" required :placeholder="t('admin.linksLabel')" />
            <input
                v-model="row.url"
                class="input"
                type="url"
                dir="ltr"
                maxlength="300"
                required
                :placeholder="t('admin.linksUrl')"
            />
            <button
                type="button"
                class="icon-btn icon-btn--sm"
                :aria-label="t('admin.linksRemove')"
                @click="rows.splice(index, 1)"
            >
                <Trash2 :size="14" />
            </button>
            <small v-if="errors[`links.${index}.url`]" class="field__error">{{
                errors[`links.${index}.url`][0]
            }}</small>
        </div>
        <div class="actions">
            <button
                type="button"
                class="btn btn--outline"
                :disabled="rows.length >= 12"
                @click="rows.push({ kind: 'instagram', label: '', url: 'https://' })"
            >
                <Plus :size="15" aria-hidden="true" /> {{ t('admin.linksAdd') }}
            </button>
            <button type="submit" class="btn" :disabled="saving">
                <Save :size="15" aria-hidden="true" /> {{ t('common.save') }}
            </button>
        </div>
    </form>
</template>
