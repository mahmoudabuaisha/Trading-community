/**
 * Minimal JSON client for the Laravel API (Sanctum SPA cookie auth).
 * Only `repository.ts` imports this file.
 */

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
        /** Per-field validation messages (HTTP 422). */
        public errors: Record<string, string[]> = {},
    ) {
        super(message)
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : ''
}

let csrfReady: Promise<unknown> | null = null
function ensureCsrf(): Promise<unknown> {
    if (xsrfToken()) return Promise.resolve()
    csrfReady ??= fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).finally(() => (csrfReady = null))
    return csrfReady
}

export async function request<T>(method: string, path: string, body?: unknown, retried = false): Promise<T> {
    if (method !== 'GET') await ensureCsrf()
    const response = await fetch(`/api${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Accept-Language': document.documentElement.lang || 'ar',
            'X-Requested-With': 'XMLHttpRequest',
            // A FormData body sets its own multipart Content-Type (with boundary).
            ...(body !== undefined && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
        },
        body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
    })

    // Expired CSRF token: refresh the cookie once and replay.
    if (response.status === 419 && !retried) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' })
        return request<T>(method, path, body, true)
    }
    if (response.status === 204) return undefined as T

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new ApiError(payload.message ?? '', response.status, payload.errors ?? {})
    return payload as T
}
