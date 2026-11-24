<script setup lang="ts">
/**
 * Decorative backdrop for the sign-in screens: night sky, gold ribbons, light
 * streaks and a reflective floor. Only cheap transform/opacity layers animate.
 */
</script>

<template>
    <div class="scene" aria-hidden="true">
        <div class="scene__sky" />
        <div class="scene__stars" />

        <div class="scene__ribbon scene__ribbon--one" />
        <div class="scene__ribbon scene__ribbon--two" />
        <div class="scene__streak scene__streak--one" />
        <div class="scene__streak scene__streak--two" />
        <div class="scene__flare scene__flare--one" />
        <div class="scene__flare scene__flare--two" />
        <div class="scene__floor" />
        <div class="scene__vignette" />
    </div>
</template>
