import { defineStore } from 'pinia'
import { ref } from 'vue'
import { repository } from '../data/repository'
import type {
    AdminStats,
    AdminUser,
    AuditEntry,
    Motivation,
    MotivationInput,
    Role,
    Lesson,
    LessonInput,
    Message,
    SiteContent,
    Thread,
    ThreadUser,
    Badge,
    BadgeInput,
} from '../types/domain'

export const useAdminStore = defineStore('admin', () => {
    const stats = ref<AdminStats>({ users: 0, tasks: 0, completed: 0, activeToday: 0 })
    const audit = ref<AuditEntry[]>([])
    const users = ref<AdminUser[]>([])
    const usersMeta = ref({ currentPage: 1, lastPage: 1, total: 0 })
    const motivations = ref<Motivation[]>([])
    const content = ref<SiteContent>({ taglineAr: null, taglineEn: null, welcomeSubAr: null, welcomeSubEn: null })
    const lessons = ref<Lesson[]>([])
    const badges = ref<Badge[]>([])
    const threads = ref<Thread[]>([])
    const activeThread = ref<{ user: ThreadUser; messages: Message[] } | null>(null)
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

    async function loadContent(): Promise<void> {
        content.value = await repository.adminContent()
    }

    async function saveContent(next: SiteContent): Promise<void> {
        content.value = await repository.adminSaveContent(next)
        await loadOverview()
    }

    async function loadLessons(): Promise<void> {
        lessons.value = await repository.adminLessons()
    }

    async function saveLesson(id: number | null, input: Partial<LessonInput>): Promise<void> {
        await repository.adminSaveLesson(id, input)
        await Promise.all([loadLessons(), loadOverview()])
    }

    async function deleteLesson(id: number): Promise<void> {
        await repository.adminDeleteLesson(id)
        await Promise.all([loadLessons(), loadOverview()])
    }

    async function loadBadges(): Promise<void> {
        badges.value = await repository.adminBadges()
    }

    async function saveBadge(id: number | null, input: Partial<BadgeInput>): Promise<void> {
        await repository.adminSaveBadge(id, input)
        await Promise.all([loadBadges(), loadOverview()])
    }

    async function deleteBadge(id: number): Promise<void> {
        await repository.adminDeleteBadge(id)
        await Promise.all([loadBadges(), loadOverview()])
    }

    async function loadThreads(): Promise<void> {
        threads.value = await repository.adminThreads()
    }

    /** Opening a thread marks the member's messages as read on the server. */
    async function openThread(userId: number): Promise<void> {
        activeThread.value = await repository.adminThread(userId)
        await loadThreads()
    }

    async function sendMessage(body: string): Promise<void> {
        if (!activeThread.value) return
        const message = await repository.adminSendMessage(activeThread.value.user.id, body)
        activeThread.value.messages.push(message)
        await loadThreads()
    }

    async function loadAll(): Promise<void> {
        loading.value = true
        error.value = null
        try {
            await Promise.all([
                loadOverview(),
                loadUsers(1),
                loadMotivations(),
                loadContent(),
                loadLessons(),
                loadThreads(),
                loadBadges(),
            ])
        } catch (e) {
            error.value = e
            throw e
        } finally {
            loading.value = false
        }
    }

    async function updateUser(
        id: number,
        patch: { displayName?: string; email?: string; role?: Role; isPremium?: boolean },
    ): Promise<void> {
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
        content,
        lessons,
        badges,
        threads,
        activeThread,
        search,
        loading,
        error,
        loadAll,
        loadUsers,
        updateUser,
        revokeSessions,
        saveMotivation,
        deleteMotivation,
        saveContent,
        saveLesson,
        deleteLesson,
        saveBadge,
        deleteBadge,
        loadThreads,
        openThread,
        sendMessage,
    }
})
