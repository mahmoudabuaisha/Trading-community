<script setup lang="ts">
import { Eye, EyeOff, Lock, LogIn, Mail, UserRound } from 'lucide-vue-next'
import { computed, reactive, ref } from 'vue'
import AuthScene from '../components/AuthScene.vue'
import SummitArt from '../components/SummitArt.vue'
import { usePointerParallax } from '../composables/usePointerParallax'
import BrandLogo from '../components/BrandLogo.vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import { cycleLocale, locale, messages, t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { describeError } from '../stores/ui'

const page = ref<HTMLElement | null>(null)
usePointerParallax(page)

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const EMAIL_KEY = 'sn-dcc:remembered-email:v1'

function rememberedEmail(): string {
    try {
        return localStorage.getItem(EMAIL_KEY) ?? ''
    } catch {
        return ''
    }
}
function rememberEmail(value: string | null): void {
    try {
        if (value === null) localStorage.removeItem(EMAIL_KEY)
        else localStorage.setItem(EMAIL_KEY, value)
    } catch {
        /* storage unavailable — nothing to remember */
    }
}

type Mode = 'login' | 'signup' | 'forgot'
const mode = ref<Mode>('login')
const form = reactive({ name: '', email: rememberedEmail(), password: '' })
const showPassword = ref(false)
const busy = ref(false)
const notice = ref<{ kind: 'error' | 'ok'; text: string } | null>(
    route.query.reset ? { kind: 'ok', text: t('auth.resetDone') } : null,
)
const fieldErrors = ref<Record<string, string[]>>({})

const titles = { login: 'signInTitle', signup: 'signUpTitle', forgot: 'forgotTitle' } as const
const subtitles = { login: 'signInSub', signup: 'signUpSub', forgot: 'forgotSub' } as const
const submitLabels = { login: 'submitSignIn', signup: 'submitSignUp', forgot: 'sendLink' } as const

const strength = computed(
    () =>
        [
            form.password.length >= 8,
            /[A-Z]/.test(form.password) || /[ء-ي]/.test(form.password),
            /[0-9]/.test(form.password),
            /[^A-Za-z0-9ء-ي]/.test(form.password),
        ].filter(Boolean).length,
)

function switchMode(next: Mode): void {
    mode.value = next
    notice.value = null
    fieldErrors.value = {}
}

async function submit(): Promise<void> {
    busy.value = true
    notice.value = null
    fieldErrors.value = {}
    try {
        if (mode.value === 'forgot') {
            const result = await repository.forgotPassword(form.email)
            notice.value = { kind: 'ok', text: result.message }
            return
        }
        if (mode.value === 'login') await auth.login(form.email, form.password, true)
        else await auth.register(form.name.trim(), form.email, form.password)
        rememberEmail(form.email)
        const next =
            typeof route.query.next === 'string' && route.query.next.startsWith('/app')
                ? route.query.next
                : '/app/overview'
        await router.replace(next)
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) fieldErrors.value = error.errors
        notice.value = { kind: 'error', text: describeError(error) }
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <main ref="page" class="auth-page">
        <AuthScene />

        <header class="auth-topbar">
            <button type="button" class="chip-btn" :aria-label="t('nav.switchLang')" @click="cycleLocale()">
                {{ locale.toUpperCase() }}
            </button>
        </header>

        <section class="auth-stage">
            <div class="auth-hero">
                <SummitArt />
                <BrandLogo :label="t('auth.logoAlt')" />
                <h1 class="auth-tagline">{{ t('auth.tagline') }}</h1>
            </div>

            <div class="auth-card">
                <span class="auth-card__glint auth-card__glint--start" aria-hidden="true" />
                <span class="auth-card__glint auth-card__glint--end" aria-hidden="true" />

                <div class="auth-card__welcome">
                    <h2>{{ t(`auth.${titles[mode]}`) }}</h2>
                    <p class="auth-card__sub">{{ t(`auth.${subtitles[mode]}`) }}</p>
                </div>

                <form class="auth-form" @submit.prevent="submit">
                    <div v-if="mode === 'signup'" class="field">
                        <div class="input-wrap">
                            <UserRound :size="20" aria-hidden="true" />
                            <input
                                v-model="form.name"
                                autocomplete="name"
                                required
                                maxlength="80"
                                :placeholder="t('auth.fullName')"
                                :aria-label="t('auth.fullName')"
                            />
                        </div>
                        <small v-if="fieldErrors.displayName" class="field__error">{{
                            fieldErrors.displayName[0]
                        }}</small>
                    </div>

                    <div class="field">
                        <div class="input-wrap">
                            <Mail :size="20" aria-hidden="true" />
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                :placeholder="t('auth.email')"
                                :aria-label="t('auth.email')"
                            />
                        </div>
                        <small v-if="fieldErrors.email" class="field__error">{{ fieldErrors.email[0] }}</small>
                    </div>

                    <div v-if="mode !== 'forgot'" class="field">
                        <div class="input-wrap">
                            <Lock :size="20" aria-hidden="true" />
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                                required
                                minlength="8"
                                :placeholder="t('auth.password')"
                                :aria-label="t('auth.password')"
                            />
                            <button
                                type="button"
                                class="input-wrap__btn"
                                :aria-label="t(showPassword ? 'auth.hidePassword' : 'auth.showPassword')"
                                @click="showPassword = !showPassword"
                            >
                                <component :is="showPassword ? Eye : EyeOff" :size="19" />
                            </button>
                        </div>
                        <small v-if="fieldErrors.password" class="field__error">{{ fieldErrors.password[0] }}</small>
                    </div>

                    <div v-if="mode === 'signup'" class="strength" aria-live="polite">
                        <div class="strength__bars">
                            <i v-for="level in 4" :key="level" :class="{ on: strength >= level }" />
                        </div>
                        <span>
                            {{ t('auth.strength') }}
                            <b>{{
                                form.password ? messages.auth.strengthLevels[strength] : t('auth.strengthEmpty')
                            }}</b>
                        </span>
                        <small>{{ t('auth.strengthHint') }}</small>
                    </div>

                    <button type="submit" class="btn-metal" :disabled="busy">
                        <LogIn :size="20" aria-hidden="true" />
                        <span>{{ busy ? t('auth.checking') : t(`auth.${submitLabels[mode]}`) }}</span>
                    </button>

                    <button v-if="mode === 'login'" type="button" class="auth-forgot" @click="switchMode('forgot')">
                        {{ t('auth.forgot') }}
                    </button>

                    <div
                        v-if="notice"
                        class="notice"
                        :class="`notice--${notice.kind}`"
                        :role="notice.kind === 'error' ? 'alert' : 'status'"
                    >
                        {{ notice.text }}
                    </div>
                </form>

                <div class="auth-switch">
                    <template v-if="mode === 'login'">
                        <span>{{ t('auth.noAccount') }}</span>
                        <button type="button" class="inline-link" @click="switchMode('signup')">
                            {{ t('auth.createAccount') }}
                        </button>
                    </template>
                    <template v-else-if="mode === 'signup'">
                        <span>{{ t('auth.haveAccount') }}</span>
                        <button type="button" class="inline-link" @click="switchMode('login')">
                            {{ t('auth.signInTitle') }}
                        </button>
                    </template>
                    <button v-else type="button" class="inline-link" @click="switchMode('login')">
                        {{ t('auth.backToSignIn') }}
                    </button>
                </div>
            </div>
        </section>
    </main>
</template>
