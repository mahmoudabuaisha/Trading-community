<script setup lang="ts">
/**
 * Decorative summit behind the logo: a rendered, lit rock face with a waving
 * flag, a halo arc, drifting fog and rising gold dust. Every moving part is a
 * small transform/opacity layer, so nothing repaints the artwork itself.
 */

/** Deterministic dust motes: position, size, speed and delay as CSS variables. */
const dust = Array.from({ length: 10 }, (_, i) => ({
    '--x': `${8 + ((i * 37) % 84)}%`,
    '--size': `${2 + (i % 3)}px`,
    '--rise': `${9 + ((i * 5) % 8)}s`,
    '--delay': `${-((i * 1.7) % 12)}s`,
    '--sway': `${(i % 2 ? 1 : -1) * (10 + ((i * 7) % 18))}px`,
}))
</script>

<template>
    <div class="summit" aria-hidden="true">
        <div class="summit__halo" />
        <div class="summit__arc" />
        <img class="summit__fog summit__fog--back" src="/images/fog-2.webp" alt="" width="1600" height="560" />
        <img
            class="summit__rock"
            src="/images/summit.webp"
            srcset="/images/summit-800.webp 800w, /images/summit.webp 1600w"
            sizes="(max-width: 980px) 190vw, 1100px"
            width="1600"
            height="932"
            alt=""
            fetchpriority="high"
        />
        <svg class="summit__flag" viewBox="0 0 64 84">
            <line x1="4" y1="84" x2="4" y2="4" class="summit__pole" />
            <circle cx="4" cy="4" r="3" class="summit__finial" />
            <g class="summit__cloth">
                <path d="M5 8 C18 0 28 16 44 9 C50 6.5 55 6 60 8 C52 14 40 20 5 34 Z" />
                <path class="summit__fold" d="M5 8 C18 0 28 16 44 9 C38 14 26 17 5 22 Z" />
            </g>
        </svg>
        <img class="summit__fog summit__fog--front" src="/images/fog-1.webp" alt="" width="1600" height="560" />
        <div class="summit__dust">
            <i v-for="(mote, index) in dust" :key="index" :style="mote" />
        </div>
    </div>
</template>
