#!/usr/bin/env python3
"""
Render the n8n workflow as an SVG from the workflow file itself.

n8n will not open its editor until an owner account exists, so a UI screenshot
is not available without typing a password into the instance. Drawing from
n8n/workflows/ess-booking.json instead keeps the picture tied to the file that
actually ships: change the workflow and re-run this, and the diagram cannot
drift from what runs.

Colours come from CSS custom properties so the drawing follows the page theme.
Output: docs/n8n-workflow.svg
"""
import json
import pathlib

WF = json.loads(pathlib.Path("n8n/workflows/ess-booking.json").read_text())[0]
nodes = {n["name"]: n for n in WF["nodes"]}

# Short, human labels. The node's own type string is machinery, not information.
ROLE = {
    "Booking received":    ("Webhook", "WordPress POSTs the booking"),
    "Normalise":           ("Code", "one field set for everything downstream"),
    "Email the client":    ("Send email", "confirmation with the slot"),
    "Email the studio":    ("Send email", "the brief, to the practice"),
    "Mark delivery sent":  ("Code", "sentinel: sent"),
    "Mark delivery failed":("Code", "sentinel: failed"),
    "Tell WordPress":      ("HTTP", "signed callback → confirmed"),
    "Respond":             ("Respond", "close the webhook"),
}
ERROR_PATH = {"Mark delivery failed"}

BOX_W, BOX_H = 178, 62
PAD_X, PAD_Y = 30, 54

xs = [n["position"][0] for n in nodes.values()]
ys = [n["position"][1] for n in nodes.values()]
min_x, min_y = min(xs), min(ys)


def place(node):
    x, y = node["position"]
    return (x - min_x) + PAD_X, (y - min_y) + PAD_Y


W = (max(xs) - min_x) + BOX_W + PAD_X * 2
H = (max(ys) - min_y) + BOX_H + PAD_Y * 2 + 26

parts = [
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" '
    f'role="img" aria-label="The booking workflow: webhook, normalise, two emails, '
    f'a success or failure sentinel, a signed callback to WordPress, then respond." '
    f'class="wf">',
    '<defs>',
    '<marker id="wf-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" '
    'markerHeight="7" orient="auto-start-reverse">'
    '<path d="M0,0 L10,5 L0,10 z" fill="var(--rule-strong)"/></marker>',
    '<marker id="wf-arrow-err" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" '
    'markerHeight="7" orient="auto-start-reverse">'
    '<path d="M0,0 L10,5 L0,10 z" fill="var(--redline)"/></marker>',
    '</defs>',
]

# Connectors first, so boxes sit on top of the lines.
for src, outputs in WF["connections"].items():
    sx, sy = place(nodes[src])
    for out_index, targets in enumerate(outputs["main"]):
        for t in targets:
            tx, ty = place(nodes[t["node"]])
            err = t["node"] in ERROR_PATH or out_index == 1
            x1, y1 = sx + BOX_W, sy + BOX_H / 2
            x2, y2 = tx, ty + BOX_H / 2
            mid = x1 + (x2 - x1) / 2
            d = f"M {x1} {y1} C {mid} {y1}, {mid} {y2}, {x2} {y2}"
            stroke = "var(--redline)" if err else "var(--rule-strong)"
            dash = ' stroke-dasharray="5 4"' if err else ""
            marker = "wf-arrow-err" if err else "wf-arrow"
            parts.append(
                f'<path d="{d}" fill="none" stroke="{stroke}" stroke-width="1.5"'
                f'{dash} marker-end="url(#{marker})"/>'
            )

for name, node in nodes.items():
    x, y = place(node)
    kind, note = ROLE.get(name, ("", ""))
    err = name in ERROR_PATH
    stroke = "var(--redline)" if err else "var(--ink)"
    parts.append(
        f'<rect x="{x}" y="{y}" width="{BOX_W}" height="{BOX_H}" rx="3" '
        f'fill="var(--surface)" stroke="{stroke}" stroke-width="1.5"/>'
    )
    parts.append(
        f'<text x="{x + 12}" y="{y + 23}" class="wf-name" fill="var(--ink)">{name}</text>'
    )
    parts.append(
        f'<text x="{x + 12}" y="{y + 40}" class="wf-kind" '
        f'fill="{"var(--redline)" if err else "var(--blueprint)"}">{kind}</text>'
    )
    parts.append(
        f'<text x="{x + 12}" y="{y + 54}" class="wf-note" fill="var(--muted)">{note}</text>'
    )

# One legend line, because the dashed branch is the part worth explaining.
parts.append(
    f'<text x="{PAD_X}" y="{H - 8}" class="wf-note" fill="var(--muted)">'
    f'Dashed: the recovery branch. A failed send leaves the booking pending for the cron '
    f'rather than marking it confirmed.</text>'
)
parts.append("</svg>")

out = pathlib.Path("docs/n8n-workflow.svg")
out.write_text("\n".join(parts))
print(f"✅ {out}  ({len(nodes)} nodes, viewBox 0 0 {W} {H}, {out.stat().st_size} bytes)")
