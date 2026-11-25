<script setup lang="ts">
import { Check, Lock, Star } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import UserAvatar from '../UserAvatar.vue'
import { repository } from '../../data/repository'
import { messages, t } from '../../i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const user = computed(() => auth.user!)
const saving = ref(false)

const byCost = (catalogue: Record<string, number>) =>
    Object.entries(catalogue)
        .map(([key, cost]) => ({ key, cost, unlocked: user.value.points >= cost }))
        .sort((a, b) => a.cost - b.cost)
const frames = computed(() => byCost(workspace.rewards.frames))
const titles = computed(() => byCost(workspace.rewards.titles))

/** The nearest reward not yet reached, to show what the next days are working towards. */
const next = computed(
    () => [...frames.value, ...titles.value].filter((item) => !item.unlocked).sort((a, b) => a.cost - b.cost)[0],
)

const frameName = (key: string) => (messages.value.rewards.frames as Record<string, string>)[key] ?? key
const titleName = (key: string) => (messages.value.rewards.titles as Record<string, string>)[key] ?? key

async function equip(frame: string, titleKey: string | null): Promise<void> {
    if (saving.value) return
    saving.value = true
    try {
        auth.setUser(await repository.equipRewards(frame, titleKey))
        ui.success(t('rewards.saved'))
    } catch (error) {
        ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="stack">
        <div class="panel rewards-summary">
            <span class="rewards-summary__points"><Star :size="26" aria-hidden="true" /> {{ user.points }}</span>
            <div>
                <h3>{{ t('rewards.points') }}</h3>
                <p>{{ t('rewards.sub', { n: workspace.rewards.pointsPerPerfectDay }) }}</p>
                <p v-if="next" class="hint">{{ t('rewards.next', { n: next.cost - user.points }) }}</p>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('rewards.framesHead') }}</h3>
                    <p>{{ t('rewards.framesSub') }}</p>
                </div>
            </div>
            <div class="reward-grid">
                <button
                    v-for="item in frames"
                    :key="item.key"
                    type="button"
                    class="reward-card"
                    :class="{ 'reward-card--on': user.frame === item.key, 'reward-card--locked': !item.unlocked }"
                    :disabled="!item.unlocked || saving"
                    :aria-pressed="user.frame === item.key"
                    @click="equip(item.key, user.titleKey)"
                >
                    <UserAvatar :name="user.displayName" :src="user.avatarUrl" :frame="item.key" size="lg" />
                    <b>{{ frameName(item.key) }}</b>
                    <small v-if="user.frame === item.key"
                        ><Check :size="13" aria-hidden="true" /> {{ t('rewards.equipped') }}</small
                    >
                    <small v-else-if="item.unlocked">{{ t('rewards.equip') }}</small>
                    <small v-else
                        ><Lock :size="13" aria-hidden="true" /> {{ t('rewards.needs', { n: item.cost }) }}</small
                    >
                </button>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('rewards.titlesHead') }}</h3>
                    <p>{{ t('rewards.titlesSub') }}</p>
                </div>
            </div>
            <div class="reward-grid">
                <button
                    type="button"
                    class="reward-card"
                    :class="{ 'reward-card--on': !user.titleKey }"
                    :disabled="saving"
                    :aria-pressed="!user.titleKey"
                    @click="equip(user.frame, null)"
                >
                    <b>{{ t('rewards.noTitle') }}</b>
                    <small v-if="!user.titleKey"
                        ><Check :size="13" aria-hidden="true" /> {{ t('rewards.equipped') }}</small
                    >
                    <small v-else>{{ t('rewards.equip') }}</small>
                </button>
                <button
                    v-for="item in titles"
                    :key="item.key"
                    type="button"
                    class="reward-card"
                    :class="{ 'reward-card--on': user.titleKey === item.key, 'reward-card--locked': !item.unlocked }"
                    :disabled="!item.unlocked || saving"
                    :aria-pressed="user.titleKey === item.key"
                    @click="equip(user.frame, item.key)"
                >
                    <b class="reward-card__title">{{ titleName(item.key) }}</b>
                    <small v-if="user.titleKey === item.key"
                        ><Check :size="13" aria-hidden="true" /> {{ t('rewards.equipped') }}</small
                    >
                    <small v-else-if="item.unlocked">{{ t('rewards.equip') }}</small>
                    <small v-else
                        ><Lock :size="13" aria-hidden="true" /> {{ t('rewards.needs', { n: item.cost }) }}</small
                    >
                </button>
            </div>
        </div>
    </div>
</template>
