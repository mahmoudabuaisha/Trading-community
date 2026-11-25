<script setup lang="ts">
import {
    BriefcaseBusiness,
    ChevronLeft,
    ChevronRight,
    Dna,
    GitBranch,
    History,
    Images,
    Map as MapIcon,
} from 'lucide-vue-next'
import { computed, type Component } from 'vue'
import { isRtl, t } from '../../i18n'

/** The growth hub: one door to each tool that looks at the long run rather than today. */
const tiles: { id: string; route: string; icon: Component }[] = [
    { id: 'insights', route: 'insights', icon: Dna },
    { id: 'timeline', route: 'timeline', icon: History },
    { id: 'roadmap', route: 'goals', icon: MapIcon },
    { id: 'vision', route: 'vision', icon: Images },
    { id: 'skills', route: 'skills', icon: GitBranch },
    { id: 'portfolio', route: 'portfolio', icon: BriefcaseBusiness },
]
const OpenIcon = computed(() => (isRtl.value ? ChevronLeft : ChevronRight))
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">GROWTH JOURNEY</span>
                <h2>{{ t('journey.head') }}</h2>
                <p>{{ t('journey.sub') }}</p>
            </div>
        </div>
        <div class="journey-grid">
            <RouterLink v-for="tile in tiles" :key="tile.id" :to="{ name: tile.route }" class="dash-card journey-tile">
                <span class="journey-tile__icon"><component :is="tile.icon" :size="26" aria-hidden="true" /></span>
                <span class="journey-tile__body">
                    <b>{{ t(`journey.tiles.${tile.id}.title`) }}</b>
                    <small>{{ t(`journey.tiles.${tile.id}.text`) }}</small>
                </span>
                <component :is="OpenIcon" :size="20" aria-hidden="true" />
            </RouterLink>
        </div>
    </section>
</template>
