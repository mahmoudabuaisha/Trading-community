/**
 * Turns the 3D view into a vertical 9:16 card for reels and stories: the
 * scene cropped to fill, a dark fade at the bottom with the member's name,
 * title and numbers, and the SN Tasks mark.
 */
export interface CardText {
    name: string
    title: string | null
    lines: string[]
    brand: string
    dir: 'rtl' | 'ltr'
}

export const CARD_WIDTH = 720
export const CARD_HEIGHT = 1280

export function drawCard(
    ctx: CanvasRenderingContext2D,
    source: HTMLCanvasElement,
    text: CardText,
    accent: string,
): void {
    const { width, height } = ctx.canvas
    ctx.fillStyle = '#07060d'
    ctx.fillRect(0, 0, width, height)

    // Crop the scene to cover the card, keeping its middle.
    const scale = Math.max(width / source.width, height / source.height)
    const w = source.width * scale
    const h = source.height * scale
    ctx.drawImage(source, (width - w) / 2, (height - h) / 2 - height * 0.04, w, h)

    const fade = ctx.createLinearGradient(0, height * 0.55, 0, height)
    fade.addColorStop(0, 'rgba(7,6,13,0)')
    fade.addColorStop(0.45, 'rgba(7,6,13,0.75)')
    fade.addColorStop(1, 'rgba(7,6,13,0.96)')
    ctx.fillStyle = fade
    ctx.fillRect(0, height * 0.55, width, height * 0.45)

    const font = getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim() || 'sans-serif'
    ctx.direction = text.dir
    ctx.textAlign = 'center'
    ctx.fillStyle = '#f4ead6'
    ctx.font = `700 ${Math.round(width * 0.075)}px ${font}`
    ctx.fillText(text.name, width / 2, height * 0.8)
    if (text.title) {
        ctx.fillStyle = accent
        ctx.font = `600 ${Math.round(width * 0.04)}px ${font}`
        ctx.fillText(text.title, width / 2, height * 0.84)
    }
    ctx.fillStyle = '#d8cfbd'
    ctx.font = `500 ${Math.round(width * 0.037)}px ${font}`
    text.lines.forEach((line, index) => ctx.fillText(line, width / 2, height * 0.89 + index * width * 0.055))

    ctx.fillStyle = accent
    ctx.font = `700 ${Math.round(width * 0.04)}px ${font}`
    ctx.direction = 'ltr'
    ctx.fillText(text.brand, width / 2, height * 0.975)
}

/** A recorder for the card canvas, if this browser can make a video from it. */
export function videoSupport(): { mimeType: string; extension: string } | null {
    if (typeof MediaRecorder === 'undefined' || !('captureStream' in HTMLCanvasElement.prototype)) return null
    for (const [mimeType, extension] of [
        ['video/mp4;codecs=avc1', 'mp4'],
        ['video/mp4', 'mp4'],
        ['video/webm;codecs=vp9', 'webm'],
        ['video/webm', 'webm'],
    ] as const) {
        if (MediaRecorder.isTypeSupported(mimeType)) return { mimeType, extension }
    }
    return null
}

export function download(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 2000)
}
