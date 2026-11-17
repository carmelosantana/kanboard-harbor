# ShadcnTheme Neutral-Zinc Restyle — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Repaint the ShadcnTheme Kanboard plugin from its warm-slate/violet palette to the authentic shadcn/ui "neutral" look (pure neutral-zinc, near-white primary, color reserved for destructive + status), exhaustively across every screen, CSS-first, with no behavior/route/settings change.

**Architecture:** This is a **repaint, not a rearchitecture**. The four-file CSS split (`shadcn-light.css`, `shadcn-dark.css`, `shadcn-core.css`, `shadcn-login.css`), the token→Kanboard-variable *mapping*, the theme system, settings, routes, upload feature, and the `kbx-*` shared primitives are all preserved. Only the **source token values** and a handful of hard-coded violet/warm colors change, plus link decoupling, a radius bump, minor component reconciliation, and a JS trim. Most surfaces re-tint automatically from the swapped tokens.

**Tech Stack:** Plain buildless CSS (OKLCH tokens, `color-mix()`, `:has()`), plain ES5/ES6 JS (no build, no deps, no inline `<script>`), PHP 8.4 Kanboard plugin, PHPUnit tests run via `./testing/run-plugin-tests.sh ShadcnTheme`.

## Global Constraints

Every task's requirements implicitly include this section. Values are copied verbatim from the spec (`docs/superpowers/specs/2026-07-11-shadcn-theme-restyle-design.md`, in the outer repo).

- **Lane:** work ONLY inside `ShadcnTheme/`. Never read/modify `CalendarPlugin/`, `TimeBlock/`, or any other plugin dir. No harness edits (already mounted + listed).
- **Branch:** all commits on `feat/shadcn-restyle` in the `ShadcnTheme` git repo. Do NOT push, tag, or release. Hand the branch back.
- **Buildless:** plain CSS/JS only, no build step, external `Assets/js` only, **no inline `<script>`** (CSP `default-src 'self'`). Tokens / `color-mix()` / `:has()` / OKLCH are all fine (browser floor unchanged: Chrome 105+, Firefox 121+, Safari 15.4+).
- **Repaint only:** do NOT change routes, settings, the favicon/logo upload feature, the no-FOUC theme-preload, the light/dark/system toggle, or any plugin behavior. No new settings/routes/options, no new deps, no external fonts/icons. System sans stack stays.
- **Host-side edits only:** never `docker exec … sed/tee` into the running container (corrupts the bind-mount + trips a security warning). Edit files on the host; the `:8081` dev stack bind-mounts them live.
- **Preserve these E2E-earned fixes** (do not regress — they exist for documented reasons):
  - Header project-selector double-box collapse (`shadcn-core.css:241-262`, scoped under `header` for 0,1,1 specificity).
  - Board/calendar row-hover suppression (`shadcn-core.css:347-356`, `#board`/`.fc`/`.cal-calendar` excluded from `table tr:hover td`).
  - Fieldset flattening (`shadcn-core.css:266-282`).
  - `.listing` bullet removal (`shadcn-core.css:309-315`).
  - Input `box-sizing: border-box` height alignment (`shadcn-core.css:180-202`).
- **Preserve `theme-preload.js` and `theme-switcher.js` unchanged** — they drive the no-FOUC + toggle.
- **`kbx-*` primitives are shared** (BulkProjectDelete, FeatureSync, DependencyPlugin, …). Re-tint via tokens/fallbacks and verify they still render; do not touch other plugins' markup.
- **Semantic status colors stay colored:** success (green), warning (amber), info (blue), destructive (red) must remain distinguishable. Only the *neutral chrome* goes monochrome.
- **Live-stack caveat:** the `:8081` dev stack is SHARED with two parallel agents. Live/screenshot verification is best-effort; if the stack is busy or mid-recreate, the hard gate is the unit tests + grep assertions + review, and the authoritative full matrix falls to the orchestrator.

### Exact new token values (spec §1)

**Dark (`shadcn-dark.css`, `.theme-dark`):**

| Token | New value |
|---|---|
| `--background` | `oklch(0.145 0 0)` |
| `--foreground` | `oklch(0.985 0 0)` |
| `--card` / `--card-foreground` | `oklch(0.205 0 0)` / `oklch(0.985 0 0)` |
| `--popover` / `--popover-foreground` | `oklch(0.205 0 0)` / `oklch(0.985 0 0)` |
| `--primary` / `--primary-foreground` | `oklch(0.922 0 0)` / `oklch(0.205 0 0)` |
| `--secondary` / `--secondary-foreground` | `oklch(0.269 0 0)` / `oklch(0.985 0 0)` |
| `--muted` / `--muted-foreground` | `oklch(0.269 0 0)` / `oklch(0.708 0 0)` |
| `--accent` / `--accent-foreground` | `oklch(0.269 0 0)` / `oklch(0.985 0 0)` |
| `--destructive` / `--destructive-foreground` | `oklch(0.704 0.191 22.216)` (unchanged) / `oklch(0.985 0 0)` |
| `--border` | `oklch(1 0 0 / 10%)` |
| `--input` | `oklch(1 0 0 / 15%)` |
| `--ring` | `oklch(0.556 0 0)` |

**Light (`shadcn-light.css`, `:root`):**

| Token | New value |
|---|---|
| `--background` / `--foreground` | `oklch(1 0 0)` / `oklch(0.145 0 0)` |
| `--card` / `--card-foreground` | `oklch(1 0 0)` / `oklch(0.145 0 0)` |
| `--popover` / `--popover-foreground` | `oklch(1 0 0)` / `oklch(0.145 0 0)` |
| `--primary` / `--primary-foreground` | `oklch(0.205 0 0)` / `oklch(0.985 0 0)` |
| `--secondary` / `--secondary-foreground` | `oklch(0.97 0 0)` / `oklch(0.205 0 0)` |
| `--muted` / `--muted-foreground` | `oklch(0.97 0 0)` / `oklch(0.556 0 0)` |
| `--accent` / `--accent-foreground` | `oklch(0.97 0 0)` / `oklch(0.205 0 0)` |
| `--destructive` | `oklch(0.577 0.245 27.325)` |
| `--border` / `--input` | `oklch(0.922 0 0)` / `oklch(0.922 0 0)` |
| `--ring` | `oklch(0.708 0 0)` |

`--radius` = `0.625rem` (in `shadcn-core.css`, shared by both themes).

The primary **inverts** between modes (near-white on dark, near-black on light). That is authentic shadcn and the reason primary must never be treated as a hue — links, focus rings, and accents must NOT derive from `--primary`.

## File Structure

Six files change; no files created or deleted (except the plan doc + screenshots).

- `Assets/css/shadcn-dark.css` — swap dark token values; purge every hard-coded violet/warm color; decouple dark links. (Task 2)
- `Assets/css/shadcn-light.css` — swap light token values; decouple light links; neutralize warm greys. (Task 3)
- `Assets/css/shadcn-core.css` — `--radius` bump + reorganize into 4 labelled layers (Task 4); component reconciliation: checkbox contrast note, status-dot badge variants, neutral `kbx-*` fallbacks, tab strip (Task 5).
- `Assets/css/shadcn-login.css` — neutralize violet spotlight + warm page bg; fix the near-white-primary checkbox check contrast. (Task 6)
- `Assets/js/shadcn-enhancements.js` — remove ripple + `animate-in`; keep the useful behaviors. (Task 7)
- `plugin.json`, `Plugin.php`, `Test/PluginTest.php`, `CHANGELOG.md`, `README.md` — version bump to 1.1.0 (fix the 1.0.4/1.0.2 drift) + docs. (Task 8)

---

## Task 1: Branch setup + baseline "before" screenshots

**Files:**
- No source edits. Creates the working branch and (best-effort) baseline screenshots under `docs/superpowers/screenshots/before/`.

**Interfaces:**
- Produces: the `feat/shadcn-restyle` branch that all later tasks commit onto; baseline screenshots for the hand-back before/after matrix.

**Why first:** the "before" screenshots MUST be captured against the current committed 1.0.4 (warm/violet) look. The instant Task 2 edits a bind-mounted CSS file, the running `:8081` container serves the new palette — so baseline capture cannot happen later.

- [ ] **Step 1: Create the branch**

```bash
cd ShadcnTheme
git checkout -b feat/shadcn-restyle
git status   # expect: On branch feat/shadcn-restyle, clean
```

- [ ] **Step 2: Confirm the shared dev stack is reachable (best-effort)**

Run: `curl -s -o /dev/null -w "HTTP %{http_code}\n" --max-time 5 http://localhost:8081/`
Expected: `HTTP 302` (redirect to login) or `HTTP 200`. If the stack is down or mid-recreate, SKIP steps 3-4, note "baseline screenshots blocked — shared stack unavailable" in the task result, and continue to Task 2. Do not block the build on screenshots.

- [ ] **Step 3: Capture baseline screenshots (best-effort, both themes)**

Use the `claude-in-chrome` MCP (load via `ToolSearch` query `select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__read_page`). Log in at `http://localhost:8081/login` as `admin`/`admin`. Capture these representative screens in **both** the current dark and light modes (toggle via the user dropdown → Theme):
- Board (a project board with a few colored task cards)
- Task view (open one task)
- Dashboard (`/dashboard`)
- One settings page (e.g. `/settings` application settings)
- Login (`/login`, logged out)

Save PNGs to `docs/superpowers/screenshots/before/<screen>-<dark|light>.png`.

If the browser MCP is unavailable in this environment, note it and rely on the reviewer + orchestrator for the visual matrix — do not fail the task.

- [ ] **Step 4: Commit the baseline (if any captured)**

```bash
cd ShadcnTheme
git add docs/superpowers/plans/2026-07-11-shadcn-restyle.md docs/superpowers/screenshots/before/ 2>/dev/null || git add docs/superpowers/plans/2026-07-11-shadcn-restyle.md
git commit -m "chore(shadcn-restyle): add plan + baseline screenshots on feat/shadcn-restyle"
```

If no screenshots were captured, still commit the plan doc so the branch has a clean starting commit.

---

## Task 2: Neutralize the dark theme (`shadcn-dark.css`)

**Files:**
- Modify: `Assets/css/shadcn-dark.css`

**Interfaces:**
- Consumes: the exact dark token table in Global Constraints.
- Produces: a fully neutral dark theme with **zero** hard-coded violet (`293`) or warm (`49.25`, `56.043`, `34.298`, `106.423`, `58.071`, `48.717`) chroma/hue values in the neutral chrome; links decoupled from `--primary`. Semantic status colors (alerts, task-color accents, overdue chip) stay colored.

- [ ] **Step 1: Swap the token block (lines 16-50)**

Replace the token values in the first `.theme-dark { … }` block so it reads:

```css
  /* Base colors — shadcn neutral (pure grayscale, chroma 0) */
  --background: oklch(0.145 0 0);
  --foreground: oklch(0.985 0 0);

  /* Card colors */
  --card: oklch(0.205 0 0);
  --card-foreground: oklch(0.985 0 0);

  /* Popover colors */
  --popover: oklch(0.205 0 0);
  --popover-foreground: oklch(0.985 0 0);

  /* Primary — near-white; primary is NOT a hue. Foreground text is dark. */
  --primary: oklch(0.922 0 0);
  --primary-foreground: oklch(0.205 0 0);

  /* Secondary colors */
  --secondary: oklch(0.269 0 0);
  --secondary-foreground: oklch(0.985 0 0);

  /* Muted colors */
  --muted: oklch(0.269 0 0);
  --muted-foreground: oklch(0.708 0 0);

  /* Accent colors */
  --accent: oklch(0.269 0 0);
  --accent-foreground: oklch(0.985 0 0);

  /* Destructive colors (semantic — stays red) */
  --destructive: oklch(0.704 0.191 22.216);
  --destructive-foreground: oklch(0.985 0 0);

  /* Border and input — hairlines */
  --border: oklch(1 0 0 / 10%);
  --input: oklch(1 0 0 / 15%);
  --ring: oklch(0.556 0 0);                            /* neutral focus ring */
```

Leave the shadow tokens (`--shadow-*`, lines 53-55) and chart colors (lines 58-62) untouched — charts are semantic.

- [ ] **Step 2: Decouple dark links from `--primary` (lines 81-83)**

Replace:

```css
  /* Link colors */
  --link-color-primary: var(--primary);
  --link-color-focus: oklch(0.72 0.19 293);
  --link-color-hover: oklch(0.72 0.19 293);
```

with:

```css
  /* Link colors — decoupled from primary (near-white primary would blend into
     text). Links read as foreground; underline appears on hover/focus only. */
  --link-color-primary: var(--foreground);
  --link-color-focus: var(--foreground);
  --link-color-hover: var(--foreground);
```

- [ ] **Step 3: Neutralize the primary-button focus values (lines 116-117)**

Replace:

```css
  --button-primary-background-color-focus: oklch(0.72 0.19 293);
  --button-primary-border-color-focus: oklch(0.72 0.19 293);
```

with:

```css
  --button-primary-background-color-focus: var(--primary);
  --button-primary-border-color-focus: var(--primary);
```

(Primary hover dimming is handled by the `opacity: 0.9` rule in `shadcn-core.css`; the focus state stays near-white.)

- [ ] **Step 4: Neutralize the warm zebra-row token (line 128)**

Replace `--table-nth-background-color: oklch(0.18 0.005 49.25);` with `--table-nth-background-color: oklch(0.18 0 0);`.

- [ ] **Step 5: Rewrite the explicit dark `a` rules (lines 482-489)**

Replace:

```css
/* Dark theme link styling */
.theme-dark a {
  color: var(--primary);
}

.theme-dark a:hover {
  color: oklch(0.72 0.19 293);
}
```

with:

```css
/* Dark theme link styling — neutral, underline on hover/focus only. Task-title
   links stay clean (their own rules set text-decoration:none and only underline
   is added here on hover). */
.theme-dark a {
  color: var(--foreground);
  text-decoration: none;
}

.theme-dark a:hover,
.theme-dark a:focus-visible {
  color: var(--foreground);
  text-decoration: underline;
}
```

- [ ] **Step 6: Neutralize the "today" date chip (lines 658-663)**

Replace the `.task-date-today` rule:

```css
/* Today date chip: accent violet tint */
.theme-dark .task-board-icons-row .task-date-today {
  background-color: color-mix(in oklch, var(--primary) 28%, var(--card));
  border-color: var(--primary);
  color: oklch(0.82 0.1 293);
}
```

with a neutral-emphasis chip (still distinguishable from plain meta chips, but hueless):

```css
/* Today date chip: neutral emphasis (brighter hairline + foreground text) */
.theme-dark .task-board-icons-row .task-date-today {
  background-color: color-mix(in oklch, var(--foreground) 12%, var(--card));
  border-color: color-mix(in oklch, var(--foreground) 35%, var(--card));
  color: var(--foreground);
}
```

(The overdue chip above it keeps its destructive-red tint — that is semantic status and stays.)

- [ ] **Step 7: Neutralize the dropdown-polish hover tint (lines 744-746)**

Replace:

```css
/* Subtle violet hover tint — text stays legible white */
.theme-dark .dropdown-submenu-open > li:not(.no-hover):hover {
  background-color: oklch(0.62 0.19 293 / 15%);
  color: var(--popover-foreground);
}
```

with:

```css
/* Subtle neutral hover tint — text stays legible */
.theme-dark .dropdown-submenu-open > li:not(.no-hover):hover {
  background-color: var(--accent);
  color: var(--accent-foreground);
}
```

- [ ] **Step 8: Verify no violet/warm chroma survives in the neutral chrome**

Run:
```bash
cd ShadcnTheme
grep -nE '293|0\.19 293|49\.25|56\.043|34\.298|106\.423|58\.071|48\.717' Assets/css/shadcn-dark.css
```
Expected: **no matches**. (The task-color accent rules and alert semantic colors use their own hues like `150`, `95`, `250`, `22.216` — those are intentional status/task colors and are fine. The grep patterns above target only the removed violet `293` and the warm greys.)

Also confirm the chart colors and destructive `22.216` are still present (they should be):
```bash
grep -nE '22\.216|chart-' Assets/css/shadcn-dark.css | head
```
Expected: destructive + chart lines still present.

- [ ] **Step 9: Best-effort visual smoke check**

If the `:8081` stack is up, hard-reload the board in **dark** mode and confirm: background is pure neutral (no warm cast), Save/Add buttons are near-white with dark text, links are white without permanent underline, focus rings are neutral grey. If the stack is unavailable, note it and rely on the grep gate.

- [ ] **Step 10: Commit**

```bash
cd ShadcnTheme
git add Assets/css/shadcn-dark.css
git commit -m "feat(shadcn-restyle): neutralize dark theme tokens + decouple links (spec §1a/§1c)"
```

---

## Task 3: Neutralize the light theme (`shadcn-light.css`)

**Files:**
- Modify: `Assets/css/shadcn-light.css`

**Interfaces:**
- Consumes: the exact light token table in Global Constraints.
- Produces: a fully neutral light theme; links decoupled from `--primary`; no warm-grey (`106.424`, `58.071`, `48.717`, `49.25`, `56.043`) values in the neutral chrome. Primary is near-black `oklch(0.205 0 0)`.

- [ ] **Step 1: Swap the token block (lines 14-48)**

Replace the values in the `:root { … }` token block so it reads:

```css
  /* Base colors — shadcn neutral light (pure grayscale, chroma 0) */
  --background: oklch(1 0 0);
  --foreground: oklch(0.145 0 0);

  /* Card colors */
  --card: oklch(1 0 0);
  --card-foreground: oklch(0.145 0 0);

  /* Popover colors */
  --popover: oklch(1 0 0);
  --popover-foreground: oklch(0.145 0 0);

  /* Primary — near-black; primary is NOT a hue. */
  --primary: oklch(0.205 0 0);
  --primary-foreground: oklch(0.985 0 0);

  /* Secondary colors */
  --secondary: oklch(0.97 0 0);
  --secondary-foreground: oklch(0.205 0 0);

  /* Muted colors */
  --muted: oklch(0.97 0 0);
  --muted-foreground: oklch(0.556 0 0);

  /* Accent colors */
  --accent: oklch(0.97 0 0);
  --accent-foreground: oklch(0.205 0 0);

  /* Destructive colors (semantic — stays red) */
  --destructive: oklch(0.577 0.245 27.325);
  --destructive-foreground: oklch(0.985 0 0);

  /* Border and input — hairlines */
  --border: oklch(0.922 0 0);
  --input: oklch(0.922 0 0);
  --ring: oklch(0.708 0 0);
```

Leave the chart colors (lines 51-55) untouched.

Note: `--destructive-foreground` becomes pure-neutral `oklch(0.985 0 0)` (matching the dark theme), so no warm chroma remains anywhere in this file.

- [ ] **Step 2: Decouple light links from `--primary` (lines 81-83)**

Replace:

```css
  /* Link colors */
  --link-color-primary: var(--primary);
  --link-color-focus: var(--primary);
  --link-color-hover: var(--primary);
```

with:

```css
  /* Link colors — decoupled from primary (near-black primary would read as
     plain body text with no affordance difference; underline on hover instead). */
  --link-color-primary: var(--foreground);
  --link-color-focus: var(--foreground);
  --link-color-hover: var(--foreground);
```

- [ ] **Step 3: Rewrite the explicit `a` rules (lines 281-291)**

Replace:

```css
/* Better contrast for links */
a {
  color: var(--primary);
  text-decoration: none;
}

a:hover {
  color: var(--primary);
  opacity: 0.8;
  text-decoration: underline;
}
```

with:

```css
/* Links — neutral foreground, underline on hover/focus only. */
a {
  color: var(--foreground);
  text-decoration: none;
}

a:hover,
a:focus-visible {
  color: var(--foreground);
  text-decoration: underline;
}
```

- [ ] **Step 4: Neutralize the warm responsive/space vars if present**

The `@media (max-width: 768px)` block (lines 297-317) only touches spacing and min-height — no colors. Leave it. No change in this step; it exists to confirm you checked the tail of the file.

- [ ] **Step 5: Verify no warm-grey chroma survives in the neutral chrome**

Run:
```bash
cd ShadcnTheme
grep -nE '0\.001 106\.42|0\.013 58\.071|0\.003 48\.717|0\.004 49\.25|0\.006 56\.043' Assets/css/shadcn-light.css
```
Expected: **no matches**. The semantic `--alert-*` / `--chart-*` hues (`164.4`, `184.704`, `29.699`, `27.325`, etc.) are intentional status colors and stay.

- [ ] **Step 6: Best-effort visual smoke check**

If the stack is up, switch to **light** mode and confirm: pure white/neutral-grey surfaces (no warm beige), near-black primary buttons with white text, neutral focus rings, links underline only on hover. Otherwise note the stack state.

- [ ] **Step 7: Commit**

```bash
cd ShadcnTheme
git add Assets/css/shadcn-light.css
git commit -m "feat(shadcn-restyle): neutralize light theme tokens + decouple links (spec §1b/§1c)"
```

---

## Task 4: Core — radius bump + 4-layer reorg (`shadcn-core.css`)

**Files:**
- Modify: `Assets/css/shadcn-core.css`

**Interfaces:**
- Consumes: nothing new.
- Produces: `--radius: 0.625rem`; the file divided into four clearly labelled layers so the audit is navigable. **No rule text changes** in this task beyond the radius value and the layer banner comments — this is a structural pass only. Component behavior changes happen in Task 5.

**Reorg safety note:** the preserved E2E fixes are all **specificity-based** (`header .select-…` = 0,1,1 beats native 0,1,0; `#board tr:hover` beats `table tr:hover` by id), not source-order-based, so relocating blocks does not regress them. Still, verify selector inventory before/after (Step 4).

- [ ] **Step 1: Bump the radius (line 15)**

Replace `--radius: 0.5rem;` with `--radius: 0.625rem;`.

- [ ] **Step 2: Add the four layer banner comments**

Insert these four banner comments at the layer boundaries, reordering whole blocks so each sits under its banner. Target structure (top → bottom):

```
/* ============================================================
   LAYER 1 — TOKENS / RADIUS / TYPE
   ============================================================ */
```
Contains: the `:root` design-system block (radius, font, spacing, shadow, transition tokens) and the typography rules (`body`, `h1`–`h6`).

```
/* ============================================================
   LAYER 2 — BASE & RESET
   ============================================================ */
```
Contains: base form/input rules (`input[...]`, `select`, `textarea`, placeholder, textarea sizing, the `box-sizing` height-alignment fix), `fieldset`/`legend` flattening, the `table`/`th`/`td` base + board/calendar hover suppression, `ul.listing` bullet removal, and the global `a/button/[tabindex]:focus-visible` + `prefers-reduced-motion` + `prefers-contrast` rules.

```
/* ============================================================
   LAYER 3 — COMPONENTS
   ============================================================ */
```
Contains: `.btn*` buttons, `.panel/.task-board/.modal-content` cards, `.dropdown-submenu-open/#select-dropdown-menu` dropdowns, `.alert*` alerts, `.board-column-header/.board-task-list/.task-board` board, and (added in Task 5) tabs + status-dot badges. Also the `kbx-*` primitives and `kbx-badge` and branding logo classes live here (they are reusable components).

```
/* ============================================================
   LAYER 4 — KANBOARD-SPECIFIC OVERRIDES
   ============================================================ */
```
Contains: the `header .select-dropdown-input-container` double-box collapse fix (the one rule that must stay scoped under `header`).

Keep every existing rule's text identical; only move blocks and add the banner comments. Preserve relative order within any group of rules that target overlapping selectors.

- [ ] **Step 3: Verify the radius and lint the CSS structure**

Run:
```bash
cd ShadcnTheme
grep -n -- '--radius: 0.625rem;' Assets/css/shadcn-core.css
grep -c 'LAYER [1-4]' Assets/css/shadcn-core.css
```
Expected: the radius line matches; `LAYER` banner count is `4`.

- [ ] **Step 4: Verify no rule was dropped in the reorg**

Run this to confirm every top-level selector that existed still exists (compare against the committed version):
```bash
cd ShadcnTheme
git show HEAD:Assets/css/shadcn-core.css | grep -oE '^[.#a-zA-Z][^{]*\{' | sort -u > /tmp/core-before.txt
grep -oE '^[.#a-zA-Z][^{]*\{' Assets/css/shadcn-core.css | sort -u > /tmp/core-after.txt
diff /tmp/core-before.txt /tmp/core-after.txt
```
Expected: **no differences** (the reorg preserves all selectors; only order and the radius value changed). If `diff` shows a removed selector, restore it.

- [ ] **Step 5: Best-effort visual regression check**

If the stack is up, confirm the preserved fixes still hold in dark mode: header project selector is a single clean box (not double), board/calendar rows don't wash on hover, fieldsets are flat, `.listing` rows have no bullets, text inputs line up with selects. Corners are slightly rounder (0.625rem). Otherwise rely on Step 4's diff gate.

- [ ] **Step 6: Commit**

```bash
cd ShadcnTheme
git add Assets/css/shadcn-core.css
git commit -m "refactor(shadcn-restyle): bump --radius to 0.625rem + reorganize core into 4 labelled layers (spec §2)"
```

---

## Task 5: Core — component reconciliation (`shadcn-core.css`)

**Files:**
- Modify: `Assets/css/shadcn-core.css`

**Interfaces:**
- Consumes: the neutral tokens from Tasks 2-4.
- Produces: neutral `kbx-*` fallbacks (no violet defaults); a documented checkbox-contrast decision; new status-dot badge variants (`.kbx-badge--dot`, `--success`, `--warning`, `--neutral`); a modest segmented-tab treatment for `.views`. All pure CSS, additive, backwards-compatible for the other suite plugins.

- [ ] **Step 1: Neutralize the violet `kbx-*` fallbacks**

These `var(--token, <fallback>)` fallbacks only apply when ShadcnTheme is NOT installed (other plugins standalone), but keep them consistent with the neutral palette.

In `.kbx-btn:focus-visible` (line ~534) replace `outline: 2px solid var(--ring, oklch(0.62 0.19 293 / 70%));` with `outline: 2px solid var(--ring, oklch(0.556 0 0));`.

In `.kbx-checkbox input[type="checkbox"]` (line ~596) replace `accent-color: var(--primary, oklch(0.62 0.19 293));` with `accent-color: var(--primary, oklch(0.922 0 0));`.

In `.kbx-checkbox input[type="checkbox"]:focus-visible` (line ~601) replace `outline: 2px solid var(--ring, oklch(0.62 0.19 293 / 70%));` with `outline: 2px solid var(--ring, oklch(0.556 0 0));`.

- [ ] **Step 2: Document the checkbox-contrast decision (add a comment above `.kbx-checkbox`)**

The native `accent-color` checkbox is fine with a near-white primary: browsers auto-pick a **contrasting** checkmark (dark check on the near-white fill in dark mode, white check on the near-black fill in light mode). No color change needed for the native checkbox. Add this comment immediately above the `.kbx-checkbox {` rule so the choice is documented (spec §3 requires documenting it):

```css
/* Checkbox contrast note (spec §3): with a near-white --primary, the native
   accent-color checkbox stays readable because the browser auto-selects a
   contrasting check glyph (dark check on the near-white fill in dark mode,
   light check on the near-black fill in light mode). So --primary is kept as
   the accent here. The login page uses a custom appearance:none checkbox whose
   check is drawn manually — that one is fixed separately in shadcn-login.css
   (mask-image + --primary-foreground) so its check follows the token per theme. */
```

- [ ] **Step 3: Repoint `.kbx-badge--accent` to neutral emphasis**

The old `--accent` badge painted a violet chip. Under the neutral palette a near-white-on-near-white chip is unreadable, so redefine it as a neutral-emphasis chip. Replace the `.kbx-badge--accent { … }` rule (lines ~654-658) with:

```css
/* kbx-badge variant: accent — neutral emphasis (brighter hairline + foreground
   text). Primary is not a hue in the neutral palette, so this no longer paints a
   brand color; use --success/--warning/--destructive for semantic meaning. */
.kbx-badge--accent {
  background-color: color-mix(in oklch, var(--foreground, oklch(0.985 0 0)) 10%, var(--card, #1c1c1e));
  color: var(--foreground, oklch(0.985 0 0));
  border-color: color-mix(in oklch, var(--foreground, oklch(0.985 0 0)) 30%, var(--card, #1c1c1e));
}
```

- [ ] **Step 4: Add status-dot + semantic badge variants**

Immediately after the `.kbx-badge--destructive { … }` rule (end of file, ~line 665), append:

```css
/* kbx-badge variant: success (green) */
.kbx-badge--success {
  background-color: color-mix(in oklch, var(--kbx-success, oklch(0.6 0.13 160)) 20%, var(--card, #1c1c1e));
  color: var(--kbx-success, oklch(0.72 0.13 160));
  border-color: var(--kbx-success, oklch(0.6 0.13 160));
}

/* kbx-badge variant: warning (amber) */
.kbx-badge--warning {
  background-color: color-mix(in oklch, var(--kbx-warning, oklch(0.75 0.15 80)) 20%, var(--card, #1c1c1e));
  color: var(--kbx-warning, oklch(0.82 0.15 80));
  border-color: var(--kbx-warning, oklch(0.75 0.15 80));
}

/* kbx-badge variant: neutral (muted) */
.kbx-badge--neutral {
  background-color: var(--muted, #2a2a2e);
  color: var(--muted-foreground, #b0b0b0);
  border-color: var(--border, rgba(255,255,255,0.1));
}

/* kbx-badge status dot — a leading ● that inherits the variant color via
   currentColor. Usage: <span class="kbx-badge kbx-badge--success kbx-badge--dot">Done</span> */
.kbx-badge--dot::before {
  content: "";
  width: 0.5em;
  height: 0.5em;
  border-radius: 50%;
  background-color: currentColor;
  flex: 0 0 auto;
}
```

(These reuse the existing `.kbx-badge` base for shape/size; the dot rides on `currentColor` so no extra token wiring is needed. `--kbx-success`/`--kbx-warning` are optional override hooks with green/amber fallbacks — no new required tokens.)

- [ ] **Step 5: Add a modest segmented-tab treatment for `.views`**

Kanboard already wires the `--views-*` variables (set in both theme files), so `.views` strips already re-tint. Add a light segmented-pill active state in the COMPONENTS layer (after the alerts block). Keep it conservative — token-driven, no layout assumptions beyond what Kanboard renders:

```css
/* ================================
   TABS — Kanboard .views strips as shadcn segmented tabs
   ================================ */
.views {
  gap: var(--space-1);
}

.views a,
.views button {
  border-radius: calc(var(--radius) - 2px);
  padding: var(--space-1) var(--space-3);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--muted-foreground);
  text-decoration: none;
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.views a:hover,
.views button:hover {
  color: var(--foreground);
  background-color: var(--accent);
}

.views a.active,
.views .active a,
.views button.active {
  color: var(--views-active-color, var(--foreground));
  background-color: var(--background);
  box-shadow: var(--shadow-sm);
}
```

If the live audit (Task 9) shows this fights Kanboard's `.views` markup on any screen, the fallback is to drop the active-pill rule and rely on the `--views-*` variables alone — note it, do not force it.

- [ ] **Step 6: Verify no violet fallback survives + component rules present**

Run:
```bash
cd ShadcnTheme
grep -nE '0\.19 293|0\.62 0\.19' Assets/css/shadcn-core.css
grep -nE 'kbx-badge--success|kbx-badge--warning|kbx-badge--neutral|kbx-badge--dot|\.views a\.active' Assets/css/shadcn-core.css
```
Expected: first grep → **no matches**; second grep → all five present.

- [ ] **Step 7: Best-effort visual check**

If the stack is up, open a screen with a `.views` tab strip (e.g. a project's board/calendar/list sub-view tabs) in both themes and confirm the active tab reads as a segmented pill and inactive tabs are muted. Otherwise rely on the grep gate.

- [ ] **Step 8: Commit**

```bash
cd ShadcnTheme
git add Assets/css/shadcn-core.css
git commit -m "feat(shadcn-restyle): neutral kbx fallbacks, status-dot badges, segmented tabs, checkbox note (spec §3)"
```

---

## Task 6: Login card re-tint + checkbox contrast fix (`shadcn-login.css`)

**Files:**
- Modify: `Assets/css/shadcn-login.css`

**Interfaces:**
- Consumes: neutral tokens.
- Produces: a neutral login card (no violet spotlight, no warm page bg) whose "remember me" checkmark is visible in **both** themes despite the near-white/near-black primary.

- [ ] **Step 1: Neutralize the radial spotlight (lines 31-37)**

Replace:

```css
  /* Subtle radial spotlight to give depth */
  background-image: radial-gradient(
    ellipse 80% 50% at 50% -10%,
    oklch(0.62 0.19 293 / 10%) 0%,
    transparent 60%
  );
```

with:

```css
  /* Subtle neutral spotlight to give depth (hueless) */
  background-image: radial-gradient(
    ellipse 80% 50% at 50% -10%,
    oklch(0.556 0 0 / 8%) 0%,
    transparent 60%
  );
```

- [ ] **Step 2: Fix the checkbox check contrast (lines 154-162)**

The current `:checked::after` paints a hard-coded `white` checkmark inside a `--primary`-colored box. With a near-white primary (dark mode) the white check is invisible. Switch to a **mask-image** so the check color follows `--primary-foreground` and flips per theme. Replace:

```css
.form-login input[type="checkbox"]:checked::after {
  content: "";
  position: absolute;
  inset: 2px;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 12 10' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolyline points='1,5 4,9 11,1' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
}
```

with:

```css
/* Check glyph via mask so its color follows --primary-foreground and flips per
   theme (dark check on near-white box in dark mode; light check on near-black
   box in light mode). The SVG stroke color is irrelevant under a mask — only its
   alpha shape matters. */
.form-login input[type="checkbox"]:checked::after {
  content: "";
  position: absolute;
  inset: 2px;
  background-color: var(--primary-foreground);
  -webkit-mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 12 10' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolyline points='1,5 4,9 11,1' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 12 10' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolyline points='1,5 4,9 11,1' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  -webkit-mask-size: contain;
  mask-size: contain;
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
  -webkit-mask-position: center;
  mask-position: center;
}
```

- [ ] **Step 3: Soften the input focus ring to match core (lines 105-110 and 164-167)**

`--ring` is now a solid opaque neutral, so `box-shadow: 0 0 0 3px var(--ring)` renders a hard opaque ring. Match `shadcn-core.css`'s soft ring. In the `.form-login input[type="text|password|email"]:focus` rule replace `box-shadow: 0 0 0 3px var(--ring);` with `box-shadow: 0 0 0 3px color-mix(in oklch, var(--ring) 25%, transparent);`. Do the same in the `.form-login input[type="checkbox"]:focus` rule.

- [ ] **Step 4: Neutralize the warm light-mode page background (line 255)**

Replace `background-color: oklch(0.97 0.001 106.424);` with `background-color: oklch(0.97 0 0);`.

- [ ] **Step 5: Verify**

Run:
```bash
cd ShadcnTheme
grep -nE '0\.19 293|0\.001 106\.424' Assets/css/shadcn-login.css
grep -n 'mask-image' Assets/css/shadcn-login.css
```
Expected: first grep → **no matches**; second grep → two `mask-image` lines (webkit + standard).

- [ ] **Step 6: Best-effort visual check (login page, both themes)**

If the stack is up, log out, open `/login`, and in both dark and light confirm: neutral card + neutral spotlight, near-white (dark) / near-black (light) "Sign in" button, and — critically — tick the "remember me" box and confirm the **check glyph is visible** in both themes. Otherwise rely on the grep gate.

- [ ] **Step 7: Commit**

```bash
cd ShadcnTheme
git add Assets/css/shadcn-login.css
git commit -m "feat(shadcn-restyle): neutral login card + theme-flipping checkbox check via mask (spec §3)"
```

---

## Task 7: Trim the enhancement JS (`shadcn-enhancements.js`)

**Files:**
- Modify: `Assets/js/shadcn-enhancements.js`

**Interfaces:**
- Consumes: nothing.
- Produces: JS with the Material-style **ripple** and scroll-in **`animate-in`** behaviors removed (plus their injected `<style>` blocks), keeping the genuinely useful behaviors: tooltip fade-in, form focus/validation classes, table responsiveness, backdrop-click modal close (with the form guard), Escape-to-close, dropdown fade-in. No inline scripts, no new deps, no changes to Kanboard core interactions.

- [ ] **Step 1: Remove the ripple call and method**

In `init()` (lines 23-32) delete the line `this.setupRippleEffects();`.
Delete the entire `setupRippleEffects() { … }` method (lines 34-91), including its injected `#shadcn-ripple-styles` `<style>` block and the document click listener that adds ripples.

- [ ] **Step 2: Remove the scroll-animation call and method**

In `init()` delete the line `this.setupAnimations();`.
Delete the entire `setupAnimations() { … }` method (lines 411-459), including its `IntersectionObserver`, the `.animate-in` observation of `.task-board/.panel/.table-list-row`, and the injected `#shadcn-animation-styles` `<style>` block.

Note: that removed `<style>` block also defined `.keyboard-navigation *:focus`. That is acceptable — `setupKeyboardNavigation`'s `.keyboard-navigation` class toggle now has no dedicated style, and the core `:focus-visible` outlines (in `shadcn-core.css`) already cover keyboard focus. Do NOT remove `setupKeyboardNavigation` itself (Escape-to-close is useful).

- [ ] **Step 3: Keep everything else intact**

Confirm these methods and their `init()` calls remain: `setupTooltipEnhancements`, `setupFormEnhancements` (+ `setupFormValidation`), `setupTableEnhancements` (+ `makeTablesResponsive`), `setupModalEnhancements` (+ `enhanceModal`, with the form-guard backdrop close), `setupDropdownEnhancements` (+ `enhanceDropdown`), `setupKeyboardNavigation` (+ `handleTabNavigation`), `addLoadingState`, `showToast`.

Flash→toast wiring is **optional and deferred** — the spec explicitly allows leaving Kanboard flashes as re-tinted alerts rather than fighting core flash rendering. Do NOT wire flash interception in this task; keep `showToast()` available for future use. (Leaving flashes as alerts is the documented fallback.)

- [ ] **Step 4: Verify the removals and that the file still parses**

Run:
```bash
cd ShadcnTheme
grep -nE 'setupRippleEffects|setupAnimations|shadcn-ripple-styles|shadcn-animation-styles|animate-in' Assets/js/shadcn-enhancements.js
node --check Assets/js/shadcn-enhancements.js && echo "JS OK"
```
Expected: first grep → **no matches**; `node --check` → `JS OK`. (If `node` is unavailable, load the file in the browser console via the live stack and confirm no syntax error; or skip and rely on the live smoke check.)

- [ ] **Step 5: Verify the kept behaviors are present**

Run:
```bash
cd ShadcnTheme
grep -cE 'setupModalEnhancements|setupDropdownEnhancements|setupKeyboardNavigation|setupTooltipEnhancements|showToast' Assets/js/shadcn-enhancements.js
```
Expected: `5`.

- [ ] **Step 6: Best-effort live smoke check**

If the stack is up, hard-reload, open a read-only modal (e.g. "My activity stream") and confirm backdrop-click and Escape still close it; open a form modal (edit task) and confirm backdrop-click does NOT close it; open a dropdown and confirm it still fades in; click a button and confirm there is **no ripple**. Otherwise rely on `node --check` + grep.

- [ ] **Step 7: Commit**

```bash
cd ShadcnTheme
git add Assets/js/shadcn-enhancements.js
git commit -m "refactor(shadcn-restyle): drop ripple + scroll-in animations, keep useful JS behaviors (spec §5)"
```

---

## Task 8: Version bump (fix drift) + tests + docs

**Files:**
- Modify: `plugin.json`, `Plugin.php`, `Test/PluginTest.php`, `CHANGELOG.md`, `README.md`

**Interfaces:**
- Consumes: nothing.
- Produces: version `1.1.0` aligned across `plugin.json` (was 1.0.4) and `Plugin.php::getPluginVersion()` (was 1.0.2); a `PluginTest` assertion pinning the version to `1.1.0`; updated changelog + readme. Full test suite green.

- [ ] **Step 1: Bump `plugin.json` (line 4)**

Replace `"version": "1.0.4",` with `"version": "1.1.0",`.

- [ ] **Step 2: Bump `Plugin.php::getPluginVersion()` (line 214)**

Replace `return '1.0.2';` with `return '1.1.0';`.

- [ ] **Step 3: Add a version-value assertion to `PluginTest`**

`PluginTest` currently only asserts the version is non-empty. Add an exact-value test so the 1.1.0 bump is guarded and future drift is caught. Insert after `testPluginVersionIsNotEmpty()` (after line 20):

```php
    public function testPluginVersionValue()
    {
        $plugin = new Plugin($this->container);
        $this->assertSame('1.1.0', $plugin->getPluginVersion());
    }
```

- [ ] **Step 4: Run the full test suite**

Run: `./testing/run-plugin-tests.sh ShadcnTheme`
Expected: all green — `PluginTest` (now incl. `testPluginVersionValue`), `SettingsControllerTest`, `ThemeControllerTest`. If `testPluginVersionValue` fails, re-check Step 2.

- [ ] **Step 5: Add the CHANGELOG entry**

Insert a new section directly under the `## [1.0.4]` heading's preamble (i.e. as the newest entry, above `## [1.0.4]`):

```markdown
## [1.1.0] - 2026-07-11

### Changed

- **Neutral-zinc repaint** — the palette moves from warm-slate/violet to the
  authentic shadcn/ui "neutral" look: a pure neutral-zinc, near-monochrome
  palette with a **near-white primary** (near-black in light mode). Color is now
  reserved for destructive actions and semantic status (success/warning/info).
  Applied exhaustively across every screen in both light and dark, CSS-first,
  keeping Kanboard's top-nav layout.
- **Links decoupled from primary** — since primary is no longer a hue, links now
  render as `--foreground` with an underline on hover/focus only (no permanent
  underline on task-title links).
- **Radius** bumped to `0.625rem` to match shadcn's neutral tokens.
- **Core CSS reorganized** into four labelled layers (tokens → base → components
  → Kanboard overrides) for a navigable audit.
- **Status-dot badges** — new `.kbx-badge--dot` plus `--success`/`--warning`/
  `--neutral` variants driven by tokens.
- **Login checkbox** now uses a mask-image check so the glyph stays visible under
  the near-white/near-black primary in both themes.
- **Leaner JS** — removed the Material-style button ripple and the scroll-in
  `animate-in` animation (neither is shadcn); kept modal backdrop/Escape close,
  dropdown/tooltip fade-in, and form/table helpers.

### Fixed

- **Version drift** — `plugin.json` (1.0.4) and `Plugin.php` (1.0.2) had diverged;
  both are now aligned to 1.1.0, guarded by a new `PluginTest` assertion.
```

Also append the version link reference at the bottom of the file (next to the existing `[1.0.0]` link):

```markdown
[1.1.0]: https://github.com/carmelosantana/kanboard-shadcn-theme/releases/tag/v1.1.0
```

- [ ] **Step 6: Update the README palette note**

In the Features table's "shadcn/ui design tokens" row (line 18), update the description to reflect the neutral palette. Replace that row with:

```markdown
| **shadcn/ui "neutral" design tokens** | Pure neutral-zinc palette with a near-white primary (near-black in light mode); color reserved for destructive + status. CSS custom properties (`--background`, `--foreground`, `--accent`, `--border`, `--radius`, etc.) plus `kbx-` primitive variables for a consistent palette across both themes. |
```

- [ ] **Step 7: Re-run the suite to confirm still green**

Run: `./testing/run-plugin-tests.sh ShadcnTheme`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
cd ShadcnTheme
git add plugin.json Plugin.php Test/PluginTest.php CHANGELOG.md README.md
git commit -m "chore(shadcn-restyle): bump to 1.1.0 (fix plugin.json/Plugin.php drift) + tests + docs (spec §7/§8)"
```

---

## Task 9: Exhaustive screen audit + "after" screenshots + leftover-color sweep

**Files:**
- No source edits unless the audit surfaces a straggler (contrast miss / hard-coded leftover). Any fix here is a small follow-up commit. Adds `docs/superpowers/screenshots/after/`.

**Interfaces:**
- Consumes: the finished repaint.
- Produces: the completeness gate for the hand-back — the §4 screen walk in both themes, before/after screenshot pairs, a repo-wide leftover-color sweep, and confirmation the `kbx-*` primitives render. This is the primary acceptance gate for a visual change (best-effort on the shared stack).

- [ ] **Step 1: Repo-wide leftover-color sweep (hard gate — always runs)**

Run:
```bash
cd ShadcnTheme
echo "== violet 293 =="; grep -rnE '0\.19 293|0\.62 0\.19|0\.72 0\.19' Assets/css/ || echo none
echo "== warm greys =="; grep -rnE '0\.004 49\.25|0\.006 56\.043|0\.007 34\.298|0\.001 106\.42|0\.013 58\.071|0\.003 48\.717' Assets/css/ || echo none
```
Expected: `== violet 293 ==` → `none`. `== warm greys ==` → `none`. Any hit is a straggler — fix it (re-tint to the neutral equivalent), then re-run.

- [ ] **Step 2: Confirm the neutral tokens are actually served (hard gate, if stack up)**

Run:
```bash
curl -s http://localhost:8081/plugins/ShadcnTheme/Assets/css/shadcn-dark.css | grep -m1 'background: oklch(0.145 0 0)'
curl -s http://localhost:8081/plugins/ShadcnTheme/Assets/css/shadcn-light.css | grep -m1 'primary: oklch(0.205 0 0)'
curl -s http://localhost:8081/plugins/ShadcnTheme/Assets/css/shadcn-core.css | grep -m1 'radius: 0.625rem'
```
Expected: each prints its matching line — confirming the bind-mounted, edited CSS is what the container serves. If the stack is down, skip and note it.

- [ ] **Step 3: Walk the §4 screen checklist in BOTH themes (best-effort)**

If the stack is up, log in and open each surface below in dark **and** light, reconciling each to the component specs and watching for unreadable contrast (near-white-on-near-white, near-black-on-near-black), hard-coded leftover colors, or layout breaks:
- **Auth:** login, password reset, 2FA check.
- **Dashboard:** overview, my tasks, my subtasks, my projects, my activity, my calendar, notifications.
- **Board:** columns, swimlanes, collapsed swimlanes, task cards, board filters dropdown, board header/actions, WIP-limit states, drag ghosts.
- **Task view:** details, subtasks, comments (+ markdown), internal/external links, attachments, time tracking, metadata, sidebar action list, task colors.
- **Task forms/modals:** create, edit, close/remove confirm, small/medium/large modals.
- **Project views:** list, calendar, gantt, table/analytics, activity, sub-view tab strips.
- **Project settings:** columns, categories, swimlanes, permissions, automatic actions, notifications, integrations, share/public.
- **Admin/Settings:** application settings, users, groups, project management, links, currencies, automated actions, plugins page.
- **User profile/preferences:** profile, password, 2FA, sessions, notifications, API/personal tokens, external accounts.
- **Global chrome:** header (board selector, search, user menu), flash messages, tooltips, dropdown menus, confirm dialogs, pagination, empty states.
- **This plugin's own pages:** `/shadcn-theme/settings` + the config sidebar link.

Log any straggler you find; fix trivial ones (re-tint from tokens) and record any you cannot fully resolve for the hand-back.

- [ ] **Step 4: Verify `kbx-*` primitives render under the neutral palette (best-effort)**

If another suite plugin exposing `kbx-*` is reachable on the stack (e.g. BulkProjectDelete's toolbar/modal, FeatureSync), open it and confirm `.kbx-toolbar`, `.kbx-btn`, `.kbx-badge` (+ the new dot/semantic variants if used), `.kbx-modal`, `.kbx-checkbox` all render sanely (neutral, readable) in both themes. Do NOT modify those plugins. If none are reachable, note it — the token/fallback design means they re-tint automatically.

- [ ] **Step 5: Capture "after" screenshots (best-effort)**

Capture the same representative screens as Task 1 (board, task view, dashboard, a settings page, login) in both dark and light, to `docs/superpowers/screenshots/after/<screen>-<dark|light>.png`.

- [ ] **Step 6: Commit any stragglers + screenshots**

```bash
cd ShadcnTheme
git add -A
git commit -m "test(shadcn-restyle): exhaustive audit — after screenshots + leftover-color sweep + straggler fixes" || echo "nothing to commit"
```

- [ ] **Step 7: Whole-branch review**

Use `superpowers:requesting-code-review` for a whole-branch review of `feat/shadcn-restyle` (diff vs `main`). Acceptance: **no Critical findings**. Address any Critical/Major before hand-back; record Minors as open items.

---

## Hand-back checklist (Task 9 output → orchestrator)

Report:
- Branch name (`feat/shadcn-restyle`) + commit range in `ShadcnTheme/`.
- Test counts from `./testing/run-plugin-tests.sh ShadcnTheme` (PluginTest incl. new version assertion, SettingsControllerTest, ThemeControllerTest — all green).
- Before/after screenshot evidence (or a note that the shared `:8081` stack blocked live capture).
- Any hard-coded-color or contrast stragglers not fully resolved.
- Whole-branch review verdict (target: no Critical).
- Open items (e.g. the deferred flash→toast, CalendarPlugin week/day-view follow-up per spec §9).

The orchestrator (not this agent) merges `feat/shadcn-restyle` → `main`, tags `kanboard-shadcn-theme` **v1.1.0** (CI releases), and bumps the `kanboard-modmenu-directory` entry (version + download URL → v1.1.0).

---

## Self-review (completed against the spec)

- **§1a dark tokens** → Task 2 Step 1. **§1b light tokens** → Task 3 Step 1. **§1c link decoupling** → Task 2 Steps 2/5 + Task 3 Steps 2/3. **--radius 0.625rem** → Task 4 Step 1.
- **§2 four-layer reorg** → Task 4 Step 2 (with selector-inventory diff gate Step 4).
- **§3 components:** buttons (token-driven, verified — Task 2 Step 3 focus fix); inputs/box-sizing (preserved, Task 4 layer 2); checkbox contrast (native = Task 5 Step 2 note; login custom = Task 6 Step 2 mask fix — both documented); cards/panels/modals (token-driven, preserved); status-dot badges (Task 5 Step 4); dropdowns/popovers (Task 2 Step 7 neutral hover); tables (preserved incl. board/calendar hover suppression); alerts (semantic kept); tabs (Task 5 Step 5); board columns/task cards (task-color accents kept, Task 2); header/nav (double-box fix preserved, Task 4); login card (Task 6).
- **§4 exhaustive audit** → Task 9 Step 3 (full checklist) + leftover sweep Step 1 + kbx verify Step 4.
- **§5 JS** → Task 7 (remove ripple + animate-in + their styles; keep the rest; flash→toast deferred per the documented fallback).
- **§6 non-goals** → honored: no IA restructure, no new widgets/settings/routes, no font/icon deps, no other-plugin markup changes (Global Constraints).
- **§7 version/release** → Task 8 (1.1.0 in both files, drift fixed; docs; orchestrator owns push/tag/directory).
- **§8 testing** → Task 8 Step 4/7 (suite green + version assertion) + Task 9 (screenshot matrix, regression focus on preserved fixes, contrast, kbx render).
- **Preserved E2E fixes** → Global Constraints list + Task 4 reorg-safety note + Task 4 Step 5 check.
- **Placeholder scan:** every code step contains exact old→new content; no TBD/TODO. **Type/name consistency:** class names (`.kbx-badge--dot/--success/--warning/--neutral`, `.views a.active`), token names, and version string `1.1.0` are consistent across tasks.
