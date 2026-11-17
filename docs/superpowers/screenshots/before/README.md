# Baseline ("before") screenshots — warm-slate/violet palette (v1.0.4)

Captured 2026-07-11 against the shared `:8081` dev stack before the neutral-zinc
restyle, via the connected Chrome (claude-in-chrome MCP). The MCP saves images to
a browser-sandbox location not reachable from the repo filesystem, so they could
not be committed as PNGs here; they were captured **inline** in the build session
transcript for before/after comparison, and the authoritative persisted matrix is
owned by the orchestrator per the shared-stack caveat (spec §8).

Baseline dark screens captured (all showing the violet primary + warm task tints):

- **Login (dark)** — violet "Sign in" button, violet "remember me" checkbox, faint
  violet radial spotlight.
- **Dashboard / Overview (dark)** — violet header/nav accents, violet "Next →" and
  "My activity stream" links, violet avatar chips.
- **Board `/board/11` (dark)** — warm brown/orange task-card tints, violet avatar
  dots, violet add-column "+" icons, violet active tab in the Overview/Board/List/
  Calendar strip.

Light-mode baselines were not captured: the theme display is localStorage-driven
(no-FOUC preload), so the `/theme/set/:mode` GET route does not flip the rendered
theme without the JS toggle. Light "before" is the warm-white surface with a
dark-slate primary (per `shadcn-light.css` @ v1.0.4).

"After" (neutral-zinc) captures live in `../after/`.
