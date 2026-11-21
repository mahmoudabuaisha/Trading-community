import { defineStore } from 'pinia'
import { ref } from 'vue'
import { repository } from '../data/repository'
import type { AdminStats, AdminUser, AuditEntry, Motivation, MotivationInput, Role } from '../types/domain'

export const useAdminStore = defineStore('admin', () => {
    const stats = ref<AdminStats>({ users: 0, tasks: 0, completed: 0, activeToday: 0 })
    const audit = ref<AuditEntry[]>([])
    const users = ref<AdminUser[]>([])
    const usersMeta = ref({ currentPage: 1, lastPage: 1, total: 0 })
    const motivations = ref<Motivation[]>([])
    const search = ref('')
    const loading = ref(false)
    const error = ref<unknown>(null)

    async function loadOverview(): Promise<void> {
        const overview = await repository.adminOverview()
        stats.value = overview.stats
        audit.value = overview.audit
    }

    async function loadUsers(page = usersMeta.value.currentPage): Promise<void> {
        const result = await repository.adminUsers(search.value.trim(), page)
        users.value = result.data
        usersMeta.value = result.meta
    }

    async function loadMotivations(): Promise<void> {
        motivations.value = await repository.adminMotivations()
    }

    async function loadAll(): Promise<void> {
        loading.value = true
        error.value = null
        try {
            await Promise.all([loadOverview(), loadUsers(1), loadMotivations()])
        } catch (e) {
            error.value = e
            throw e
        } finally {
            loading.value = false
        }
    }

    async function updateUser(id: number, patch: { displayName?: string; email?: string; role?: Role }): Promise<void> {
        await repository.adminUpdateUser(id, patch)
        await Promise.all([loadUsers(), loadOverview()])
    }

    async function revokeSessions(id: number): Promise<void> {
        await repository.adminRevokeSessions(id)
        await loadOverview()
    }

    async function saveMotivation(id: number | null, input: Partial<MotivationInput>): Promise<void> {
        if (id === null) await repository.adminCreateMotivation(input as MotivationInput)
        else await repository.adminUpdateMotivation(id, input)
        await Promise.all([loadMotivations(), loadOverview()])
    }

    async function deleteMotivation(id: number): Promise<void> {
        await repository.adminDeleteMotivation(id)
        await Promise.all([loadMotivations(), loadOverview()])
    }

    return {
        stats,
        audit,
        users,
        usersMeta,
        motivations,
        search,
        loading,
        error,
        loadAll,
        loadUsers,
        updateUser,
        revokeSessions,
        saveMotivation,
        deleteMotivation,
    }
})
