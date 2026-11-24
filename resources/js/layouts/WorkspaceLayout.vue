<script setup lang="ts">
import {
    Award,
    BarChart3,
    CalendarDays,
    Clock3,
    Flag,
    GraduationCap,
    Crown,
    House,
    LogOut,
    Mail,
    Menu,
    Shield,
    Sparkles,
    Target,
    TrendingUp,
    UserRound,
    X,
} from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue'
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router'
import BrandLogo from '../components/BrandLogo.vue'
import EmptyState from '../components/EmptyState.vue'
import WorkspaceBackdrop from '../components/WorkspaceBackdrop.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { useSiteCopy } from '../lib/content'
import { describeError } from '../stores/ui'
import { formatDate, locale, setLocale, t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useWorkspaceStore } from '../stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const route = useRoute()
const router = useRouter()

const { tagline } = useSiteCopy()
const navOpen = ref(false)
const now = ref(new Date())
let clock = 0
let unreadPoll = 0

interface NavItem {
    key: string
    to: RouteLocationRaw
    icon: Component
    active: boolean
}

const navItems = computed<NavItem[]>(() => {
    const period = route.params.period
    const item = (key: string, name: string, icon: Component, params?: { period: string }): NavItem => ({
        key,
        to: { name, params },
        icon,
        active: route.name === name && (!params || params.period === period),
    })
    return [
        item('overview', 'overview', House),
        item('daily', 'tasks', CalendarDays, { period: 'daily' }),
        item('weekly', 'tasks', BarChart3, { period: 'weekly' }),
        item('monthly', 'tasks', Target, { period: 'monthly' }),
        item('yearly', 'tasks', Flag, { period: 'yearly' }),
        item('goals', 'goals', TrendingUp),
        item('achievements', 'achievements', Award),
        item('growth', 'growth', Sparkles),
        item('education', 'education', GraduationCap),
        item('messages', 'messages', Mail),
        item('profile', 'profile', UserRound),
    ]
})

const pageTitle = computed(() =>
    route.name === 'tasks'
        ? t('title.tasks', { period: t(`period.${String(route.params.period)}`) })
        : t(`title.${String(route.name)}`),
)
const fullName = computed(() => `${auth.user?.displayName ?? ''} ${auth.user?.surname ?? ''}`.trim())

watch(pageTitle, (title) => (document.title = `${title} · SN Tasks`), { immediate: true })
watch(
    () => route.fullPath,
    () => (navOpen.value = false),
)

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') navOpen.value = false
}

onMounted(() => {
    if (auth.user) setLocale(auth.user.language)
    void workspace.load()
    clock = window.setInterval(() => (now.value = new Date()), 1000)
    void workspace.refreshUnread()
    unreadPoll = window.setInterval(() => void workspace.refreshUnread(), 60_000)
    window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
    window.clearInterval(clock)
    window.clearInterval(unreadPoll)
    window.removeEventListener('keydown', onKeydown)
})

async function signOut(): Promise<void> {
    await auth.logout()
    workspace.reset()
    await router.replace({ name: 'auth' })
}
</script>

<template>
    <main class="app-shell" :class="{ 'app-shell--nav-open': navOpen }">
        <WorkspaceBackdrop />

        <div v-if="navOpen" class="nav-scrim" @click="navOpen = false" />

        <aside class="sidebar" :aria-label="t('nav.main')">
            <RouterLink :to="{ name: 'overview' }" class="sidebar__logo" :aria-label="t('nav.overview')">
                <BrandLogo label="SN Tasks" />
            </RouterLink>

            <nav class="sidebar__nav">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.key"
                    :to="item.to"
                    class="nav-item"
                    :class="{ 'nav-item--active': item.active }"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    <span class="nav-item__icon"><component :is="item.icon" :size="20" aria-hidden="true" /></span>
                    <span>{{ t(`nav.${item.key}`) }}</span>
                    <i v-if="item.key === 'achievements' && workspace.achievements.length" class="nav-item__count">
                        {{ workspace.achievements.length }}
                    </i>
                    <i
                        v-if="item.key === 'messages' && workspace.unread.member"
                        class="nav-item__count nav-item__count--alert"
                    >
                        {{ workspace.unread.member }}
                    </i>
                </RouterLink>

                <RouterLink
                    v-if="auth.isAdmin"
                    :to="{ name: 'admin' }"
                    class="nav-item"
                    :class="{ 'nav-item--active': route.name === 'admin' }"
                    :aria-current="route.name === 'admin' ? 'page' : undefined"
                >
                    <span class="nav-item__icon"><Shield :size="20" aria-hidden="true" /></span>
                    <span>{{ t('nav.admin') }}</span>
                    <i v-if="workspace.unread.admin" class="nav-item__count nav-item__count--alert">{{
                        workspace.unread.admin
                    }}</i>
                </RouterLink>

                <button type="button" class="nav-item nav-item--button" @click="signOut">
                    <span class="nav-item__icon"><LogOut :size="20" aria-hidden="true" /></span>
                    <span>{{ t('nav.signOut') }}</span>
                </button>
            </nav>

            <div class="sidebar__badge">
                <Crown :size="22" aria-hidden="true" />
                <b>SN TASKS</b>
                <small>{{ t(`role.${auth.user?.role ?? 'user'}`) }}</small>
            </div>
        </aside>

        <section class="main-area">
            <header class="topbar">
                <div class="topbar__start">
                    <button
                        type="button"
                        class="icon-btn topbar__menu"
                        :aria-expanded="navOpen"
                        :aria-label="t('nav.open')"
                        @click="navOpen = !navOpen"
                    >
                        <component :is="navOpen ? X : Menu" :size="20" aria-hidden="true" />
                    </button>
                    <RouterLink :to="{ name: 'profile' }" class="user-chip" :aria-label="t('nav.profile')">
                        <UserAvatar :name="fullName" :src="auth.user?.avatarUrl" />
                        <span>
                            <b
                                ><Crown v-if="auth.isAdmin" :size="14" aria-hidden="true" />
                                {{ auth.user?.displayName }}</b
                            >
                            <small>{{ t(`role.${auth.user?.role ?? 'user'}`) }}</small>
                        </span>
                    </RouterLink>
                    <button
                        type="button"
                        class="chip-btn"
                        :aria-label="t('nav.switchLang')"
                        @click="setLocale(locale === 'ar' ? 'en' : 'ar')"
                    >
                        {{ t('nav.langButton') }}
                    </button>
                </div>

                <div class="date-card">
                    <span
                        ><CalendarDays :size="18" aria-hidden="true" />{{
                            formatDate(now, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
                        }}</span
                    >
                    <span
                        ><Clock3 :size="18" aria-hidden="true" />{{
                            formatDate(now, { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                        }}</span
                    >
                </div>

                <h1 class="topbar__title" :class="{ 'topbar__title--tagline': route.name === 'overview' }">
                    {{ route.name === 'overview' ? tagline : pageTitle }}
                </h1>
            </header>

            <div v-if="workspace.loading" class="loading-panel">
                <div class="loader" aria-hidden="true" />
                <span>{{ t('common.loading') }}</span>
            </div>
            <EmptyState
                v-else-if="workspace.error && !workspace.loaded"
                :title="describeError(workspace.error)"
                text=""
            >
                <button type="button" class="btn" @click="workspace.load(true)">↻</button>
            </EmptyState>
            <RouterView v-else />
        </section>
    </main>
</template>
