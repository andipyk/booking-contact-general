#!/usr/bin/env python3
"""
Turn raw browser captures into the assets the case study ships.

Two output sizes per still, because the page offers a click-to-enlarge view and
an "enlarge" that serves the same pixels back is a lie:

    portfolio/assets/<name>.jpg        width-capped, what the page renders
    portfolio/assets/full/<name>.jpg   the capture at its own width, for the
                                       enlarged view

docs/screenshots/ keeps the untouched captures as the build record, and is also
what this script rebuilds from on a re-run — pass a raw capture directory only
the first time.

The browser tool's own GIF export lands somewhere the shell cannot reach, so the
animation is stitched here from ordinary screenshots instead.

Usage:
    python3 bin/build-assets.py                 # rebuild from docs/screenshots/
    python3 bin/build-assets.py <raw-capture-dir>   # import captures first
"""
import pathlib
import shutil
import sys
from typing import Optional

from PIL import Image

DOCS = pathlib.Path("docs/screenshots")
WEB = pathlib.Path("portfolio/assets")
FULL = WEB / "full"
for d in (DOCS, WEB, FULL):
    d.mkdir(parents=True, exist_ok=True)

# Raw capture index -> the name it ships under.
IMPORT = {
    13: "01-home",
    12: "02-projects",
     9: "03-project-spec",
    15: "04-contact",
     4: "05-booking-days",
     5: "06-booking-day-selected",
     6: "07-booking-time",
     7: "08-booking-filled",
     8: "09-booking-confirmed",
    20: "10-mailpit",
    18: "11-lighthouse",
    17: "12-mobile",
    # n8n's own UI. Only capturable once an owner account exists, which is a
    # manual step — see the skill. Run #4 is kept because it is the callback
    # bug caught in the act: the emails carry green ticks and the callback node
    # is red, which is the exact shape of four of this build's six bugs.
    29: "13-n8n-overview",
    25: "14-n8n-canvas",
    26: "15-n8n-run-success",
    28: "16-n8n-run-failed",
}

# The booking flow, in order, with how long each frame holds.
FRAMES = [
    ("05-booking-days", 1700),
    ("06-booking-day-selected", 1500),
    ("07-booking-time", 1900),
    ("08-booking-filled", 2400),
    ("09-booking-confirmed", 3200),
]

WEB_WIDTH = 1000
GIF_WIDTH = 820
# The overlay copy, so "enlarge" on the animation means something on a desktop
# too. Loaded only when someone clicks, so the page itself stays light.
GIF_FULL_WIDTH = 1280


def import_raw(raw: pathlib.Path) -> None:
    """Copy the captures we keep, and drop anything left from an earlier run."""
    for stale in DOCS.glob("*.jpg"):
        if stale.stem not in IMPORT.values():
            stale.unlink()
            print(f"  dropped stale {stale.name}")
    for idx, name in IMPORT.items():
        hits = sorted(raw.glob(f"screenshot-*-{idx}.jpg"))
        if not hits:
            raise SystemExit(f"no capture with index {idx} in {raw}")
        shutil.copy(hits[-1], DOCS / f"{name}.jpg")
    print(f"  imported {len(IMPORT)} captures")


def encode(img: Image.Image, dest: pathlib.Path, width: Optional[int]) -> int:
    if width and img.width > width:
        img = img.resize((width, round(img.height * width / img.width)), Image.LANCZOS)
    img.convert("RGB").save(dest, "JPEG", quality=82, optimize=True, progressive=True)
    return dest.stat().st_size


if len(sys.argv) > 1:
    print("import:")
    import_raw(pathlib.Path(sys.argv[1]))

print("\nstills (page copy / enlarged copy):")
for src in sorted(DOCS.glob("*.jpg")):
    img = Image.open(src)
    small = encode(img.copy(), WEB / src.name, WEB_WIDTH)
    large = encode(img.copy(), FULL / src.name, None)
    print(f"  {src.stem:26} {small // 1024:>4} KB  /  {large // 1024:>4} KB  ({img.width}px)")

print("\nanimation:")
frames, durations = [], []
for name, ms in FRAMES:
    frames.append(Image.open(DOCS / f"{name}.jpg").convert("RGB"))
    durations.append(ms)

def write_gif(dest: pathlib.Path, width: int) -> int:
    sized = [
        f.resize((width, round(f.height * width / f.width)), Image.LANCZOS)
        if f.width != width else f
        for f in frames
    ]
    # One shared adaptive palette, no dithering: a flat UI bands otherwise.
    pal = [f.convert("P", palette=Image.ADAPTIVE, colors=128, dither=Image.NONE) for f in sized]
    pal[0].save(dest, save_all=True, append_images=pal[1:],
                duration=durations, loop=0, optimize=True, disposal=2)
    return dest.stat().st_size


small = write_gif(WEB / "booking-flow.gif", GIF_WIDTH)
large = write_gif(FULL / "booking-flow.gif", GIF_FULL_WIDTH)
shutil.copy(WEB / "booking-flow.gif", "docs/booking-flow.gif")
print(f"  booking-flow.gif           {small // 1024:>4} KB  /  {large // 1024:>4} KB  "
      f"({len(frames)} frames, {sum(durations) / 1000:.1f}s)")

page = sum(p.stat().st_size for p in WEB.glob("*.*"))
full = sum(p.stat().st_size for p in FULL.glob("*"))
print(f"\npage assets {page // 1024} KB   enlarged {full // 1024} KB   "
      f"total {(page + full) // 1024} KB")
