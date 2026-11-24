<script setup lang="ts">
/**
 * The SN Tasks wordmark as vector art: crown, gold "S", steel "N", orbit ring
 * and lockup text. Colours come from design tokens; the bevel is an SVG
 * lighting filter so the mark stays sharp at any size.
 */
defineProps<{ label: string }>()
</script>

<template>
    <svg class="brand-logo" viewBox="0 0 560 470" role="img" :aria-label="label">
        <defs>
            <linearGradient id="bl-gold" x1="0" y1="0" x2="0.35" y2="1">
                <stop offset="0" class="bl-stop-gold-hi" />
                <stop offset="0.3" class="bl-stop-gold" />
                <stop offset="0.52" class="bl-stop-gold-lo" />
                <stop offset="0.68" class="bl-stop-gold-hi" />
                <stop offset="1" class="bl-stop-gold-deep" />
            </linearGradient>
            <linearGradient id="bl-steel" x1="0" y1="0" x2="0.3" y2="1">
                <stop offset="0" class="bl-stop-steel-hi" />
                <stop offset="0.38" class="bl-stop-steel" />
                <stop offset="0.56" class="bl-stop-steel-lo" />
                <stop offset="0.72" class="bl-stop-steel-hi" />
                <stop offset="1" class="bl-stop-steel-lo" />
            </linearGradient>
            <linearGradient id="bl-ring" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" class="bl-stop-gold" stop-opacity="0" />
                <stop offset="0.25" class="bl-stop-gold" />
                <stop offset="0.6" class="bl-stop-gold-hi" />
                <stop offset="1" class="bl-stop-gold" stop-opacity="0.15" />
            </linearGradient>
            <linearGradient id="bl-rule" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" class="bl-stop-gold" stop-opacity="0" />
                <stop offset="0.5" class="bl-stop-gold-hi" />
                <stop offset="1" class="bl-stop-gold" stop-opacity="0" />
            </linearGradient>

            <!-- Polished-metal bevel: blurred alpha as a height map, lit from the upper left. -->
            <filter id="bl-bevel" x="-10%" y="-10%" width="120%" height="130%">
                <feGaussianBlur in="SourceAlpha" stdDeviation="2.2" result="height" />
                <feSpecularLighting
                    in="height"
                    surfaceScale="7"
                    specularConstant="1.15"
                    specularExponent="26"
                    result="shine"
                    class="bl-light"
                >
                    <feDistantLight azimuth="235" elevation="52" />
                </feSpecularLighting>
                <feComposite in="shine" in2="SourceAlpha" operator="in" result="shineClipped" />
                <feDiffuseLighting
                    in="height"
                    surfaceScale="5"
                    diffuseConstant="1"
                    result="shade"
                    lighting-color="#ffffff"
                >
                    <feDistantLight azimuth="235" elevation="58" />
                </feDiffuseLighting>
                <feComposite
                    in="SourceGraphic"
                    in2="shade"
                    operator="arithmetic"
                    k1="1.15"
                    k2="0.1"
                    k3="0"
                    k4="0"
                    result="lit"
                />
                <feComposite in="lit" in2="SourceAlpha" operator="in" result="litClipped" />
                <feComposite
                    in="shineClipped"
                    in2="litClipped"
                    operator="arithmetic"
                    k1="0"
                    k2="0.75"
                    k3="1"
                    k4="0"
                    result="metal"
                />
                <feDropShadow in="metal" dx="0" dy="10" stdDeviation="9" flood-color="#000" flood-opacity="0.75" />
            </filter>
            <filter id="bl-glow" x="-30%" y="-80%" width="160%" height="260%">
                <feGaussianBlur stdDeviation="5" />
            </filter>
        </defs>

        <!-- Orbit ring: back half -->
        <g transform="rotate(-11 280 215)">
            <path
                d="M 28 215 A 252 78 0 0 1 532 215"
                fill="none"
                stroke="url(#bl-ring)"
                stroke-width="3"
                opacity="0.55"
            />
        </g>

        <!-- Crown -->
        <g filter="url(#bl-bevel)">
            <path
                fill="url(#bl-gold)"
                d="M232 96 L222 44 L250 66 L266 30 L280 62 L294 30 L310 66 L338 44 L328 96 Z M228 102 H332 V112 Q280 120 228 112 Z"
            />
            <circle cx="222" cy="40" r="6" fill="url(#bl-gold)" />
            <circle cx="266" cy="25" r="6" fill="url(#bl-gold)" />
            <circle cx="294" cy="25" r="6" fill="url(#bl-gold)" />
            <circle cx="338" cy="40" r="6" fill="url(#bl-gold)" />
        </g>

        <!-- Monogram -->
        <g filter="url(#bl-bevel)" class="bl-monogram">
            <text x="196" y="292" text-anchor="middle" fill="url(#bl-gold)">S</text>
            <text x="352" y="292" text-anchor="middle" fill="url(#bl-steel)">N</text>
        </g>

        <!-- Orbit ring: front half, with a soft bloom -->
        <g transform="rotate(-11 280 215)">
            <path
                d="M 532 215 A 252 78 0 0 1 28 215"
                fill="none"
                stroke="url(#bl-ring)"
                stroke-width="9"
                filter="url(#bl-glow)"
                opacity="0.7"
            />
            <path
                d="M 532 215 A 252 78 0 0 1 28 215"
                fill="none"
                stroke="url(#bl-ring)"
                stroke-width="5"
                stroke-linecap="round"
            />
            <path d="M 500 232 A 252 78 0 0 1 120 280" fill="none" stroke="#fff" stroke-width="1.2" opacity="0.7" />
        </g>

        <!-- Lockup -->
        <line x1="60" y1="368" x2="150" y2="368" stroke="url(#bl-rule)" stroke-width="2" />
        <line x1="410" y1="368" x2="500" y2="368" stroke="url(#bl-rule)" stroke-width="2" />
        <text class="bl-word" x="280" y="386" text-anchor="middle" fill="url(#bl-gold)">TASKS</text>
        <line x1="70" y1="412" x2="490" y2="412" stroke="url(#bl-rule)" stroke-width="1.5" />
        <text class="bl-motto" x="280" y="446" text-anchor="middle">PLAN • ORGANIZE • ACHIEVE</text>
    </svg>
</template>
