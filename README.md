# Harbor — Kanboard theme and UI kit

Harbor is the flagship theme for Carmelo's Kanboard plugin suite and the UI kit the suite's plugins build on. It
succeeds **ShadcnTheme**, which it replaces (`"replaces": ["ShadcnTheme"]`).

## Features

| Feature | Detail |
|---|---|
| **No flash on load** | The server writes the theme class, `color-scheme`, the background and a small critical style into the page before any stylesheet loads. Only the "auto" theme runs a tiny blocking script. |
| **Presets** | **Newburgh** (default, dark-first, from the madeinnewburgh.com palette) and **Shadcn** (neutral). Each has a light and a dark mode, and both pass WCAG AA for text. |
| **Theme toggle** | Saved as Kanboard's own user theme preference (light, dark or automatic). It works without JavaScript. |
| **Density toggle** | Open **Theme** in the user menu and choose **Comfortable** (default) or **Compact** under **Density**. Harbor saves the choice per user and applies it before first paint. |
| **Login** | A floating sign-in card, plus a six-box two-factor step (one field without JavaScript). Core field names, CSRF and hooks are unchanged. |
| **Kit** | Global tokens, feedback, toolbars, popups, avatars with presence, copy buttons, and keyboard hints with a shortcut sheet. Explore the kit in **Settings → Harbor → Style guide**. |
| **Board** (`board`) | Floating task cards, sticky column headers, and WIP limit cues on the core board. |
| **Activity** (`activity`) | Timestamped activity rows, deep-link highlighting, and a daily activity heatmap. |
| **Floating surfaces** (`float`) | Dark stages, floating cards and controls, and typed-confirm dialogs. |
| **Chat** (`chat`) | Chat threads, message bubbles, tool chips, and a composer. |
| **Charts** (`charts`) | Stat tiles, sparklines, line charts with a shared scale and a readable table, and capacity usage bars. |
| **Operations** (`ops`) | Status badges with icons and words, data tables, job step lists, service tiles, SSE log viewers, and approval countdowns. |
| **Navigation** (`nav`) | Collapsible breadcrumbs and a date-range picker with relative presets and a custom calendar. The app shell and deferred skeletons are in kit core. |
| **Actions** | Plugins register actions through the `kit:actions` hook, and Harbor's own routes re-check permission through the registry on every run. Harbor renders its subtask actions as a menu. General plugin action menus and the `/` palette come in a later phase (spec D11). |
| **Subtask status** | Clicking a subtask's checkbox opens a menu: Mark done, Start working, Back to todo. Each choice sets one status, with no cycling. Timers start and stop as in core. The row locks while the request is in flight, and an Undo toast follows. |

## Requirements

- **Kanboard** >= 1.2.47
- **PHP** >= 8.4
- **Steward** >= 1.2.0 when Steward is installed (optional). Older Steward replaces Harbor's page layout.
- Not together with **BattleLobby** (`"conflicts"`).

## Installation

For a fresh install, use ModMenu or unzip a release into `plugins/Harbor`. To switch from ShadcnTheme, open
its entry in ModMenu and choose **Migrate**. ModMenu installs Harbor, runs its import, and disables ShadcnTheme.
Harbor carries over each user's legacy theme preference once and preserves the dark default for users who left
the legacy setting unchanged.

## For plugin authors

- Use core markup plus the kit's `data-slot` components, and write colours as `var(--token, <fallback>)` so a
  plugin still looks right without Harbor. `recommends` Harbor, never `requires` it.
- Register CSS and JS only on your own routes, and inject no styles at runtime.
- Register actions with `$this->hook->on('kit:actions', fn () => [...])`. Every action's route must go through
  `harborActionRegistry->execute()` or call the same `can` check itself.

Action descriptors have `id` (namespaced, such as `example.open`), `label`, `slot`, `key` (a shortcut or `null`),
`when` (a state guard), `route`, `risk` (`read`, `reversible`, or `consequential`), `stepUp`, and `can` (the permission callback).
The optional `states_from` map supplies the state names Harbor evaluates against `when` for menu enablement.

| Kit | Components |
| --- | --- |
| Kit core | Design tokens, form controls, tabs, popups (menus, tooltips, and popovers), pills, callouts, toasts with Undo, progress, empty states, toolbars, the app shell, deferred skeletons, avatars with presence, copy buttons, visually hidden text, keyboard hints, the shortcut sheet, and the subtask action menu. |
| `board` | Floating task cards, sticky column headers, and WIP limit cues. |
| `activity` | Activity rows and heatmaps. |
| `float` | Floating stages and controls, and typed confirmation. |
| `chat` | Chat threads, message bubbles, tool chips, and the composer. |
| `charts` | Stat tiles, sparklines, line charts with a readable table, and capacity usage bars. |
| `ops` | Status badges, data tables, step lists, service tiles, SSE log viewers, and approval countdowns. |
| `nav` | Collapsible breadcrumbs and date-range pickers. |

Request surface assets from `initialize()` with a `kit:modules` listener. Return module names for
the routes that render those components and an empty array elsewhere. Core controllers have a
`null` plugin name.

```php
$this->hook->on('kit:modules', static fn (string $controller, ?string $plugin): array =>
    $controller === 'AuditController' && $plugin === 'Example' ? ['ops', 'nav'] : []
);
```

Harbor registers each requested asset once per page. The `chat` module also loads `float`.
Tokens and kit core are available on every page with Harbor enabled.

Design spec: `docs/specs/2026-11-23-harbor-design-system-design.md` in the suite repository.

## Development

From the suite repository root:

```
./testing/run-plugin-tests.sh Harbor
node --test 'Harbor/Test/js/*.test.mjs'
```

## License

MIT
