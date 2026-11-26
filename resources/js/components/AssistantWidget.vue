<script setup lang="ts">
import { Bot, Lock, MessageCircle, Send, Sparkles, X } from 'lucide-vue-next'
import { computed, nextTick, ref, watch } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { repository } from '../data/repository'
import { messages as dict, t } from '../i18n'
import { today } from '../lib/dates'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { AssistantStatus } from '../types/domain'

/**
 * The personal assistant. "My guide" is free for everyone: buttons whose
 * answers are built from the member's own data. "Ask freely" is the AI
 * assistant, a paid service opened in a member's subscription.
 */
interface Bubble {
    from: 'me' | 'bot'
    text: string
    link?: { to: RouteLocationRaw; label: string }
}

const workspace = useWorkspaceStore()
const ui = useUiStore()
const open = ref(false)
const mode = ref<'guide' | 'ai'>('guide')
const guide = ref<Bubble[]>([])
const chat = ref<Bubble[]>([])
const draft = ref('')
const thinking = ref(false)
const status = ref<AssistantStatus | null>(null)
const scroller = ref<HTMLElement | null>(null)

const shown = computed(() => (mode.value === 'guide' ? guide.value : chat.value))
const SECTIONS = ['tasks', 'goals', 'habits', 'journey', 'leaderboard', 'store', 'books', 'world'] as const
const ROUTES: Record<(typeof SECTIONS)[number], RouteLocationRaw> = {
    tasks: { name: 'tasks', params: { period: 'daily' } },
    goals: { name: 'goals' },
    habits: { name: 'habits' },
    journey: { name: 'journey' },
    leaderboard: { name: 'leaderboard' },
    store: { name: 'store' },
    books: { name: 'books' },
    world: { name: 'world' },
}

async function scrollDown(): Promise<void> {
    await nextTick()
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight, behavior: 'smooth' })
}
watch(open, async (isOpen) => {
    if (!isOpen) return
    if (!guide.value.length) guide.value.push({ from: 'bot', text: t('assistant.hello') })
    if (!status.value) status.value = await repository.assistantStatus().catch(() => null)
    void scrollDown()
})

function say(question: string, answer: Bubble): void {
    guide.value.push({ from: 'me', text: question }, answer)
    void scrollDown()
}

const PRIORITY = { urgent: 0, high: 1, medium: 2, low: 3 } as const

/** Answers built from the member's own data, no AI involved. */
async function ask(topic: 'today' | 'performance' | 'goal' | 'points' | 'contact'): Promise<void> {
    const question = t(`assistant.q.${topic}`)
    if (topic === 'today') {
        const open = workspace.todayTasks
            .filter((task) => task.status !== 'completed' && task.status !== 'cancelled')
            .sort((a, b) => PRIORITY[a.priority] - PRIORITY[b.priority])
        say(
            question,
            open.length
                ? {
                      from: 'bot',
                      text: `${t('assistant.a.todayList', { n: open.length })}\n${open
                          .slice(0, 5)
                          .map((task) => `• ${task.title}`)
                          .join('\n')}\n${t('assistant.a.todayTip')}`,
                      link: { to: { name: 'tasks', params: { period: 'daily' } }, label: t('assistant.open.tasks') },
                  }
                : {
                      from: 'bot',
                      text: t(workspace.todayTasks.length ? 'assistant.a.todayDone' : 'assistant.a.todayEmpty'),
                      link: { to: { name: 'tasks', params: { period: 'daily' } }, label: t('assistant.open.tasks') },
                  },
        )
    } else if (topic === 'performance') {
        try {
            const insights = await repository.insights(today())
            const days = dict.value.journey.weekdays
            const lines = insights.enoughData
                ? [
                      insights.bestWeekday !== null
                          ? t('assistant.a.bestDay', { day: days[insights.bestWeekday] })
                          : '',
                      insights.bestPart
                          ? t('assistant.a.bestPart', { part: t(`journey.parts.${insights.bestPart}`) })
                          : '',
                      t('assistant.a.compare', {
                          now: insights.current.tasksCompleted,
                          before: insights.previous.tasksCompleted,
                      }),
                      ...insights.tips
                          .slice(0, 2)
                          .map(
                              (tip) =>
                                  `• ${t(`journey.tips.${tip.key}`, { ...tip.params, ...(typeof tip.params.weekday === 'number' ? { day: days[tip.params.weekday] } : {}), ...(typeof tip.params.part === 'string' ? { part: t(`journey.parts.${tip.params.part}`) } : {}) })}`,
                          ),
                  ].filter(Boolean)
                : [t('assistant.a.notEnough')]
            say(question, {
                from: 'bot',
                text: lines.join('\n'),
                link: { to: { name: 'insights' }, label: t('assistant.open.insights') },
            })
        } catch (error) {
            ui.failure(error)
        }
    } else if (topic === 'goal') {
        const goal = workspace.goals.find((item) => item.status !== 'completed')
        if (!goal) {
            say(question, {
                from: 'bot',
                text: t('assistant.a.noGoal'),
                link: { to: { name: 'goals' }, label: t('assistant.open.goals') },
            })
            return
        }
        try {
            const steps = await repository.roadmap(goal.id)
            const stages = steps.filter((step) => step.parentId === null).sort((a, b) => a.position - b.position)
            const nextStep = stages
                .map(
                    (stage) =>
                        steps
                            .filter((step) => step.parentId === stage.id && !step.isDone)
                            .sort((a, b) => a.position - b.position)[0] ?? (stage.isDone ? null : stage),
                )
                .find(Boolean)
            say(question, {
                from: 'bot',
                text: nextStep
                    ? t('assistant.a.nextStep', { goal: goal.title, step: nextStep.title })
                    : t(stages.length ? 'assistant.a.goalDone' : 'assistant.a.noStages', { goal: goal.title }),
                link: { to: { name: 'roadmap', params: { id: goal.id } }, label: t('assistant.open.roadmap') },
            })
        } catch (error) {
            ui.failure(error)
        }
    } else if (topic === 'points') {
        const earning = workspace.badges.filter((badge) => badge.points > 0).slice(0, 4)
        say(question, {
            from: 'bot',
            text: [
                t('assistant.a.points'),
                ...earning.map((badge) => `• ${badge.title}: ${t('rewards.count', { n: badge.points })}`),
            ].join('\n'),
            link: { to: { name: 'store' }, label: t('assistant.open.store') },
        })
    } else {
        say(question, {
            from: 'bot',
            text: t('assistant.a.contact'),
            link: { to: { name: 'messages' }, label: t('assistant.open.messages') },
        })
    }
}

function explain(section: (typeof SECTIONS)[number]): void {
    say(t('assistant.q.explain', { name: t(`assistant.sections.${section}.name`) }), {
        from: 'bot',
        text: t(`assistant.sections.${section}.text`),
        link: { to: ROUTES[section], label: t('assistant.open.go') },
    })
}

async function send(): Promise<void> {
    const text = draft.value.trim()
    if (!text || thinking.value || !status.value?.allowed) return
    chat.value.push({ from: 'me', text })
    draft.value = ''
    thinking.value = true
    void scrollDown()
    try {
        const history = chat.value.slice(-10).map((bubble) => ({
            role: bubble.from === 'me' ? ('user' as const) : ('assistant' as const),
            content: bubble.text,
        }))
        // The conversation must start with the member's words.
        while (history.length && history[0].role !== 'user') history.shift()
        const result = await repository.askAssistant(history)
        chat.value.push({ from: 'bot', text: result.reply })
        status.value = result
    } catch (error) {
        chat.value.pop()
        draft.value = text
        ui.failure(error)
    } finally {
        thinking.value = false
        void scrollDown()
    }
}
</script>

<template>
    <div class="assistant" :class="{ 'assistant--open': open }">
        <button
            v-if="!open"
            type="button"
            class="assistant__fab"
            :aria-label="t('assistant.openWidget')"
            @click="open = true"
        >
            <Bot :size="24" aria-hidden="true" />
        </button>
        <section v-else class="assistant__panel" role="dialog" :aria-label="t('assistant.title')">
            <header class="assistant__head">
                <Bot :size="20" aria-hidden="true" />
                <b>{{ t('assistant.title') }}</b>
                <button
                    type="button"
                    class="icon-btn icon-btn--sm"
                    :aria-label="t('common.close')"
                    @click="open = false"
                >
                    <X :size="16" />
                </button>
            </header>
            <nav class="assistant__tabs">
                <button
                    type="button"
                    :class="{ on: mode === 'guide' }"
                    :aria-pressed="mode === 'guide'"
                    @click="mode = 'guide'"
                >
                    <MessageCircle :size="14" aria-hidden="true" /> {{ t('assistant.guide') }}
                </button>
                <button type="button" :class="{ on: mode === 'ai' }" :aria-pressed="mode === 'ai'" @click="mode = 'ai'">
                    <Sparkles :size="14" aria-hidden="true" /> {{ t('assistant.ai') }}
                </button>
            </nav>

            <div ref="scroller" class="assistant__log" aria-live="polite">
                <div
                    v-for="(bubble, index) in shown"
                    :key="index"
                    class="assistant__bubble"
                    :class="`assistant__bubble--${bubble.from}`"
                >
                    <p dir="auto">{{ bubble.text }}</p>
                    <RouterLink v-if="bubble.link" :to="bubble.link.to" class="pill" @click="open = false">{{
                        bubble.link.label
                    }}</RouterLink>
                </div>
                <div v-if="thinking" class="assistant__bubble assistant__bubble--bot assistant__typing">
                    <i /><i /><i />
                </div>

                <template v-if="mode === 'ai' && status && !status.allowed">
                    <div class="assistant__locked">
                        <Lock :size="22" aria-hidden="true" />
                        <p>{{ t('assistant.aiLocked') }}</p>
                        <RouterLink :to="{ name: 'messages' }" class="btn btn--outline" @click="open = false">{{
                            t('books.subscribe')
                        }}</RouterLink>
                    </div>
                </template>
                <p v-else-if="mode === 'ai' && status && !status.configured" class="hint">
                    {{ t('assistant.aiSoon') }}
                </p>
                <p v-else-if="mode === 'ai' && !chat.length" class="hint">{{ t('assistant.aiHello') }}</p>
            </div>

            <div v-if="mode === 'guide'" class="assistant__chips">
                <button
                    v-for="topic in ['today', 'performance', 'goal', 'points', 'contact'] as const"
                    :key="topic"
                    type="button"
                    class="pill"
                    @click="ask(topic)"
                >
                    {{ t(`assistant.q.${topic}`) }}
                </button>
                <details class="assistant__explain">
                    <summary class="pill">{{ t('assistant.explainMenu') }}</summary>
                    <div>
                        <button
                            v-for="section in SECTIONS"
                            :key="section"
                            type="button"
                            class="pill"
                            @click="explain(section)"
                        >
                            {{ t(`assistant.sections.${section}.name`) }}
                        </button>
                    </div>
                </details>
            </div>
            <form v-else-if="status?.allowed && status.configured" class="assistant__input" @submit.prevent="send">
                <input
                    v-model="draft"
                    class="input"
                    maxlength="2000"
                    :placeholder="t('assistant.placeholder')"
                    :aria-label="t('assistant.placeholder')"
                    :disabled="thinking || status.remaining <= 0"
                />
                <button
                    type="submit"
                    class="icon-btn"
                    :aria-label="t('assistant.send')"
                    :disabled="thinking || !draft.trim() || status.remaining <= 0"
                >
                    <Send :size="16" />
                </button>
                <small class="hint">{{ t('assistant.remaining', { n: status.remaining, limit: status.limit }) }}</small>
            </form>
        </section>
    </div>
</template>
