#!/usr/bin/env python3
"""
Draw n8n's execution history as an SVG.

n8n will not open its editor without an owner account, so its own execution list
is not screenshottable. This reads the same rows from the instance database
(via bin/n8n-executions.sh -> docs/n8n-executions.json) and draws them, which
has the useful property of keeping the failures visible: the early errors are
the debugging record, and a chart that hid them would be a nicer picture of a
less honest build.

Bars are duration. Colour is outcome. Both come from CSS custom properties, so
the drawing follows the page theme.

Output: docs/n8n-executions.svg
"""
import datetime
import json
import pathlib

rows = json.loads(pathlib.Path("docs/n8n-executions.json").read_text())


def ms(row):
    fmt = "%Y-%m-%d %H:%M:%S.%f"
    a = datetime.datetime.strptime(row["startedAt"][:23], fmt)
    b = datetime.datetime.strptime(row["stoppedAt"][:23], fmt)
    return int((b - a).total_seconds() * 1000)


runs = [(r["id"], r["status"], ms(r)) for r in rows]
peak = max(d for _, _, d in runs)

# Geometry. Room is left above the bars for the value labels and below for the
# run numbers, so nothing sits outside the viewBox.
PAD_L, PAD_R, PAD_T, PAD_B = 58, 22, 30, 52
BAR_W, GAP, PLOT_H = 46, 20, 190
W = PAD_L + len(runs) * BAR_W + (len(runs) - 1) * GAP + PAD_R
H = PAD_T + PLOT_H + PAD_B

parts = [
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" class="wf" role="img" '
    f'aria-label="Ten webhook executions in order. Runs 1 to 4 failed while the callback '
    f'was being debugged; runs 5 to 10 succeeded, taking between 266 and 1144 milliseconds.">'
]

# A baseline and two gridlines, labelled with the values they sit at.
for frac in (0.5, 1.0):
    y = PAD_T + PLOT_H - PLOT_H * frac
    parts.append(
        f'<line x1="{PAD_L}" y1="{y:.1f}" x2="{W - PAD_R}" y2="{y:.1f}" '
        f'stroke="var(--rule-soft)" stroke-width="1"/>'
    )
    parts.append(
        f'<text x="{PAD_L - 8}" y="{y + 3.5:.1f}" text-anchor="end" class="wf-note" '
        f'fill="var(--faint)">{round(peak * frac)}</text>'
    )
parts.append(
    f'<text x="{PAD_L - 8}" y="{PAD_T + PLOT_H + 3.5}" text-anchor="end" class="wf-note" '
    f'fill="var(--faint)">0</text>'
)
parts.append(
    f'<text x="{PAD_L - 8}" y="{PAD_T - 14}" text-anchor="end" class="wf-kind" '
    f'fill="var(--faint)">ms</text>'
)

for i, (rid, status, dur) in enumerate(runs):
    x = PAD_L + i * (BAR_W + GAP)
    h = max(3, PLOT_H * dur / peak)
    y = PAD_T + PLOT_H - h
    ok = status == "success"
    fill = "var(--blueprint)" if ok else "var(--redline)"
    # A failed run reads as an outline rather than a solid bar, so the eye
    # separates the two outcomes without relying on colour alone.
    outline = "" if ok else ' fill-opacity="0.28" stroke="var(--redline)" stroke-width="1.5"'
    parts.append(
        f'<rect x="{x}" y="{y:.1f}" width="{BAR_W}" height="{h:.1f}" fill="{fill}"{outline}/>'
    )
    parts.append(
        f'<text x="{x + BAR_W / 2}" y="{y - 7:.1f}" text-anchor="middle" class="wf-note" '
        f'fill="var(--muted)">{dur}</text>'
    )
    parts.append(
        f'<text x="{x + BAR_W / 2}" y="{PAD_T + PLOT_H + 17}" text-anchor="middle" '
        f'class="wf-kind" fill="{fill}">{rid}</text>'
    )
    parts.append(
        f'<text x="{x + BAR_W / 2}" y="{PAD_T + PLOT_H + 31}" text-anchor="middle" '
        f'class="wf-note" fill="var(--faint)">{"ok" if ok else "fail"}</text>'
    )

# Mark where the bugs stopped, between the last failure and the first success.
last_fail = max(i for i, (_, s, _) in enumerate(runs) if s != "success")
split = PAD_L + (last_fail + 1) * (BAR_W + GAP) - GAP / 2
parts.append(
    f'<line x1="{split:.1f}" y1="{PAD_T - 6}" x2="{split:.1f}" y2="{PAD_T + PLOT_H + 36}" '
    f'stroke="var(--rule-strong)" stroke-width="1" stroke-dasharray="3 3"/>'
)
parts.append(
    f'<text x="{split + 7:.1f}" y="{PAD_T - 14}" class="wf-kind" fill="var(--rule-strong)">'
    f'callback fixed</text>'
)
parts.append(
    f'<text x="{PAD_L}" y="{H - 8}" class="wf-note" fill="var(--muted)">'
    f'Runs 1–4 are the debugging record: the emails sent every time, and the confirmation '
    f'callback failed every time.</text>'
)
parts.append("</svg>")

out = pathlib.Path("docs/n8n-executions.svg")
out.write_text("\n".join(parts))
ok = sum(1 for _, s, _ in runs if s == "success")
print(f"✅ {out}  ({len(runs)} runs, {ok} succeeded, peak {peak} ms, viewBox 0 0 {W} {H})")
