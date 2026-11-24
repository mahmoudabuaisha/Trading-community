<script setup lang="ts">
import { Lock } from 'lucide-vue-next'
import { ref } from 'vue'
import AuthScene from '../components/AuthScene.vue'
import { useRoute, useRouter } from 'vue-router'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { describeError } from '../stores/ui'

const route = useRoute()
const router = useRouter()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const password = ref('')
const busy = ref(false)
const error = ref('')

async function submit(): Promise<void> {
    busy.value = true
    error.value = ''
    try {
        await repository.resetPassword(String(route.params.token), email.value, password.value)
        await router.replace({ name: 'auth', query: { reset: '1' } })
    } catch (e) {
        error.value = describeError(e)
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <main class="auth-page auth-page--center">
        <AuthScene />
        <div class="auth-card auth-card--solo">
            <div class="auth-card__head">
                <div class="auth-card__icon"><Lock :size="20" aria-hidden="true" /></div>
                <span class="eyebrow"><span class="live-dot" /> SECURE ACCESS</span>
            </div>
            <div class="auth-card__welcome">
                <h1>{{ t('auth.resetTitle') }}</h1>
                <p>{{ t('auth.resetSub') }}</p>
            </div>
            <form class="stack stack--tight" @submit.prevent="submit">
                <label class="field">
                    <span>{{ t('auth.email') }}</span>
                    <input v-model="email" class="input" type="email" autocomplete="email" required dir="ltr" />
                </label>
                <label class="field">
                    <span>{{ t('auth.newPassword') }}</span>
                    <input
                        v-model="password"
                        class="input"
                        type="password"
                        autocomplete="new-password"
                        required
                        minlength="8"
                        dir="ltr"
                    />
                </label>
                <button type="submit" class="btn-metal" :disabled="busy">
                    {{ busy ? t('auth.checking') : t('auth.resetSubmit') }}
                </button>
                <RouterLink :to="{ name: 'auth' }" class="inline-link">{{ t('auth.backToSignIn') }}</RouterLink>
                <div v-if="error" class="notice notice--error" role="alert">{{ error }}</div>
            </form>
        </div>
    </main>
</template>
