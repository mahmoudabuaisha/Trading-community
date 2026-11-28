<script setup lang="ts">
import { Eye, EyeOff, Lock, LogIn, Mail, UserRound } from 'lucide-vue-next'
import { computed, reactive, ref } from 'vue'
import AuthScene from '../components/AuthScene.vue'
import OfficialLinks from '../components/OfficialLinks.vue'
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

/** Left empty by people (it is hidden); sign-up bots fill it and are turned away. */
const trap = ref('')

/** "Sign in with Google" shows only once the server has its keys. */
const google = ref(false)
void repository
    .authProviders()
    .then((providers) => (google.value = providers.google))
    .catch(() => undefined)

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
    route.query.reset
        ? { kind: 'ok', text: t('auth.resetDone') }
        : route.query.google === 'failed'
          ? { kind: 'error', text: t('auth.googleFailed') }
          : null,
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
        else await auth.register(form.name.trim(), form.email, form.password, trap.value)
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
                    <input
                        v-if="mode === 'signup'"
                        v-model="trap"
                        class="auth-trap"
                        type="text"
                        name="website"
                        tabindex="-1"
                        autocomplete="off"
                        aria-hidden="true"
                    />
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

                <template v-if="google && mode !== 'forgot'">
                    <div class="auth-or">
                        <span>{{ t('auth.or') }}</span>
                    </div>
                    <a href="/auth/google" class="btn btn--outline auth-google">
                        <svg viewBox="0 0 48 48" aria-hidden="true" class="auth-google__logo">
                            <path
                                fill="#FFC107"
                                d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"
                            />
                            <path
                                fill="#FF3D00"
                                d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"
                            />
                            <path
                                fill="#4CAF50"
                                d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"
                            />
                            <path
                                fill="#1976D2"
                                d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"
                            />
                        </svg>
                        {{ t('auth.google') }}
                    </a>
                </template>

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
                <OfficialLinks compact class="auth-links" />
            </div>
        </section>
    </main>
</template>
