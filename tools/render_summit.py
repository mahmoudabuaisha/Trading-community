"""Render the sign-in summit artwork from a procedural heightfield (voxel-space ray casting).

Usage: python3 tools/render_summit.py [supersample=2] [seed=10]
Writes mountain.png (RGBA) and preview.jpg to the current directory and prints the
peak position, which anchors the flag in resources/css/app.css (.summit__flag).
Export: crop 68px of empty sky from the top, then apply saturation 0.82,
brightness 0.8 and contrast 1.14 before saving public/images/summit.webp.
Requires numpy and Pillow.
"""
import sys
import numpy as np
from PIL import Image

N = 1024
rng = np.random.default_rng(42)


def noise(freq, seed):
    """Smooth value noise in [0,1] at `freq` cells across the map (bicubic upsample of a random grid)."""
    r = np.random.default_rng(seed).random((freq + 3, freq + 3)).astype(np.float32)
    img = Image.fromarray((r * 65535).astype(np.uint16).astype(np.int32), mode="I")
    big = img.resize((N + 3 * N // freq, N + 3 * N // freq), Image.BICUBIC)
    a = np.asarray(big, dtype=np.float32) / 65535.0
    return np.clip(a[:N, :N], 0, 1)


def ridged(octaves, base, seed, gain=2.0):
    total = np.zeros((N, N), np.float32)
    amp, weight, norm, f = 1.0, np.ones((N, N), np.float32), 0.0, base
    for o in range(octaves):
        n = 1.0 - np.abs(2.0 * noise(f, seed + o) - 1.0)
        n = (n ** 2.6) * weight
        weight = np.clip(n * gain, 0, 1)
        total += n * amp
        norm += amp
        amp *= 0.5
        f *= 2
    return total / norm


yy, xx = np.mgrid[0:N, 0:N].astype(np.float32)


def peak(cx, cy, height, sharp, spread):
    d = np.hypot((xx - cx), (yy - cy) * 1.15) / spread
    return height * np.exp(-(d ** sharp))


PX, PY = 512, 380
base = peak(PX, PY, 1.0, 0.95, 150) + peak(300, 452, 0.62, 1.1, 110) + peak(726, 458, 0.6, 1.1, 110)
base += peak(160, 505, 0.26, 1.2, 105) + peak(872, 508, 0.28, 1.2, 105) + peak(PX, 520, 0.15, 1.8, 420)
r1 = ridged(8, 3, int(sys.argv[2]) if len(sys.argv) > 2 else 10)
r2 = ridged(6, 24, 50)
H = base * (0.3 + 1.5 * r1) + 0.06 * r2 * np.clip(base * 2.5, 0, 1) + 0.02 * r1
# Keep a clean, single summit: pull the map toward the main peak's cone near its centre.
core = peak(PX, PY, 1.0, 1.0, 24)
H = H / H.max()
H = np.maximum(H, core * 0.0)
# The flanking peaks stay clearly below the summit.
H *= 0.5 + 0.5 * np.exp(-(((xx - PX) / 150.0) ** 2))
H = H / H.max() * 520.0  # world height units

# Normals and lighting (key light from the upper right, slightly behind the camera).
gy, gx = np.gradient(H)
nrm = np.dstack([-gx, -gy, np.full_like(H, 1.6)])
nrm /= np.linalg.norm(nrm, axis=2, keepdims=True)
L = np.array([0.72, 0.38, 0.58]); L /= np.linalg.norm(L)       # +x right, +y toward camera, +z up
Lf = np.array([-0.75, 0.3, 0.5]); Lf /= np.linalg.norm(Lf)      # cool fill from the left
diff = np.clip(nrm @ L, 0, 1)
fill = np.clip(nrm @ Lf, 0, 1)

# Cast shadows: march each texel toward the light and test the terrain above the ray.
shadow = np.ones((N, N), np.float32)
ix, iy = xx.astype(np.int32), yy.astype(np.int32)
for step in range(4, 260, 4):
    sx = np.clip((xx + L[0] * step).astype(np.int32), 0, N - 1)
    sy = np.clip((yy + L[1] * step).astype(np.int32), 0, N - 1)
    occluded = H[sy, sx] > H + L[2] * step / np.hypot(L[0], L[1]) * 0.9 + 2.0
    shadow = np.where(occluded, np.minimum(shadow, 0.28 + step / 900.0), shadow)

slope = 1.0 - nrm[:, :, 2]
grain = 0.82 + 0.36 * noise(256, 900)
albedo = (0.075 + 0.07 * r2 + 0.06 * np.clip(H / 520.0, 0, 1)) * grain
key = np.array([1.0, 0.84, 0.58]) * 3.4       # warm gold key
cool = np.array([0.34, 0.42, 0.62]) * 1.5    # blue fill
amb = np.array([0.1, 0.1, 0.12])
col = albedo[..., None] * (amb + key * (diff * shadow)[..., None] ** 1.15 + cool * fill[..., None])
# Frost / dust catching the light on steep upper faces.
dust = np.clip((H / 520.0 - 0.45) * 2.0, 0, 1) * np.clip(diff * shadow - 0.55, 0, 1) * 2.0
col += dust[..., None] * np.array([0.5, 0.44, 0.32])

SS = int(sys.argv[1]) if len(sys.argv) > 1 else 2
W, Hh = 1600 * SS, 1000 * SS
cam_x, cam_y, cam_h = 512.0, 1015.0, 70.0
horizon, scale_h = int(Hh * 0.8), 0.985 * Hh
half = 0.46  # tan(fov/2)

out = np.zeros((Hh, W, 3), np.float32)
alpha = np.zeros((Hh, W), np.float32)
ybuf = np.full(W, Hh, np.int32)
rows = np.arange(Hh, dtype=np.int32)[:, None]
cols = (np.arange(W, dtype=np.float32) / (W - 1) * 2 - 1) * half
z, dz = 120.0, 0.45
fog_col = np.array([0.03, 0.035, 0.05])
while z < 1000:
    wx = cam_x + cols * z
    wy = np.full(W, cam_y - z, np.float32)
    inside = (wx >= 0) & (wx < N - 1) & (wy >= 0) & (wy < N - 1)
    x0 = np.clip(np.floor(wx).astype(np.int32), 0, N - 2); y0 = np.clip(np.floor(wy).astype(np.int32), 0, N - 2)
    fx = np.clip(wx - x0, 0, 1); fy = np.clip(wy - y0, 0, 1)
    def bil(A):
        if A.ndim == 3:
            return (A[y0, x0] * ((1 - fx) * (1 - fy))[:, None] + A[y0, x0 + 1] * (fx * (1 - fy))[:, None]
                    + A[y0 + 1, x0] * ((1 - fx) * fy)[:, None] + A[y0 + 1, x0 + 1] * (fx * fy)[:, None])
        return A[y0, x0] * (1 - fx) * (1 - fy) + A[y0, x0 + 1] * fx * (1 - fy) + A[y0 + 1, x0] * (1 - fx) * fy + A[y0 + 1, x0 + 1] * fx * fy
    h = bil(H)
    top = ((cam_h - h) / z * scale_h + horizon).astype(np.int32)
    top = np.where(inside & (h > 6), top, Hh)
    top = np.clip(top, 0, Hh)
    draw = (rows >= top[None, :]) & (rows < ybuf[None, :])
    if draw.any():
        fog = np.clip((z - 560) / 520, 0, 0.7)
        c = bil(col) * (1 - fog) + fog_col * fog
        out[draw] = np.broadcast_to(c[None, :, :], (Hh, W, 3))[draw]
        alpha[draw] = 1.0
    ybuf = np.minimum(ybuf, top)
    z += dz
    dz *= 1.004

# Tone map, fade the base into the floor, add the gold rim glow on the silhouette's lit side.
img = 1 - np.exp(-out * 1.25)
fade = np.clip((Hh * 0.97 - rows) / (Hh * 0.3), 0, 1) ** 1.3
alpha *= fade
rgba = np.dstack([np.clip(img, 0, 1), alpha])
im = Image.fromarray((rgba * 255).astype(np.uint8), "RGBA").resize((1600, 1000), Image.LANCZOS)
im.save("mountain.png")
a = np.asarray(im)[:, :, 3]
ys, xs = np.where(a > 128)
t = ys.min(); px = int(np.median(xs[ys == t]))
print("peak px", px, t, "of 1600x1000 ->", round(px / 1600 * 100, 2), round(t / 1000 * 100, 2))
bg = Image.new("RGBA", im.size, (6, 6, 8, 255)); bg.alpha_composite(im); bg.convert("RGB").resize((800, 500)).save("preview.jpg", quality=88)
