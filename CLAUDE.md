# CLAUDE.md

Auto-loaded every session. Keep terse — this is rules and pointers, not a tutorial.

## What this is

WordPress plugin: a "grandma-proof" frontend dashboard so non-techy clients can
edit pages and CPT content without ever touching wp-admin. Built for
AutentiWeb (Helmer's agency), deployed across multiple client sites. Auto-updates
from GitHub Releases via YahnisElsts/plugin-update-checker.

- Repo: <https://github.com/helmer-creando/client-frontend-dashboard>
- Full project context: [PROJECT-BRIEF.md](PROJECT-BRIEF.md)
- Dev flow: [docs/local-dev.md](docs/local-dev.md)

## Division of labor

- **Helmer builds the Bricks templates.** If something visual lives in a
  template, give him a screenshot + path so he can edit it. Do not try to
  reach into Bricks DB-stored templates from PHP.
- **Claude writes the PHP / CSS / JS** that lives inside this repo.

Before assuming an emoji, icon, or layout issue is "in the plugin", confirm
it isn't being rendered by the surrounding Bricks template.

## Working conventions

- **Smoke-test on Local before every push.** Auto-updater pushes releases to
  client sites within hours; regressions are expensive to roll back. See
  [docs/local-dev.md](docs/local-dev.md) for the symlink setup and the
  per-release smoke-test checklist.
- **Use ACSS 3.3.6 variables.** Don't invent new custom properties unless
  truly necessary. Common tokens: `--primary-ultra-light`, `--primary-dark`,
  `--primary-ultra-dark`, `--kh-surface-variant`, `--kh-on-surface-variant`,
  `--accent`, `--kh-error`, `--space-{xs,s,m,l,xl}`, `--radius-{s,m,l}`.
- **Never hardcode** `font-size`, `line-height`, or `letter-spacing`. Always
  `var(--text-*, fallback)`. Same for radii and spacing.
- **Bricks breakpoints**: XXL (1536+), XL (base), L (≤1024), M (≤768),
  S (≤480). Note: editor-card overflow often happens above the L viewport
  breakpoint because the sidebar narrows the card — pick wider triggers
  (e.g. ≤1280px) when in doubt.
- **Grandma test** is the golden UX standard. If a non-techy user can't
  immediately tell what a state means or how to undo a destructive action,
  it's not good enough.

## Architectural gotchas

- **Two shortcodes render the dashboard:** the monolithic
  `[client_dashboard]` (`cfd_render_dashboard`) and the composable
  `[cfd_view_router]` (`cfd_render_view_router`). Bricks templates typically
  use the router. **When you change routing, sub-views, or add new query
  param branches, update BOTH.** This bit us in v3.8.0 (Phase 2 trash branch
  was only in the monolithic shortcode → trash badge looked broken on live
  sites).
- **Self-hosted Material Symbols** via `cfd_icon($name)` helper. Adding a
  new icon requires (a) mapping `$name => 'CODEPOINT'` in the helper's
  `$map` and (b) re-running `pyftsubset` to include the new codepoint in
  `assets/fonts/material-symbols-outlined.woff2`. Subset command is in
  [docs/feature-plan-v3.8.md §2G](docs/feature-plan-v3.8.md).
- **Sticky save bar** is ACF's `.acf-form-submit` wrapper, styled by the
  plugin. We inject custom buttons via the `html_submit_button` arg in
  `acf_form()`. ACF still adds its own submit; we hide it with CSS.
- **`acf_form_head()` must run before any output** — `cfd_maybe_load_acf_form_head`
  hooks into `wp` to make this safe. Never echo before this fires.
- **Trashed posts auto-purge after `EMPTY_TRASH_DAYS`** (defined as 30 in
  `client-frontend-dashboard.php`, override-able in `wp-config.php`).
- **CPT capabilities are synced on activation** via `cfd_sync_cpt_caps()` so
  the `site_editor` role can edit each manageable CPT. After adding a new
  manageable CPT to `cfd_settings`, deactivate + reactivate the plugin (or
  call the sync directly) on each site.

## How to scope work

- **New feature** → write a `docs/feature-plan-vX.X.md` (modeled on
  [v3.8.md](docs/feature-plan-v3.8.md)). Iterate on the doc with Helmer,
  then implement against it.
- **Aesthetic / UX changes** → propose 2–3 options before implementing.
  Helmer chooses, then you build.
- **Bug fix** → reproduce on Local first if at all possible. Don't fix
  blind; the symptom is often not the root cause (see v3.8.1 Bug A: looked
  like a broken `<a href>`, was actually a missing branch in the
  composable shortcode).

## Release flow

Short version (full doc in [docs/local-dev.md](docs/local-dev.md)):

1. Bump `Version:` header AND `CFD_VERSION` constant in
   [client-frontend-dashboard.php](client-frontend-dashboard.php). Keep
   them identical — auto-updater compares the release tag to `CFD_VERSION`.
2. Commit. Style: `vX.X.X: short headline — body`. See `git log` for tone.
3. `git push origin main && git tag vX.X.X && git push origin vX.X.X`
4. `gh release create vX.X.X --title "..." --notes "..."` — auto-updater
   picks it up across all client sites within ~12h.

Never release without smoke-testing on Local first.

## Memory

Helmer's preferences and prior-session context live in `~/.claude/projects/`
auto-memory (loaded transparently). This file is the repo-scoped equivalent
that travels with the codebase.
