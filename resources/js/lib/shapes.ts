/**
 * SVG paths for the node shapes a design can use. Each shape is drawn
 * around (cx, cy) and fits a circle of radius r, so layouts can swap
 * shapes without moving anything.
 */
export type NodeShape = 'circle' | 'rounded' | 'hexagon' | 'diamond' | 'star' | 'apple'

const round = (value: number) => Math.round(value * 10) / 10

function polygon(cx: number, cy: number, points: [number, number][]): string {
    return `M${points.map(([x, y]) => `${round(cx + x)},${round(cy + y)}`).join(' L')} Z`
}

export function shapePath(shape: NodeShape | string, cx: number, cy: number, r: number): string {
    switch (shape) {
        case 'rounded': {
            const w = r * 1.05
            const k = r * 0.35
            return `M${round(cx - w + k)},${round(cy - w)} H${round(cx + w - k)} Q${round(cx + w)},${round(cy - w)} ${round(cx + w)},${round(cy - w + k)} V${round(cy + w - k)} Q${round(cx + w)},${round(cy + w)} ${round(cx + w - k)},${round(cy + w)} H${round(cx - w + k)} Q${round(cx - w)},${round(cy + w)} ${round(cx - w)},${round(cy + w - k)} V${round(cy - w + k)} Q${round(cx - w)},${round(cy - w)} ${round(cx - w + k)},${round(cy - w)} Z`
        }
        case 'hexagon':
            return polygon(
                cx,
                cy,
                Array.from({ length: 6 }, (_, i) => [
                    r * Math.cos((Math.PI / 3) * i - Math.PI / 2),
                    r * Math.sin((Math.PI / 3) * i - Math.PI / 2),
                ]),
            )
        case 'diamond':
            return polygon(cx, cy, [
                [0, -r * 1.15],
                [r, 0],
                [0, r * 1.15],
                [-r, 0],
            ])
        case 'star':
            return polygon(
                cx,
                cy,
                Array.from({ length: 10 }, (_, i) => {
                    const radius = i % 2 === 0 ? r * 1.15 : r * 0.5
                    const angle = (Math.PI / 5) * i - Math.PI / 2
                    return [radius * Math.cos(angle), radius * Math.sin(angle)]
                }),
            )
        case 'apple': {
            // A body with a dip at the top; the stem and leaf are drawn by appleStem().
            const s = r / 10
            return `M${round(cx)},${round(cy - 6 * s)} C${round(cx + 3 * s)},${round(cy - 9 * s)} ${round(cx + 10 * s)},${round(cy - 8 * s)} ${round(cx + 10 * s)},${round(cy - 1 * s)} C${round(cx + 10 * s)},${round(cy + 6 * s)} ${round(cx + 5 * s)},${round(cy + 10 * s)} ${round(cx + 2 * s)},${round(cy + 9 * s)} C${round(cx + 1 * s)},${round(cy + 8.6 * s)} ${round(cx - 1 * s)},${round(cy + 8.6 * s)} ${round(cx - 2 * s)},${round(cy + 9 * s)} C${round(cx - 5 * s)},${round(cy + 10 * s)} ${round(cx - 10 * s)},${round(cy + 6 * s)} ${round(cx - 10 * s)},${round(cy - 1 * s)} C${round(cx - 10 * s)},${round(cy - 8 * s)} ${round(cx - 3 * s)},${round(cy - 9 * s)} ${round(cx)},${round(cy - 6 * s)} Z`
        }
        default:
            return `M${round(cx - r)},${round(cy)} a${r},${r} 0 1,0 ${r * 2},0 a${r},${r} 0 1,0 ${-r * 2},0 Z`
    }
}

/** The stem and leaf on top of an apple node. */
export function appleStem(cx: number, cy: number, r: number): { stem: string; leaf: string } {
    const s = r / 10
    return {
        stem: `M${round(cx)},${round(cy - 6 * s)} Q${round(cx + 0.5 * s)},${round(cy - 10 * s)} ${round(cx + 2 * s)},${round(cy - 12 * s)}`,
        leaf: `M${round(cx + 1.5 * s)},${round(cy - 10 * s)} Q${round(cx + 7 * s)},${round(cy - 14 * s)} ${round(cx + 9 * s)},${round(cy - 9 * s)} Q${round(cx + 4 * s)},${round(cy - 7 * s)} ${round(cx + 1.5 * s)},${round(cy - 10 * s)} Z`,
    }
}

/** Split a title over two lines for a round root node (each line clipped to `width`). */
export function twoLines(text: string, width = 10): [string, string] {
    const words = text.split(/\s+/)
    let first = ''
    let index = 0
    while (index < words.length && (first + ' ' + words[index]).trim().length <= width)
        first = (first + ' ' + words[index++]).trim()
    if (!first) return [clip(text, width), '']
    return [first, clip(words.slice(index).join(' '), width)]
}

/** Shorten a label to fit under a node; the full text goes in the node's tooltip. */
export function clip(text: string, max = 22): string {
    return text.length > max ? `${text.slice(0, max - 1)}…` : text
}
