<script setup lang="ts">
import {
    Activity,
    Award,
    CircleCheckBig,
    Crown,
    GraduationCap,
    Mail,
    Plus,
    Search,
    Shield,
    Sparkles,
    Type,
    Users,
    X,
} from 'lucide-vue-next'
import { computed, onMounted, reactive, ref, watch, type Component } from 'vue'
import AdminBadges from '../../components/admin/AdminBadges.vue'
import AdminContent from '../../components/admin/AdminContent.vue'
import AdminLessons from '../../components/admin/AdminLessons.vue'
import AdminMessages from '../../components/admin/AdminMessages.vue'
import ConfirmDialog from '../../components/ConfirmDialog.vue'
import EmptyState from '../../components/EmptyState.vue'
import StatCard from '../../components/StatCard.vue'
import UserAvatar from '../../components/UserAvatar.vue'
import { ApiError } from '../../data/http'
import { formatDate, messages, t } from '../../i18n'
import { useAdminStore } from '../../stores/admin'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import {
    ADMIN_AREAS,
    MOTIVATION_CATEGORIES,
    type AdminArea,
    type AdminUser,
    type AuditEntry,
    type Motivation,
    type MotivationCategory,
    type Role,
} from '../../types/domain'

const admin = useAdminStore()
const auth = useAuthStore()
const ui = useUiStore()

/** Run an admin action, surfacing success or the server's refusal. */
async function run(action: () => Promise<void>, successKey?: string): Promise<boolean> {
    try {
        await action()
        if (successKey) ui.success(t(successKey))
        return true
    } catch (error) {
        ui.failure(error)
        return false
    }
}

type TabId = 'users' | 'content' | 'growth' | 'education' | 'badges' | 'messages' | 'audit'
const tabs: { id: TabId; label: string; icon: Component }[] = [
    { id: 'users', label: 'tabUsers', icon: Users },
    { id: 'content', label: 'tabContent', icon: Type },
    { id: 'growth', label: 'tabGrowth', icon: Sparkles },
    { id: 'education', label: 'tabEducation', icon: GraduationCap },
    { id: 'badges', label: 'tabBadges', icon: Award },
    { id: 'messages', label: 'tabMessages', icon: Mail },
    { id: 'audit', label: 'tabAudit', icon: Shield },
]
/** Only the areas this account was given; a full administrator sees them all. */
const visibleTabs = computed(() => tabs.filter((item) => auth.can(item.id)))
const tab = ref<TabId>(tabs.find((item) => auth.can(item.id))?.id ?? 'users')
const workspace = useWorkspaceStore()

const togglePremium = (user: AdminUser) =>
    run(
        () => admin.updateUser(user.id, { isPremium: !user.isPremium }),
        user.isPremium ? 'admin.premiumRevoked' : 'admin.premiumGranted',
    )

/** Jump to the inbox with this member's thread open (empty if they never wrote). */
async function openMessages(user: AdminUser): Promise<void> {
    if (await run(() => admin.openThread(user.id))) tab.value = 'messages'
}

const refresh = () => run(() => admin.loadAll())
onMounted(refresh)

// Server-side search, debounced so typing does not fire a request per keystroke.
let searchTimer = 0
watch(
    () => admin.search,
    () => {
        window.clearTimeout(searchTimer)
        searchTimer = window.setTimeout(() => void run(() => admin.loadUsers(1)), 300)
    },
)

// ── Users ────────────────────────────────────────────────────────────────
const confirm = ref<{ message: string; label: string; action: () => Promise<unknown> } | null>(null)

// Role and permissions: only a full administrator decides who helps and with what.
const access = ref<AdminUser | null>(null)
const accessForm = reactive({ role: 'user' as Role, permissions: [] as AdminArea[] })
const accessDialog = ref<HTMLDialogElement | null>(null)
const roles: Role[] = ['user', 'moderator', 'admin']

function openAccess(user: AdminUser): void {
    access.value = user
    accessForm.role = user.role
    accessForm.permissions = user.role === 'moderator' ? [...user.permissions] : []
    requestAnimationFrame(() => accessDialog.value?.showModal())
}

async function saveAccess(): Promise<void> {
    if (!access.value) return
    const permissions = accessForm.role === 'moderator' ? accessForm.permissions : []
    if (
        await run(() => admin.updateUser(access.value!.id, { role: accessForm.role, permissions }), 'admin.accessSaved')
    )
        access.value = null
}

const roleBadge = (user: AdminUser) =>
    user.role === 'admin'
        ? t('admin.fullAdmin')
        : user.role === 'moderator'
          ? t('role.moderator')
          : t(user.isPremium ? 'role.premium' : 'admin.member')

function askRevoke(user: AdminUser): void {
    confirm.value = {
        message: t('admin.confirmSessions', { name: user.displayName }),
        label: t('admin.endSessions'),
        action: () => run(() => admin.revokeSessions(user.id), 'admin.sessionsEnded'),
    }
}

async function runConfirm(): Promise<void> {
    const pending = confirm.value
    confirm.value = null
    await pending?.action()
}

const editing = ref<AdminUser | null>(null)
const editForm = reactive({ displayName: '', email: '' })
const editErrors = ref<Record<string, string[]>>({})
const editDialog = ref<HTMLDialogElement | null>(null)

function openEdit(user: AdminUser): void {
    editing.value = user
    editForm.displayName = user.displayName
    editForm.email = user.email
    editErrors.value = {}
    requestAnimationFrame(() => editDialog.value?.showModal())
}

async function saveEdit(): Promise<void> {
    if (!editing.value) return
    try {
        await admin.updateUser(editing.value.id, {
            displayName: editForm.displayName.trim(),
            email: editForm.email.trim(),
        })
        ui.success(t('admin.userUpdated'))
        editing.value = null
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) editErrors.value = error.errors
        else ui.failure(error)
    }
}

// ── Motivation studio ────────────────────────────────────────────────────
const draft = reactive({
    id: null as number | null,
    title: '',
    body: '',
    category: 'growth' as MotivationCategory,
    isActive: true,
})
const categoryLabels: Record<MotivationCategory, string> = {
    growth: 'catGrowth',
    motivation: 'catMotivation',
    announcement: 'catAnnouncement',
}

function resetDraft(): void {
    Object.assign(draft, { id: null, title: '', body: '', category: 'growth', isActive: true })
}

function editMotivation(item: Motivation): void {
    Object.assign(draft, {
        id: item.id,
        title: item.title,
        body: item.body,
        category: item.category,
        isActive: item.isActive,
    })
}

async function saveMotivation(): Promise<void> {
    if (!draft.title.trim() || !draft.body.trim()) return
    const { id, ...input } = draft
    if (
        await run(
            () => admin.saveMotivation(id, { ...input, title: input.title.trim(), body: input.body.trim() }),
            'admin.mSaved',
        )
    )
        resetDraft()
}

const toggleMotivation = (item: Motivation) =>
    run(() => admin.saveMotivation(item.id, { isActive: !item.isActive }), 'admin.mSaved')

function askDeleteMotivation(item: Motivation): void {
    confirm.value = {
        message: t('admin.confirmDeleteText'),
        label: t('common.delete'),
        action: () => run(() => admin.deleteMotivation(item.id), 'admin.mDeleted'),
    }
}

// ── Audit ────────────────────────────────────────────────────────────────
function auditTitle(entry: AuditEntry): string {
    const actions = messages.value.admin.actions as Record<string, string>
    return actions[entry.action] ?? entry.action
}

function auditDetail(entry: AuditEntry): string {
    const details = entry.details ?? {}
    const name =
        typeof details.name === 'string'
            ? details.name
            : entry.targetUserId
              ? t('admin.account', { id: entry.targetUserId })
              : ''
    const change =
        typeof details.from === 'string' && typeof details.to === 'string' && details.from !== details.to
            ? ` · ${t(`role.${details.from}`)} → ${t(`role.${details.to}`)}`
            : ''
    const areas = Array.isArray(details.permissions)
        ? ` · ${details.permissions.map((area) => t(`admin.areas.${area}`)).join('، ') || t('admin.noPerms')}`
        : ''
    return [name + change + areas, t('admin.auditBy', { name: entry.adminName })].filter(Boolean).join(' · ')
}
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN TASKS / ROOT ACCESS</span>
                <h2>{{ t('admin.head') }}</h2>
                <p>{{ t('admin.sub') }}</p>
            </div>
            <button type="button" class="btn btn--outline" :disabled="admin.loading" @click="refresh">
                <Activity :size="15" aria-hidden="true" /> {{ t('admin.refresh') }}
            </button>
        </div>

        <div class="stat-grid">
            <StatCard
                :icon="Users"
                :label="t('admin.sUsers')"
                :value="admin.stats.users"
                :note="t('admin.sUsersNote')"
            />
            <StatCard
                :icon="CircleCheckBig"
                :label="t('admin.sTasks')"
                :value="admin.stats.tasks"
                :note="t('admin.sTasksNote')"
            />
            <StatCard
                :icon="Award"
                :label="t('admin.sCompleted')"
                :value="admin.stats.completed"
                :note="t('admin.sCompletedNote')"
            />
            <StatCard
                :icon="Activity"
                :label="t('admin.sActive')"
                :value="admin.stats.activeToday"
                :note="t('admin.sActiveNote')"
            />
        </div>

        <nav class="tabs" :aria-label="t('nav.admin')">
            <button
                v-for="item in visibleTabs"
                :key="item.id"
                type="button"
                :class="{ tabs__active: tab === item.id }"
                :aria-pressed="tab === item.id"
                @click="tab = item.id"
            >
                <component :is="item.icon" :size="15" aria-hidden="true" />{{ t(`admin.${item.label}`) }}
                <i
                    v-if="item.id === 'messages' && workspace.unread.admin"
                    class="nav-item__count nav-item__count--alert"
                >
                    {{ workspace.unread.admin }}
                </i>
            </button>
        </nav>

        <div v-show="tab === 'users'" class="panel">
            <div class="section-head">
                <div>
                    <span class="eyebrow">IDENTITY &amp; ACCESS</span>
                    <h3>{{ t('admin.usersHead') }}</h3>
                    <p>{{ t('admin.usersSub') }}</p>
                </div>
                <span class="count-pill">{{ t('admin.count', { n: admin.usersMeta.total }) }}</span>
            </div>
            <label class="search-box">
                <Search :size="16" aria-hidden="true" />
                <input
                    v-model="admin.search"
                    class="input"
                    type="search"
                    :placeholder="t('admin.search')"
                    :aria-label="t('admin.search')"
                />
            </label>

            <div class="user-table" role="table">
                <div class="user-table__head" role="row">
                    <span role="columnheader">{{ t('admin.colUser') }}</span>
                    <span role="columnheader">{{ t('admin.colRole') }}</span>
                    <span role="columnheader">{{ t('admin.colJoined') }}</span>
                    <span role="columnheader">{{ t('admin.colActions') }}</span>
                </div>
                <div v-for="user in admin.users" :key="user.id" class="user-table__row" role="row">
                    <div class="user-cell" role="cell">
                        <UserAvatar :name="user.displayName" size="sm" />
                        <div>
                            <b>{{ user.displayName }}</b>
                            <small
                                ><bdi>{{ user.email }}</bdi></small
                            >
                        </div>
                    </div>
                    <span role="cell" class="count-pill" :class="{ 'count-pill--gold': user.role !== 'user' }">
                        <Crown v-if="user.role === 'admin'" :size="12" aria-hidden="true" />
                        {{ roleBadge(user) }}
                    </span>
                    <span role="cell" class="muted">{{ formatDate(user.createdAt, { dateStyle: 'medium' }) }}</span>
                    <div role="cell" class="row-actions">
                        <button type="button" class="pill" @click="openEdit(user)">{{ t('common.edit') }}</button>
                        <button v-if="auth.isAdmin" type="button" class="pill" @click="openAccess(user)">
                            {{ t('admin.access') }}
                        </button>
                        <button
                            v-if="user.role !== 'admin'"
                            type="button"
                            class="pill"
                            :class="{ 'pill--chosen': user.isPremium }"
                            @click="togglePremium(user)"
                        >
                            {{ t(user.isPremium ? 'admin.premiumRevoke' : 'admin.premiumGrant') }}
                        </button>
                        <button v-if="auth.can('messages')" type="button" class="pill" @click="openMessages(user)">
                            {{ t('admin.message') }}
                        </button>
                        <button type="button" class="pill" @click="askRevoke(user)">
                            {{ t('admin.endSessions') }}
                        </button>
                    </div>
                </div>
                <EmptyState v-if="!admin.users.length" :title="t('admin.noResults')" :text="t('admin.noResultsText')" />
            </div>

            <div v-if="admin.usersMeta.lastPage > 1" class="pager">
                <button
                    type="button"
                    class="pill"
                    :disabled="admin.usersMeta.currentPage <= 1"
                    @click="run(() => admin.loadUsers(admin.usersMeta.currentPage - 1))"
                >
                    {{ t('common.previous') }}
                </button>
                <span class="muted">{{
                    t('admin.page', { page: admin.usersMeta.currentPage, last: admin.usersMeta.lastPage })
                }}</span>
                <button
                    type="button"
                    class="pill"
                    :disabled="admin.usersMeta.currentPage >= admin.usersMeta.lastPage"
                    @click="run(() => admin.loadUsers(admin.usersMeta.currentPage + 1))"
                >
                    {{ t('common.next') }}
                </button>
            </div>
        </div>

        <AdminContent v-if="tab === 'content'" />

        <div v-show="tab === 'growth'" class="panel">
            <div class="section-head">
                <div>
                    <span class="eyebrow">GROWTH &amp; MOTIVATION STUDIO</span>
                    <h3>{{ t('admin.studioHead') }}</h3>
                    <p>{{ t('admin.studioSub') }}</p>
                </div>
                <Sparkles :size="19" class="gold" aria-hidden="true" />
            </div>
            <form class="stack stack--tight" @submit.prevent="saveMotivation">
                <input
                    v-model="draft.title"
                    class="input"
                    required
                    maxlength="180"
                    :placeholder="t('admin.mTitlePh')"
                    :aria-label="t('admin.mTitle')"
                />
                <textarea
                    v-model="draft.body"
                    class="input"
                    rows="4"
                    required
                    maxlength="5000"
                    :placeholder="t('admin.mBodyPh')"
                    :aria-label="t('admin.mBody')"
                />
                <div class="head-actions">
                    <select v-model="draft.category" class="input input--auto" :aria-label="t('admin.mCategory')">
                        <option v-for="category in MOTIVATION_CATEGORIES" :key="category" :value="category">
                            {{ t(`admin.${categoryLabels[category]}`) }}
                        </option>
                    </select>
                    <label class="check"
                        ><input v-model="draft.isActive" type="checkbox" /> {{ t('admin.visible') }}</label
                    >
                    <button type="submit" class="btn">
                        <Plus :size="15" aria-hidden="true" />{{
                            t(draft.id === null ? 'admin.addText' : 'admin.saveEdit')
                        }}
                    </button>
                    <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="resetDraft">
                        {{ t('admin.cancelEdit') }}
                    </button>
                </div>
            </form>

            <div class="motivation-list">
                <article v-for="item in admin.motivations" :key="item.id" class="motivation-item">
                    <div>
                        <span class="count-pill" :class="{ 'count-pill--ok': item.isActive }">
                            {{ t(`admin.${categoryLabels[item.category]}`) }} ·
                            {{ t(item.isActive ? 'admin.published' : 'admin.draft') }}
                        </span>
                        <h4>{{ item.title }}</h4>
                        <p>{{ item.body }}</p>
                    </div>
                    <div class="row-actions">
                        <button type="button" class="pill" @click="editMotivation(item)">{{ t('common.edit') }}</button>
                        <button type="button" class="pill" @click="toggleMotivation(item)">
                            {{ t(item.isActive ? 'admin.hide' : 'admin.publish') }}
                        </button>
                        <button type="button" class="pill" @click="askDeleteMotivation(item)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </article>
                <EmptyState
                    v-if="!admin.motivations.length"
                    :title="t('admin.mEmptyTitle')"
                    :text="t('admin.mEmptyText')"
                />
            </div>
        </div>

        <AdminLessons v-if="tab === 'education'" />

        <AdminBadges v-if="tab === 'badges'" />

        <AdminMessages v-if="tab === 'messages'" />

        <div v-show="tab === 'audit'" class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ t('admin.auditHead') }}</h3>
                    <p>{{ t('admin.auditSub') }}</p>
                </div>
                <Shield :size="17" class="gold" aria-hidden="true" />
            </div>
            <div class="audit-list">
                <div v-for="entry in admin.audit" :key="entry.id" class="audit-row">
                    <span class="audit-row__icon"><Shield :size="14" aria-hidden="true" /></span>
                    <div>
                        <b>{{ auditTitle(entry) }}</b>
                        <small>{{ auditDetail(entry) }}</small>
                    </div>
                    <time :datetime="entry.createdAt">{{
                        formatDate(entry.createdAt, { dateStyle: 'medium', timeStyle: 'short' })
                    }}</time>
                </div>
                <EmptyState
                    v-if="!admin.audit.length"
                    :title="t('admin.auditEmptyTitle')"
                    :text="t('admin.auditEmptyText')"
                />
            </div>
        </div>

        <ConfirmDialog
            v-if="confirm"
            :message="confirm.message"
            :confirm-label="confirm.label"
            @confirm="runConfirm"
            @cancel="confirm = null"
        />

        <dialog
            v-if="access"
            ref="accessDialog"
            class="dialog"
            @cancel.prevent="access = null"
            @click.self="access = null"
        >
            <form class="dialog__body" @submit.prevent="saveAccess">
                <div class="dialog__head">
                    <h2>{{ t('admin.accessHead', { name: access.displayName }) }}</h2>
                    <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="access = null">
                        <X :size="18" />
                    </button>
                </div>
                <label class="field">
                    <span>{{ t('admin.colRole') }}</span>
                    <select v-model="accessForm.role" class="input">
                        <option v-for="role in roles" :key="role" :value="role">{{ t(`role.${role}`) }}</option>
                    </select>
                    <small class="hint">{{ t(`admin.roleHint.${accessForm.role}`) }}</small>
                </label>
                <fieldset v-if="accessForm.role === 'moderator'" class="perm-list">
                    <legend>{{ t('admin.permsLegend') }}</legend>
                    <label v-for="area in ADMIN_AREAS" :key="area" class="check">
                        <input v-model="accessForm.permissions" type="checkbox" :value="area" />
                        {{ t(`admin.areas.${area}`) }}
                    </label>
                </fieldset>
                <div class="actions">
                    <button type="button" class="btn btn--outline" @click="access = null">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="submit" class="btn">{{ t('common.save') }}</button>
                </div>
            </form>
        </dialog>

        <dialog
            v-if="editing"
            ref="editDialog"
            class="dialog"
            @cancel.prevent="editing = null"
            @click.self="editing = null"
        >
            <form class="dialog__body" @submit.prevent="saveEdit">
                <div class="dialog__head">
                    <h2>{{ t('admin.editUser') }}</h2>
                    <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="editing = null">
                        <X :size="18" />
                    </button>
                </div>
                <label class="field">
                    <span>{{ t('admin.name') }}</span>
                    <input v-model="editForm.displayName" class="input" required maxlength="80" />
                    <small v-if="editErrors.displayName" class="field__error">{{ editErrors.displayName[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.email') }}</span>
                    <input v-model="editForm.email" class="input" type="email" required dir="ltr" />
                    <small v-if="editErrors.email" class="field__error">{{ editErrors.email[0] }}</small>
                </label>
                <div class="actions">
                    <button type="button" class="btn btn--outline" @click="editing = null">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="submit" class="btn">{{ t('common.save') }}</button>
                </div>
            </form>
        </dialog>
    </section>
</template>
