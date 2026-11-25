<script setup lang="ts">
import { ArrowLeft, ArrowRight, ImagePlus, Link2, Plus, Quote, Sparkles, Target, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import ConfirmDialog from '../../components/ConfirmDialog.vue'
import EmptyState from '../../components/EmptyState.vue'
import JourneyHead from '../../components/JourneyHead.vue'
import { repository } from '../../data/repository'
import { isRtl, t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import { VISION_AREAS, VISION_COLORS, type VisionBoard, type VisionItem, type VisionSize } from '../../types/domain'

const workspace = useWorkspaceStore()
const ui = useUiStore()

const boards = ref<VisionBoard[]>([])
const activeId = ref<number | null>(null)
const loaded = ref(false)
const board = computed(() => boards.value.find((item) => item.id === activeId.value) ?? null)
const deleting = ref(false)

function put(next: VisionBoard): void {
    const index = boards.value.findIndex((item) => item.id === next.id)
    if (index >= 0) boards.value[index] = next
    else boards.value.push(next)
    activeId.value = next.id
}
async function run(action: () => Promise<VisionBoard | void>): Promise<boolean> {
    try {
        const result = await action()
        if (result) put(result)
        return true
    } catch (error) {
        ui.failure(error)
        return false
    }
}

onMounted(async () => {
    try {
        boards.value = await repository.visionBoards()
        activeId.value = boards.value[0]?.id ?? null
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})

const boardForm = reactive({
    title: '',
    area: 'personal' as VisionBoard['area'],
    color: 'gold' as VisionBoard['color'],
})
async function createBoard(): Promise<void> {
    if (!boardForm.title.trim()) return
    if (await run(() => repository.createBoard({ ...boardForm, title: boardForm.title.trim() }))) boardForm.title = ''
}
async function removeBoard(): Promise<void> {
    deleting.value = false
    const id = activeId.value
    if (id === null) return
    if (await run(() => repository.deleteBoard(id))) {
        boards.value = boards.value.filter((item) => item.id !== id)
        activeId.value = boards.value[0]?.id ?? null
    }
}

const itemForm = reactive({ kind: 'dream' as 'quote' | 'dream', text: '', goalId: null as number | null })
const fileInput = ref<HTMLInputElement | null>(null)
async function addText(): Promise<void> {
    if (!board.value || !itemForm.text.trim()) return
    if (
        await run(() =>
            repository.addVisionItem(board.value!.id, {
                kind: itemForm.kind,
                text: itemForm.text.trim(),
                goalId: itemForm.goalId,
            }),
        )
    )
        Object.assign(itemForm, { text: '', goalId: null })
}
async function addImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (file && board.value) await run(() => repository.addVisionItem(board.value!.id, { kind: 'image', image: file }))
}

const sizes: VisionSize[] = ['sm', 'md', 'lg']
const resize = (item: VisionItem) =>
    run(() => repository.updateVisionItem(item.id, { size: sizes[(sizes.indexOf(item.size) + 1) % sizes.length] }))
const linkGoal = (item: VisionItem, event: Event) =>
    run(() =>
        repository.updateVisionItem(item.id, { goalId: Number((event.target as HTMLSelectElement).value) || null }),
    )
async function removeItem(item: VisionItem): Promise<void> {
    if (!board.value) return
    const current = board.value
    if (await run(() => repository.deleteVisionItem(item.id)))
        put({ ...current, items: current.items.filter((other) => other.id !== item.id) })
}

/** Arrange freely: move an item one place, or drag it onto another to take its place. */
function reorder(from: number, to: number): void {
    if (!board.value || to < 0 || to >= board.value.items.length || from === to) return
    const items = [...board.value.items]
    items.splice(to, 0, ...items.splice(from, 1))
    put({ ...board.value, items })
    void run(() => repository.updateBoard(board.value!.id, { order: items.map((item) => item.id) }))
}
const dragged = ref<number | null>(null)
const goalTitle = (id: number | null) => workspace.goals.find((goal) => goal.id === id)?.title
const PrevIcon = computed(() => (isRtl.value ? ArrowRight : ArrowLeft))
const NextIcon = computed(() => (isRtl.value ? ArrowLeft : ArrowRight))
</script>

<template>
    <section class="stack">
        <JourneyHead eyebrow="VISION BOARD" :title="t('journey.tiles.vision.title')" :text="t('journey.vision.sub')" />

        <nav v-if="boards.length" class="tabs" :aria-label="t('journey.tiles.vision.title')">
            <button
                v-for="item in boards"
                :key="item.id"
                type="button"
                :class="{ tabs__active: item.id === activeId }"
                :aria-pressed="item.id === activeId"
                @click="activeId = item.id"
            >
                {{ item.title }}
            </button>
        </nav>

        <div v-if="board" class="panel vision" :data-theme="board.color">
            <div class="section-head">
                <div>
                    <span class="count-pill">{{ t(`journey.vision.areas.${board.area}`) }}</span>
                    <h3>{{ board.title }}</h3>
                </div>
                <div class="head-actions">
                    <select
                        class="input input--auto"
                        :value="board.color"
                        :aria-label="t('journey.vision.color')"
                        @change="
                            run(() =>
                                repository.updateBoard(board!.id, {
                                    color: ($event.target as HTMLSelectElement).value as VisionBoard['color'],
                                }),
                            )
                        "
                    >
                        <option v-for="color in VISION_COLORS" :key="color" :value="color">
                            {{ t(`journey.vision.colors.${color}`) }}
                        </option>
                    </select>
                    <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                        <ImagePlus :size="15" aria-hidden="true" /> {{ t('journey.vision.addImage') }}
                    </button>
                    <button
                        type="button"
                        class="icon-btn"
                        :aria-label="t('journey.vision.deleteBoard')"
                        :title="t('journey.vision.deleteBoard')"
                        @click="deleting = true"
                    >
                        <Trash2 :size="16" />
                    </button>
                    <input
                        ref="fileInput"
                        class="sr-only"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        tabindex="-1"
                        @change="addImage"
                    />
                </div>
            </div>

            <form class="vision__add" @submit.prevent="addText">
                <select v-model="itemForm.kind" class="input input--auto" :aria-label="t('journey.vision.kind')">
                    <option value="dream">{{ t('journey.vision.kinds.dream') }}</option>
                    <option value="quote">{{ t('journey.vision.kinds.quote') }}</option>
                </select>
                <input
                    v-model="itemForm.text"
                    class="input"
                    maxlength="500"
                    :placeholder="t(`journey.vision.ph.${itemForm.kind}`)"
                    :aria-label="t(`journey.vision.ph.${itemForm.kind}`)"
                />
                <select
                    v-if="workspace.goals.length"
                    v-model="itemForm.goalId"
                    class="input input--auto"
                    :aria-label="t('journey.vision.linkGoal')"
                >
                    <option :value="null">{{ t('journey.vision.noGoal') }}</option>
                    <option v-for="goal in workspace.goals" :key="goal.id" :value="goal.id">{{ goal.title }}</option>
                </select>
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" /> {{ t('journey.vision.add') }}
                </button>
            </form>

            <div v-if="board.items.length" class="vision__grid">
                <article
                    v-for="(item, index) in board.items"
                    :key="item.id"
                    class="vision-item"
                    :class="[
                        `vision-item--${item.kind}`,
                        `vision-item--${item.size}`,
                        { 'vision-item--dragged': dragged === index },
                    ]"
                    draggable="true"
                    @dragstart="dragged = index"
                    @dragend="dragged = null"
                    @dragover.prevent
                    @drop.prevent="dragged !== null && reorder(dragged, index)"
                >
                    <img v-if="item.imageUrl" :src="item.imageUrl" alt="" loading="lazy" />
                    <p v-else>
                        <component :is="item.kind === 'quote' ? Quote : Sparkles" :size="18" aria-hidden="true" />
                        {{ item.text }}
                    </p>
                    <RouterLink
                        v-if="goalTitle(item.goalId)"
                        :to="{ name: 'roadmap', params: { id: item.goalId! } }"
                        class="vision-item__goal"
                    >
                        <Target :size="13" aria-hidden="true" /> {{ goalTitle(item.goalId) }}
                    </RouterLink>
                    <div class="vision-item__tools">
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :disabled="index === 0"
                            :aria-label="t('journey.vision.earlier')"
                            @click="reorder(index, index - 1)"
                        >
                            <component :is="PrevIcon" :size="13" />
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :disabled="index === board.items.length - 1"
                            :aria-label="t('journey.vision.later')"
                            @click="reorder(index, index + 1)"
                        >
                            <component :is="NextIcon" :size="13" />
                        </button>
                        <button type="button" class="pill" @click="resize(item)">
                            {{ t(`journey.vision.sizes.${item.size}`) }}
                        </button>
                        <label
                            v-if="workspace.goals.length"
                            class="vision-item__link"
                            :title="t('journey.vision.linkGoal')"
                        >
                            <Link2 :size="13" aria-hidden="true" />
                            <select
                                :value="item.goalId ?? ''"
                                :aria-label="t('journey.vision.linkGoal')"
                                @change="linkGoal(item, $event)"
                            >
                                <option value="">{{ t('journey.vision.noGoal') }}</option>
                                <option v-for="goal in workspace.goals" :key="goal.id" :value="goal.id">
                                    {{ goal.title }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :aria-label="t('common.delete')"
                            @click="removeItem(item)"
                        >
                            <Trash2 :size="13" />
                        </button>
                    </div>
                </article>
            </div>
            <p v-else class="hint">{{ t('journey.vision.emptyBoard') }}</p>
        </div>
        <EmptyState v-else-if="loaded" :title="t('journey.vision.emptyTitle')" :text="t('journey.vision.emptyText')" />

        <form class="panel stack stack--tight" @submit.prevent="createBoard">
            <h3>{{ t('journey.vision.newBoard') }}</h3>
            <div class="head-actions">
                <input
                    v-model="boardForm.title"
                    class="input"
                    required
                    maxlength="80"
                    :placeholder="t('journey.vision.boardPh')"
                    :aria-label="t('journey.vision.boardPh')"
                />
                <select v-model="boardForm.area" class="input input--auto" :aria-label="t('journey.vision.area')">
                    <option v-for="area in VISION_AREAS" :key="area" :value="area">
                        {{ t(`journey.vision.areas.${area}`) }}
                    </option>
                </select>
                <select v-model="boardForm.color" class="input input--auto" :aria-label="t('journey.vision.color')">
                    <option v-for="color in VISION_COLORS" :key="color" :value="color">
                        {{ t(`journey.vision.colors.${color}`) }}
                    </option>
                </select>
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" /> {{ t('journey.vision.create') }}
                </button>
            </div>
        </form>

        <ConfirmDialog
            v-if="deleting"
            :message="t('journey.vision.confirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="removeBoard"
            @cancel="deleting = false"
        />
    </section>
</template>
