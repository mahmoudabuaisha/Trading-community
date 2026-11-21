<script setup lang="ts">
import {
    Award,
    BarChart3,
    CalendarDays,
    Clock3,
    Command,
    Flag,
    LayoutGrid,
    LogOut,
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
import EmptyState from '../components/EmptyState.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { describeError } from '../stores/ui'
import { formatDate, locale, setLocale, t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useWorkspaceStore } from '../stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const route = useRoute()
const router = useRouter()

const navOpen = ref(false)
const now = ref(new Date())
let clock = 0

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
        item('overview', 'overview', LayoutGrid),
        item('daily', 'tasks', CalendarDays, { period: 'daily' }),
        item('weekly', 'tasks', BarChart3, { period: 'weekly' }),
        item('monthly', 'tasks', Target, { period: 'monthly' }),
        item('yearly', 'tasks', Flag, { period: 'yearly' }),
        item('goals', 'goals', TrendingUp),
        item('achievements', 'achievements', Award),
        item('growth', 'growth', Sparkles),
        item('profile', 'profile', UserRound),
    ]
})

const pageTitle = computed(() =>
    route.name === 'tasks'
        ? t('title.tasks', { period: t(`period.${String(route.params.period)}`) })
        : t(`title.${String(route.name)}`),
)
const fullName = computed(() => `${auth.user?.displayName ?? ''} ${auth.user?.surname ?? ''}`.trim())

watch(pageTitle, (title) => (document.title = `${title} · SN Daily Command Center`), { immediate: true })
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
    window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
    window.clearInterval(clock)
    window.removeEventListener('keydown', onKeydown)
})

async function signOut(): Promise<void> {
    await auth.logout()
    workspace.reset()
    await router.replace({ name: 'auth' })
}
</script>

<template>
    <main class="app-shell">
        <div v-if="navOpen" class="nav-scrim" @mousedown.self="navOpen = false">
            <aside class="nav-drawer" :aria-label="t('nav.main')">
                <div class="nav-drawer__head">
                    <div class="brand">
                        <span class="brand__mark"><Command :size="20" aria-hidden="true" /></span>
                        <span><b>SN</b><small>DAILY COMMAND CENTER</small></span>
                    </div>
                    <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="navOpen = false">
                        <X :size="18" />
                    </button>
                </div>
                <div class="nav-welcome">
                    <span>YOUR SPACE · YOUR PACE</span>
                    <b>{{ t('nav.welcomeTitle') }}</b>
                    <small>{{ t('nav.welcomeSub') }}</small>
                </div>

                <div class="nav-caption">{{ t('nav.workspace') }}</div>
                <nav class="nav-list">
                    <RouterLink
                        v-for="item in navItems"
                        :key="item.key"
                        :to="item.to"
                        class="nav-item"
                        :class="{ 'nav-item--active': item.active }"
                    >
                        <span class="nav-item__icon"><component :is="item.icon" :size="17" aria-hidden="true" /></span>
                        <span>{{ t(`nav.${item.key}`) }}</span>
                        <i v-if="item.key === 'achievements' && workspace.achievements.length" class="nav-item__count">
                            {{ workspace.achievements.length }}
                        </i>
                        <span v-if="item.active" class="nav-item__current">{{ t('nav.current') }}</span>
                    </RouterLink>
                </nav>

                <template v-if="auth.isAdmin">
                    <div class="nav-caption">{{ t('nav.administration') }}</div>
                    <nav class="nav-list">
                        <RouterLink
                            :to="{ name: 'admin' }"
                            class="nav-item"
                            :class="{ 'nav-item--active': route.name === 'admin' }"
                        >
                            <span class="nav-item__icon"><Shield :size="17" aria-hidden="true" /></span>
                            <span>{{ t('nav.admin') }}</span>
                            <span v-if="route.name === 'admin'" class="nav-item__current">{{ t('nav.current') }}</span>
                        </RouterLink>
                    </nav>
                </template>

                <div class="nav-drawer__foot">
                    <div class="mini-profile">
                        <UserAvatar :name="fullName" :src="auth.user?.avatarUrl" />
                        <div>
                            <b>{{ fullName }}</b>
                            <small>{{ t(`role.${auth.user?.role ?? 'user'}`) }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn--outline btn--block" @click="signOut">
                        <LogOut :size="16" aria-hidden="true" /> {{ t('nav.signOut') }}
                    </button>
                </div>
            </aside>
        </div>

        <section class="main-area">
            <header class="topbar">
                <div>
                    <span class="topbar__kicker">SN WORKSPACE / 01</span>
                    <h1>{{ pageTitle }}</h1>
                </div>
                <div class="topbar__actions">
                    <button
                        type="button"
                        class="chip-btn"
                        :aria-label="t('nav.switchLang')"
                        @click="setLocale(locale === 'ar' ? 'en' : 'ar')"
                    >
                        {{ t('nav.langButton') }}
                    </button>
                    <button
                        type="button"
                        class="chip-btn chip-btn--menu"
                        :aria-expanded="navOpen"
                        :aria-label="t('nav.open')"
                        @click="navOpen = !navOpen"
                    >
                        <span class="chip-btn__glyph"
                            ><component :is="navOpen ? X : Menu" :size="19" aria-hidden="true"
                        /></span>
                        <span>{{ t('nav.menu') }}</span>
                    </button>
                    <div class="date-chip">
                        <Clock3 :size="15" aria-hidden="true" />
                        <span>{{ formatDate(now, { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</span>
                        <b>{{ formatDate(now, { day: 'numeric', month: 'short' }) }}</b>
                    </div>
                    <RouterLink :to="{ name: 'profile' }" :aria-label="t('nav.profile')">
                        <UserAvatar :name="fullName" :src="auth.user?.avatarUrl" />
                    </RouterLink>
                </div>
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
