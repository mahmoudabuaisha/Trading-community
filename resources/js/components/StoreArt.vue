<script setup lang="ts">
import { Package, Palette, UserRound } from 'lucide-vue-next'
import type { StoreItem } from '../types/domain'
import TitleBadge from './TitleBadge.vue'
import UserAvatar from './UserAvatar.vue'
import { isLivePortrait } from '../lib/portraits'

/**
 * The picture of a store item: its uploaded image when it has one, otherwise
 * a drawing of what it does (the frame on an avatar, the theme's colours, the title).
 */
defineProps<{
    item: Pick<StoreItem, 'kind' | 'value' | 'imageUrl' | 'name'> &
        Partial<Pick<StoreItem, 'style'>> &
        Partial<Pick<StoreItem, 'themeAccent' | 'themeBackground' | 'themeImageUrl'>>
}>()
</script>

<template>
    <span
        class="store-art"
        :class="[
            `store-art--${item.kind}`,
            { 'portrait-live': item.kind === 'avatar' && isLivePortrait(item.imageUrl) },
        ]"
        :style="
            item.kind === 'theme' && item.themeAccent
                ? { '--swatch': item.themeAccent, '--swatch-bg': item.themeBackground ?? '#07080a' }
                : undefined
        "
    >
        <UserAvatar
            v-if="item.kind === 'frame' && item.value === 'image' && item.imageUrl"
            name="SN"
            :frame="item.imageUrl"
            size="lg"
        />
        <img
            v-else-if="item.imageUrl ?? (item.kind === 'theme' ? item.themeImageUrl : null)"
            :src="(item.imageUrl ?? item.themeImageUrl)!"
            alt=""
            loading="lazy"
        />
        <UserAvatar v-else-if="item.kind === 'frame'" name="SN" :frame="item.value" size="lg" />
        <span v-else-if="item.kind === 'theme'" class="store-art__swatch"
            ><Palette :size="30" aria-hidden="true"
        /></span>
        <span v-else-if="item.kind === 'emblem'" class="store-art__emblem">{{ item.value || '✦' }}</span>
        <TitleBadge v-else-if="item.kind === 'title'" :text="item.value ?? ''" :look="item.style" size="md" />
        <Package v-else-if="item.kind === 'product'" :size="40" aria-hidden="true" />
        <UserRound v-else :size="40" aria-hidden="true" />
    </span>
</template>
