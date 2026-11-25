<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import { formatDate, t } from '../i18n'
import type { PortfolioProject } from '../types/domain'

/** One project of a portfolio, as its owner and its visitors both see it. */
defineProps<{ project: PortfolioProject }>()
</script>

<template>
    <article class="dash-card project-card">
        <img v-if="project.imageUrl" :src="project.imageUrl" alt="" class="project-card__image" loading="lazy" />
        <div class="project-card__body">
            <span class="count-pill">{{ t(`journey.portfolio.categories.${project.category}`) }}</span>
            <h3>{{ project.title }}</h3>
            <time v-if="project.completedOn" :datetime="project.completedOn">{{
                formatDate(`${project.completedOn}T00:00`, { month: 'long', year: 'numeric' })
            }}</time>
            <p v-if="project.description">{{ project.description }}</p>
            <div v-if="project.skills.length" class="row-actions">
                <span v-for="skill in project.skills" :key="skill" class="count-pill count-pill--gold">{{
                    skill
                }}</span>
            </div>
            <a
                v-if="project.link"
                :href="project.link"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="project-card__link"
            >
                <ExternalLink :size="14" aria-hidden="true" /> <bdi>{{ project.link.replace(/^https?:\/\//, '') }}</bdi>
            </a>
            <slot />
        </div>
    </article>
</template>
