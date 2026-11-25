<script setup lang="ts">
import { Copy, ImagePlus, Plus, RefreshCw } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import ConfirmDialog from '../../components/ConfirmDialog.vue'
import EmptyState from '../../components/EmptyState.vue'
import JourneyHead from '../../components/JourneyHead.vue'
import ProjectCard from '../../components/ProjectCard.vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import {
    PORTFOLIO_CATEGORIES,
    PORTFOLIO_VISIBILITIES,
    type Portfolio,
    type PortfolioProject,
    type PortfolioVisibility,
} from '../../types/domain'

const ui = useUiStore()
const portfolio = ref<Portfolio>({ projects: [], visibility: 'private', slug: null })
const loaded = ref(false)
const filter = ref<PortfolioProject['category'] | 'all'>('all')
const shown = computed(() =>
    portfolio.value.projects.filter((project) => filter.value === 'all' || project.category === filter.value),
)
const usedCategories = computed(() =>
    PORTFOLIO_CATEGORIES.filter((category) =>
        portfolio.value.projects.some((project) => project.category === category),
    ),
)
const link = computed(() => (portfolio.value.slug ? `${window.location.origin}/p/${portfolio.value.slug}` : ''))

async function run(action: () => Promise<Portfolio | void>, successKey?: string): Promise<boolean> {
    try {
        const result = await action()
        if (result) portfolio.value = result
        if (successKey) ui.success(t(successKey))
        return true
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        ui.failure(error)
        return false
    }
}
onMounted(async () => {
    await run(() => repository.portfolio())
    loaded.value = true
})

const blank = () => ({
    id: null as number | null,
    title: '',
    description: '',
    category: 'trading' as PortfolioProject['category'],
    skills: '',
    link: '',
    completedOn: '',
})
const draft = reactive(blank())
const image = ref<File | null>(null)
const errors = ref<Record<string, string[]>>({})
const fileInput = ref<HTMLInputElement | null>(null)
const deleting = ref<PortfolioProject | null>(null)

function reset(): void {
    Object.assign(draft, blank())
    image.value = null
    errors.value = {}
}
function edit(project: PortfolioProject): void {
    Object.assign(draft, {
        ...project,
        skills: project.skills.join('، '),
        link: project.link ?? '',
        completedOn: project.completedOn ?? '',
    })
    image.value = null
    window.scrollTo({ top: 0, behavior: 'smooth' })
}
async function save(): Promise<void> {
    if (!draft.title.trim()) return
    errors.value = {}
    const saved = await run(
        () =>
            repository.saveProject(draft.id, {
                title: draft.title.trim(),
                description: draft.description.trim(),
                category: draft.category,
                skills: draft.skills
                    .split(/[,،]/)
                    .map((skill) => skill.trim())
                    .filter(Boolean)
                    .slice(0, 12),
                link: draft.link.trim() || null,
                completedOn: draft.completedOn || null,
                image: image.value,
            }),
        'journey.portfolio.saved',
    )
    if (saved) reset()
}
async function confirmDelete(): Promise<void> {
    const project = deleting.value
    deleting.value = null
    if (project && (await run(() => repository.deleteProject(project.id)))) await run(() => repository.portfolio())
}

const setVisibility = (event: Event) =>
    run(
        () =>
            repository.portfolioSettings({
                visibility: (event.target as HTMLSelectElement).value as PortfolioVisibility,
            }),
        'journey.portfolio.settingsSaved',
    )
const newLink = () => run(() => repository.portfolioSettings({ newLink: true }), 'journey.portfolio.linkChanged')
async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(link.value)
        ui.success(t('journey.portfolio.copied'))
    } catch {
        ui.failure(new ApiError(t('journey.portfolio.copyFailed'), 422))
    }
}
</script>

<template>
    <section class="stack">
        <JourneyHead
            eyebrow="ACHIEVEMENT PORTFOLIO"
            :title="t('journey.tiles.portfolio.title')"
            :text="t('journey.portfolio.sub')"
        />

        <div class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('journey.portfolio.shareHead') }}</h3>
                    <p>{{ t(`journey.portfolio.visibilityHint.${portfolio.visibility}`) }}</p>
                </div>
                <select
                    class="input input--auto"
                    :value="portfolio.visibility"
                    :aria-label="t('journey.portfolio.shareHead')"
                    @change="setVisibility"
                >
                    <option v-for="value in PORTFOLIO_VISIBILITIES" :key="value" :value="value">
                        {{ t(`journey.portfolio.visibility.${value}`) }}
                    </option>
                </select>
            </div>
            <div v-if="portfolio.visibility !== 'private' && link" class="broadcast-now">
                <input
                    class="input"
                    readonly
                    dir="ltr"
                    :value="link"
                    :aria-label="t('journey.portfolio.link')"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <button type="button" class="btn" @click="copy">
                    <Copy :size="15" aria-hidden="true" /> {{ t('journey.portfolio.copy') }}
                </button>
                <button
                    type="button"
                    class="btn btn--outline"
                    :title="t('journey.portfolio.newLinkHint')"
                    @click="newLink"
                >
                    <RefreshCw :size="15" aria-hidden="true" /> {{ t('journey.portfolio.newLink') }}
                </button>
            </div>
        </div>

        <form class="panel stack stack--tight" @submit.prevent="save">
            <h3>{{ t(draft.id === null ? 'journey.portfolio.addHead' : 'journey.portfolio.editHead') }}</h3>
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('journey.portfolio.title') }}</span>
                    <input v-model="draft.title" class="input" required maxlength="120" />
                </label>
                <label class="field">
                    <span>{{ t('journey.portfolio.category') }}</span>
                    <select v-model="draft.category" class="input">
                        <option v-for="category in PORTFOLIO_CATEGORIES" :key="category" :value="category">
                            {{ t(`journey.portfolio.categories.${category}`) }}
                        </option>
                    </select>
                </label>
                <label class="field field--wide">
                    <span>{{ t('journey.portfolio.description') }}</span>
                    <textarea v-model="draft.description" class="input" rows="3" maxlength="5000" />
                </label>
                <label class="field">
                    <span>{{ t('journey.portfolio.skills') }}</span>
                    <input v-model="draft.skills" class="input" :placeholder="t('journey.portfolio.skillsPh')" />
                </label>
                <label class="field">
                    <span>{{ t('journey.portfolio.linkField') }}</span>
                    <input
                        v-model="draft.link"
                        class="input"
                        type="url"
                        dir="ltr"
                        maxlength="500"
                        placeholder="https://"
                    />
                    <small v-if="errors.link" class="field__error">{{ errors.link[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('journey.portfolio.completedOn') }}</span>
                    <input v-model="draft.completedOn" class="input" type="date" />
                </label>
            </div>
            <div class="head-actions">
                <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                    <ImagePlus :size="15" aria-hidden="true" /> {{ image ? image.name : t('journey.portfolio.image') }}
                </button>
                <input
                    ref="fileInput"
                    class="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    tabindex="-1"
                    @change="image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                />
                <small v-if="errors.image" class="field__error">{{ errors.image[0] }}</small>
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" />
                    {{ t(draft.id === null ? 'journey.portfolio.add' : 'common.save') }}
                </button>
                <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="reset">
                    {{ t('common.cancel') }}
                </button>
            </div>
        </form>

        <nav v-if="usedCategories.length > 1" class="tabs" :aria-label="t('journey.portfolio.category')">
            <button
                type="button"
                :class="{ tabs__active: filter === 'all' }"
                :aria-pressed="filter === 'all'"
                @click="filter = 'all'"
            >
                {{ t('store.all') }}
            </button>
            <button
                v-for="category in usedCategories"
                :key="category"
                type="button"
                :class="{ tabs__active: filter === category }"
                :aria-pressed="filter === category"
                @click="filter = category"
            >
                {{ t(`journey.portfolio.categories.${category}`) }}
            </button>
        </nav>
        <div v-if="shown.length" class="project-grid">
            <ProjectCard v-for="project in shown" :key="project.id" :project="project">
                <div class="row-actions">
                    <button type="button" class="pill" @click="edit(project)">{{ t('common.edit') }}</button>
                    <button type="button" class="pill" @click="deleting = project">{{ t('common.delete') }}</button>
                </div>
            </ProjectCard>
        </div>
        <EmptyState
            v-else-if="loaded"
            :title="t('journey.portfolio.emptyTitle')"
            :text="t('journey.portfolio.emptyText')"
        />

        <ConfirmDialog
            v-if="deleting"
            :message="t('journey.portfolio.confirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </section>
</template>
