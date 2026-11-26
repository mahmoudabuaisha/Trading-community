<script setup lang="ts">
import { Check, Lock, Palette, Star, X } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import { t } from '../../i18n'
import { useAuthStore } from '../../stores/auth'
import { useDesignsStore } from '../../stores/designs'
import { useUiStore } from '../../stores/ui'
import type { Design, DesignTarget } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import GoalMap from './GoalMap.vue'
import SkillShape from './SkillShape.vue'

/** Choose how the goal map or the skill tree looks: free designs, or ones bought with points. */
const props = defineProps<{ target: DesignTarget }>()
const emit = defineEmits<{ close: [] }>()
const store = useDesignsStore()
const auth = useAuthStore()
const ui = useUiStore()

const dialog = ref<HTMLDialogElement | null>(null)
const busy = ref(false)
const buying = ref<Design | null>(null)
onMounted(() => dialog.value?.showModal())

// Sample content for the previews.
const sampleStages = [
    {
        id: 1,
        title: t('designs.sampleA'),
        isDone: true,
        children: [
            { id: 11, title: '1', isDone: true },
            { id: 12, title: '2', isDone: true },
        ],
    },
    {
        id: 2,
        title: t('designs.sampleB'),
        isDone: false,
        children: [
            { id: 21, title: '1', isDone: true },
            { id: 22, title: '2', isDone: false },
        ],
    },
    { id: 3, title: t('designs.sampleC'), isDone: false, children: [{ id: 31, title: '1', isDone: false }] },
]
const sampleLevels = [1, 2, 3, 4, 5].map((n) => ({ id: n, title: `${n}`, passed: n <= 2, unlocked: n <= 3 }))

async function run(action: () => Promise<void>, successKey: string): Promise<void> {
    busy.value = true
    try {
        await action()
        ui.success(t(successKey))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}
async function confirmBuy(): Promise<void> {
    const design = buying.value
    buying.value = null
    if (design) await run(() => store.buy(design), 'designs.bought')
}
</script>

<template>
    <dialog ref="dialog" class="dialog dialog--wide" @cancel.prevent="emit('close')" @click.self="emit('close')">
        <div class="dialog__body">
            <div class="dialog__head">
                <h2><Palette :size="20" aria-hidden="true" /> {{ t(`designs.head.${target}`) }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </div>
            <p class="hint">{{ t('designs.sub', { n: auth.user?.points ?? 0 }) }}</p>
            <div class="design-grid">
                <article
                    v-for="design in store.forTarget(props.target)"
                    :key="design.id"
                    class="design-card"
                    :class="{ 'design-card--on': store.selected[target] === design.id }"
                >
                    <GoalMap
                        v-if="target === 'goal_map'"
                        :title="t('designs.sampleGoal')"
                        :stages="sampleStages"
                        :design="design"
                        compact
                    />
                    <SkillShape
                        v-else
                        :title="t('designs.sampleSkill')"
                        :levels="sampleLevels"
                        :design="design"
                        compact
                    />
                    <div class="design-card__body">
                        <b>{{ design.name }}</b>
                        <small v-if="design.description">{{ design.description }}</small>
                        <div class="row-actions">
                            <span v-if="store.selected[target] === design.id" class="count-pill count-pill--ok"
                                ><Check :size="12" aria-hidden="true" /> {{ t('designs.inUse') }}</span
                            >
                            <button
                                v-else-if="design.owned"
                                type="button"
                                class="btn"
                                :disabled="busy"
                                @click="run(() => store.use(design), 'designs.applied')"
                            >
                                {{ t('designs.use') }}
                            </button>
                            <template v-else>
                                <span class="store-card__price"
                                    ><Star :size="14" aria-hidden="true" /> {{ design.price }}</span
                                >
                                <button
                                    type="button"
                                    class="btn"
                                    :disabled="busy || (auth.user?.points ?? 0) < design.price"
                                    @click="buying = design"
                                >
                                    <Lock
                                        v-if="(auth.user?.points ?? 0) < design.price"
                                        :size="14"
                                        aria-hidden="true"
                                    />
                                    {{ t('store.buy') }}
                                </button>
                            </template>
                            <span v-if="design.price === 0" class="count-pill">{{ t('designs.free') }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
        <ConfirmDialog
            v-if="buying"
            :message="t('store.confirm', { name: buying.name, n: buying.price })"
            :confirm-label="t('store.buy')"
            @confirm="confirmBuy"
            @cancel="buying = null"
        />
    </dialog>
</template>
