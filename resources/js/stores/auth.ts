import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import type { AdminArea, User } from '../types/domain'

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)
    /** True once the first session check has finished (either way). */
    const ready = ref(false)
    let pending: Promise<void> | null = null

    const isAdmin = computed(() => user.value?.role === 'admin')
    /** Administrators and moderators may open the admin center. */
    const isStaff = computed(() => user.value?.role === 'admin' || user.value?.role === 'moderator')

    /** May the signed-in user work in this area of the admin center? The server checks again. */
    function can(area: AdminArea): boolean {
        return user.value?.permissions.includes(area) ?? false
    }

    /** Resolve the current session once; later callers share the same request. */
    function ensureLoaded(): Promise<void> {
        if (ready.value) return Promise.resolve()
        pending ??= repository
            .me()
            .then((me) => void (user.value = me))
            .catch((error) => {
                if (!(error instanceof ApiError && error.status === 401)) console.error(error)
                user.value = null
            })
            .finally(() => {
                ready.value = true
                pending = null
            })
        return pending
    }

    async function login(email: string, password: string, remember: boolean): Promise<void> {
        user.value = await repository.login(email, password, remember)
    }

    async function register(displayName: string, email: string, password: string): Promise<void> {
        user.value = await repository.register(displayName, email, password)
    }

    async function logout(): Promise<void> {
        try {
            await repository.logout()
        } finally {
            user.value = null
        }
    }

    function setUser(next: User | null): void {
        user.value = next
    }

    return { user, ready, isAdmin, isStaff, can, ensureLoaded, login, register, logout, setUser }
})
