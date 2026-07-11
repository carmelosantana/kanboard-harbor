# "After" evidence — neutral-zinc palette (v1.1.0)

Captured 2026-07-11 against the shared `:8081` dev stack after the restyle, via the
connected Chrome (claude-in-chrome MCP) + DOM probes. Images render to a
browser-sandbox path not reachable from the repo, so — as with `../before/` — they
were captured **inline** in the build session; the authoritative persisted matrix
is the orchestrator's per the shared-stack caveat (spec §8).

## Hard gates (controller-run, all pass)
- Leftover-color sweep across `Assets/css/`: **zero** true violet (hue 293), **zero**
  warm-grey chroma. (The only `0.19` hits are `oklch(0.72 0.19 350)` — hue 350 = the
  semantic `.color-pink` task-accent rules, which correctly stay colored.)
- Served-CSS check (bind-mount → container): `shadcn-dark.css` serves
  `--background: oklch(0.145 0 0)`; `shadcn-light.css` serves `--primary: oklch(0.205 0 0)`;
  `shadcn-core.css` serves `--radius: 0.625rem`; served `shadcn-enhancements.js` has
  0 references to the removed ripple/`animate-in` code.
- PHP suite: 21/21 tests, 31 assertions green (incl. new `testPluginVersionValue`).

## Visual after (representative, both themes)
- **Dashboard (dark)** — pure neutral-zinc surfaces, neutral nav/links (no violet),
  overdue dates stay red (semantic). Matches baseline screen, repainted.
- **Dashboard (light)** — white/neutral surfaces, near-black text, neutral hairline
  dividers, neutral links, red overdue dates.
- **Board `/board/11` (dark)** — borderless neutral columns; the new `.views`
  segmented tab strip renders correctly (active "Board" pill, muted inactive tabs);
  task cards keep their semantic color rails; overdue chips red; add-column "+"
  icons now neutral (were violet). Resolves Task 5's deferred `.views`-fidelity note.

## DOM-probe verifications (real computed styles, not static-only)
- **Login checkbox mask (Task 6 critical fix), dark:** checked box bg =
  `oklch(0.922 0 0)` (`--primary`, near-white), check glyph = `oklch(0.205 0 0)`
  (`--primary-foreground`, dark) via `mask-image` — high contrast, visible.
- **…light:** box bg = `oklch(0.205 0 0)` (near-black), check = `oklch(0.985 0 0)`
  (near-white) — flips per theme, visible. The near-white-primary "white check on
  white" bug is fixed in both themes.
- **`kbx-*` primitives (dark):** `kbx-btn` neutral secondary; `kbx-btn--destructive`
  red; base/`--neutral` badges neutral; `--success` green, `--warning` amber,
  `--destructive` red; `--accent` repointed to neutral-emphasis (no violet);
  `.kbx-badge--dot::before` inherits `currentColor`. All render for the consuming
  suite plugins.

## Notes / limitations
- The login page itself could not be reached in-browser: the persistent "remember me"
  cookie auto-re-authenticates immediately on `/logout`. The login card's checkbox
  fix was instead verified via the DOM probe above (its CSS is injected site-wide,
  scoped to `.form-login`).
- The exhaustive §4 per-screen walk (every admin/settings/profile subpage) was
  sampled at the representative screens above; because the repaint is token-driven
  and the leftover-color sweep is clean, the full matrix is left to the orchestrator
  per the shared-stack caveat.
- User-avatar dots remain Kanboard's hash-generated colors (not theme chrome) — out
  of scope.
