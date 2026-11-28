<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { officialLinks } from '../lib/content'

/** "Follow us": the official Instagram and Telegram accounts, grouped by network. */
defineProps<{ compact?: boolean }>()
const groups = computed(() =>
    (['instagram', 'telegram', 'other'] as const)
        .map((kind) => ({ kind, links: officialLinks.value.filter((link) => link.kind === kind) }))
        .filter((group) => group.links.length),
)
</script>

<template>
    <section v-if="groups.length" class="official-links" :class="{ 'official-links--compact': compact }">
        <h2 class="official-links__head">{{ t('links.head') }}</h2>
        <div v-for="group in groups" :key="group.kind" class="official-links__group">
            <a
                v-for="link in group.links"
                :key="link.url"
                :href="link.url"
                target="_blank"
                rel="noopener noreferrer"
                class="official-link"
                :class="`official-link--${link.kind}`"
                :aria-label="`${t(`links.kinds.${link.kind}`)}: ${link.label}`"
            >
                <svg
                    v-if="link.kind === 'instagram'"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    class="official-link__icon"
                >
                    <rect
                        x="3"
                        y="3"
                        width="18"
                        height="18"
                        rx="5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                    <circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="2" />
                    <circle cx="17.4" cy="6.6" r="1.2" fill="currentColor" />
                </svg>
                <svg
                    v-else-if="link.kind === 'telegram'"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    class="official-link__icon"
                >
                    <path
                        fill="currentColor"
                        d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.7.8l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.5 13.2 1.8 11.7c-1-.3-1-1 .2-1.5L20.6 3c.9-.3 1.6.2 1.3 1.3z"
                    />
                </svg>
                <svg v-else viewBox="0 0 24 24" aria-hidden="true" class="official-link__icon">
                    <path
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"
                    />
                </svg>
                <span>{{ link.label }}</span>
            </a>
        </div>
    </section>
</template>
