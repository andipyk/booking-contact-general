"""
Generate the project images.

Hotlinked placeholder services were rejected: a portfolio piece that 404s when
someone else's CDN changes is not finished work. These are drawn locally as
axonometric plan fragments, so the repo carries its own assets.
"""
from PIL import Image, ImageDraw
import random, math, pathlib

OUT = pathlib.Path("bin/seed-images")
OUT.mkdir(parents=True, exist_ok=True)
W, H = 1600, 1067

PALETTES = [
    ("#e8e2d6", "#1f2933", "#1d4ed8"),
    ("#dfe4e8", "#22272b", "#b45309"),
    ("#e6e1dc", "#14171a", "#0f766e"),
    ("#e3e6e2", "#1a1f24", "#7c2d92"),
    ("#ece7df", "#1f2328", "#b91c1c"),
    ("#dde3e6", "#161a1e", "#1d4ed8"),
]

def plan(seed, palette, out):
    random.seed(seed)
    bg, ink, accent = palette
    img = Image.new("RGB", (W, H), bg)
    d = ImageDraw.Draw(img, "RGBA")

    # Faint construction grid, like a drawing sheet.
    for x in range(0, W, 40):
        d.line([(x, 0), (x, H)], fill=(0, 0, 0, 14), width=1)
    for y in range(0, H, 40):
        d.line([(0, y), (W, y)], fill=(0, 0, 0, 14), width=1)

    # A rough floor plate carved into rooms by recursive splitting.
    m = 150
    rects = [(m, m, W - m, H - m)]
    for _ in range(random.randint(4, 6)):
        rects.sort(key=lambda r: (r[2] - r[0]) * (r[3] - r[1]), reverse=True)
        x0, y0, x1, y1 = rects.pop(0)
        if (x1 - x0) > (y1 - y0):
            cut = random.uniform(0.34, 0.66)
            xm = x0 + (x1 - x0) * cut
            rects += [(x0, y0, xm, y1), (xm, y0, x1, y1)]
        else:
            cut = random.uniform(0.34, 0.66)
            ym = y0 + (y1 - y0) * cut
            rects += [(x0, y0, x1, ym), (x0, ym, x1, y1)]

    for i, (x0, y0, x1, y1) in enumerate(rects):
        if i == random.randrange(len(rects)):
            d.rectangle([x0, y0, x1, y1], fill=accent + "22")
        d.rectangle([x0, y0, x1, y1], outline=ink, width=3)

    # Outer wall, heavier, the way a plan reads.
    d.rectangle([m, m, W - m, H - m], outline=ink, width=7)

    # Structural grid dots.
    for gx in range(m, W - m + 1, (W - 2 * m) // 4):
        for gy in range(m, H - m + 1, (H - 2 * m) // 3):
            d.ellipse([gx - 6, gy - 6, gx + 6, gy + 6], outline=accent, width=3)

    # A single service run, drawn dashed, as a duct or riser would be.
    ry = random.uniform(m + 60, H - m - 60)
    x = m + 20
    while x < W - m - 20:
        d.line([(x, ry), (min(x + 26, W - m - 20), ry)], fill=accent, width=5)
        x += 46

    # Section marker.
    d.line([(m, H - m + 46), (W - m, H - m + 46)], fill=ink, width=2)
    d.polygon([(m, H - m + 46), (m + 26, H - m + 34), (m + 26, H - m + 58)], fill=ink)
    d.polygon([(W - m, H - m + 46), (W - m - 26, H - m + 34), (W - m - 26, H - m + 58)], fill=ink)

    img.save(out, "JPEG", quality=86, optimize=True)
    return out

names = [
    "wythe-avenue-addition",
    "gowanus-workshop-fitout",
    "prospect-heights-brownstone",
    "navy-yard-lab",
    "bed-stuy-passive-house",
    "dumbo-loft-mep",
]
for i, n in enumerate(names):
    p = plan(i * 17 + 3, PALETTES[i], OUT / f"{n}.jpg")
    print(f"  ✓ {p.name}  {p.stat().st_size // 1024} KB")
