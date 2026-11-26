<script setup lang="ts">
import {
    BadgeCheck,
    BarChart3,
    Bell,
    Camera,
    ChevronLeft,
    ChevronRight,
    Crown,
    Gift,
    House,
    Lock,
    LogOut,
    Mail,
    Pencil,
    Settings,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-vue-next'
import { computed, nextTick, reactive, ref, type Component } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NameEmblem from '../components/NameEmblem.vue'
import ProfileNotifications from '../components/profile/ProfileNotifications.vue'
import ProfilePrivacy from '../components/profile/ProfilePrivacy.vue'
import ProfileRewards from '../components/profile/ProfileRewards.vue'
import ProfileSecurity from '../components/profile/ProfileSecurity.vue'
import ProfileSettings from '../components/profile/ProfileSettings.vue'
import ProfileStats from '../components/profile/ProfileStats.vue'
import { ApiError } from '../data/http'
import { formatDate, isRtl, t } from '../i18n'
import { fileToAvatar } from '../lib/image'
import { standingName } from '../lib/roles'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const user = computed(() => auth.user!)
type Section = 'settings' | 'notifications' | 'privacy' | 'security' | 'stats' | 'rewards'
const section = computed(() => (route.params.section || null) as Section | null)
/** Grouped the way most apps do: how the app behaves, then the member's own record. */
const groups: { id: string; items: { id: Section; icon: Component }[] }[] = [
    {
        id: 'settings',
        items: [
            { id: 'settings', icon: Settings },
            { id: 'notifications', icon: Bell },
            { id: 'privacy', icon: Lock },
            { id: 'security', icon: ShieldCheck },
        ],
    },
    {
        id: 'record',
        items: [
            { id: 'stats', icon: BarChart3 },
            { id: 'rewards', icon: Gift },
        ],
    },
]
const panels: Record<Section, Component> = {
    settings: ProfileSettings,
    notifications: ProfileNotifications,
    privacy: ProfilePrivacy,
    security: ProfileSecurity,
    stats: ProfileStats,
    rewards: ProfileRewards,
}

/** "Back" points against the reading direction; rows open in the reading direction. */
const BackIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))
const OpenIcon = computed(() => (isRtl.value ? ChevronLeft : ChevronRight))
// The title shows on its own line, so this line is the member's standing.
const roleLabel = computed(() => standingName(user.value))
const verified = computed(() => user.value.role !== 'user' || user.value.isPremium)

function goBack(): void {
    if (section.value) void router.push({ name: 'profile' })
    else if (window.history.length > 1) router.back()
    else void router.push({ name: 'overview' })
}

// ── Names ────────────────────────────────────────────────────────────────
// Titles are bought in the store; members only set their own names here.
type NameField = 'displayName' | 'surname'
const nameRows: { field: NameField; icon: Component }[] = [
    { field: 'displayName', icon: UserRound },
    { field: 'surname', icon: Pencil },
]
const editing = ref(false)
const nameForm = reactive({ displayName: '', surname: '' })
const nameErrors = ref<Record<string, string[]>>({})
const savingNames = ref(false)
const dialog = ref<HTMLDialogElement | null>(null)

async function openNames(focus: NameField = 'displayName'): Promise<void> {
    Object.assign(nameForm, {
        displayName: user.value.displayName,
        surname: user.value.surname,
    })
    nameErrors.value = {}
    editing.value = true
    await nextTick()
    dialog.value?.showModal()
    dialog.value?.querySelector<HTMLInputElement>(`[name="${focus}"]`)?.focus()
}

async function saveNames(): Promise<void> {
    savingNames.value = true
    nameErrors.value = {}
    try {
        await workspace.patchProfile({
            displayName: nameForm.displayName.trim(),
            surname: nameForm.surname.trim(),
        })
        editing.value = false
        ui.success(t('profile.saved'))
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) nameErrors.value = error.errors
        else ui.failure(error)
    } finally {
        savingNames.value = false
    }
}

// ── Photo ────────────────────────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null)

async function pickAvatar(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    const result = await fileToAvatar(file)
    if (!result.ok) {
        ui.failure(new ApiError(t(`profile.avatar${result.reason[0].toUpperCase()}${result.reason.slice(1)}`), 422))
        return
    }
    try {
        await workspace.patchProfile({ avatarUrl: result.dataUrl })
        ui.success(t('profile.photoSaved'))
    } catch (error) {
        ui.failure(error)
    }
}

async function signOut(): Promise<void> {
    await auth.logout()
    workspace.reset()
    await router.replace({ name: 'auth' })
}
</script>

<template>
    <section class="profile">
        <header class="profile__bar">
            <RouterLink :to="{ name: 'overview' }" class="profile__nav" :aria-label="t('nav.overview')"
                ><House :size="24" aria-hidden="true"
            /></RouterLink>
            <h2 class="profile__title">{{ section ? t(`profile.section.${section}`) : t('nav.profile') }}</h2>
            <button type="button" class="profile__nav" :aria-label="t('profile.back')" @click="goBack">
                <component :is="BackIcon" :size="26" aria-hidden="true" />
            </button>
        </header>

        <template v-if="!section">
            <div class="dash-card profile-hero">
                <div class="profile-hero__identity">
                    <h3>
                        <BadgeCheck
                            v-if="verified"
                            :size="28"
                            class="profile-hero__verified"
                            :aria-label="t('profile.verified')"
                        />
                        {{ user.displayName }}
                        <NameEmblem :emblem="user.emblem" />
                    </h3>
                    <p v-if="user.title" class="profile-hero__title">{{ user.title }}</p>
                    <p>
                        <Mail :size="20" aria-hidden="true" /> <bdi>{{ user.email }}</bdi>
                    </p>
                    <p class="profile-hero__role"><Crown :size="22" aria-hidden="true" /> {{ roleLabel }}</p>
                    <p v-if="user.isPremium && user.premiumUntil" class="profile-hero__until">
                        {{ t('profile.premiumUntil', { date: formatDate(user.premiumUntil, { dateStyle: 'long' }) }) }}
                    </p>
                    <p v-if="user.specialties.length" class="profile-hero__tags">
                        <span v-for="key in user.specialties" :key="key" class="count-pill count-pill--gold">{{
                            t(`specialty.${key}`)
                        }}</span>
                    </p>
                    <RouterLink v-else :to="{ name: 'profile', params: { section: 'settings' } }" class="pill">{{
                        t('specialty.choose')
                    }}</RouterLink>
                </div>
                <button
                    type="button"
                    class="profile-hero__avatar"
                    :class="
                        user.frame.startsWith('/')
                            ? 'profile-hero__avatar--picture'
                            : user.frame !== 'none'
                              ? `profile-hero__avatar--${user.frame}`
                              : ''
                    "
                    :aria-label="t('profile.changeAvatar')"
                    @click="fileInput?.click()"
                >
                    <img v-if="user.avatarUrl" :src="user.avatarUrl" alt="" />
                    <UserRound v-else :size="84" aria-hidden="true" />
                    <img
                        v-if="user.frame.startsWith('/')"
                        :src="user.frame"
                        alt=""
                        class="profile-hero__frame-picture"
                    />
                    <span class="profile-hero__camera"><Camera :size="18" aria-hidden="true" /></span>
                </button>
                <input
                    ref="fileInput"
                    class="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    tabindex="-1"
                    @change="pickAvatar"
                />
            </div>

            <div class="dash-card profile-list">
                <button type="button" class="profile-row" @click="openNames()">
                    <UserRound :size="24" class="profile-row__icon" aria-hidden="true" />
                    <span class="profile-row__label">{{ t('profile.editName') }}</span>
                    <component :is="OpenIcon" :size="20" class="profile-row__open" aria-hidden="true" />
                </button>
                <button type="button" class="profile-row" @click="fileInput?.click()">
                    <Camera :size="24" class="profile-row__icon" aria-hidden="true" />
                    <span class="profile-row__label">{{ t('profile.changeAvatar') }}</span>
                    <component :is="OpenIcon" :size="20" class="profile-row__open" aria-hidden="true" />
                </button>
                <button
                    v-for="row in nameRows"
                    :key="row.field"
                    type="button"
                    class="profile-row"
                    @click="openNames(row.field)"
                >
                    <component :is="row.icon" :size="24" class="profile-row__icon" aria-hidden="true" />
                    <span class="profile-row__label">{{ t(`profile.names.${row.field}`) }}</span>
                    <span class="profile-row__value">{{ user[row.field] || '—' }}</span>
                    <component :is="OpenIcon" :size="20" class="profile-row__open" aria-hidden="true" />
                </button>
            </div>

            <div v-for="group in groups" :key="group.id" class="dash-card profile-list">
                <h3 class="profile-list__head">{{ t(`profile.group.${group.id}`) }}</h3>
                <RouterLink
                    v-for="item in group.items"
                    :key="item.id"
                    :to="{ name: 'profile', params: { section: item.id } }"
                    class="profile-row"
                >
                    <component :is="item.icon" :size="24" class="profile-row__icon" aria-hidden="true" />
                    <span class="profile-row__label">{{ t(`profile.section.${item.id}`) }}</span>
                    <span v-if="item.id === 'privacy'" class="profile-row__value">{{
                        t(user.privacy.showOnLeaderboard ? 'privacy.visible' : 'privacy.hidden')
                    }}</span>
                    <span v-else-if="item.id === 'rewards'" class="profile-row__value">{{
                        t('rewards.count', { n: user.points })
                    }}</span>
                    <component :is="OpenIcon" :size="20" class="profile-row__open" aria-hidden="true" />
                </RouterLink>
            </div>

            <button type="button" class="dash-card profile-logout" @click="signOut">
                <LogOut :size="22" aria-hidden="true" /> {{ t('nav.signOut') }}
            </button>
        </template>

        <component :is="panels[section]" v-else />

        <dialog
            v-if="editing"
            ref="dialog"
            class="dialog"
            @cancel.prevent="editing = false"
            @click.self="editing = false"
        >
            <form class="dialog__body" @submit.prevent="saveNames">
                <div class="dialog__head">
                    <h2>{{ t('profile.editName') }}</h2>
                    <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="editing = false">
                        <X :size="18" />
                    </button>
                </div>
                <label class="field">
                    <span>{{ t('profile.names.displayName') }}</span>
                    <input
                        v-model="nameForm.displayName"
                        name="displayName"
                        class="input"
                        required
                        maxlength="120"
                        autocomplete="given-name"
                    />
                    <small v-if="nameErrors.displayName" class="field__error">{{ nameErrors.displayName[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('profile.names.surname') }}</span>
                    <input
                        v-model="nameForm.surname"
                        name="surname"
                        class="input"
                        maxlength="120"
                        autocomplete="family-name"
                    />
                </label>
                <div class="actions">
                    <button type="button" class="btn btn--outline" @click="editing = false">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="submit" class="btn" :disabled="savingNames">
                        {{ savingNames ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </form>
        </dialog>
    </section>
</template>
