import { onBeforeUnmount, onMounted, type Ref } from 'vue'

/**
 * Publishes the pointer position over `target` as two CSS variables,
 * `--px` and `--py` (each −1…1, eased), for depth/tilt effects in CSS.
 * Inactive on touch devices and when the user prefers reduced motion.
 */
export function usePointerParallax(target: Ref<HTMLElement | null>): void {
    let frame = 0
    let goalX = 0
    let goalY = 0
    let x = 0
    let y = 0

    function tick(): void {
        x += (goalX - x) * 0.08
        y += (goalY - y) * 0.08
        target.value?.style.setProperty('--px', x.toFixed(4))
        target.value?.style.setProperty('--py', y.toFixed(4))
        // Stop the loop once settled; the next pointer move restarts it.
        frame = Math.abs(goalX - x) + Math.abs(goalY - y) > 0.001 ? requestAnimationFrame(tick) : 0
    }

    function onMove(event: PointerEvent): void {
        if (event.pointerType !== 'mouse') return
        goalX = (event.clientX / window.innerWidth) * 2 - 1
        goalY = (event.clientY / window.innerHeight) * 2 - 1
        if (!frame) frame = requestAnimationFrame(tick)
    }

    function onLeave(): void {
        goalX = 0
        goalY = 0
        if (!frame) frame = requestAnimationFrame(tick)
    }

    onMounted(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
        window.addEventListener('pointermove', onMove, { passive: true })
        document.documentElement.addEventListener('pointerleave', onLeave)
    })
    onBeforeUnmount(() => {
        window.removeEventListener('pointermove', onMove)
        document.documentElement.removeEventListener('pointerleave', onLeave)
        cancelAnimationFrame(frame)
    })
}
