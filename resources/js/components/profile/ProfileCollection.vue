<script setup lang="ts">
import { Check, ShoppingBag } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import EmptyState from '../EmptyState.vue'
import StoreArt from '../StoreArt.vue'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import type { StoreItem, StoreKind } from '../../types/domain'

/** Everything the member bought (or was given), grouped by kind, ready to wear. */
const auth = useAuthStore()
const ui = useUiStore()
const items = ref<StoreItem[]>([])
const loaded = ref(false)
const busy = ref(false)
const ORDER: StoreKind[] = ['frame', 'title', 'avatar', 'emblem', 'theme', 'product']

const groups = computed(() =>
    ORDER.map((kind) => ({ kind, items: items.value.filter((item) => item.owned && item.kind === kind) })).filter(
        (group) => group.items.length,
    ),
)

async function load(action = repository.store): Promise<void> {
    const result = await action()
    items.value = result.items
    auth.setUser(result.user)
}

onMounted(async () => {
    try {
        await load()
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})

/** Wear an item, or take it off. A profile picture is replaced, not taken off. */
async function toggle(item: StoreItem): Promise<void> {
    if (busy.value) return
    busy.value = true
    const off = item.equipped && item.kind !== 'avatar'
    try {
        await load(() => repository.equipItem(item.kind, off ? null : item.id))
        ui.success(t(off ? 'store.removed' : 'store.applied'))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <div class="stack">
        <section v-for="group in groups" :key="group.kind" class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t(`store.kinds.${group.kind}`) }}</h3>
                    <p>{{ t('collection.count', { n: group.items.length }) }}</p>
                </div>
            </div>
            <div class="collection-grid">
                <article
                    v-for="item in group.items"
                    :key="item.id"
                    class="collection-card"
                    :class="{ 'collection-card--on': item.equipped }"
                >
                    <StoreArt :item="item" />
                    <b>{{ item.name }}</b>
                    <span v-if="item.equipped" class="count-pill count-pill--ok"
                        ><Check :size="12" aria-hidden="true" /> {{ t('collection.inUse') }}</span
                    >
                    <button
                        v-if="item.kind !== 'product'"
                        type="button"
                        class="btn btn--sm"
                        :class="{ 'btn--outline': item.equipped }"
                        :disabled="busy || (item.equipped && item.kind === 'avatar')"
                        @click="toggle(item)"
                    >
                        {{ t(item.equipped ? (item.kind === 'avatar' ? 'store.inUse' : 'store.remove') : 'store.use') }}
                    </button>
                    <RouterLink v-else :to="{ name: 'store' }" class="btn btn--sm btn--outline">{{
                        t('collection.open')
                    }}</RouterLink>
                </article>
            </div>
        </section>

        <EmptyState
            v-if="loaded && !groups.length"
            :title="t('collection.emptyTitle')"
            :text="t('collection.emptyText')"
        >
            <RouterLink :to="{ name: 'store' }" class="btn"
                ><ShoppingBag :size="15" aria-hidden="true" /> {{ t('collection.toStore') }}</RouterLink
            >
        </EmptyState>
    </div>
</template>
