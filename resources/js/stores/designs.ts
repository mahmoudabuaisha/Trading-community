import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { repository } from '../data/repository'
import { useAuthStore } from './auth'
import type { Design, DesignTarget, User } from '../types/domain'

/** The designs of the goal map and the skill tree, and the one in use for each. */
export const useDesignsStore = defineStore('designs', () => {
    const designs = ref<Design[]>([])
    const selected = ref<Record<DesignTarget, number | null>>({ goal_map: null, skill_tree: null })
    const loaded = ref(false)

    function apply(result: { designs: Design[]; selected: Record<DesignTarget, number | null>; user: User }): void {
        designs.value = result.designs
        selected.value = result.selected
        useAuthStore().setUser(result.user)
        loaded.value = true
    }

    async function load(): Promise<void> {
        if (!loaded.value) apply(await repository.designs())
    }

    /** Skill trees can also wear a goal-map design (its colour, node shape and picture), drawn as a tree. */
    const forTarget = (target: DesignTarget) =>
        target === 'skill_tree'
            ? [
                  ...designs.value.filter((design) => design.target === target),
                  ...designs.value.filter((design) => design.target === 'goal_map'),
              ]
            : designs.value.filter((design) => design.target === target)
    const current = computed(
        () => (target: DesignTarget) =>
            designs.value.find((design) => design.id === selected.value[target]) ?? forTarget(target)[0] ?? null,
    )

    async function buy(design: Design): Promise<void> {
        apply(await repository.buyDesign(design.id))
    }

    async function use(design: Design, target?: DesignTarget): Promise<void> {
        apply(await repository.selectDesign(design.id, target))
    }

    return { designs, selected, loaded, load, forTarget, current, buy, use }
})
