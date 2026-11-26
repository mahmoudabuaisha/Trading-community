/**
 * "My summit": the member's record as one sculpted diorama, like a trophy on
 * a desk. A single continuous mountain stands on an obsidian pedestal with a
 * gold rim; a gold path spirals up to the top. Everything sits in a fixed,
 * ordered place:
 *
 * - completed tasks light lanterns along the path, from the foot upwards;
 * - completed goals plant flags at stations on the path, and one at the top;
 * - this month's habit checks grow trees in a ring of thirty plots;
 * - life areas raise coloured obelisks evenly round the pedestal's rim;
 * - points become gold beads orbiting the mountain;
 * - the streak brightens the aurora and the halo over the summit.
 *
 * When a group outgrows its places, a small gold counter shows the total
 * instead of piling more pieces up. All of it is generated in code (no model
 * files) and kept light for phones: one indexed mountain mesh, instancing for
 * repeated pieces, no real-time shadows, capped pixel ratio and frame rate,
 * and nothing drawn while the tab is hidden.
 */
import {
    AdditiveBlending,
    BufferAttribute,
    BufferGeometry,
    CanvasTexture,
    CatmullRomCurve3,
    Color,
    ConeGeometry,
    CylinderGeometry,
    DirectionalLight,
    DoubleSide,
    FogExp2,
    Group,
    HemisphereLight,
    InstancedMesh,
    MathUtils,
    Mesh,
    MeshBasicMaterial,
    MeshStandardMaterial,
    Object3D,
    OctahedronGeometry,
    PerspectiveCamera,
    PlaneGeometry,
    PointLight,
    Points,
    PointsMaterial,
    Scene,
    SphereGeometry,
    Sprite,
    SpriteMaterial,
    SRGBColorSpace,
    TorusGeometry,
    TubeGeometry,
    Vector3,
    WebGLRenderer,
} from 'three'
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js'

export interface WorldData {
    name: string
    tasksCompleted: number
    areas: { name: string; color: string; done: number }[]
    goalsCompleted: string[]
    habitChecks: number
    streak: number
    pointsEarned: number
    skillLevels: number
    title: string | null
    accent: string
}

export interface SummitWorld {
    canvas: HTMLCanvasElement
    /** A clean picture of the scene at the given size (no text on it). */
    capture: (width: number, height: number) => Promise<Blob | null>
    dispose: () => void
}

import { PLACES, POINTS_PER_BEAD } from './places'

const BASE_RADIUS = 4.6
const PEDESTAL_TOP = 0
const PEDESTAL_RADIUS = 7.4
const PATH_TURNS = 2.6

// ── Small helpers ────────────────────────────────────────────────────────

/** Smooth, repeatable noise from a position, so the same member always gets the same mountain. */
function noise(x: number, y: number, seed: number): number {
    const s = Math.sin(x * 1.7 + seed) * Math.cos(y * 2.3 - seed * 0.5)
    const t = Math.sin(x * 3.9 - y * 1.3 + seed * 2.1) * 0.5
    const u = Math.sin(x * 7.1 + y * 5.7 + seed) * 0.25
    return (s + t + u) / 1.75
}

function canvasTexture(width: number, height: number, paint: (ctx: CanvasRenderingContext2D) => void): CanvasTexture {
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    paint(canvas.getContext('2d')!)
    const texture = new CanvasTexture(canvas)
    texture.colorSpace = SRGBColorSpace
    return texture
}

function glowTexture(color: string): CanvasTexture {
    return canvasTexture(64, 64, (ctx) => {
        const gradient = ctx.createRadialGradient(32, 32, 0, 32, 32, 32)
        gradient.addColorStop(0, color)
        gradient.addColorStop(0.3, `${color}99`)
        gradient.addColorStop(1, `${color}00`)
        ctx.fillStyle = gradient
        ctx.fillRect(0, 0, 64, 64)
    })
}

/** The night behind the diorama: deep indigo above, a warm glow of the accent near the horizon. */
function skyTexture(accent: string): CanvasTexture {
    return canvasTexture(16, 512, (ctx) => {
        const gradient = ctx.createLinearGradient(0, 0, 0, 512)
        gradient.addColorStop(0, '#05050b')
        gradient.addColorStop(0.55, '#0b0a12')
        // Opaque stops only: a see-through sky would wash out to white.
        gradient.addColorStop(0.82, `#${new Color('#0b0a12').lerp(new Color(accent), 0.22).getHexString()}`)
        gradient.addColorStop(1, '#0a0806')
        ctx.fillStyle = gradient
        ctx.fillRect(0, 0, 16, 512)
    })
}

function auroraTexture(accent: string): CanvasTexture {
    return canvasTexture(256, 64, (ctx) => {
        const vertical = ctx.createLinearGradient(0, 0, 0, 64)
        vertical.addColorStop(0, '#0000')
        vertical.addColorStop(0.55, `${accent}cc`)
        vertical.addColorStop(1, '#7fe0b000')
        ctx.fillStyle = vertical
        ctx.fillRect(0, 0, 256, 64)
        ctx.globalCompositeOperation = 'destination-in'
        const horizontal = ctx.createLinearGradient(0, 0, 256, 0)
        horizontal.addColorStop(0, '#0000')
        horizontal.addColorStop(0.25, '#000f')
        horizontal.addColorStop(0.75, '#000f')
        horizontal.addColorStop(1, '#0000')
        ctx.fillStyle = horizontal
        ctx.fillRect(0, 0, 256, 64)
    })
}

/** A small gold counter ("×128") that replaces pieces once a group is full. */
function counterSprite(text: string, accent: string): Sprite {
    const texture = canvasTexture(256, 96, (ctx) => {
        ctx.fillStyle = 'rgba(10, 9, 7, 0.82)'
        ctx.strokeStyle = accent
        ctx.lineWidth = 4
        ctx.beginPath()
        ctx.roundRect(6, 10, 244, 76, 38)
        ctx.fill()
        ctx.stroke()
        ctx.fillStyle = '#f3e2b8'
        ctx.font = '700 46px system-ui, sans-serif'
        ctx.textAlign = 'center'
        ctx.textBaseline = 'middle'
        ctx.fillText(text, 128, 50)
    })
    const sprite = new Sprite(new SpriteMaterial({ map: texture, transparent: true, depthWrite: false }))
    sprite.scale.set(1.6, 0.6, 1)
    return sprite
}

const compact = (n: number): string => (n >= 10000 ? `${Math.round(n / 1000)}k` : String(n))

// ── The mountain ─────────────────────────────────────────────────────────

/** The mountain's radius at an angle and a height fraction (0 at the foot, 1 at the top). */
function makeSurface(seed: number) {
    return (theta: number, t: number): number => {
        const taper = Math.pow(1 - t, 1.2)
        const ridges = 1 + 0.16 * noise(Math.cos(theta) * 2.2, t * 3.4, seed) + 0.07 * Math.sin(theta * 5 + seed)
        return BASE_RADIUS * taper * ridges
    }
}

/**
 * One closed, indexed mesh: rings of shared vertices round the mountain, a
 * single apex, and the base sealed against the pedestal, so there are no
 * seams, cracks or floating pieces.
 */
function mountainGeometry(
    height: number,
    surface: (theta: number, t: number) => number,
    accent: Color,
    seed: number,
): BufferGeometry {
    const segments = 56
    const rings = 22
    const positions: number[] = []
    const colors: number[] = []
    const rock = new Color('#3b352d')
    const rockLight = new Color('#6b604f')
    const snow = new Color('#efe6d2')
    const vein = accent.clone().multiplyScalar(0.9)
    const snowLine = 0.72

    for (let j = 0; j <= rings; j++) {
        const t = j / rings
        for (let i = 0; i < segments; i++) {
            const theta = (i / segments) * Math.PI * 2
            const r = j === rings ? 0 : surface(theta, t)
            positions.push(Math.cos(theta) * r, PEDESTAL_TOP + t * height, Math.sin(theta) * r)
            // Rock darkens towards the foot, snow caps the top, thin gold veins run through.
            const shade = 0.86 + 0.14 * noise(theta * 3, t * 9, seed + 3)
            let color = rock
                .clone()
                .lerp(rockLight, t * 0.9)
                .multiplyScalar(shade)
            const veinStrength = Math.max(0, 1 - Math.abs(noise(theta * 4.5, t * 6, seed + 9)) * 22)
            if (t < snowLine) color = color.lerp(vein, veinStrength * 0.45)
            const snowAmount = MathUtils.smoothstep(
                t + 0.05 * noise(theta * 6, t, seed),
                snowLine - 0.04,
                snowLine + 0.06,
            )
            color = color.lerp(snow, snowAmount)
            colors.push(color.r, color.g, color.b)
        }
    }
    const index: number[] = []
    for (let j = 0; j < rings; j++) {
        for (let i = 0; i < segments; i++) {
            const a = j * segments + i
            const b = j * segments + ((i + 1) % segments)
            const c = (j + 1) * segments + i
            const d = (j + 1) * segments + ((i + 1) % segments)
            index.push(a, c, b, b, c, d)
        }
    }
    const geometry = new BufferGeometry()
    geometry.setAttribute('position', new BufferAttribute(new Float32Array(positions), 3))
    geometry.setAttribute('color', new BufferAttribute(new Float32Array(colors), 3))
    geometry.setIndex(index)
    geometry.computeVertexNormals()
    return geometry
}

// ── The scene ────────────────────────────────────────────────────────────

export function createSummitWorld(
    host: HTMLElement,
    data: WorldData,
    options: { reducedMotion: boolean; lowPower: boolean },
): SummitWorld {
    const seed = (data.name.split('').reduce((sum, ch) => (sum * 31 + ch.charCodeAt(0)) % 9973, 7) % 97) / 10
    const accentHex = data.accent
    const accent = new Color(accentHex)
    const motion = !options.reducedMotion

    const renderer = new WebGLRenderer({ antialias: !options.lowPower, powerPreference: 'low-power' })
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, options.lowPower ? 1.25 : 1.75))
    renderer.outputColorSpace = SRGBColorSpace
    host.appendChild(renderer.domElement)

    const scene = new Scene()
    scene.background = skyTexture(accentHex)
    scene.fog = new FogExp2(0x07060d, 0.022)

    // The mountain grows with the work done: from a hill to a proud peak.
    const height = 4.2 + Math.min(1, Math.log10(1 + data.tasksCompleted) / 2.7) * 4.6
    const surface = makeSurface(seed)

    // Light: cool sky from above, a warm key from the front, a rim of the accent behind.
    scene.add(new HemisphereLight(0x8c90b8, 0x120e08, 1.15))
    const key = new DirectionalLight(0xffe2b0, 2.3)
    key.position.set(6, 10, 8)
    scene.add(key)
    const rim = new DirectionalLight(accent, 1.4)
    rim.position.set(-8, 6, -9)
    scene.add(rim)
    const summitLight = new PointLight(accent, 0, 9, 1.6)
    summitLight.position.set(0, height + 0.8, 0)
    scene.add(summitLight)

    // Stars.
    const starCount = options.lowPower ? 260 : 600
    const starPositions = new Float32Array(starCount * 3)
    for (let i = 0; i < starCount; i++) {
        const theta = Math.random() * Math.PI * 2
        const phi = Math.random() * Math.PI * 0.45
        const r = 60 + Math.random() * 30
        starPositions.set(
            [r * Math.sin(phi) * Math.cos(theta), r * Math.cos(phi) + 4, r * Math.sin(phi) * Math.sin(theta)],
            i * 3,
        )
    }
    const starGeometry = new BufferGeometry()
    starGeometry.setAttribute('position', new BufferAttribute(starPositions, 3))
    const stars = new Points(
        starGeometry,
        new PointsMaterial({
            color: 0xfff4dc,
            size: 0.35,
            sizeAttenuation: true,
            transparent: true,
            opacity: 0.85,
            fog: false,
        }),
    )
    scene.add(stars)

    // The pedestal: obsidian tiers with a gold rim, and a soft pool of light beneath.
    const obsidian = new MeshStandardMaterial({ color: 0x15130f, roughness: 0.45, metalness: 0.35 })
    const gold = new MeshStandardMaterial({
        color: accent,
        roughness: 0.28,
        metalness: 0.9,
        emissive: accent,
        emissiveIntensity: 0.18,
    })
    const top = new Mesh(new CylinderGeometry(PEDESTAL_RADIUS, PEDESTAL_RADIUS + 0.3, 0.9, 72), obsidian)
    top.position.y = PEDESTAL_TOP - 0.45
    const step = new Mesh(new CylinderGeometry(PEDESTAL_RADIUS + 0.55, PEDESTAL_RADIUS + 0.8, 0.45, 72), obsidian)
    step.position.y = PEDESTAL_TOP - 1.12
    const rimTop = new Mesh(new TorusGeometry(PEDESTAL_RADIUS, 0.07, 8, 96), gold)
    rimTop.rotation.x = Math.PI / 2
    rimTop.position.y = PEDESTAL_TOP
    const rimStep = new Mesh(new TorusGeometry(PEDESTAL_RADIUS + 0.55, 0.05, 8, 96), gold)
    rimStep.rotation.x = Math.PI / 2
    rimStep.position.y = PEDESTAL_TOP - 0.9
    scene.add(top, step, rimTop, rimStep)

    // The ground on the pedestal: dark moss between the mountain's foot and the rim.
    const ground = new Mesh(
        new CylinderGeometry(PEDESTAL_RADIUS - 0.05, PEDESTAL_RADIUS - 0.05, 0.04, 72),
        new MeshStandardMaterial({ color: 0x1d2218, roughness: 0.95 }),
    )
    ground.position.y = PEDESTAL_TOP + 0.01
    scene.add(ground)

    // The mountain itself.
    const mountain = new Mesh(
        mountainGeometry(height, surface, accent, seed),
        new MeshStandardMaterial({ vertexColors: true, flatShading: true, roughness: 0.82, metalness: 0.08 }),
    )
    scene.add(mountain)

    // The path: a gold ribbon spiralling from the foot to just under the summit.
    const pathAt = (u: number): Vector3 => {
        const t = 0.035 + u * 0.82
        const theta = u * PATH_TURNS * Math.PI * 2 + 0.6
        const r = surface(theta, t) + 0.1
        return new Vector3(Math.cos(theta) * r, PEDESTAL_TOP + t * height + 0.04, Math.sin(theta) * r)
    }
    const pathCurve = new CatmullRomCurve3(Array.from({ length: 160 }, (_, i) => pathAt(i / 159)))
    scene.add(new Mesh(new TubeGeometry(pathCurve, 320, 0.055, 6, false), gold))

    const dummy = new Object3D()
    const pieces: { appear: number; apply: (scale: number) => void }[] = []

    // Lanterns: one place per task along the path, lit from the bottom up.
    const lit = Math.min(data.tasksCompleted, PLACES.lanterns)
    const posts = new InstancedMesh(new CylinderGeometry(0.05, 0.07, 0.24, 6), obsidian, PLACES.lanterns)
    const flames = new InstancedMesh(
        new OctahedronGeometry(0.11, 0),
        new MeshStandardMaterial({ color: accent, emissive: accent, emissiveIntensity: 1.6, roughness: 0.4 }),
        Math.max(1, lit),
    )
    flames.count = lit
    const glowPositions = new Float32Array(Math.max(1, lit) * 3)
    for (let k = 0; k < PLACES.lanterns; k++) {
        const p = pathAt((k + 0.5) / PLACES.lanterns)
        dummy.position.copy(p).add(new Vector3(0, 0.12, 0))
        dummy.rotation.set(0, 0, 0)
        dummy.scale.setScalar(1)
        dummy.updateMatrix()
        posts.setMatrixAt(k, dummy.matrix)
        if (k < lit) {
            const flamePos = p.clone().add(new Vector3(0, 0.33, 0))
            glowPositions.set([flamePos.x, flamePos.y, flamePos.z], k * 3)
            const index = k
            pieces.push({
                appear: 0.3 + (k / PLACES.lanterns) * 1.6,
                apply: (scale) => {
                    dummy.position.copy(flamePos)
                    dummy.rotation.set(0, index, 0)
                    dummy.scale.setScalar(scale)
                    dummy.updateMatrix()
                    flames.setMatrixAt(index, dummy.matrix)
                    flames.instanceMatrix.needsUpdate = true
                },
            })
        }
    }
    scene.add(posts, flames)
    const glowGeometry = new BufferGeometry()
    glowGeometry.setAttribute('position', new BufferAttribute(glowPositions, 3))
    glowGeometry.setDrawRange(0, lit)
    const glows = new Points(
        glowGeometry,
        new PointsMaterial({
            map: glowTexture(accentHex),
            size: 0.9,
            transparent: true,
            depthWrite: false,
            blending: AdditiveBlending,
            opacity: 0.9,
        }),
    )
    scene.add(glows)
    if (data.tasksCompleted > PLACES.lanterns) {
        const counter = counterSprite(`✓ ${compact(data.tasksCompleted)}`, accentHex)
        const end = pathAt(1)
        counter.position
            .copy(end)
            .add(new Vector3(end.x, 0, end.z).normalize().multiplyScalar(1.6))
            .add(new Vector3(0, 0.5, 0))
        scene.add(counter)
    }

    // Flags: stations along the path for completed goals, and the member's flag on the summit.
    const flagGroup = new Group()
    const cloths: Mesh[] = []
    const clothMaterial = new MeshStandardMaterial({
        color: accent,
        emissive: accent,
        emissiveIntensity: 0.25,
        side: DoubleSide,
        roughness: 0.6,
    })
    const poleMaterial = new MeshStandardMaterial({ color: 0xe8dcc0, roughness: 0.4, metalness: 0.6 })
    const plantFlag = (at: Vector3, size: number, appear: number) => {
        const flag = new Group()
        const pole = new Mesh(new CylinderGeometry(0.025 * size, 0.03 * size, 1.1 * size, 6), poleMaterial)
        pole.position.y = 0.55 * size
        const cloth = new Mesh(new PlaneGeometry(0.55 * size, 0.34 * size), clothMaterial)
        cloth.geometry.translate(0.275 * size, 0, 0)
        cloth.position.y = 0.92 * size
        flag.add(pole, cloth)
        flag.position.copy(at)
        cloths.push(cloth)
        flagGroup.add(flag)
        pieces.push({ appear, apply: (scale) => flag.scale.setScalar(scale) })
    }
    const goalFlags = Math.min(data.goalsCompleted.length, PLACES.flags)
    for (let k = 0; k < goalFlags; k++) {
        const u = (k + 1) / (PLACES.flags + 1)
        plantFlag(pathAt(u).add(new Vector3(0, 0.02, 0)), 0.85, 1 + k * 0.15)
    }
    plantFlag(new Vector3(0, PEDESTAL_TOP + height - 0.06, 0), 1.35, 2)
    scene.add(flagGroup)
    if (data.goalsCompleted.length > PLACES.flags) {
        const counter = counterSprite(`⚑ ${compact(data.goalsCompleted.length)}`, accentHex)
        counter.position.set(1.4, PEDESTAL_TOP + height + 0.4, 0)
        scene.add(counter)
    }

    // Habit plots: thirty places in a ring round the mountain's foot; each check grows a tree.
    const plotRadius = BASE_RADIUS + 1.25
    const grown = Math.min(data.habitChecks, PLACES.plots)
    const plots = new InstancedMesh(
        new CylinderGeometry(0.16, 0.2, 0.06, 8),
        new MeshStandardMaterial({ color: 0x2b2418, roughness: 1 }),
        PLACES.plots,
    )
    const trees = new InstancedMesh(
        new ConeGeometry(0.26, 0.75, 7),
        new MeshStandardMaterial({ color: 0x3f8f6a, roughness: 0.8, flatShading: true }),
        Math.max(1, grown),
    )
    trees.count = grown
    for (let k = 0; k < PLACES.plots; k++) {
        const angle = (k / PLACES.plots) * Math.PI * 2
        const at = new Vector3(Math.cos(angle) * plotRadius, PEDESTAL_TOP + 0.05, Math.sin(angle) * plotRadius)
        dummy.position.copy(at)
        dummy.rotation.set(0, 0, 0)
        dummy.scale.setScalar(1)
        dummy.updateMatrix()
        plots.setMatrixAt(k, dummy.matrix)
        if (k < grown) {
            const index = k
            const lean = 0.85 + 0.3 * Math.abs(noise(k, 1, seed))
            pieces.push({
                appear: 0.5 + (k / PLACES.plots) * 1.2,
                apply: (scale) => {
                    dummy.position.copy(at).add(new Vector3(0, 0.38 * scale * lean, 0))
                    dummy.rotation.set(0, index, 0)
                    dummy.scale.set(scale, scale * lean, scale)
                    dummy.updateMatrix()
                    trees.setMatrixAt(index, dummy.matrix)
                    trees.instanceMatrix.needsUpdate = true
                },
            })
        }
    }
    scene.add(plots, trees)
    if (data.habitChecks > PLACES.plots) {
        const counter = counterSprite(`🌿 ${compact(data.habitChecks)}`, accentHex)
        counter.position.set(0, PEDESTAL_TOP + 0.9, plotRadius + 0.2)
        scene.add(counter)
    }

    // Life areas: obelisks evenly round the rim, as tall as the work done in each.
    const areas = data.areas.slice(0, PLACES.areas)
    areas.forEach((area, k) => {
        const angle = (k / Math.max(1, areas.length)) * Math.PI * 2 + Math.PI / 2
        const tall = 0.6 + Math.min(1, area.done / 40) * 1.7
        const color = new Color(area.color)
        const obelisk = new Mesh(
            new CylinderGeometry(0.0, 0.26, tall, 4),
            new MeshStandardMaterial({
                color,
                emissive: color,
                emissiveIntensity: 0.35,
                roughness: 0.3,
                metalness: 0.4,
                flatShading: true,
            }),
        )
        const base = new Mesh(new CylinderGeometry(0.34, 0.38, 0.14, 4), obsidian)
        const r = PEDESTAL_RADIUS - 0.5
        const holder = new Group()
        holder.position.set(Math.cos(angle) * r, PEDESTAL_TOP, Math.sin(angle) * r)
        base.position.y = 0.07
        obelisk.position.y = 0.14 + tall / 2
        const halo = new Sprite(
            new SpriteMaterial({
                map: glowTexture(`#${color.getHexString()}`),
                transparent: true,
                depthWrite: false,
                blending: AdditiveBlending,
            }),
        )
        halo.scale.setScalar(0.9)
        halo.position.y = 0.14 + tall
        holder.add(base, obelisk, halo)
        scene.add(holder)
        pieces.push({ appear: 0.2 + k * 0.08, apply: (scale) => holder.scale.set(1, scale, 1) })
    })

    // Points: gold beads orbiting at mid-height.
    const beadCount = Math.min(Math.floor(data.pointsEarned / POINTS_PER_BEAD), PLACES.beads)
    const beads = new InstancedMesh(new SphereGeometry(0.09, 8, 6), gold, Math.max(1, beadCount))
    beads.count = beadCount
    const orbitY = PEDESTAL_TOP + height * 0.5
    const orbitR = surface(0, 0.5) + 1.5
    scene.add(beads)
    if (Math.floor(data.pointsEarned / POINTS_PER_BEAD) > PLACES.beads) {
        const counter = counterSprite(`★ ${compact(data.pointsEarned)}`, accentHex)
        counter.position.set(orbitR + 0.4, orbitY + 0.6, 0)
        scene.add(counter)
    }

    // Streak: an aurora behind the summit and a halo over it, brighter with every day kept.
    const streakGlow = Math.min(1, data.streak / 14)
    const aurora = new Mesh(
        new PlaneGeometry(26, 7),
        new MeshBasicMaterial({
            map: auroraTexture(accentHex),
            transparent: true,
            opacity: 0.12 + streakGlow * 0.6,
            depthWrite: false,
            blending: AdditiveBlending,
            side: DoubleSide,
            fog: false,
        }),
    )
    aurora.position.set(0, height + 3.5, -11)
    scene.add(aurora)
    const halo = new Mesh(
        new TorusGeometry(0.75, 0.025, 6, 48),
        new MeshBasicMaterial({ color: accent, transparent: true, opacity: 0.25 + streakGlow * 0.6 }),
    )
    halo.rotation.x = Math.PI / 2
    halo.position.set(0, PEDESTAL_TOP + height + 1.75, 0)
    scene.add(halo)
    summitLight.intensity = 2 + streakGlow * 6

    // Camera: a slow dolly in on arrival (the one orchestrated moment), then a gentle turn.
    const camera = new PerspectiveCamera(38, 1, 0.1, 200)
    const target = new Vector3(0, height * 0.42, 0)
    /** Tall screens step back so the whole pedestal stays in the picture. */
    const fit = (aspect: number): number => Math.pow(Math.max(1, 1.15 / aspect), 0.95)
    const hostAspect = host.clientWidth / Math.max(1, host.clientHeight)
    const restPosition = new Vector3(13.5, height * 0.62 + 4, 15.5)
        .sub(target)
        .multiplyScalar(fit(hostAspect))
        .add(target)
    const startPosition = motion
        ? restPosition
              .clone()
              .multiplyScalar(1.45)
              .add(new Vector3(0, 4, 0))
        : restPosition.clone()
    camera.position.copy(startPosition)
    const controls = new OrbitControls(camera, renderer.domElement)
    controls.enableDamping = true
    controls.enablePan = false
    controls.minDistance = 10
    controls.maxDistance = 46
    controls.minPolarAngle = 0.45
    controls.maxPolarAngle = 1.42
    controls.target.copy(target)
    controls.autoRotateSpeed = 0.45
    controls.enabled = !motion

    const resize = () => {
        const width = host.clientWidth
        const h = host.clientHeight
        renderer.setSize(width, h, false)
        camera.aspect = width / Math.max(1, h)

        camera.updateProjectionMatrix()
    }
    resize()
    const observer = new ResizeObserver(resize)
    observer.observe(host)

    const intro = motion ? 2.4 : 0
    if (!motion) pieces.forEach((piece) => piece.apply(1))
    else pieces.forEach((piece) => piece.apply(0.001))

    let frame = 0
    let last = 0
    let elapsed = 0
    let running = true
    let introDone = !motion
    const interval = options.lowPower ? 1000 / 30 : 0

    const draw = (dt: number) => {
        elapsed += dt
        if (!introDone) {
            const k = MathUtils.smootherstep(Math.min(1, elapsed / intro), 0, 1)
            camera.position.lerpVectors(startPosition, restPosition, k)
            for (const piece of pieces) {
                const p = MathUtils.clamp((elapsed - piece.appear) / 0.45, 0, 1)
                // A little overshoot, as if each piece settles into its place.
                piece.apply(Math.max(0.001, p < 1 ? Math.sin(p * Math.PI * 0.62) / Math.sin(Math.PI * 0.62) : 1))
            }
            if (elapsed > intro + 1.6) {
                introDone = true
                pieces.forEach((piece) => piece.apply(1))
                controls.enabled = true
                controls.autoRotate = true
            }
        }
        if (motion) {
            cloths.forEach((cloth, index) => (cloth.rotation.y = Math.sin(elapsed * 2.1 + index) * 0.28))
            ;(aurora.material as MeshBasicMaterial).opacity =
                (0.12 + streakGlow * 0.6) * (0.82 + Math.sin(elapsed * 0.6) * 0.18)
            aurora.position.x = Math.sin(elapsed * 0.12) * 1.5
            halo.rotation.z = elapsed * 0.4
            stars.rotation.y = elapsed * 0.003
            ;(glows.material as PointsMaterial).size = 0.85 + Math.sin(elapsed * 3) * 0.08
        }
        for (let i = 0; i < beadCount; i++) {
            const angle = (i / beadCount) * Math.PI * 2 + (motion ? elapsed * 0.22 : 0)
            dummy.position.set(
                Math.cos(angle) * orbitR,
                orbitY + Math.sin(angle * 2 + elapsed * 0.8) * 0.18,
                Math.sin(angle) * orbitR,
            )
            dummy.rotation.set(0, 0, 0)
            dummy.scale.setScalar(1)
            dummy.updateMatrix()
            beads.setMatrixAt(i, dummy.matrix)
        }
        if (beadCount) beads.instanceMatrix.needsUpdate = true
        controls.update()
        renderer.render(scene, camera)
    }

    const loop = (now: number) => {
        frame = requestAnimationFrame(loop)
        if (!running) return
        if (interval && now - last < interval) return
        const dt = last ? Math.min(0.1, (now - last) / 1000) : 0
        last = now
        draw(dt)
    }
    frame = requestAnimationFrame(loop)

    const onVisibility = () => {
        running = document.visibilityState === 'visible'
        last = 0
    }
    document.addEventListener('visibilitychange', onVisibility)

    return {
        canvas: renderer.domElement,
        capture: (width, h) => {
            // Draw once at the requested size, read it, and put the view back.
            const ratio = renderer.getPixelRatio()
            const aspect = camera.aspect
            const position = camera.position.clone()
            renderer.setPixelRatio(1)
            renderer.setSize(width, h, false)
            camera.aspect = width / h
            camera.position
                .sub(controls.target)
                .multiplyScalar(fit(camera.aspect) / fit(aspect))
                .add(controls.target)
            camera.updateProjectionMatrix()
            if (!introDone) pieces.forEach((piece) => piece.apply(1))
            renderer.render(scene, camera)
            const blob = new Promise<Blob | null>((resolve) => renderer.domElement.toBlob(resolve, 'image/png'))
            renderer.setPixelRatio(ratio)
            camera.aspect = aspect
            camera.position.copy(position)
            resize()
            return blob
        },
        dispose: () => {
            cancelAnimationFrame(frame)
            document.removeEventListener('visibilitychange', onVisibility)
            observer.disconnect()
            controls.dispose()
            scene.traverse((object) => {
                const mesh = object as Mesh
                mesh.geometry?.dispose()
                const material = mesh.material as { dispose?: () => void; map?: { dispose: () => void } } | undefined
                material?.map?.dispose()
                material?.dispose?.()
            })
            ;(scene.background as CanvasTexture | null)?.dispose()
            renderer.dispose()
            renderer.domElement.remove()
        },
    }
}

/** Is WebGL available at all? Without it the page shows a still picture instead. */
export function webglAvailable(): boolean {
    try {
        const canvas = document.createElement('canvas')
        return Boolean(canvas.getContext('webgl2') ?? canvas.getContext('webgl'))
    } catch {
        return false
    }
}
