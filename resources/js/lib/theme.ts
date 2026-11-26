/**
 * Themes bought in the store are data: an accent colour and, optionally, a
 * dark base colour. This module turns them into the design tokens the
 * stylesheet already reads (--gold-*, --accent-rgb, --bg-*), so every
 * button, border, heading and chart follows without per-theme CSS.
 */

type Rgb = [number, number, number]

const hexToRgb = (hex: string): Rgb => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)) as Rgb
const rgbToHex = (rgb: Rgb) =>
    `#${rgb
        .map((v) =>
            Math.round(Math.max(0, Math.min(255, v)))
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`

function rgbToHsl([r, g, b]: Rgb): [number, number, number] {
    r /= 255
    g /= 255
    b /= 255
    const max = Math.max(r, g, b)
    const min = Math.min(r, g, b)
    const l = (max + min) / 2
    if (max === min) return [0, 0, l * 100]
    const d = max - min
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
    const h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4
    return [h * 60, s * 100, l * 100]
}

function hslToRgb(h: number, s: number, l: number): Rgb {
    s /= 100
    l /= 100
    const k = (n: number) => (n + h / 30) % 12
    const a = s * Math.min(l, 1 - l)
    const f = (n: number) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))
    return [f(0) * 255, f(8) * 255, f(4) * 255]
}

const hsl = (h: number, s: number, l: number) =>
    rgbToHex(hslToRgb(h, Math.max(0, Math.min(100, s)), Math.max(0, Math.min(100, l))))
const channels = (hex: string) => hexToRgb(hex).join(' ')

/** Relative luminance (WCAG). */
function luminance(hex: string): number {
    const [r, g, b] = hexToRgb(hex).map((v) => {
        const c = v / 255
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
    })
    return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

export function contrast(a: string, b: string): number {
    const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m)
    return (x + 0.05) / (y + 0.05)
}

const DEFAULT_BASE = '#07080a'

/** CSS custom properties for a theme. The accent's lightness is kept in a readable band on dark backgrounds. */
export function themeTokens(accent: string, background: string | null): Record<string, string> {
    const [h, s, l] = rgbToHsl(hexToRgb(accent))
    const mid = Math.max(48, Math.min(68, l))
    const shades = {
        200: hsl(h, s * 0.9, Math.max(mid + 22, 84)),
        300: hsl(h, s, Math.min(80, mid + 12)),
        500: hsl(h, s, mid),
        700: hsl(h, s * 0.85, mid * 0.6),
        900: hsl(h, s * 0.5, 15),
    }
    const tokens: Record<string, string> = {
        '--gold-200': shades[200],
        '--gold-300': shades[300],
        '--gold-500': shades[500],
        '--gold-700': shades[700],
        '--gold-900': shades[900],
        '--accent-rgb': channels(shades[500]),
        '--accent-light-rgb': channels(shades[300]),
        '--gold-gradient': `linear-gradient(100deg, ${shades[200]} 0%, ${shades[500]} 45%, ${shades[700]} 100%)`,
        '--gold-metal': `linear-gradient(180deg, #ffffff 0%, ${shades[200]} 22%, ${shades[500]} 52%, ${shades[700]} 70%, ${shades[300]} 100%)`,
        '--text-on-gold': luminance(shades[500]) > 0.3 ? '#14110b' : '#ffffff',
        // Defined from the accent at the root, so they are restated for themes applied lower down (previews).
        '--line': `rgb(${channels(shades[500])} / 0.22)`,
        '--line-soft': `rgb(${channels(shades[500])} / 0.1)`,
        '--line-strong': `rgb(${channels(shades[500])} / 0.5)`,
    }
    if (background) {
        const [bh, bs, bl] = rgbToHsl(hexToRgb(background))
        tokens['--bg-900'] = background
        tokens['--bg-800'] = hsl(bh, bs, bl + 2)
        tokens['--bg-700'] = hsl(bh, bs, bl + 4)
        tokens['--backdrop-base'] =
            `linear-gradient(180deg, ${hsl(bh, bs, bl * 0.7)} 0%, ${background} 60%, ${hsl(bh, bs, bl * 0.6)} 100%)`
    }
    return tokens
}

/** Readability problems an administrator should know about before saving a theme. */
export function themeWarnings(accent: string, background: string | null): ('accentDark' | 'backgroundLight')[] {
    const base = background ?? DEFAULT_BASE
    const warnings: ('accentDark' | 'backgroundLight')[] = []
    if (contrast(themeTokens(accent, background)['--gold-300'], base) < 4.5) warnings.push('accentDark')
    if (luminance(base) > 0.05) warnings.push('backgroundLight')
    return warnings
}

const APPLIED: string[] = []

/** Dress the whole page in a theme, or (with null) return it to the platform's own gold. */
export function applyTheme(
    theme: { accent: string; background: string | null } | null,
    target: HTMLElement = document.documentElement,
): void {
    for (const name of APPLIED.splice(0)) target.style.removeProperty(name)
    if (!theme) return
    for (const [name, value] of Object.entries(themeTokens(theme.accent, theme.background))) {
        target.style.setProperty(name, value)
        APPLIED.push(name)
    }
}
