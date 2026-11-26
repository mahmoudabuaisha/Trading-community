import { ref, watch } from 'vue'

/**
 * The sound that marks the end of a focus session. Built-in tones are
 * synthesised; a member may also pick a short audio file of their own,
 * kept on this device.
 */
export const TONES = ['bell', 'chime', 'alarm', 'soft', 'custom'] as const
export type Tone = (typeof TONES)[number]

const KEY = 'sn-dcc:focus-tone:v1'
const FILE_KEY = 'sn-dcc:focus-tone-file:v1'
/** Local storage holds a few megabytes; a ringtone has to fit. */
export const MAX_TONE_BYTES = 1_500_000

function readTone(): Tone {
    try {
        const stored = localStorage.getItem(KEY) as Tone | null
        return stored && TONES.includes(stored) ? stored : 'bell'
    } catch {
        return 'bell'
    }
}

export const focusTone = ref<Tone>(readTone())
export const customToneName = ref<string>(localStorage.getItem(FILE_KEY + ':name') ?? '')

watch(focusTone, (tone) => {
    try {
        localStorage.setItem(KEY, tone)
    } catch {
        /* the choice lasts for this visit only */
    }
})

/** Save a picked audio file as the custom tone. Returns false when it is too large or not audio. */
export async function saveCustomTone(file: File): Promise<'ok' | 'type' | 'size'> {
    if (!file.type.startsWith('audio/')) return 'type'
    if (file.size > MAX_TONE_BYTES) return 'size'
    const dataUrl = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(String(reader.result))
        reader.onerror = () => reject(reader.error)
        reader.readAsDataURL(file)
    })
    try {
        localStorage.setItem(FILE_KEY, dataUrl)
        localStorage.setItem(FILE_KEY + ':name', file.name)
    } catch {
        return 'size'
    }
    customToneName.value = file.name
    focusTone.value = 'custom'
    return 'ok'
}

let playing: { stop: () => void } | null = null

export function stopTone(): void {
    playing?.stop()
    playing = null
}

/** Notes of each built-in tone: [frequency, start, length] in seconds. */
const PATTERNS: Record<Exclude<Tone, 'custom'>, { wave: OscillatorType; notes: [number, number, number][] }> = {
    bell: {
        wave: 'sine',
        notes: [0, 1.2, 2.4].flatMap((t): [number, number, number][] => [
            [880, t, 1.1],
            [1320, t, 0.8],
        ]),
    },
    chime: {
        wave: 'sine',
        notes: [
            [784, 0, 0.6],
            [988, 0.25, 0.6],
            [1175, 0.5, 0.9],
            [1568, 0.8, 1.2],
        ],
    },
    alarm: {
        wave: 'square',
        notes: Array.from({ length: 12 }, (_, i): [number, number, number] => [i % 2 ? 990 : 1180, i * 0.28, 0.2]),
    },
    soft: {
        wave: 'triangle',
        notes: [
            [523, 0, 1.4],
            [659, 0.6, 1.4],
            [784, 1.2, 2],
        ],
    },
}

/** Play the chosen tone (or one being previewed). */
export function playTone(tone: Tone = focusTone.value): void {
    stopTone()
    if (tone === 'custom') {
        const source = localStorage.getItem(FILE_KEY)
        if (source) {
            const audio = new Audio(source)
            void audio.play().catch(() => playTone('bell'))
            const timer = window.setTimeout(() => audio.pause(), 30_000)
            playing = { stop: () => (audio.pause(), window.clearTimeout(timer)) }
            return
        }
        tone = 'bell'
    }
    try {
        const context = new AudioContext()
        const pattern = PATTERNS[tone]
        let end = 0
        for (const [frequency, start, length] of pattern.notes) {
            const gain = context.createGain()
            gain.connect(context.destination)
            const at = context.currentTime + start
            gain.gain.setValueAtTime(0.0001, at)
            gain.gain.exponentialRampToValueAtTime(tone === 'alarm' ? 0.05 : 0.12, at + 0.02)
            gain.gain.exponentialRampToValueAtTime(0.0001, at + length)
            const oscillator = context.createOscillator()
            oscillator.type = pattern.wave
            oscillator.frequency.value = frequency
            oscillator.connect(gain)
            oscillator.start(at)
            oscillator.stop(at + length)
            end = Math.max(end, start + length)
        }
        const timer = window.setTimeout(() => void context.close(), (end + 0.3) * 1000)
        playing = { stop: () => (window.clearTimeout(timer), void context.close()) }
    } catch {
        /* browsers that block audio stay silent */
    }
}
