import { FRAME_STYLES } from '../types/domain'

/** Share of a frame picture's width taken by the photo area inside it (the six frames are cut to this). */
export const FRAME_HOLE = 0.503

export type FrameStyle = (typeof FRAME_STYLES)[number]

export const isFrameStyle = (frame?: string | null): frame is FrameStyle =>
    !!frame && (FRAME_STYLES as readonly string[]).includes(frame)

/**
 * The picture to draw over a photo: one of the six illustrated frames, or a
 * frame picture an administrator uploaded. Null draws the plain photo.
 */
export function frameSrc(frame?: string | null): string | null {
    if (!frame || frame === 'none') return null
    if (frame.startsWith('/') || frame.startsWith('http')) return frame
    return isFrameStyle(frame) ? `/images/frames/${frame}.webp` : null
}
