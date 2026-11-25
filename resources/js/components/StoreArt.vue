<script setup lang="ts">
import { Palette, Tag, UserRound } from 'lucide-vue-next'
import type { StoreItem } from '../types/domain'
import UserAvatar from './UserAvatar.vue'

/**
 * The picture of a store item: its uploaded image when it has one, otherwise
 * a drawing of what it does (the frame on an avatar, the theme's colours, the title).
 */
defineProps<{ item: Pick<StoreItem, 'kind' | 'value' | 'imageUrl' | 'name'> }>()
</script>

<template>
    <span
        class="store-art"
        :class="`store-art--${item.kind}`"
        :data-theme="item.kind === 'theme' ? item.value : undefined"
    >
        <img v-if="item.imageUrl" :src="item.imageUrl" alt="" loading="lazy" />
        <UserAvatar v-else-if="item.kind === 'frame'" name="SN" :frame="item.value" size="lg" />
        <span v-else-if="item.kind === 'theme'" class="store-art__swatch"
            ><Palette :size="30" aria-hidden="true"
        /></span>
        <span v-else-if="item.kind === 'title'" class="store-art__title"
            ><Tag :size="18" aria-hidden="true" /> {{ item.value }}</span
        >
        <UserRound v-else :size="40" aria-hidden="true" />
    </span>
</template>
