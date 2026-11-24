<script setup lang="ts">
/**
 * Decorative summit behind the logo: a faceted rock face lit from the upper
 * right, with a flag, a halo arc and cloud banks. Static SVG — the noise
 * filters are painted once and never animated.
 */
</script>

<template>
    <svg class="summit" viewBox="0 0 1200 700" preserveAspectRatio="xMidYMin meet" aria-hidden="true">
        <defs>
            <linearGradient id="sc-depth" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#000" stop-opacity="0" />
                <stop offset="0.5" stop-color="#000" stop-opacity="0.35" />
                <stop offset="1" stop-color="#000" stop-opacity="0.9" />
            </linearGradient>
            <linearGradient id="sc-rim" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" class="sc-stop-gold" stop-opacity="1" />
                <stop offset="0.55" class="sc-stop-gold" stop-opacity="0.35" />
                <stop offset="1" class="sc-stop-gold" stop-opacity="0" />
            </linearGradient>
            <radialGradient id="sc-halo" cx="0.5" cy="0.42" r="0.4">
                <stop offset="0" class="sc-stop-gold" stop-opacity="0.36" />
                <stop offset="0.55" class="sc-stop-gold" stop-opacity="0.08" />
                <stop offset="1" class="sc-stop-gold" stop-opacity="0" />
            </radialGradient>
            <linearGradient id="sc-fade" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0.72" stop-color="#fff" />
                <stop offset="1" stop-color="#fff" stop-opacity="0" />
            </linearGradient>
            <mask id="sc-ground"><rect width="1200" height="700" fill="url(#sc-fade)" /></mask>
            <clipPath id="sc-silhouette">
                <path
                    d="M0 690 L20 660 L60 668 L118 612 L156 626 L216 556 L254 572 L308 506 L338 520 L384 460 L420 478 L456 408 L482 424 L512 350 L556 280 L574 300 L600 178 L614 208 L634 244 L650 232 L680 304 L702 290 L738 370 L766 354 L808 440 L842 424 L886 508 L922 492 L972 566 L1010 550 L1062 620 L1102 604 L1156 668 L1200 690 L1200 700 L0 700 Z"
                />
            </clipPath>

            <!-- Rock grain: stretched fractal noise as a height map, mixed in at half strength. -->
            <filter id="sc-relief" x="0" y="0" width="100%" height="100%">
                <feGaussianBlur in="SourceGraphic" stdDeviation="1.4" result="soft" />
                <feTurbulence type="fractalNoise" baseFrequency="0.028 0.07" numOctaves="5" seed="9" result="noise" />
                <feDiffuseLighting
                    in="noise"
                    surfaceScale="6"
                    diffuseConstant="1.15"
                    lighting-color="#fff"
                    result="lit"
                >
                    <feDistantLight azimuth="300" elevation="42" />
                </feDiffuseLighting>
                <feComposite
                    in="lit"
                    in2="soft"
                    operator="arithmetic"
                    k1="1.25"
                    k2="0"
                    k3="0.12"
                    k4="0"
                    result="grained"
                />
                <feComposite in="grained" in2="SourceAlpha" operator="in" />
            </filter>
            <!-- Cloud banks: soft fractal noise turned into alpha. -->
            <filter id="sc-cloud" x="-10%" y="-10%" width="120%" height="120%">
                <feTurbulence type="fractalNoise" baseFrequency="0.0065 0.013" numOctaves="5" seed="4" />
                <feColorMatrix values="0 0 0 0 0.62  0 0 0 0 0.64  0 0 0 0 0.72  0 0 0 2.6 -1.05" />
                <feGaussianBlur stdDeviation="3" />
            </filter>
            <radialGradient id="sc-cloud-l" cx="0.21" cy="0.68" r="0.2">
                <stop offset="0" stop-color="#fff" />
                <stop offset="1" stop-color="#fff" stop-opacity="0" />
            </radialGradient>
            <radialGradient id="sc-cloud-r" cx="0.79" cy="0.64" r="0.2">
                <stop offset="0" stop-color="#fff" />
                <stop offset="1" stop-color="#fff" stop-opacity="0" />
            </radialGradient>
            <mask id="sc-cloud-mask">
                <rect width="1200" height="700" fill="url(#sc-cloud-l)" />
                <rect width="1200" height="700" fill="url(#sc-cloud-r)" />
            </mask>
            <filter id="sc-soft"><feGaussianBlur stdDeviation="2.6" /></filter>
        </defs>

        <rect width="1200" height="700" fill="url(#sc-halo)" />
        <!-- Halo arc behind the summit -->
        <path
            d="M 290 450 A 330 330 0 0 1 910 450"
            fill="none"
            class="sc-stroke-gold"
            stroke-width="1.6"
            opacity="0.55"
        />
        <path
            d="M 290 450 A 330 330 0 0 1 910 450"
            fill="none"
            class="sc-stroke-gold"
            stroke-width="7"
            opacity="0.25"
            filter="url(#sc-soft)"
        />

        <g mask="url(#sc-ground)">
            <!-- Rock faces: one wedge per ridge segment, lit or shadowed by which way it faces. -->
            <g clip-path="url(#sc-silhouette)">
                <g filter="url(#sc-relief)">
                    <path fill="#77736d" d="M600 178 L614 208 L619 464 L605 395 Z" />
                    <path fill="#4b4844" d="M605 395 L619 464 L624 700 L600 700 Z" />
                    <path fill="#9d9890" d="M614 208 L634 244 L653 439 L619 464 Z" />
                    <path fill="#6c6862" d="M619 464 L653 439 L660 700 L624 700 Z" />
                    <path fill="#2b2927" d="M634 244 L650 232 L671 493 L653 439 Z" />
                    <path fill="#1d1c1a" d="M653 439 L671 493 L688 700 L660 700 Z" />
                    <path fill="#a39e95" d="M650 232 L680 304 L702 498 L671 493 Z" />
                    <path fill="#8e8a82" d="M671 493 L702 498 L740 700 L688 700 Z" />
                    <path fill="#2a2926" d="M680 304 L702 290 L728 446 L702 498 Z" />
                    <path fill="#232220" d="M702 498 L728 446 L778 700 L740 700 Z" />
                    <path fill="#8d8981" d="M702 290 L738 370 L779 538 L728 446 Z" />
                    <path fill="#74716a" d="M728 446 L779 538 L842 700 L778 700 Z" />
                    <path fill="#1e1d1c" d="M738 370 L766 354 L828 546 L779 538 Z" />
                    <path fill="#171615" d="M779 538 L828 546 L890 700 L842 700 Z" />
                    <path fill="#9b968e" d="M766 354 L808 440 L862 552 L828 546 Z" />
                    <path fill="#696560" d="M828 546 L862 552 L964 700 L890 700 Z" />
                    <path fill="#2d2c29" d="M808 440 L842 424 L957 589 L862 552 Z" />
                    <path fill="#272624" d="M862 552 L957 589 L1024 700 L964 700 Z" />
                    <path fill="#837f78" d="M842 424 L886 508 L1012 618 L957 589 Z" />
                    <path fill="#5f5c57" d="M957 589 L1012 618 L1100 700 L1024 700 Z" />
                    <path fill="#242321" d="M886 508 L922 492 L1011 573 L1012 618 Z" />
                    <path fill="#1a1a18" d="M1012 618 L1011 573 L1164 700 L1100 700 Z" />
                    <path fill="#a4a096" d="M922 492 L972 566 L1098 632 L1011 573 Z" />
                    <path fill="#78746d" d="M1011 573 L1098 632 L1251 700 L1164 700 Z" />
                    <path fill="#1c1b1a" d="M972 566 L1010 550 L1188 638 L1098 632 Z" />
                    <path fill="#121211" d="M1098 632 L1188 638 L1318 700 L1251 700 Z" />
                    <path fill="#959088" d="M1010 550 L1062 620 L1214 653 L1188 638 Z" />
                    <path fill="#86827a" d="M1188 638 L1214 653 L1408 700 L1318 700 Z" />
                    <path fill="#252422" d="M1062 620 L1102 604 L1267 643 L1214 653 Z" />
                    <path fill="#1c1b1a" d="M1214 653 L1267 643 L1478 700 L1408 700 Z" />
                    <path fill="#8b877f" d="M1102 604 L1156 668 L1357 683 L1267 643 Z" />
                    <path fill="#73706a" d="M1267 643 L1357 683 L1573 700 L1478 700 Z" />
                    <path fill="#79756e" d="M1156 668 L1200 690 L1405 695 L1357 683 Z" />
                    <path fill="#5f5c57" d="M1357 683 L1405 695 L1650 700 L1573 700 Z" />
                    <path fill="#252422" d="M600 178 L574 300 L557 468 L603 440 Z" />
                    <path fill="#1a1918" d="M603 440 L557 468 L554 700 L600 700 Z" />
                    <path fill="#52504b" d="M574 300 L556 280 L553 472 L557 468 Z" />
                    <path fill="#373532" d="M557 468 L553 472 L523 700 L554 700 Z" />
                    <path fill="#22211f" d="M556 280 L512 350 L485 510 L553 472 Z" />
                    <path fill="#181716" d="M553 472 L485 510 L446 700 L523 700 Z" />
                    <path fill="#32302d" d="M512 350 L482 424 L431 547 L485 510 Z" />
                    <path fill="#2c2b28" d="M485 510 L431 547 L394 700 L446 700 Z" />
                    <path fill="#5b5954" d="M482 424 L456 408 L422 523 L431 547 Z" />
                    <path fill="#45433f" d="M431 547 L422 523 L348 700 L394 700 Z" />
                    <path fill="#252422" d="M456 408 L420 478 L342 600 L422 523 Z" />
                    <path fill="#21201e" d="M422 523 L342 600 L285 700 L348 700 Z" />
                    <path fill="#55534e" d="M420 478 L384 460 L303 587 L342 600 Z" />
                    <path fill="#464440" d="M342 600 L303 587 L222 700 L285 700 Z" />
                    <path fill="#21201e" d="M384 460 L338 520 L237 624 L303 587 Z" />
                    <path fill="#161614" d="M303 587 L237 624 L142 700 L222 700 Z" />
                    <path fill="#5e5b56" d="M338 520 L308 506 L219 597 L237 624 Z" />
                    <path fill="#4f4d48" d="M237 624 L219 597 L89 700 L142 700 Z" />
                    <path fill="#31302d" d="M308 506 L254 572 L103 648 L219 597 Z" />
                    <path fill="#272623" d="M219 597 L103 648 L-6 700 L89 700 Z" />
                    <path fill="#52504b" d="M254 572 L216 556 L50 639 L103 648 Z" />
                    <path fill="#403e3b" d="M103 648 L50 639 L-72 700 L-6 700 Z" />
                    <path fill="#312f2c" d="M216 556 L156 626 L-5 660 L50 639 Z" />
                    <path fill="#22211f" d="M50 639 L-5 660 L-177 700 L-72 700 Z" />
                    <path fill="#55534e" d="M156 626 L118 612 L-99 662 L-5 660 Z" />
                    <path fill="#3b3a36" d="M-5 660 L-99 662 L-244 700 L-177 700 Z" />
                    <path fill="#2f2e2b" d="M118 612 L60 668 L-92 681 L-99 662 Z" />
                    <path fill="#1d1c1b" d="M-99 662 L-92 681 L-345 700 L-244 700 Z" />
                    <path fill="#5a5752" d="M60 668 L20 660 L-220 682 L-92 681 Z" />
                    <path fill="#504d49" d="M-92 681 L-220 682 L-415 700 L-345 700 Z" />
                    <path fill="#21201e" d="M20 660 L0 690 L-266 696 L-220 682 Z" />
                    <path fill="#1a1918" d="M-220 682 L-266 696 L-450 700 L-415 700 Z" />
                </g>
                <rect width="1200" height="700" fill="url(#sc-depth)" />
            </g>
            <!-- Gold rim light along the lit ridge -->
            <path
                fill="none"
                stroke="url(#sc-rim)"
                stroke-width="2.4"
                stroke-linejoin="round"
                d="M600 178 L614 208 L634 244 L650 232 L680 304 L702 290 L738 370 L766 354 L808 440 L842 424 L886 508"
            />
            <path
                fill="none"
                stroke="url(#sc-rim)"
                stroke-width="8"
                stroke-linejoin="round"
                opacity="0.35"
                filter="url(#sc-soft)"
                d="M600 178 L614 208 L634 244 L650 232 L680 304 L702 290 L738 370 L766 354 L808 440 L842 424 L886 508"
            />
        </g>

        <!-- Flag -->
        <line x1="600" y1="180" x2="600" y2="108" class="sc-stroke-gold" stroke-width="2.4" stroke-linecap="round" />
        <path class="sc-fill-gold" d="M601 110 q17 -9 36 1 q-16 5 -36 21 z" />

        <rect width="1200" height="700" filter="url(#sc-cloud)" mask="url(#sc-cloud-mask)" opacity="0.55" />
    </svg>
</template>
