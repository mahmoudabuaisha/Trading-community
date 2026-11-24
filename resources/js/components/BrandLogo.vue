<script setup lang="ts">
/**
 * The SN Tasks wordmark as vector art: crown, gold "S", steel "N", orbit ring
 * and lockup text. Colours come from design tokens; the bevel is an SVG
 * lighting filter so the mark stays sharp at any size.
 *
 * Live effects (light sweep, orbiting spark, crown glints) live in a second,
 * filter-free SVG stacked on top, so animating them never re-rasterises the
 * bevelled artwork underneath.
 */
defineProps<{ label: string }>()
</script>

<template>
    <div class="brand-logo">
        <svg class="brand-logo__art" viewBox="0 0 560 470" role="img" :aria-label="label">
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
                        k1="1.05"
                        k2="0.12"
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
                        k2="0.55"
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
                    d="M230 98 L216 46 L247 70 L262 34 L280 66 L298 34 L313 70 L344 46 L330 98 Z M226 104 H334 V114 Q280 123 226 114 Z"
                />
                <circle cx="216" cy="41" r="6.5" fill="url(#bl-gold)" />
                <circle cx="262" cy="28" r="6.5" fill="url(#bl-gold)" />
                <circle cx="280" cy="60" r="5" fill="url(#bl-gold)" />
                <circle cx="298" cy="28" r="6.5" fill="url(#bl-gold)" />
                <circle cx="344" cy="41" r="6.5" fill="url(#bl-gold)" />
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

        <svg class="brand-logo__fx" viewBox="0 0 560 470" aria-hidden="true">
            <defs>
                <clipPath id="bl-letters" class="bl-monogram">
                    <text x="196" y="292" text-anchor="middle">S</text>
                    <text x="352" y="292" text-anchor="middle">N</text>
                </clipPath>
                <linearGradient id="bl-sweep" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#fff" stop-opacity="0" />
                    <stop offset="0.5" stop-color="#fff" stop-opacity="0.85" />
                    <stop offset="1" stop-color="#fff" stop-opacity="0" />
                </linearGradient>
                <radialGradient id="bl-spark">
                    <stop offset="0" stop-color="#fff" />
                    <stop offset="0.35" class="bl-stop-gold-hi" stop-opacity="0.9" />
                    <stop offset="1" class="bl-stop-gold" stop-opacity="0" />
                </radialGradient>
            </defs>

            <!-- Light sweeping across the letters -->
            <g clip-path="url(#bl-letters)">
                <rect
                    class="bl-sweep"
                    x="-160"
                    y="80"
                    width="120"
                    height="240"
                    fill="url(#bl-sweep)"
                    transform="skewX(-18)"
                />
            </g>

            <!-- Spark travelling around the orbit ring -->
            <g transform="rotate(-11 280 215)">
                <g class="bl-comet">
                    <circle r="16" fill="url(#bl-spark)" />
                    <circle r="3" fill="#fff" />
                </g>
            </g>

            <!-- Glints on the crown -->
            <path
                class="bl-glint bl-glint--one"
                d="M262 10 L265 25 L280 28 L265 31 L262 46 L259 31 L244 28 L259 25 Z"
            />
            <path
                class="bl-glint bl-glint--two"
                d="M344 26 L346 38 L358 41 L346 44 L344 56 L342 44 L330 41 L342 38 Z"
            />
            <path
                class="bl-glint bl-glint--three"
                d="M150 150 L152 162 L164 165 L152 168 L150 180 L148 168 L136 165 L148 162 Z"
            />
        </svg>
    </div>
</template>
