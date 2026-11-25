<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import BrandLogo from '../components/BrandLogo.vue'
import EmptyState from '../components/EmptyState.vue'
import ProjectCard from '../components/ProjectCard.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import type { PublicPortfolio } from '../types/domain'

/** The page behind a shared portfolio link. A private or unknown link shows the same "not available". */
const route = useRoute()
const portfolio = ref<PublicPortfolio | null>(null)
const missing = ref(false)

onMounted(async () => {
    try {
        portfolio.value = await repository.publicPortfolio(String(route.params.slug))
    } catch {
        missing.value = true
    }
})
</script>

<template>
    <main class="public-page">
        <RouterLink :to="{ name: 'auth' }" class="public-page__brand" aria-label="SN Tasks"
            ><BrandLogo label="SN Tasks"
        /></RouterLink>
        <template v-if="portfolio">
            <header class="panel public-page__owner">
                <UserAvatar :name="portfolio.owner.displayName" :src="portfolio.owner.avatarUrl" size="lg" />
                <div>
                    <h1>{{ portfolio.owner.displayName }}</h1>
                    <p v-if="portfolio.owner.specialties.length">
                        {{ portfolio.owner.specialties.map((key) => t(`specialty.${key}`)).join(' · ') }}
                    </p>
                    <p v-if="portfolio.owner.bio">{{ portfolio.owner.bio }}</p>
                </div>
            </header>
            <div v-if="portfolio.projects.length" class="project-grid">
                <ProjectCard v-for="project in portfolio.projects" :key="project.id" :project="project" />
            </div>
            <EmptyState v-else :title="t('journey.portfolio.emptyTitle')" :text="t('journey.portfolio.publicEmpty')" />
        </template>
        <EmptyState
            v-else-if="missing"
            :title="t('journey.portfolio.missingTitle')"
            :text="t('journey.portfolio.missingText')"
        />
    </main>
</template>
