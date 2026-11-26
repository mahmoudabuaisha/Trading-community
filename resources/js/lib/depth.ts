/**
 * Light depth for the whole workspace, kept cheap enough for phones:
 *
 * - the summit behind the pages drifts at a slower pace than the content as
 *   the page scrolls (layers at different depths), using transforms only;
 * - on devices with a mouse, the card under the pointer tilts slightly
 *   towards it, as if it were a physical plate.
 *
 * Both are skipped when the member asks for reduced motion.
 */
const TILTABLE = '.dash-card, .store-card, .leader-card, .tier-option, .goal-card, .stat-card'
const MAX_TILT = 4 // degrees

export function installDepth(): () => void {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return () => {}
    const root = document.documentElement
    let frame = 0
    let scrollY = window.scrollY

    const paint = () => {
        frame = 0
        root.style.setProperty('--depth-scroll', String(Math.min(scrollY, 2000)))
    }
    const onScroll = () => {
        scrollY = window.scrollY
        if (!frame) frame = requestAnimationFrame(paint)
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    paint()

    let current: HTMLElement | null = null
    const fine = matchMedia('(hover: hover) and (pointer: fine)').matches
    const release = (card: HTMLElement) => {
        card.style.removeProperty('--tilt-x')
        card.style.removeProperty('--tilt-y')
        card.classList.remove('is-tilting')
    }
    const onMove = (event: PointerEvent) => {
        const card = (event.target as Element | null)?.closest<HTMLElement>(TILTABLE) ?? null
        if (card !== current) {
            if (current) release(current)
            current = card
        }
        if (!card) return
        const box = card.getBoundingClientRect()
        // Large panels barely move; small cards respond more.
        const strength = Math.min(1, 520 / Math.max(box.width, box.height))
        const x = ((event.clientX - box.left) / box.width - 0.5) * 2
        const y = ((event.clientY - box.top) / box.height - 0.5) * 2
        card.classList.add('is-tilting')
        card.style.setProperty('--tilt-x', `${(x * MAX_TILT * strength).toFixed(2)}deg`)
        card.style.setProperty('--tilt-y', `${(-y * MAX_TILT * strength).toFixed(2)}deg`)
    }
    const onLeave = () => {
        if (current) release(current)
        current = null
    }
    if (fine) {
        document.addEventListener('pointermove', onMove, { passive: true })
        document.addEventListener('pointerleave', onLeave)
    }

    return () => {
        window.removeEventListener('scroll', onScroll)
        document.removeEventListener('pointermove', onMove)
        document.removeEventListener('pointerleave', onLeave)
        if (frame) cancelAnimationFrame(frame)
        onLeave()
    }
}
