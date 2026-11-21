<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    CalendarCheck,
    Check,
    CircleCheck,
    Clock3,
    Eye,
    EyeOff,
    Lock,
    Mail,
    Sparkles,
    Target,
    TrendingUp,
    UserRound,
    Volume2,
    VolumeX,
    Zap,
} from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import { formatDate, isRtl, locale, messages, setLocale, t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { describeError } from '../stores/ui'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const EMAIL_KEY = 'sn-dcc:remembered-email:v1'
const SOUND_KEY = 'sn-dcc:ui-sound:v1'

function readStorage(key: string): string {
    try {
        return localStorage.getItem(key) ?? ''
    } catch {
        return ''
    }
}
function writeStorage(key: string, value: string | null): void {
    try {
        if (value === null) localStorage.removeItem(key)
        else localStorage.setItem(key, value)
    } catch {
        /* storage unavailable — nothing to remember */
    }
}

type Mode = 'login' | 'signup' | 'forgot'
const mode = ref<Mode>('login')
const form = reactive({ name: '', email: readStorage(EMAIL_KEY), password: '', remember: true })
const showPassword = ref(false)
const busy = ref(false)
const notice = ref<{ kind: 'error' | 'ok'; text: string } | null>(
    route.query.reset ? { kind: 'ok', text: t('auth.resetDone') } : null,
)
const fieldErrors = ref<Record<string, string[]>>({})
const soundOn = ref(readStorage(SOUND_KEY) === 'on')
const now = ref(new Date())
let clock = 0

onMounted(() => (clock = window.setInterval(() => (now.value = new Date()), 1000)))
onBeforeUnmount(() => window.clearInterval(clock))

const quote = computed(() => messages.value.quotes[new Date().getDate() % messages.value.quotes.length])
const strength = computed(
    () =>
        [
            form.password.length >= 8,
            /[A-Z]/.test(form.password) || /[ء-ي]/.test(form.password),
            /[0-9]/.test(form.password),
            /[^A-Za-z0-9ء-ي]/.test(form.password),
        ].filter(Boolean).length,
)
const SubmitArrow = computed(() => (isRtl.value ? ArrowLeft : ArrowRight))

/** Short confirmation chime, only when the member opted in. */
function chime(from = 660, to = 880): void {
    if (!soundOn.value) return
    try {
        const context = new AudioContext()
        const oscillator = context.createOscillator()
        const gain = context.createGain()
        oscillator.type = 'sine'
        oscillator.frequency.setValueAtTime(from, context.currentTime)
        oscillator.frequency.exponentialRampToValueAtTime(to, context.currentTime + 0.07)
        gain.gain.setValueAtTime(0.0001, context.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.035, context.currentTime + 0.012)
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.12)
        oscillator.connect(gain).connect(context.destination)
        oscillator.start()
        oscillator.stop(context.currentTime + 0.13)
        oscillator.onended = () => void context.close()
    } catch {
        /* audio is a nicety; ignore browsers that block it */
    }
}

function toggleSound(): void {
    soundOn.value = !soundOn.value
    writeStorage(SOUND_KEY, soundOn.value ? 'on' : 'off')
    chime(720, 760)
}

function switchMode(next: Mode): void {
    mode.value = next
    notice.value = null
    fieldErrors.value = {}
    chime()
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
        if (mode.value === 'login') await auth.login(form.email, form.password, form.remember)
        else await auth.register(form.name.trim(), form.email, form.password)
        writeStorage(EMAIL_KEY, form.remember ? form.email : null)
        chime()
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
    <main class="auth-page">
        <div class="auth-ambient" aria-hidden="true"><i /><i /><i /></div>

        <header class="auth-topbar">
            <a class="brand" href="#top" aria-label="SN Daily Command Center">
                <span class="brand__mark"><Target :size="21" aria-hidden="true" /></span>
                <span><b>SN</b><small>DAILY COMMAND CENTER</small></span>
            </a>
            <div class="auth-topbar__meta">
                <span class="live-dot" /> <span>{{ t('brand.tagline') }}</span>
            </div>
            <div class="head-actions">
                <button
                    type="button"
                    class="chip-btn"
                    :aria-label="t('nav.switchLang')"
                    @click="setLocale(locale === 'ar' ? 'en' : 'ar')"
                >
                    {{ t('nav.langButton') }}
                </button>
                <button type="button" class="chip-btn" :aria-pressed="soundOn" @click="toggleSound">
                    <span>{{ t(soundOn ? 'auth.soundOn' : 'auth.soundOff') }}</span>
                    <component :is="soundOn ? Volume2 : VolumeX" :size="15" aria-hidden="true" />
                </button>
            </div>
        </header>

        <section id="top" class="auth-stage">
            <div class="auth-left">
                <div class="scene" aria-hidden="true">
                    <div class="scene__backlight" />
                    <div class="scene__pillars"><i /><i /><i /><i /><i /><i /><i /></div>
                    <div class="scene__planet"><span /></div>
                    <div class="scene__orbit scene__orbit--one" />
                    <div class="scene__orbit scene__orbit--two" />
                    <div class="scene__caption"><span>SN / THE LEGACY</span><b>BUILT BEYOND ROUTINE</b></div>
                </div>

                <div class="eyebrow eyebrow--boxed">
                    <span><Sparkles :size="14" aria-hidden="true" /></span> YOUR DAY. YOUR DIRECTION.
                </div>
                <h1>
                    {{ t('auth.heroLine1') }}<br /><em>{{ t('auth.heroLine2') }}</em>
                </h1>
                <p class="auth-lead">{{ t('auth.lead') }}</p>

                <div class="preview-card">
                    <div class="section-head">
                        <div>
                            <span class="mini-label">{{ t('auth.quickLook') }}</span>
                            <strong>{{ t('auth.scoreTitle') }}</strong>
                        </div>
                        <span class="eyebrow"><TrendingUp :size="14" aria-hidden="true" /> BUILT FOR MOMENTUM</span>
                    </div>
                    <div class="today-progress">
                        <div class="ring ring--lg" style="--pct: 82%">
                            <div>
                                <b>360°</b><small>{{ t('auth.fullView') }}</small>
                            </div>
                        </div>
                        <div>
                            <strong>{{ t('auth.allTools') }}</strong>
                            <span>{{ t('auth.cycle') }}</span>
                            <div class="track"><i style="inline-size: 82%" /></div>
                        </div>
                    </div>
                    <div class="preview-card__foot">
                        <span><CalendarCheck :size="14" aria-hidden="true" /> {{ t('auth.featTasks') }}</span>
                        <span><Zap :size="14" aria-hidden="true" /> {{ t('auth.featFocus') }}</span>
                        <span><Target :size="14" aria-hidden="true" /> {{ t('auth.featGoals') }}</span>
                    </div>
                </div>

                <div class="quote-card">
                    <span class="quote-card__icon" aria-hidden="true">“</span>
                    <div>
                        <small>{{ t('auth.quoteLabel') }}</small>
                        <p>{{ quote }}</p>
                    </div>
                </div>
            </div>

            <div class="auth-right">
                <div class="clock-card">
                    <div class="clock-card__icon"><Clock3 :size="16" aria-hidden="true" /></div>
                    <div>
                        <strong>{{
                            formatDate(now, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
                        }}</strong>
                        <span>{{
                            formatDate(now, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
                        }}</span>
                    </div>
                    <span class="count-pill count-pill--gold">LIVE</span>
                </div>

                <div class="auth-card">
                    <div class="auth-card__head">
                        <div class="auth-card__icon"><Lock :size="20" aria-hidden="true" /></div>
                        <span class="eyebrow"><span class="live-dot" /> SECURE ACCESS</span>
                    </div>
                    <div class="auth-card__welcome">
                        <span>{{ t(mode === 'signup' ? 'auth.startJourney' : 'auth.welcomeBack') }}</span>
                        <h2>
                            {{
                                t(
                                    mode === 'login'
                                        ? 'auth.signInTitle'
                                        : mode === 'signup'
                                          ? 'auth.signUpTitle'
                                          : 'auth.forgotTitle',
                                )
                            }}
                        </h2>
                        <p>
                            {{
                                t(
                                    mode === 'login'
                                        ? 'auth.signInSub'
                                        : mode === 'signup'
                                          ? 'auth.signUpSub'
                                          : 'auth.forgotSub',
                                )
                            }}
                        </p>
                    </div>

                    <div v-if="mode !== 'forgot'" class="segmented segmented--wide" role="tablist">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="mode === 'login'"
                            :class="{ segmented__active: mode === 'login' }"
                            @click="switchMode('login')"
                        >
                            {{ t('auth.tabSignIn') }}
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="mode === 'signup'"
                            :class="{ segmented__active: mode === 'signup' }"
                            @click="switchMode('signup')"
                        >
                            {{ t('auth.tabSignUp') }}
                        </button>
                    </div>

                    <form class="stack stack--tight" @submit.prevent="submit">
                        <label v-if="mode === 'signup'" class="field">
                            <span>{{ t('auth.fullName') }}</span>
                            <div class="input-wrap">
                                <UserRound :size="17" aria-hidden="true" />
                                <input
                                    v-model="form.name"
                                    autocomplete="name"
                                    required
                                    maxlength="80"
                                    :placeholder="t('auth.fullNamePh')"
                                />
                            </div>
                            <small v-if="fieldErrors.displayName" class="field__error">{{
                                fieldErrors.displayName[0]
                            }}</small>
                        </label>
                        <label class="field">
                            <span>{{ t('auth.email') }}</span>
                            <div class="input-wrap">
                                <Mail :size="17" aria-hidden="true" />
                                <input
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    dir="ltr"
                                    placeholder="name@example.com"
                                />
                            </div>
                            <small v-if="fieldErrors.email" class="field__error">{{ fieldErrors.email[0] }}</small>
                        </label>
                        <label v-if="mode !== 'forgot'" class="field">
                            <span>{{ t('auth.password') }}</span>
                            <div class="input-wrap">
                                <Lock :size="17" aria-hidden="true" />
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                                    required
                                    minlength="8"
                                    dir="ltr"
                                    placeholder="••••••••••"
                                />
                                <button
                                    type="button"
                                    class="input-wrap__btn"
                                    :aria-label="t(showPassword ? 'auth.hidePassword' : 'auth.showPassword')"
                                    @click="showPassword = !showPassword"
                                >
                                    <component :is="showPassword ? EyeOff : Eye" :size="17" />
                                </button>
                            </div>
                            <small v-if="fieldErrors.password" class="field__error">{{
                                fieldErrors.password[0]
                            }}</small>
                        </label>

                        <div v-if="mode === 'signup'" class="strength" aria-live="polite">
                            <div class="strength__bars">
                                <i v-for="level in 4" :key="level" :class="{ on: strength >= level }" />
                            </div>
                            <span
                                >{{ t('auth.strength') }}
                                <b>{{
                                    form.password ? messages.auth.strengthLevels[strength] : t('auth.strengthEmpty')
                                }}</b></span
                            >
                            <small>{{ t('auth.strengthHint') }}</small>
                        </div>

                        <div v-if="mode === 'login'" class="auth-options">
                            <label class="check"
                                ><input v-model="form.remember" type="checkbox" />
                                <span>{{ t('auth.remember') }}</span></label
                            >
                            <button type="button" class="inline-link" @click="switchMode('forgot')">
                                {{ t('auth.forgot') }}
                            </button>
                        </div>
                        <p v-else-if="mode === 'signup'" class="hint">{{ t('auth.terms') }}</p>

                        <button type="submit" class="btn btn--gold btn--block" :disabled="busy">
                            {{
                                busy
                                    ? t('auth.checking')
                                    : t(
                                          mode === 'login'
                                              ? 'auth.submitSignIn'
                                              : mode === 'signup'
                                                ? 'auth.submitSignUp'
                                                : 'auth.sendLink',
                                      )
                            }}
                            <component :is="SubmitArrow" :size="17" aria-hidden="true" />
                        </button>
                        <button v-if="mode === 'forgot'" type="button" class="inline-link" @click="switchMode('login')">
                            {{ t('auth.backToSignIn') }}
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

                    <div class="auth-card__foot">
                        <span><Check :size="13" aria-hidden="true" /> {{ t('auth.private') }}</span>
                        <span>SN WORKSPACE <b>•</b> 01</span>
                    </div>
                </div>
                <div class="auth-below"><CalendarCheck :size="15" aria-hidden="true" /> {{ t('auth.below') }}</div>
            </div>
        </section>

        <footer class="auth-footer">
            <span>© {{ now.getFullYear() }} SN DAILY COMMAND CENTER</span>
            <span>BUILT FOR FOCUS <b>✦</b> MADE FOR PROGRESS</span>
        </footer>

        <div class="float-widget" aria-hidden="true">
            <div class="float-widget__check"><CircleCheck :size="16" /></div>
            <span
                >{{ t('auth.floatNew') }}<br /><b>{{ t('auth.floatReady') }}</b></span
            >
        </div>
    </main>
</template>
