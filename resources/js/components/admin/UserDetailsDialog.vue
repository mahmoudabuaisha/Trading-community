<script setup lang="ts">
import { Award, CircleCheckBig, Flame, ListTodo, ShoppingBag, Star, Target, Timer, X } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import { repository } from '../../data/repository'
import { formatDate, messages, t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import type { UserDetails } from '../../types/domain'
import StatCard from '../StatCard.vue'
import UserAvatar from '../UserAvatar.vue'

/** Everything about one account, read-only. Private messages are deliberately not part of it. */
const props = defineProps<{ userId: number }>()
const emit = defineEmits<{ close: [] }>()
const ui = useUiStore()

const dialog = ref<HTMLDialogElement | null>(null)
const details = ref<UserDetails | null>(null)
const tab = ref<'tasks' | 'completedTasks'>('tasks')

onMounted(async () => {
    dialog.value?.showModal()
    try {
        details.value = await repository.adminUserDetails(props.userId)
    } catch (error) {
        ui.failure(error)
        emit('close')
    }
})

const date = (iso: string | null) => (iso ? formatDate(iso, { dateStyle: 'medium' }) : '—')
const dateTime = (iso: string | null) => (iso ? formatDate(iso, { dateStyle: 'medium', timeStyle: 'short' }) : '—')
const achievementTitle = (item: { key: string; title: string | null }) =>
    item.title ?? (messages.value.achievements.keys as Record<string, { title: string }>)[item.key]?.title ?? item.key
</script>

<template>
    <dialog ref="dialog" class="dialog dialog--wide" @cancel.prevent="emit('close')" @click.self="emit('close')">
        <div class="dialog__body">
            <div class="dialog__head">
                <h2>{{ t('admin.udHead') }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </div>

            <template v-if="details">
                <div class="user-details__who">
                    <UserAvatar :name="details.user.displayName" :frame="details.user.frame" size="lg" />
                    <div>
                        <h3>{{ details.user.displayName }} {{ details.user.surname }}</h3>
                        <p>
                            <bdi>{{ details.user.email }}</bdi>
                        </p>
                        <div class="row-actions">
                            <span class="count-pill count-pill--gold">{{ t(`role.${details.user.role}`) }}</span>
                            <span v-if="details.user.isPremium" class="count-pill count-pill--ok">{{
                                t('role.premium')
                            }}</span>
                            <span v-if="details.user.title" class="count-pill">{{ details.user.title }}</span>
                            <span v-for="key in details.user.specialties" :key="key" class="count-pill">{{
                                t(`specialty.${key}`)
                            }}</span>
                        </div>
                    </div>
                </div>

                <dl class="user-details__facts">
                    <div>
                        <dt>{{ t('profile.gender') }}</dt>
                        <dd>{{ t(`profile.genders.${details.user.gender ?? 'none'}`) }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('profile.names.nickname') }}</dt>
                        <dd>{{ details.user.nickname || '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('admin.colJoined') }}</dt>
                        <dd>{{ date(details.user.createdAt) }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('admin.udLastSeen') }}</dt>
                        <dd>{{ dateTime(details.user.lastSeenAt) }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('profile.timezone') }}</dt>
                        <dd dir="ltr">{{ details.user.timezone }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('profile.language') }}</dt>
                        <dd>{{ details.user.language.toUpperCase() }}</dd>
                    </div>
                    <div v-if="details.user.bio" class="user-details__wide">
                        <dt>{{ t('profile.bio') }}</dt>
                        <dd>{{ details.user.bio }}</dd>
                    </div>
                </dl>

                <div class="stat-grid">
                    <StatCard
                        :icon="Star"
                        :label="t('rewards.balance')"
                        :value="details.points.balance"
                        :note="t('admin.udEarned', { n: details.points.earned })"
                    />
                    <StatCard
                        :icon="ListTodo"
                        :label="t('admin.udTasks')"
                        :value="details.counts.tasks"
                        :note="t('admin.udTasksDone', { n: details.counts.tasksCompleted })"
                    />
                    <StatCard
                        :icon="Target"
                        :label="t('nav.goals')"
                        :value="details.counts.goals"
                        :note="t('admin.udTasksDone', { n: details.counts.goalsCompleted })"
                    />
                    <StatCard
                        :icon="Flame"
                        :label="t('journey.insights.streak')"
                        :value="details.counts.streak"
                        :note="t('admin.udHabits', { n: details.counts.habits })"
                    />
                    <StatCard
                        :icon="Timer"
                        :label="t('journey.insights.metrics.focusMinutes')"
                        :value="details.counts.focusMinutes"
                        :note="t('admin.udSkills', { n: details.counts.skillLevels })"
                    />
                </div>

                <section class="user-details__section">
                    <nav class="tabs" :aria-label="t('admin.udTasks')">
                        <button
                            type="button"
                            :class="{ tabs__active: tab === 'tasks' }"
                            :aria-pressed="tab === 'tasks'"
                            @click="tab = 'tasks'"
                        >
                            <ListTodo :size="14" aria-hidden="true" /> {{ t('admin.udAdded') }} ({{
                                details.counts.tasks
                            }})
                        </button>
                        <button
                            type="button"
                            :class="{ tabs__active: tab === 'completedTasks' }"
                            :aria-pressed="tab === 'completedTasks'"
                            @click="tab = 'completedTasks'"
                        >
                            <CircleCheckBig :size="14" aria-hidden="true" /> {{ t('admin.udCompleted') }} ({{
                                details.counts.tasksCompleted
                            }})
                        </button>
                    </nav>
                    <ul v-if="details[tab].length" class="device-list user-details__list">
                        <li v-for="task in details[tab]" :key="task.id" class="device-row">
                            <span>
                                <b>{{ task.title }}</b>
                                <small
                                    >{{ t(`period.${task.periodType}`) }} ·
                                    {{ tab === 'tasks' ? date(task.createdAt) : dateTime(task.completedAt) }}</small
                                >
                            </span>
                            <span class="count-pill" :class="{ 'count-pill--ok': task.status === 'completed' }">{{
                                t(`status.${task.status}`)
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="hint">{{ t('admin.udNone') }}</p>
                    <p v-if="details[tab].length === 50" class="hint">{{ t('admin.udLatest') }}</p>
                </section>

                <section class="user-details__section">
                    <h4><Award :size="16" aria-hidden="true" /> {{ t('nav.achievements') }}</h4>
                    <ul
                        v-if="details.achievements.length + details.badges.length"
                        class="device-list user-details__list"
                    >
                        <li
                            v-for="item in [...details.badges, ...details.achievements]"
                            :key="item.key"
                            class="device-row"
                        >
                            <span>
                                <b>{{ achievementTitle(item) }}</b>
                                <small
                                    >{{ date(item.earnedAt)
                                    }}{{ item.times > 1 ? ` · ${t('admin.udTimes', { n: item.times })}` : '' }}</small
                                >
                            </span>
                            <span v-if="item.points" class="count-pill count-pill--gold">{{
                                t('rewards.count', { n: item.points })
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="hint">{{ t('admin.udNone') }}</p>
                </section>

                <section class="user-details__section">
                    <h4><ShoppingBag :size="16" aria-hidden="true" /> {{ t('admin.udPurchases') }}</h4>
                    <ul v-if="details.purchases.length" class="device-list user-details__list">
                        <li v-for="(item, index) in details.purchases" :key="index" class="device-row">
                            <span>
                                <b>{{ item.name }}</b>
                                <small>{{ t(`store.kind.${item.kind}`) }} · {{ dateTime(item.boughtAt) }}</small>
                            </span>
                            <span class="count-pill count-pill--gold">{{ t('rewards.count', { n: item.price }) }}</span>
                        </li>
                    </ul>
                    <p v-else class="hint">{{ t('admin.udNone') }}</p>
                </section>

                <section v-if="details.goals.length" class="user-details__section">
                    <h4><Target :size="16" aria-hidden="true" /> {{ t('nav.goals') }}</h4>
                    <ul class="device-list user-details__list">
                        <li v-for="goal in details.goals" :key="goal.id" class="device-row">
                            <span
                                ><b>{{ goal.title }}</b></span
                            >
                            <span class="count-pill" :class="{ 'count-pill--ok': goal.status === 'completed' }"
                                >{{ goal.currentValue }} / {{ goal.targetValue }}</span
                            >
                        </li>
                    </ul>
                </section>
                <p class="hint">{{ t('admin.udPrivacy') }}</p>
            </template>
            <p v-else class="hint">{{ t('common.loading') }}</p>
        </div>
    </dialog>
</template>
