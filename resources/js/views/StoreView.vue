<script setup lang="ts">
import { Check, ShoppingBag, Star } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import EmptyState from '../components/EmptyState.vue'
import StoreArt from '../components/StoreArt.vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { STORE_KINDS, type StoreItem, type StoreKind, type User } from '../types/domain'

const auth = useAuthStore()
const ui = useUiStore()

const items = ref<StoreItem[]>([])
const loaded = ref(false)
const busy = ref(false)
const filter = ref<StoreKind | 'all' | 'mine'>('all')
const buying = ref<StoreItem | null>(null)

const points = computed(() => auth.user?.points ?? 0)
const kinds = computed(() => STORE_KINDS.filter((kind) => items.value.some((item) => item.kind === kind)))
const shown = computed(() =>
    items.value.filter((item) =>
        filter.value === 'all' ? true : filter.value === 'mine' ? item.owned : item.kind === filter.value,
    ),
)

function apply(result: { items: StoreItem[]; user: User }): void {
    items.value = result.items
    auth.setUser(result.user)
}

onMounted(async () => {
    try {
        apply(await repository.store())
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})

async function run(action: () => Promise<{ items: StoreItem[]; user: User }>, successKey: string): Promise<void> {
    if (busy.value) return
    busy.value = true
    try {
        apply(await action())
        ui.success(t(successKey))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}

async function confirmBuy(): Promise<void> {
    const item = buying.value
    buying.value = null
    if (item) await run(() => repository.buyItem(item.id), 'store.bought')
}

/** Use an owned item, or stop using it. A profile photo cannot be "taken off", only replaced. */
const toggleUse = (item: StoreItem) =>
    run(
        () => repository.equipItem(item.kind, item.equipped && item.kind !== 'avatar' ? null : item.id),
        item.equipped && item.kind !== 'avatar' ? 'store.removed' : 'store.applied',
    )
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN REWARDS</span>
                <h2>{{ t('store.head') }}</h2>
                <p>{{ t('store.sub') }}</p>
            </div>
            <span class="points-chip"><Star :size="20" aria-hidden="true" /> {{ points }}</span>
        </div>

        <nav class="tabs" :aria-label="t('store.head')">
            <button
                type="button"
                :class="{ tabs__active: filter === 'all' }"
                :aria-pressed="filter === 'all'"
                @click="filter = 'all'"
            >
                {{ t('store.all') }}
            </button>
            <button
                v-for="kind in kinds"
                :key="kind"
                type="button"
                :class="{ tabs__active: filter === kind }"
                :aria-pressed="filter === kind"
                @click="filter = kind"
            >
                {{ t(`store.kinds.${kind}`) }}
            </button>
            <button
                type="button"
                :class="{ tabs__active: filter === 'mine' }"
                :aria-pressed="filter === 'mine'"
                @click="filter = 'mine'"
            >
                {{ t('store.mine') }}
            </button>
        </nav>

        <div v-if="shown.length" class="store-grid">
            <article
                v-for="item in shown"
                :key="item.id"
                class="dash-card store-card"
                :class="{ 'store-card--owned': item.owned }"
            >
                <StoreArt :item="item" />
                <div class="store-card__body">
                    <h3>{{ item.name }}</h3>
                    <p>{{ item.description || t(`store.kind.${item.kind}`) }}</p>
                    <div class="store-card__foot">
                        <template v-if="item.owned">
                            <button
                                type="button"
                                class="btn"
                                :class="{ 'btn--outline': item.equipped }"
                                :disabled="busy || (item.equipped && item.kind === 'avatar')"
                                @click="toggleUse(item)"
                            >
                                <Check v-if="item.equipped" :size="15" aria-hidden="true" />
                                {{
                                    t(
                                        item.equipped
                                            ? item.kind === 'avatar'
                                                ? 'store.inUse'
                                                : 'store.remove'
                                            : 'store.use',
                                    )
                                }}
                            </button>
                            <span class="count-pill count-pill--ok">{{ t('store.owned') }}</span>
                        </template>
                        <template v-else>
                            <span class="store-card__price"
                                ><Star :size="16" aria-hidden="true" /> {{ item.price }}</span
                            >
                            <button
                                type="button"
                                class="btn"
                                :disabled="busy || points < item.price"
                                @click="buying = item"
                            >
                                <ShoppingBag :size="15" aria-hidden="true" /> {{ t('store.buy') }}
                            </button>
                        </template>
                    </div>
                    <small v-if="!item.owned && points < item.price" class="hint">{{
                        t('store.missing', { n: item.price - points })
                    }}</small>
                </div>
            </article>
        </div>
        <EmptyState
            v-else-if="loaded"
            :title="t(filter === 'mine' ? 'store.emptyMine' : 'store.emptyTitle')"
            :text="t('store.emptyText')"
        />

        <p class="hint">{{ t('store.howTo') }}</p>

        <ConfirmDialog
            v-if="buying"
            :message="t('store.confirm', { name: buying.name, n: buying.price })"
            :confirm-label="t('store.buy')"
            @confirm="confirmBuy"
            @cancel="buying = null"
        />
    </section>
</template>
