# CFD Feature Plan — v3.8 (Phase 2: Hide, Drafts, Papelera, Icon Self-Host)

> **Design filter:** Every feature must pass the "grandma test." If it adds cognitive load for a non-techy client, it doesn't ship — or it ships hidden behind a toggle that only the admin sees.

---

## Table of Contents

1. [Background](#1-background)
2. [Features](#2-features)
   - 2A. Hide / Show toggle (publish ↔ draft)
   - 2B. Draft creation buttons
   - 2C. Papelera per CPT (pill-badge entry)
   - 2D. Trash actions (Restaurar, Eliminar definitivamente)
   - 2E. Undo toast on trash-delete
   - 2F. `EMPTY_TRASH_DAYS` auto-define
   - 2G. Self-hosted Material Symbols (icon FOIT fix)
3. [Capabilities matrix](#3-capabilities-matrix)
4. [Priority & sequencing](#4-priority--sequencing)
5. [Architecture notes](#5-architecture-notes)
6. [Release checklist](#6-release-checklist)

---

## 1. Background

### What Phase 1 already shipped (v3.6.0)

- Listing card redesign — stretched-link click, last-edited timestamp
- Draft awareness pill on cards when `post_status === 'draft'` (currently labeled "En proceso (oculto)")
- Listing query expanded to include both `publish` and `draft`
- Delete confirm modal with title interpolation
- Editor header action zone (Duplicar + Eliminar in overflow menu)
- Mobile polish

### What Phase 2 fills in (this doc)

The Phase 1 plan deliberately deferred:

- **Draft creation** from the dashboard (dashboard always saved as `publish`)
- **Hide-don't-delete** mechanism with a visible toggle
- **Papelera** view per CPT — restore + permanent delete
- **Undo toast** after trash-delete
- **Self-hosted icon font** — the Material Symbols FOIT issue

This doc closes all five.

### Why now

Older clients had mostly static content; the trash/draft features weren't worth the build. **New clients now expect dynamic content management** — particularly the upcoming "free resource library" client. Site editors will be creating, hiding, drafting, and recovering content regularly. Phase 2 makes those workflows grandma-proof.

---

## 2. Features

### 2A. Hide / Show toggle — unifies "hide" with `draft` status

**Problem:** Right now a `site_editor` can only delete a post (or work around it via the wp-admin "Status" dropdown). There's no dashboard-level way to hide a post and bring it back.

**Solution:** A single toggle that flips `publish` ↔ `draft`, exposed on the card and inside the editor.

#### Decision: one mechanism, not two

The dashboard already shows draft posts with a pill. We unify "hide" with `draft` status everywhere in the dashboard. The per-CPT **"Show on page?"** ACF toggle (visible on some sites' editors) is a separate concern — it's a *content field* controlling a specific template section, not a global visibility primitive. The two mechanisms coexist; the dashboard's hide button only touches `post_status`.

#### Pill copy change

Rename the pill in [dashboard-renderer.php:1220](includes/dashboard-renderer.php:1220):

```
"En proceso (oculto)"  →  "Oculto"
```

(Cleaner; "En proceso" implied unfinished work, which isn't always true.)

#### UX: button on the card

Add a third action between Edit and Delete:

| State | Icon | Label | Action |
|-------|------|-------|--------|
| `publish` | `visibility_off` | "Ocultar" (tooltip) | → `draft` |
| `draft` | `visibility` | "Mostrar" (tooltip) | → `publish` |

Card markup gains an icon-only button (matches existing trash button pattern). Stretched-link conflict resolved with the same `z-index` pattern as the existing Edit/Delete buttons.

#### UX: button on the editor

Add a third item to the existing header overflow menu (alongside Duplicar / Eliminar):

- When published: `visibility_off` + "Ocultar"
- When hidden: `visibility` + "Mostrar"

#### Implementation

New handler in [dashboard-renderer.php](includes/dashboard-renderer.php), modeled on `cfd_handle_cpt_delete`:

```php
add_action('template_redirect', 'cfd_handle_cpt_visibility');

function cfd_handle_cpt_visibility(): void {
    // GET param: ?action=toggle_visibility&id=N&_wpnonce=...
    // Verify: nonce, current_user_can('edit_post', $post_id),
    //         post is in user's manageable_cpts
    // Flip: publish ↔ draft via wp_update_post
    // Redirect: back to manage view with ?hidden=true or ?shown=true
}
```

URL builder helper:

```php
function cfd_get_visibility_toggle_url(int $post_id, string $cpt_slug): string {
    return add_query_arg(array(
        'action'   => 'toggle_visibility',
        'id'       => $post_id,
        '_wpnonce' => wp_create_nonce('cfd_visibility_' . $post_id),
        'manage'   => $cpt_slug,
    ), cfd_get_dashboard_url());
}
```

Success messages — drop-in next to existing `?trashed=true` handler at [dashboard-renderer.php:999](includes/dashboard-renderer.php:999):

```php
if (isset($_GET['hidden']) && $_GET['hidden'] === 'true') {
    echo '<div class="cd-success">… Entrada ocultada.</div>';
}
if (isset($_GET['shown']) && $_GET['shown'] === 'true') {
    echo '<div class="cd-success">… Entrada publicada.</div>';
}
```

**Edge cases:**
- User trashes then restores → restores to original status via `_wp_trash_meta_status` (free with WP).
- User hides a post that's already a draft → no-op, redirect with success anyway.
- Concurrent edits → `wp_update_post` is atomic.

**Grandma test:** ✅ One button, label flips to match state. Never ambiguous.

---

### 2B. Draft creation buttons

**Problem:** The create form has only "Crear y publicar". Editors can't draft a post before publishing — they have to publish and then hide, or use wp-admin.

**Solution:** A secondary draft button on both create and edit views, with strict visual hierarchy so the primary action stays obvious.

#### Create view (`cfd_render_cpt_creator` at [dashboard-renderer.php:1445](includes/dashboard-renderer.php:1445))

Two buttons inside the form, stacked or side-by-side:

| | Style | Label | Icon | Result |
|--|------|-------|------|--------|
| Primary | filled, accent | **Crear y publicar** | `auto_fix_high` | `post_status: 'publish'` |
| Secondary | outline / ghost | **Guardar borrador** | `visibility_off` | `post_status: 'draft'` |

ACF `acf_form()` accepts only one submit button via `html_submit_button`. Two paths:

1. Wrap the form and add the second button as a regular `<button name="cfd_save_as">` submit. Read `$_POST['cfd_save_as']` in `acf/save_post` hook (priority 20) and override the post status before/after ACF writes.
2. Use `acf_form_head()` and build the form ourselves. Heavier lift.

Recommend path 1.

```php
add_action('acf/save_post', 'cfd_apply_save_intent', 20);

function cfd_apply_save_intent($post_id): void {
    if (!is_numeric($post_id)) return;
    $intent = isset($_POST['cfd_save_as']) ? sanitize_key($_POST['cfd_save_as']) : '';
    if (!in_array($intent, array('draft', 'publish'), true)) return;
    if (!current_user_can('edit_post', (int) $post_id)) return;
    wp_update_post(array('ID' => $post_id, 'post_status' => $intent));
}
```

#### Edit view (`cfd_render_cpt_editor`)

Same pattern, label changes with current state:

| Current state | Secondary button | Result |
|---------------|------------------|--------|
| `publish` | **Guardar y ocultar** | save + flip to `draft` |
| `draft` | **Guardar y publicar** | save + flip to `publish` |

Primary stays **Guardar cambios** in both cases.

#### Microcopy

Below the buttons, replace the current "Los cambios se publican de inmediato." hint with state-aware copy:

- Create: *"Publica para que aparezca online, o guarda como borrador para terminar después."*
- Edit (publish): *"Guarda para actualizar, o oculta para que deje de aparecer online."*
- Edit (draft): *"Guarda para seguir trabajando, o publica para que aparezca online."*

**Grandma test:** ✅ Primary button is always the obvious action. Secondary exists for the moment they need it.

---

### 2C. Papelera per CPT — pill-badge entry

**Problem:** WP has trash; clients have no way to see or recover from it.

**Solution:** A small count-gated pill-badge integrated into the listing's count line. Click → trash view for that CPT.

#### Scope: per-CPT, not global

Each `?manage={cpt}` listing shows only that CPT's trashed items. Reasoning:

- Each CPT is its own workflow context.
- Restore returns the item to its original listing — that's where the user expects to find it.
- A global papelera would need a CPT filter inside and a permanent sidebar item — more cognitive weight, less context.

#### Entry point: pill-badge on count line

In the listing view at [dashboard-renderer.php:1170](includes/dashboard-renderer.php:1170):

```
Mostrando 1–3 de 3   [🗑 2]
```

- Pill-badge: small rounded pill, muted background, trash icon + integer count.
- **Count-gated:** zero pixels when trash is empty.
- Click → `?manage={cpt}&view=trash`.
- Position: inline with the count line, right side. On mobile, drops to a second line if it doesn't fit.

#### Trash view

New render function `cfd_render_trash_view($cpt_slug, $user)`:

- Same card shape as the main listing for familiarity.
- Cards visually desaturated (CSS: lower opacity on title/thumbnail, muted meta text).
- Each card shows:
  - Title
  - "Eliminado hace 3 días" (uses `human_time_diff` against `_wp_trash_meta_time`)
  - "Se borrará permanentemente en 27 días" (computed from `EMPTY_TRASH_DAYS - days_elapsed`)
- Each card has two actions:
  - **Restaurar** — green-ish accent, `restore_from_trash` icon
  - **Eliminar definitivamente** — danger, `delete_forever` icon
- Top of view: `← Volver a {CPT}` link, no "Vaciar papelera" button.

WP_Query in this view:

```php
'post_status' => 'trash'
```

#### Empty trash state

If user lands on `?view=trash` but trash is empty (e.g., they had it bookmarked):

> "La papelera está vacía. Los elementos que elimines aparecerán aquí durante 30 días."

**Grandma test:** ✅ The pill only appears when there's something to recover. Click → see exactly what you deleted, when, and how to bring it back.

---

### 2D. Trash actions — Restaurar + Eliminar definitivamente

#### Restaurar

Handler modeled on `cfd_handle_cpt_delete`:

```php
add_action('template_redirect', 'cfd_handle_cpt_restore');

function cfd_handle_cpt_restore(): void {
    // GET: ?action=restore&id=N&_wpnonce=...
    // Verify: nonce, current_user_can('edit_post', $post_id), in manageable_cpts
    // Action: wp_untrash_post($post_id)
    //   ↑ this reads _wp_trash_meta_status and returns the post to its prior
    //     status (publish or draft) automatically. Free.
    // Redirect: ?manage={cpt}&view=trash&restored=true
}
```

#### Eliminar definitivamente (type-`ELIMINAR` confirm)

This is the destructive action. We need a different modal from the existing trash-confirm modal.

New modal component (extending the existing `cfd_render_delete_modal` pattern):

```
┌──────────────────────────────────────────┐
│  Eliminar permanentemente                │
│                                          │
│  "Bodywork" se eliminará para siempre.   │
│  Esta acción no se puede deshacer.       │
│                                          │
│  Para confirmar, escribe: ELIMINAR       │
│  ┌────────────────────────────────────┐  │
│  │                                    │  │
│  └────────────────────────────────────┘  │
│                                          │
│     [Cancelar]   [Eliminar para siempre] │
│                  ↑ disabled until input  │
│                    matches "ELIMINAR"    │
└──────────────────────────────────────────┘
```

JS attaches an `input` listener that enables the destructive button only when `value.trim().toUpperCase() === 'ELIMINAR'`. On confirm, submits to:

```
?action=delete_forever&id=N&_wpnonce=...
```

Handler:

```php
add_action('template_redirect', 'cfd_handle_cpt_delete_forever');

function cfd_handle_cpt_delete_forever(): void {
    // GET: action, id, nonce
    // Verify: nonce, current_user_can('delete_post', $post_id), in manageable_cpts
    // ALSO verify: confirmation token from POST (belt and suspenders — JS isn't enough)
    //   The form POSTs cfd_confirm=ELIMINAR alongside the nonce
    // Action: wp_delete_post($post_id, true)  // force=true bypasses trash
    // Redirect: ?manage={cpt}&view=trash&deleted_forever=true
}
```

**Why type `ELIMINAR` and not the post title:**
- Post titles can have tildes, accents, special chars — mobile typing nightmare.
- `ELIMINAR` is unambiguous, accent-free, and the all-caps treatment screams "this is serious."
- Pattern used by GitHub (type repo name), Vercel (type project name), etc.

**Capability:** `site_editor` can permanently delete. The type-`ELIMINAR` guardrail is the friction layer.

---

### 2E. Undo toast on trash-delete

**Problem:** WP's trash has a 30-day window, but clients won't remember they can recover. Mid-flow undo handles "oops I clicked the wrong thing" in real time.

**Solution:** 8-second toast immediately after trash-delete, with a **Deshacer** action that AJAX-untrashes without a page reload.

#### Trigger

After the existing trash redirect (`?trashed=true`), the listing renders. Detect the param and inject an inline `<script>` block that triggers the toast on `DOMContentLoaded`.

Pass the trashed post ID through the URL so the toast knows what to restore:

```
?manage={cpt}&trashed=true&trashed_id=N
```

#### Toast markup

```html
<div class="cfd-toast" role="status" aria-live="polite">
    <span class="cfd-toast__icon"><span class="material-symbols-outlined">delete</span></span>
    <span class="cfd-toast__msg">"Bodywork" movido a la papelera.</span>
    <button type="button" class="cfd-toast__action" data-restore-id="123">Deshacer</button>
    <button type="button" class="cfd-toast__close" aria-label="Cerrar">✕</button>
</div>
```

Sits at the bottom of the screen, mobile-safe-area-inset friendly. Auto-dismisses after 8 seconds (visible progress bar optional but nice).

#### AJAX restore

REST endpoint at `/wp-json/cfd/v1/restore/<id>`:

```php
register_rest_route('cfd/v1', '/restore/(?P<id>\d+)', array(
    'methods'  => 'POST',
    'callback' => 'cfd_rest_restore_post',
    'permission_callback' => function($request) {
        $post_id = (int) $request['id'];
        return current_user_can('edit_post', $post_id);
    },
));
```

On success: toast updates to "Restaurado" with a `check_circle` icon, then dismisses. No page reload (the listing is already showing post-trash state; we add the row back via JS, or just reload the listing — reload is simpler and the latency is fine).

Recommend reload for v3.8 simplicity. Optimistic DOM patching can come in v3.9 if it bothers anyone.

**No undo on permanent delete.** The type-`ELIMINAR` confirm is the safety net.

---

### 2F. `EMPTY_TRASH_DAYS` auto-define

The auto-purge window is WP's. Default is 30 days, but it's controlled by a constant in `wp-config.php` that most clients never touch.

Add to the plugin's main file [client-frontend-dashboard.php](client-frontend-dashboard.php), near the top:

```php
// Ensure trash auto-purges after 30 days. Client can override in wp-config.php.
if ( ! defined( 'EMPTY_TRASH_DAYS' ) ) {
    define( 'EMPTY_TRASH_DAYS', 30 );
}
```

The `if (!defined)` guard means any site that already sets this (e.g., to disable trash with `0`, or extend to `60`) keeps its value.

---

### 2G. Self-hosted Material Symbols — kills the icon FOIT

**Problem:** The plugin enqueues Material Symbols from Google Fonts with `&display=swap`. While the font loads, raw icon names ("arrow_back", "edit") render as literal text — jarring transition. See screenshot from user report.

**Solution:** Self-host a subsetted Material Symbols Outlined WOFF2 inside the plugin, with `font-display: block`.

#### Source

The user has [`/Users/helmer/node_modules/material-symbols/material-symbols-outlined.woff2`](file:///Users/helmer/node_modules/material-symbols/material-symbols-outlined.woff2) — the full variable font, **3.9 MB**. The `outlined.css` shipped with that package has a clean `@font-face` declaration with `font-display: block` already set.

#### Subsetting

3.9 MB is too large to ship. The plugin uses ~15 distinct icon names. Subsetting brings the WOFF2 to ~8–15 KB.

**Subsetting tool:** `pyftsubset` (part of fonttools, `pip install fonttools brotli`).

```bash
pyftsubset material-symbols-outlined.woff2 \
    --output-file=material-symbols-outlined-subset.woff2 \
    --flavor=woff2 \
    --layout-features='liga,calt' \
    --text="arrow_back add_circle edit delete search_off inbox check_circle open_in_new file_copy more_vert save auto_fix_high visibility visibility_off restore_from_trash delete_forever"
```

(The `--text` flag tells pyftsubset to keep glyphs for those ligature names. `liga` and `calt` features must be preserved — they're what make typing `arrow_back` resolve to the icon glyph.)

This is a one-time build step. Result goes into `assets/fonts/material-symbols-outlined-subset.woff2`.

#### Plugin changes

1. **Remove** the Google Fonts enqueue at [dashboard-renderer.php:216-221](includes/dashboard-renderer.php:216):

   ```php
   // DELETE this block:
   wp_enqueue_style(
       'cfd-material-symbols',
       'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:...',
       array(), null
   );
   ```

2. **Add** a small CSS file at `assets/css/material-symbols.css`:

   ```css
   @font-face {
       font-family: "Material Symbols Outlined";
       font-style: normal;
       font-weight: 100 700;
       font-display: block;
       src: url("../fonts/material-symbols-outlined-subset.woff2") format("woff2");
   }
   .material-symbols-outlined {
       font-family: "Material Symbols Outlined";
       font-weight: normal;
       font-style: normal;
       font-size: 24px;
       line-height: 1;
       letter-spacing: normal;
       text-transform: none;
       display: inline-block;
       white-space: nowrap;
       word-wrap: normal;
       direction: ltr;
       -webkit-font-smoothing: antialiased;
       -moz-osx-font-smoothing: grayscale;
       text-rendering: optimizeLegibility;
       font-feature-settings: "liga";
   }
   ```

3. **Enqueue** the new file in place of the Google Fonts URL:

   ```php
   wp_enqueue_style(
       'cfd-material-symbols',
       plugin_dir_url(CFD_PLUGIN_FILE) . 'assets/css/material-symbols.css',
       array(),
       CFD_VERSION
   );
   ```

#### One required icon-name change

[dashboard-renderer.php:1475](includes/dashboard-renderer.php:1475) uses `magic_button`. That name doesn't exist in Material Symbols and is currently rendering as a literal. Replace with `auto_fix_high`.

#### Future icons

When Phase 2 introduces new icons (`visibility`, `visibility_off`, `restore_from_trash`, `delete_forever`), they're already included in the subset above. Adding more icons in the future = re-run pyftsubset with an expanded `--text` list. Worth documenting this as a maintainer step in the plugin README.

---

## 3. Capabilities matrix

Who can do what in Phase 2:

| Action | Admin | site_editor | Notes |
|--------|-------|-------------|-------|
| View card | ✅ | ✅ | Subject to `manageable_cpts` per-user config |
| Edit | ✅ | ✅ | `edit_post` |
| Hide / Show (publish ↔ draft) | ✅ | ✅ | `edit_post` |
| Trash | ✅ | ✅ | `delete_post` |
| View Papelera | ✅ | ✅ | Same as trash cap |
| Restore | ✅ | ✅ | `edit_post` |
| **Eliminar definitivamente** | ✅ | ✅ | `delete_post` + type-`ELIMINAR` modal |

No new roles or capabilities created. Phase 2 rides entirely on existing WP caps.

---

## 4. Priority & sequencing

### Effort estimates

| # | Item | Effort | Risk |
|---|------|--------|------|
| 1 | 2G. Self-hosted icon font | S | Low — file swap + CSS |
| 2 | "Oculto" pill copy change | XS | None |
| 3 | 2A. Hide/Show handler + card button + editor menu item | M | Low |
| 4 | 2B. Draft creation buttons (create + edit) | M | Medium — ACF form integration |
| 5 | 2F. `EMPTY_TRASH_DAYS` auto-define | XS | None |
| 6 | 2C. Papelera pill-badge + trash view render | M | Low |
| 7 | 2D. Restore handler | S | Low |
| 8 | 2D. Permanent-delete handler + type-`ELIMINAR` modal | M | Medium — destructive op, needs careful testing |
| 9 | 2E. Undo toast (markup + REST endpoint + auto-dismiss) | M | Low |
| 10 | CSS for everything above | M | Low |

### Suggested implementation order

Group by file/concern to minimize context switches:

1. **2G. Icon font self-host** — first, because it stabilizes the visual baseline for everything that follows. Subset the font, write the CSS, swap the enqueue, smoke-test all existing icons. Also swap `magic_button` → `auto_fix_high`.
2. **"Oculto" pill rename + `EMPTY_TRASH_DAYS`** — trivial 2-line changes, knock them out together.
3. **2A. Hide/Show toggle** — new handler, card button, editor menu item. Touches multiple files but is one self-contained feature.
4. **2B. Draft creation buttons** — new ACF hook for save-intent, both forms updated. Higher integration risk; do after 2A so the underlying state plumbing is proven.
5. **2C + 2D. Papelera + trash actions** — these belong together. Build the trash view first (read-only listing), then add Restaurar, then add Eliminar definitivamente with the new modal.
6. **2E. Undo toast** — last, because it's pure polish on top of the trash flow. REST endpoint + JS + CSS.

### What gets cut if scope blows up

Drop **2E (undo toast)** first. The Papelera + 30-day window already covers recoverability. The toast is pure UX delight, not load-bearing.

---

## 5. Architecture notes

### Adding handlers without breaking things

All new `template_redirect` handlers follow the existing `cfd_handle_cpt_delete` pattern:
1. Bail unless on the dashboard page.
2. Bail unless logged in.
3. Read GET params, sanitize.
4. Bail unless our specific action.
5. Verify nonce.
6. Verify capability.
7. Verify post is in the user's `manageable_cpts`.
8. Do the thing.
9. Redirect to a clean URL with a success param.

Five new handlers in Phase 2: visibility toggle, restore, permanent delete, save-intent (acf/save_post — different mechanism but same defensive pattern), and the REST callback.

### URL surface (after Phase 2)

Manage view: `?manage={cpt}`
- `&buscar=`, `&orderby=`, taxonomy facets (existing)
- `&view=trash` (new)
- `&trashed=true`, `&trashed_id=N` (existing + new for toast)
- `&restored=true`, `&deleted_forever=true`, `&hidden=true`, `&shown=true` (new)

Editor: `?edit={cpt}&id=N` (existing)

Create: `?create={cpt}` (existing)

Actions (`?action=...`):
- `trash` (existing)
- `restore` (new)
- `delete_forever` (new)
- `toggle_visibility` (new)
- `duplicate` is handled via `?duplicate=` param, not `?action=`, for historical reasons. Don't change that.

### REST endpoint surface

One new route in Phase 2: `POST /wp-json/cfd/v1/restore/<id>`.

Registered under a new namespace `cfd/v1` — first time the plugin exposes REST. Use this as the foundation for any future AJAX needs (e.g., optimistic DOM patching, autosave). Capability check inside `permission_callback`, not via a separate filter.

### CSS class additions

All new classes follow existing BEM convention with `kh-` or `cfd-` prefix:

- `.kh-content-item__visibility` — the card hide/show button
- `.kh-trash-badge` — the pill-badge on count line
- `.kh-trash-view` — wrapper for the trash listing
- `.kh-content-item--trashed` — desaturated card variant
- `.kh-content-item__restore`, `.kh-content-item__delete-forever` — trash card actions
- `.cfd-confirm-modal--destructive` — modal variant with type-to-confirm input
- `.cfd-toast`, `.cfd-toast__msg`, `.cfd-toast__action`, `.cfd-toast__close` — undo toast

### File changes summary

| File | Change |
|------|--------|
| [client-frontend-dashboard.php](client-frontend-dashboard.php) | Add `EMPTY_TRASH_DAYS` guarded define. Version bump to `3.8.0`. |
| [includes/dashboard-renderer.php](includes/dashboard-renderer.php) | New handlers (visibility, restore, delete_forever, save-intent hook). New `cfd_render_trash_view()`. Pill rename. Card button. Editor overflow menu item. Pill-badge on count line. Updated success messages. Swap icon enqueue from CDN to local. Replace `magic_button` with `auto_fix_high`. |
| [includes/styles.php](includes/styles.php) | New CSS for all components listed above. |
| `includes/rest.php` (new) | REST endpoint `POST cfd/v1/restore/<id>`. |
| `assets/fonts/material-symbols-outlined-subset.woff2` (new) | Subsetted icon font, ~10 KB. |
| `assets/css/material-symbols.css` (new) | `@font-face` + class definition. |
| `assets/js/dashboard.js` or new `assets/js/toast.js` | Undo toast logic + type-`ELIMINAR` modal logic. |
| `docs/v3-component-map.md` | Updated with new functions/hooks. |

---

## 6. Release checklist

- [ ] All Phase 2 features implemented per this doc
- [ ] Icon font subsetted; all existing icons render correctly (visual diff against current `display=swap` baseline)
- [ ] `magic_button` swapped to `auto_fix_high` everywhere
- [ ] `EMPTY_TRASH_DAYS` define present and guarded
- [ ] All 5 new template_redirect handlers verify: nonce, capability, manageable_cpts membership
- [ ] Type-`ELIMINAR` confirm verified server-side, not just client-side (POST token, not just JS state)
- [ ] REST endpoint passes capability check
- [ ] Mobile pass: pill-badge fits, undo toast respects safe-area-inset-bottom, type-`ELIMINAR` modal input gets focus correctly
- [ ] `site_editor` role tested end-to-end: hide, show, trash, restore, permanent delete
- [ ] Version bump: header + `CFD_VERSION` constant
- [ ] Staging deploy
- [ ] GitHub release tagged `v3.8.0`

---

## Appendix — what was considered and rejected

- **Global Papelera in the sidebar.** Rejected: needs CPT filter inside, adds permanent cognitive weight, and trash is contextual to each CPT workflow anyway.
- **"Vaciar papelera" bulk button.** Rejected: single biggest footgun in any trash UI. The 30-day auto-purge handles cleanup at scale.
- **Type-post-title confirm instead of `ELIMINAR`.** Rejected: titles can have tildes/accents/punctuation, painful on mobile, easy to fat-finger.
- **Undo toast on permanent delete.** Rejected: physically impossible to undo (`wp_delete_post` with `force=true` is gone). The type-`ELIMINAR` confirm is the safety net.
- **Admin-only permanent delete.** Rejected: `site_editor` already has `delete_post`, and the type-`ELIMINAR` guardrail is sufficient friction. Locking it to admins would mean clients have to bug us to clean up their trash, which defeats the self-service goal.
- **Card with count for Papelera entry.** Rejected: more visual weight than a pill, and would occupy listing real estate even when count is zero (or look weird when hidden).
- **Bottom-of-listing Papelera link.** Rejected (original plan): forces scrolling on long listings, breaks the "recover what I just deleted" flow.
- **Perfmatters "Local Google Fonts" toggle.** Rejected: self-hosts *all* Google Fonts site-wide, overkill for one icon font, spams uploads folder.
- **Bricks Builder Custom Fonts upload for Material Symbols.** Rejected: Bricks gives you `@font-face` but not the ligature CSS / class scaffolding. Same total work, but stored in Bricks DB instead of plugin source — worse for version control.
- **Replace Material Symbols with inline SVGs.** Considered: zero font dependency. Rejected for v3.8 because of icon-flexibility tradeoff (adding a new icon = PHP edit, not just typing a name). Could revisit in a later version once the icon set is fully stable.
