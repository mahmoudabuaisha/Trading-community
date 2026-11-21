/** Largest data URL the API accepts for an avatar. */
export const AVATAR_MAX_CHARS = 520_000
const AVATAR_MAX_BYTES = 8 * 1024 * 1024
const AVATAR_EDGE = 420

export type AvatarError = 'invalid' | 'tooBig' | 'failed'

/** Downscale an image file to a compact JPEG data URL suitable for storing as an avatar. */
export async function fileToAvatar(
    file: File,
): Promise<{ ok: true; dataUrl: string } | { ok: false; reason: AvatarError }> {
    if (!file.type.startsWith('image/') || file.size > AVATAR_MAX_BYTES) return { ok: false, reason: 'invalid' }
    try {
        const bitmap = await createImageBitmap(file)
        const scale = Math.min(1, AVATAR_EDGE / Math.max(bitmap.width, bitmap.height))
        const canvas = document.createElement('canvas')
        canvas.width = Math.max(1, Math.round(bitmap.width * scale))
        canvas.height = Math.max(1, Math.round(bitmap.height * scale))
        const context = canvas.getContext('2d')
        if (!context) return { ok: false, reason: 'failed' }
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
        bitmap.close()
        const dataUrl = canvas.toDataURL('image/jpeg', 0.78)
        return dataUrl.length > AVATAR_MAX_CHARS ? { ok: false, reason: 'tooBig' } : { ok: true, dataUrl }
    } catch {
        return { ok: false, reason: 'failed' }
    }
}
