<script setup lang="ts">
import { Crown, X } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { today } from '../../lib/dates'
import { useUiStore } from '../../stores/ui'
import type { GrantType, SubscriptionSheet } from '../../types/domain'

/**
 * A member's paid subscription: switched on by hand after payment outside
 * the site, optionally until a date, opening only the services picked here.
 */
const props = defineProps<{ userId: number; name: string }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const ui = useUiStore()

const dialog = ref<HTMLDialogElement | null>(null)
const sheet = ref<SubscriptionSheet | null>(null)
const form = reactive({
    isPremium: false,
    hasEnd: false,
    premiumUntil: '',
    grants: { lesson: [] as number[], book: [] as number[], store_item: [] as number[] },
})
const saving = ref(false)
const groups: GrantType[] = ['lesson', 'book', 'store_item']
const chosen = computed(() => groups.reduce((sum, type) => sum + form.grants[type].length, 0))

onMounted(async () => {
    dialog.value?.showModal()
    try {
        const data = await repository.adminSubscription(props.userId)
        sheet.value = data
        Object.assign(form, {
            isPremium: data.isPremium,
            hasEnd: data.premiumUntil !== null,
            premiumUntil: data.premiumUntil ?? '',
            grants: {
                lesson: [...data.grants.lesson],
                book: [...data.grants.book],
                store_item: [...data.grants.store_item],
            },
        })
    } catch (error) {
        ui.failure(error)
        emit('close')
    }
})

function selectAll(type: GrantType, on: boolean): void {
    form.grants[type] = on ? sheet.value!.options[type].map((option) => option.id) : []
}

async function save(isPremium = form.isPremium): Promise<void> {
    saving.value = true
    try {
        await repository.adminSaveSubscription(props.userId, {
            isPremium,
            premiumUntil: isPremium && form.hasEnd && form.premiumUntil ? form.premiumUntil : null,
            grants: form.grants,
        })
        ui.success(t(isPremium ? 'admin.subSaved' : 'admin.premiumRevoked'))
        emit('saved')
        emit('close')
    } catch (error) {
        ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <dialog ref="dialog" class="dialog dialog--wide" @cancel.prevent="emit('close')" @click.self="emit('close')">
        <form class="dialog__body" @submit.prevent="save()">
            <div class="dialog__head">
                <h2><Crown :size="20" aria-hidden="true" /> {{ t('admin.subHead', { name }) }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </div>
            <p class="hint">{{ t('admin.subSub') }}</p>

            <template v-if="sheet">
                <label class="setting-row">
                    <span>
                        <b>{{ t('admin.subActive') }}</b>
                        <small>{{ t(sheet.active ? 'admin.subNowOn' : 'admin.subNowOff') }}</small>
                    </span>
                    <input v-model="form.isPremium" type="checkbox" class="switch" />
                </label>

                <div v-if="form.isPremium" class="head-actions">
                    <label class="check"
                        ><input v-model="form.hasEnd" type="checkbox" /> {{ t('admin.subHasEnd') }}</label
                    >
                    <input
                        v-if="form.hasEnd"
                        v-model="form.premiumUntil"
                        class="input input--auto"
                        type="date"
                        :min="today()"
                        required
                        :aria-label="t('admin.subUntil')"
                    />
                    <small class="hint">{{ form.hasEnd ? t('admin.subUntilHint') : t('admin.subForever') }}</small>
                </div>

                <fieldset v-for="type in groups" :key="type" class="perm-list" :disabled="!form.isPremium">
                    <legend>{{ t(`admin.subGroups.${type}`) }}</legend>
                    <template v-if="sheet.options[type].length">
                        <label v-for="option in sheet.options[type]" :key="option.id" class="check">
                            <input v-model="form.grants[type]" type="checkbox" :value="option.id" /> {{ option.title }}
                        </label>
                        <div class="row-actions">
                            <button type="button" class="pill" @click="selectAll(type, true)">
                                {{ t('admin.subAll') }}
                            </button>
                            <button type="button" class="pill" @click="selectAll(type, false)">
                                {{ t('admin.subNone') }}
                            </button>
                        </div>
                    </template>
                    <p v-else class="hint">{{ t(`admin.subEmpty.${type}`) }}</p>
                </fieldset>

                <div class="actions">
                    <button
                        v-if="sheet.active"
                        type="button"
                        class="btn btn--outline"
                        :disabled="saving"
                        @click="save(false)"
                    >
                        {{ t('admin.subCancel') }}
                    </button>
                    <button type="submit" class="btn" :disabled="saving">
                        {{ saving ? t('common.saving') : t('admin.subSave', { n: chosen }) }}
                    </button>
                </div>
            </template>
        </form>
    </dialog>
</template>
