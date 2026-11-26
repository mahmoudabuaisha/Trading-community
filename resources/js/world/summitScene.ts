/**
 * "My summit": a low-poly mountain floating in a night sky, built from what
 * the member actually did. Everything is generated in code (no model files)
 * and kept small so it runs on phones: a few thousand triangles, instanced
 * meshes for repeated things, no real-time shadows, capped pixel ratio and
 * frame rate, and no work at all while the tab is hidden.
 */
import {
    AdditiveBlending,
    AmbientLight,
    BufferAttribute,
    BufferGeometry,
    CanvasTexture,
    Color,
    ConeGeometry,
    CylinderGeometry,
    DirectionalLight,
    DoubleSide,
    FogExp2,
    Group,
    InstancedMesh,
    Mesh,
    MeshBasicMaterial,
    MeshLambertMaterial,
    Object3D,
    OctahedronGeometry,
    PerspectiveCamera,
    PlaneGeometry,
    Points,
    PointsMaterial,
    Scene,
    SphereGeometry,
    Sprite,
    SpriteMaterial,
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

/** A repeatable random sequence, so a member's world looks the same every visit. */
function seeded(seed: number): () => number {
    let s = seed >>> 0 || 1
    return () => {
        s ^= s << 13
        s ^= s >>> 17
        s ^= s << 5
        return ((s >>> 0) % 100000) / 100000
    }
}

function glowTexture(color: string): CanvasTexture {
    const canvas = document.createElement('canvas')
    canvas.width = canvas.height = 64
    const ctx = canvas.getContext('2d')!
    const gradient = ctx.createRadialGradient(32, 32, 0, 32, 32, 32)
    gradient.addColorStop(0, color)
    gradient.addColorStop(0.35, `${color}88`)
    gradient.addColorStop(1, `${color}00`)
    ctx.fillStyle = gradient
    ctx.fillRect(0, 0, 64, 64)
    return new CanvasTexture(canvas)
}

/** Vertical bands of colour for the aurora. */
function auroraTexture(accent: string): CanvasTexture {
    const canvas = document.createElement('canvas')
    canvas.width = 256
    canvas.height = 64
    const ctx = canvas.getContext('2d')!
    const vertical = ctx.createLinearGradient(0, 0, 0, 64)
    vertical.addColorStop(0, '#00000000')
    vertical.addColorStop(0.5, `${accent}cc`)
    vertical.addColorStop(1, '#7fe0b000')
    ctx.fillStyle = vertical
    ctx.fillRect(0, 0, 256, 64)
    ctx.globalCompositeOperation = 'destination-in'
    const horizontal = ctx.createLinearGradient(0, 0, 256, 0)
    horizontal.addColorStop(0, '#0000')
    horizontal.addColorStop(0.2, '#000f')
    horizontal.addColorStop(0.8, '#000f')
    horizontal.addColorStop(1, '#0000')
    ctx.fillStyle = horizontal
    ctx.fillRect(0, 0, 256, 64)
    return new CanvasTexture(canvas)
}

/** A rocky low-poly peak: a cone whose rings are jittered, with snow above a height. */
function peakGeometry(
    radius: number,
    height: number,
    random: () => number,
    snowLine: number,
    rock: Color,
    snow: Color,
): BufferGeometry {
    const geometry = new ConeGeometry(radius, height, 9, 6, false).toNonIndexed()
    const position = geometry.getAttribute('position') as BufferAttribute
    for (let i = 0; i < position.count; i++) {
        const y = position.getY(i)
        if (y < height / 2 - 0.01) {
            const k = 1 + (random() - 0.5) * 0.35
            position.setX(i, position.getX(i) * k)
            position.setZ(i, position.getZ(i) * k)
            position.setY(i, y + (random() - 0.5) * height * 0.06)
        }
    }
    // Flat shading by face: colour each triangle by the height of its centre.
    const colors = new Float32Array(position.count * 3)
    for (let i = 0; i < position.count; i += 3) {
        const centre = (position.getY(i) + position.getY(i + 1) + position.getY(i + 2)) / 3
        const t = (centre + height / 2) / height
        const shade = 0.75 + random() * 0.25
        const color =
            t > snowLine ? snow.clone().multiplyScalar(0.85 + random() * 0.15) : rock.clone().multiplyScalar(shade)
        for (let j = 0; j < 3; j++) colors.set([color.r, color.g, color.b], (i + j) * 3)
    }
    geometry.setAttribute('color', new BufferAttribute(colors, 3))
    geometry.computeVertexNormals()
    return geometry
}

export interface SummitWorld {
    canvas: HTMLCanvasElement
    /** Draw the next frame now (used when recording). */
    renderNow: () => void
    setAutoRotate: (on: boolean) => void
    /** Turn the camera by an angle (radians); used to make one full turn while recording. */
    turn: (radians: number) => void
    onFrame: (callback: (() => void) | null) => void
    dispose: () => void
}

export function createSummitWorld(
    host: HTMLElement,
    data: WorldData,
    options: { reducedMotion: boolean; lowPower: boolean },
): SummitWorld {
    const random = seeded(data.name.split('').reduce((sum, ch) => sum * 31 + ch.charCodeAt(0), 7))
    const accent = new Color(data.accent)

    const renderer = new WebGLRenderer({ antialias: !options.lowPower, alpha: true, powerPreference: 'low-power' })
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, options.lowPower ? 1.25 : 1.75))
    renderer.setClearColor(0x000000, 0)
    host.appendChild(renderer.domElement)

    const scene = new Scene()
    scene.fog = new FogExp2(0x07060d, 0.035)
    const camera = new PerspectiveCamera(42, 1, 0.1, 200)
    camera.position.set(0, 4.5, 15)

    scene.add(new AmbientLight(0x8a90b8, 0.9))
    const moon = new DirectionalLight(0xfff1d6, 1.6)
    moon.position.set(-6, 10, 6)
    scene.add(moon)
    const rim = new DirectionalLight(accent, 0.8)
    rim.position.set(6, 3, -6)
    scene.add(rim)

    // ── Stars ──
    const starCount = options.lowPower ? 700 : 1400
    const stars = new Float32Array(starCount * 3)
    for (let i = 0; i < starCount; i++) {
        const theta = random() * Math.PI * 2
        const phi = Math.acos(random() * 2 - 1)
        const r = 60 + random() * 30
        stars.set(
            [
                r * Math.sin(phi) * Math.cos(theta),
                Math.abs(r * Math.cos(phi)) * 0.8 + 2,
                r * Math.sin(phi) * Math.sin(theta),
            ],
            i * 3,
        )
    }
    const starGeometry = new BufferGeometry()
    starGeometry.setAttribute('position', new BufferAttribute(stars, 3))
    const starField = new Points(
        starGeometry,
        new PointsMaterial({
            color: 0xe9e4ff,
            size: 0.35,
            sizeAttenuation: true,
            transparent: true,
            opacity: 0.9,
            fog: false,
        }),
    )
    scene.add(starField)

    // ── The island and the main summit: taller the more tasks are done ──
    const world = new Group()
    scene.add(world)
    const rock = new Color(0x3a3f4f)
    const snow = new Color(0xf3f1ea)
    const height = 3.2 + Math.min(4.2, Math.log2(1 + data.tasksCompleted) * 0.7)
    const summit = new Mesh(
        peakGeometry(2.6, height, random, 0.72, rock, snow),
        new MeshLambertMaterial({ vertexColors: true, flatShading: true }),
    )
    summit.position.y = height / 2
    world.add(summit)

    const island = new Mesh(
        new CylinderGeometry(5.2, 2.2, 2.4, 10, 1),
        new MeshLambertMaterial({ color: 0x23202c, flatShading: true }),
    )
    island.position.y = -1.2
    world.add(island)
    const top = new Mesh(
        new CylinderGeometry(5.3, 5.2, 0.3, 10, 1),
        new MeshLambertMaterial({ color: 0x2f3a33, flatShading: true }),
    )
    top.position.y = 0.0
    world.add(top)

    // ── A peak for each life area, in its colour, sized by what was done there ──
    data.areas.forEach((area, index) => {
        const angle = (index / Math.max(1, data.areas.length)) * Math.PI * 2 + 0.4
        const h = 1 + Math.min(2.6, Math.log2(1 + area.done) * 0.6)
        const tint = new Color(area.color).lerp(rock, 0.55)
        const peak = new Mesh(
            peakGeometry(1.1, h, random, 0.8, tint, snow),
            new MeshLambertMaterial({ vertexColors: true, flatShading: true }),
        )
        peak.position.set(Math.cos(angle) * 3.6, h / 2, Math.sin(angle) * 3.6)
        world.add(peak)
        const crown = new Sprite(
            new SpriteMaterial({
                map: glowTexture(area.color),
                blending: AdditiveBlending,
                depthWrite: false,
                transparent: true,
            }),
        )
        crown.scale.setScalar(0.9)
        crown.position.set(peak.position.x, h + 0.25, peak.position.z)
        world.add(crown)
    })

    // ── Lanterns along a path spiralling up the summit: one per task, up to 60 ──
    const lanternCount = Math.min(60, data.tasksCompleted)
    const lanternTexture = glowTexture('#ffd28a')
    for (let i = 0; i < lanternCount; i++) {
        const t = (i + 1) / 61
        const angle = t * Math.PI * 6
        const y = t * height * 0.9
        const radius = 2.6 * (1 - y / height) + 0.15
        const lantern = new Sprite(
            new SpriteMaterial({
                map: lanternTexture,
                blending: AdditiveBlending,
                depthWrite: false,
                transparent: true,
            }),
        )
        lantern.scale.setScalar(0.45)
        lantern.position.set(Math.cos(angle) * radius, y + 0.1, Math.sin(angle) * radius)
        world.add(lantern)
    }

    // ── Trees on the slopes: one per habit check this month, up to 80 ──
    const treeCount = Math.min(80, data.habitChecks)
    if (treeCount) {
        const trees = new InstancedMesh(
            new ConeGeometry(0.16, 0.5, 5),
            new MeshLambertMaterial({ color: 0x3f8f5a, flatShading: true }),
            treeCount,
        )
        const dummy = new Object3D()
        for (let i = 0; i < treeCount; i++) {
            const angle = random() * Math.PI * 2
            const r = 3 + random() * 2
            dummy.position.set(Math.cos(angle) * r, 0.4, Math.sin(angle) * r)
            dummy.scale.setScalar(0.7 + random() * 0.6)
            dummy.updateMatrix()
            trees.setMatrixAt(i, dummy.matrix)
        }
        world.add(trees)
    }

    // ── A flag on the summit (always) and one per completed goal around it ──
    const flags = new Group()
    world.add(flags)
    const flagMaterial = new MeshBasicMaterial({ color: accent, side: DoubleSide })
    const poleMaterial = new MeshBasicMaterial({ color: 0xd9d4c4 })
    const plantFlag = (x: number, y: number, z: number, scale: number) => {
        const pole = new Mesh(new CylinderGeometry(0.02, 0.02, 1, 5), poleMaterial)
        pole.position.set(x, y + 0.5 * scale, z)
        pole.scale.setScalar(scale)
        const cloth = new Mesh(new PlaneGeometry(0.55, 0.32, 6, 1), flagMaterial)
        cloth.position.set(x + 0.28 * scale, y + 0.82 * scale, z)
        cloth.scale.setScalar(scale)
        flags.add(pole, cloth)
        return cloth
    }
    const cloths = [plantFlag(0, height, 0, 1.3)]
    data.goalsCompleted.slice(0, 12).forEach((_, index) => {
        const angle = (index / 12) * Math.PI * 2
        cloths.push(plantFlag(Math.cos(angle) * 1.1, height * 0.62, Math.sin(angle) * 1.1, 0.7))
    })

    // ── Crystals orbiting: one per 25 points earned, up to 30 ──
    const crystalCount = Math.min(30, Math.floor(data.pointsEarned / 25))
    let crystals: InstancedMesh | null = null
    if (crystalCount) {
        crystals = new InstancedMesh(
            new OctahedronGeometry(0.16, 0),
            new MeshLambertMaterial({ color: accent, emissive: accent, emissiveIntensity: 0.6, flatShading: true }),
            crystalCount,
        )
        world.add(crystals)
    }
    const crystalDummy = new Object3D()

    // ── Aurora: brighter the longer the streak ──
    const aurora = new Mesh(
        new PlaneGeometry(26, 6, 1, 1),
        new MeshBasicMaterial({
            map: auroraTexture(`#${accent.getHexString()}`),
            transparent: true,
            opacity: Math.min(0.85, 0.15 + data.streak * 0.05),
            blending: AdditiveBlending,
            depthWrite: false,
            side: DoubleSide,
            fog: false,
        }),
    )
    aurora.position.set(0, 12, -14)
    aurora.rotation.x = -0.2
    scene.add(aurora)

    // ── Mist under the island ──
    const mistTexture = glowTexture('#b8b4cc')
    const mists: Sprite[] = []
    for (let i = 0; i < 6; i++) {
        const mist = new Sprite(
            new SpriteMaterial({ map: mistTexture, transparent: true, opacity: 0.14, depthWrite: false }),
        )
        mist.scale.set(9, 3.5, 1)
        mist.position.set((random() - 0.5) * 12, -2.6 + random(), (random() - 0.5) * 10)
        scene.add(mist)
        mists.push(mist)
    }

    // A soft moon.
    const moonBall = new Mesh(new SphereGeometry(1.6, 16, 12), new MeshBasicMaterial({ color: 0xfff4dc, fog: false }))
    moonBall.position.set(-22, 20, -40)
    scene.add(moonBall)
    const moonHalo = new Sprite(
        new SpriteMaterial({
            map: glowTexture('#fff1d0'),
            blending: AdditiveBlending,
            transparent: true,
            depthWrite: false,
            fog: false,
        }),
    )
    moonHalo.scale.setScalar(12)
    moonHalo.position.copy(moonBall.position)
    scene.add(moonHalo)

    // ── Camera control: drag to turn, slow turn on its own ──
    const controls = new OrbitControls(camera, renderer.domElement)
    controls.enablePan = false
    controls.enableDamping = true
    controls.minDistance = 9
    controls.maxDistance = 22
    controls.minPolarAngle = 0.5
    controls.maxPolarAngle = 1.45
    controls.target.set(0, height * 0.45, 0)
    controls.autoRotate = !options.reducedMotion
    controls.autoRotateSpeed = 0.6

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

    let frame = 0
    let last = 0
    let elapsed = 0
    let running = true
    let afterRender: (() => void) | null = null
    const interval = options.lowPower ? 1000 / 30 : 0

    const draw = (dt: number) => {
        elapsed += dt
        if (!options.reducedMotion) {
            cloths.forEach((cloth, index) => (cloth.rotation.y = Math.sin(elapsed * 2.2 + index) * 0.25))
            aurora.position.x = Math.sin(elapsed * 0.15) * 2
            ;(aurora.material as MeshBasicMaterial).opacity =
                Math.min(0.85, 0.15 + data.streak * 0.05) * (0.8 + Math.sin(elapsed * 0.7) * 0.2)
            mists.forEach((mist, index) => (mist.position.x += Math.sin(elapsed * 0.1 + index) * 0.004))
            starField.rotation.y = elapsed * 0.004
        }
        if (crystals) {
            for (let i = 0; i < crystalCount; i++) {
                const angle = (i / crystalCount) * Math.PI * 2 + (options.reducedMotion ? 0 : elapsed * 0.25)
                crystalDummy.position.set(
                    Math.cos(angle) * 5.8,
                    height * 0.55 + Math.sin(angle * 3 + elapsed) * 0.3,
                    Math.sin(angle) * 5.8,
                )
                crystalDummy.rotation.set(elapsed, elapsed * 0.7, 0)
                crystalDummy.updateMatrix()
                crystals.setMatrixAt(i, crystalDummy.matrix)
            }
            crystals.instanceMatrix.needsUpdate = true
        }
        controls.update()
        renderer.render(scene, camera)
        afterRender?.()
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
        renderNow: () => draw(0),
        setAutoRotate: (on) => (controls.autoRotate = on && !options.reducedMotion),
        turn: (radians) => {
            const offset = camera.position.clone().sub(controls.target)
            const cos = Math.cos(radians)
            const sin = Math.sin(radians)
            camera.position.set(
                controls.target.x + offset.x * cos - offset.z * sin,
                camera.position.y,
                controls.target.z + offset.x * sin + offset.z * cos,
            )
        },
        onFrame: (callback) => (afterRender = callback),
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
