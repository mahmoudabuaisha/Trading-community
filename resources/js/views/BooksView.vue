<script setup lang="ts">
import { BookOpen, Crown, Lock, MessageCircle } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import EmptyState from '../components/EmptyState.vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { useUiStore } from '../stores/ui'
import type { Book } from '../types/domain'

/** The paid library: every member sees the shelf; a book opens for the subscribers it was granted to. */
const ui = useUiStore()
const books = ref<Book[]>([])
const loaded = ref(false)

onMounted(async () => {
    try {
        books.value = await repository.books()
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN LIBRARY</span>
                <h2>{{ t('books.head') }}</h2>
                <p>{{ t('books.sub') }}</p>
            </div>
            <BookOpen :size="22" class="gold" aria-hidden="true" />
        </div>

        <div v-if="books.length" class="book-grid">
            <article
                v-for="book in books"
                :key="book.id"
                class="dash-card book-card"
                :class="{ 'book-card--locked': !book.unlocked }"
            >
                <span class="book-cover">
                    <img v-if="book.coverUrl" :src="book.coverUrl" alt="" loading="lazy" />
                    <BookOpen v-else :size="40" aria-hidden="true" />
                    <span v-if="!book.unlocked" class="book-cover__lock"><Lock :size="22" aria-hidden="true" /></span>
                </span>
                <div class="book-card__body">
                    <span class="count-pill count-pill--gold"
                        ><Crown :size="12" aria-hidden="true" /> {{ t('books.paid') }}</span
                    >
                    <h3>{{ book.title }}</h3>
                    <p v-if="book.author" class="book-card__author">{{ book.author }}</p>
                    <p v-if="book.description" class="book-card__text">{{ book.description }}</p>
                    <a
                        v-if="book.unlocked && book.hasFile"
                        class="btn"
                        :href="`/api/books/${book.id}/read`"
                        target="_blank"
                        rel="noopener"
                    >
                        <BookOpen :size="15" aria-hidden="true" /> {{ t('books.read') }}
                    </a>
                    <p v-else-if="book.unlocked" class="hint">{{ t('books.soon') }}</p>
                    <RouterLink v-else :to="{ name: 'messages' }" class="btn btn--outline">
                        <MessageCircle :size="15" aria-hidden="true" /> {{ t('books.subscribe') }}
                    </RouterLink>
                </div>
            </article>
        </div>
        <EmptyState v-else-if="loaded" :title="t('books.emptyTitle')" :text="t('books.emptyText')" />
    </section>
</template>
