# CFD Feature Plan — v3.9 (Options Page Editing + Native Body Editor)

Status: **shipped** in v3.9.0 (`fc85329`, tag `v3.9.0`). This doc is the
as-built record so future sessions know how the feature works and why it
was built the way it was.

## Table of Contents

1. [Background](#1-background)
2. [Feature A — ACF Options Page editing](#2-feature-a--acf-options-page-editing)
3. [Feature B — native body editor (opt-in per CPT)](#3-feature-b--native-body-editor-opt-in-per-cpt)
4. [Files touched](#4-files-touched)
5. [Design decisions & rejected alternatives](#5-design-decisions--rejected-alternatives)
6. [Smoke-test path](#6-smoke-test-path)

---

## 1. Background

Two needs surfaced from the same incoming client (Aaron Tritt — an entheogen
safe-use resource library, CPT `resources` with a `resource_category`
taxonomy):

- **Edit site-wide content that isn't tied to a single post** — e.g. which
  resource is "featured" per category, and editable intro/headline copy that
  lives in a Bricks archive template. There's no per-post screen for this; the
  canonical ACF answer is an **Options Page**.
- **Write free-form article body content.** Until now the dashboard only
  exposed ACF fields + the post title; the native WordPress body editor was
  hard-off (`'post_content' => false`). `resources` articles need a real body.

Both are opt-in and ship together because they serve the same client.

---

## 2. Feature A — ACF Options Page editing

Lets non-technical clients edit ACF Options Pages from `/mi-espacio/` without
ever touching wp-admin.

### How a site enables it (no code snippet)

Options pages are **auto-detected** from ACF, then **ticked in the UI**:

1. ACF → Options Pages → create a page (e.g. "Featured Content").
2. ACF → Field Groups → a group located to that page
   (Location: *Options Page is equal to …*).
3. **Settings → Client Dashboard → "Manageable Settings"** → tick the page.

The plugin reads everything else straight from ACF:

| Plugin field   | ACF source                                  |
|----------------|---------------------------------------------|
| `label`        | `menu_title` (falls back to `page_title`)   |
| `icon`         | `icon_url` (Dashicon; URL icons → generic)  |
| `options_id`   | `post_id` (**NOT always `'option'`**)       |
| `field_groups` | `acf_get_field_groups(['options_page'=>$slug])` |
| `capability`   | `capability` (default `edit_posts`)         |

> **`options_id` caveat:** ACF Options Pages don't always use `'option'` as
> their `post_id` — sub-pages and custom storage differ. We read it from ACF
> per page; never hardcode `'option'`.

### Module: `includes/options-pages.php`

- `cfd_detect_options_pages()` — reads ACF, normalizes every page. Icon
  normalization via `cfd_normalize_options_icon()` (Dashicon class in, URL →
  `dashicons-admin-generic` so markup never breaks).
- `cfd_get_options_pages()` — detected pages filtered to the admin's saved
  selection (`cfd_settings['options_pages']`), statically cached. Ends with an
  `apply_filters('cfd_options_pages', $pages)` extensibility hook — **advanced
  override only, not the registration path.**
- `cfd_get_options_page($key)` — single lookup.
- `cfd_user_can_access_options_page($key, $user_id)` — capability gate +
  per-user allowlist (see access model below).
- `cfd_get_accessible_options_pages($user_id)` — list for the current user.
- `cfd_render_options_page($key)` — the editor. `acf_form()` with
  `post_id => options_id` and the resolved `field_groups`. Same save-bar /
  success-message language as the CPT editor.

### Routing — THREE places (the v3.8.0 lesson)

A new `?options=KEY` view had to be wired in **all three** routing surfaces,
or it renders blank in whichever path a given site uses:

1. `cfd_get_dashboard_view()` in [config.php](../includes/config.php) — adds
   the `'options'` view when `?options=` resolves to a registered page.
2. `cfd_render_view_router()` — composable `[cfd_view_router]` switch.
3. `cfd_render_dashboard()` — monolithic `[client_dashboard]` branch.

> This is the exact "two shortcodes" gotcha that bit us in v3.8.0 (trash badge).
> See [CLAUDE.md](../CLAUDE.md) → Architectural gotchas.

### Sidebar + cards

- **Sidebar:** `cfd_render_sidebar_nav()` appends accessible options pages
  after a divider (no section heading text — removed per design review).
- **Home cards:** new shortcode **`[cfd_options_cards]`**
  (`cfd_render_options_cards_shortcode`) renders one `cd-page-card` per
  accessible options page (`?options=KEY`, "Editar →"), matching the
  page/CPT card grid. Returns empty string when none are accessible, so the
  surrounding Bricks headline can be hidden with the same condition. **The
  "Páginas adicionales" headline is authored in the Bricks template**, not the
  plugin — the shortcode only emits the cards.

### Per-user access model

Mirrors the existing CPT/page restriction exactly:

- New user meta `cfd_user_options_pages` (allowlist), managed on the user
  profile screen ("Allowed Settings" row, only rendered when pages exist).
- Honored **only** for non-admins with `cfd_restrict_access === '1'`.
- **Unset allowlist = unrestricted** (same as `cfd_user_cpts`); an empty array
  (saved with restriction on) = deny all.
- Admins always bypass.

---

## 3. Feature B — native body editor (opt-in per CPT)

The CPT editor and creator now pass:

```php
'post_content' => post_type_supports($cpt_slug, 'editor'),
```

(in `cfd_render_cpt_editor()` and `cfd_render_cpt_creator()`).

- **Zero config.** Whatever you tick under **ACF → CPT → Supports → Editor**
  controls it. No registry flag, no setting — read straight from WordPress core.
- **Classic TinyMCE, not Gutenberg.** `acf_form()` renders the classic
  `wp_editor` on the frontend; Gutenberg can't run inside an ACF frontend form
  (and the classic editor is the better grandma-proof choice for prose).
- **Backwards-compatible.** Every existing CPT has editor support off →
  `false` → byte-identical to before. Only CPTs you deliberately enable
  (e.g. `resources`) get the body field.

---

## 4. Files touched

| File | Change |
|------|--------|
| `includes/options-pages.php` | **New** — detection, access, renderer |
| `client-frontend-dashboard.php` | `require` the module; version → 3.9.0 |
| `includes/config.php` | `'options'` branch in `cfd_get_dashboard_view()` |
| `includes/dashboard-renderer.php` | router + monolithic routing, sidebar section, `[cfd_options_cards]`, `post_type_supports('editor')` in both CPT forms |
| `includes/admin-settings.php` | "Manageable Settings" checkbox section + `options_pages` sanitization |
| `includes/roles-and-access.php` | per-user `cfd_user_options_pages` allowlist UI + save |

No CSS shipped — cards reuse `cd-page-card`; sidebar reuses existing nav styles.

---

## 5. Design decisions & rejected alternatives

- **Auto-detect + settings toggle, NOT a `cfd_options_pages` registration
  snippet.** The original brief proposed a per-site filter snippet. Rejected
  for Helmer's multi-site auto-update workflow: a code snippet on every client
  site is friction, and the metadata the snippet carried (label/icon/post_id/
  field groups) is all readable from ACF. The toggle mirrors how Manageable
  CPTs already work. The filter survives as an internal extensibility hook.
- **Dashicons for the sidebar/cards**, not self-hosted Material Symbols. The
  sidebar already renders Dashicons (CPT `menu_icon`), and ACF options pages
  store a Dashicon — so no `pyftsubset` re-subset was needed.
- **No "Settings/Ajustes" sidebar heading.** Removed in review; the divider
  alone is enough separation.
- **Classic editor over frontend Gutenberg** — see Feature B. Mounting
  Gutenberg on the frontend means rebuilding the wp-admin editor screen
  (dozens of `@wordpress/*` packages, fragile across core updates) — wildly
  out of proportion for prose content.

---

## 6. Smoke-test path

1. ACF Options Page + field group located to it.
2. Settings → Client Dashboard → "Manageable Settings" → tick it → Save.
   (Page with no field group shows a red "⚠ no field group attached".)
3. `/mi-espacio/` → sidebar item + `[cfd_options_cards]` card → edit → save →
   green confirmation, values persist.
4. Per-user: Users → editor → Restrict access → "Allowed Settings" → untick →
   item vanishes for that user.
5. Body editor: open/create a `resources` entry → native TinyMCE body present;
   other CPTs unchanged.
6. **Backwards-compat:** with nothing ticked in "Manageable Settings", the
   dashboard is identical to v3.8.1 — no sidebar section, no cards.
