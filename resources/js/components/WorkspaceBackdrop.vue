<script setup lang="ts">
/** A bought theme may bring its own picture; it then takes the summit's place. */
defineProps<{ image?: string | null }>()
/**
 * Decorative backdrop for the signed-in workspace: night sky, the summit with
 * its flag behind the header, a halo and mist. Static apart from the flag,
 * so long pages scroll smoothly over it.
 */
</script>

<template>
    <div class="backdrop" aria-hidden="true">
        <div class="backdrop__sky" />
        <img v-if="image" :src="image" alt="" class="backdrop__theme" />
        <div v-else class="backdrop__summit">
            <div class="summit__arc" />
            <img
                class="backdrop__rock"
                src="/images/summit.webp"
                srcset="/images/summit-800.webp 800w, /images/summit.webp 1600w"
                sizes="(max-width: 980px) 150vw, 1100px"
                width="1600"
                height="932"
                alt=""
            />
            <svg class="summit__flag" viewBox="0 0 64 84">
                <line x1="4" y1="84" x2="4" y2="4" class="summit__pole" />
                <circle cx="4" cy="4" r="3" class="summit__finial" />
                <g class="summit__cloth">
                    <path d="M5 8 C18 0 28 16 44 9 C50 6.5 55 6 60 8 C52 14 40 20 5 34 Z" />
                    <path class="summit__fold" d="M5 8 C18 0 28 16 44 9 C38 14 26 17 5 22 Z" />
                </g>
            </svg>
            <img class="backdrop__fog" src="/images/fog-1.webp" alt="" width="1600" height="560" />
        </div>
        <div class="backdrop__veil" />
    </div>
</template>
