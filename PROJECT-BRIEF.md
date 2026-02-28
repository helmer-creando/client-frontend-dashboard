# Client Frontend Dashboard — Project Brief & Current State

## What This Is

A WordPress plugin called **Client Frontend Dashboard** that gives non-technical clients a beautiful, self-contained frontend dashboard to edit their pages, images, and CPT content — without ever touching wp-admin. Built for wellness/therapy/holistic sites.

**Live staging site:** `blueprint.co-creador.com`
**Plugin version:** 2.2.2
**Author:** AutentiWeb (https://autentiweb.com)
**GitHub repo:** https://github.com/helmer-creando/client-frontend-dashboard (public)

---

## Developer Profile

- Solo WordPress developer (AutentiWeb) building custom "grandma-proof" client sites
- Stack: **Bricks Builder** + **ACF Pro** + **AutomaticCSS (ACSS)** + **Frames** + **Perfmatters**
- Spanish-language client sites (all UI text is in Spanish)
- Values clarity, maintainability, and human-friendly UX over clever hacks

---

## Tech Stack (Always Active)

| Tool | Role |
|------|------|
| **Bricks Builder** | Page/template builder — primary layout system |
| **ACF Pro** | Custom fields & structured data |
| **AutomaticCSS (ACSS)** | CSS framework — all styles use ACSS variables/tokens |
| **Frames** | Pre-built component library for ACSS — key for responsiveness |
| **Perfmatters** | Performance optimization + code snippets (replaced WP Code Snippets in v2.2) |
| **LiteSpeed Cache** | Server-level page caching (has caused issues — see below) |

---

## Plugin Architecture

```
client-frontend-dashboard/
├── client-frontend-dashboard.php    ← Bootstrap: constants, auto-updater, requires, hooks
├── plugin-update-checker/           ← YahnisElsts v5.6 library (GitHub Releases auto-updates)
├── includes/
│   ├── config.php                   ← Centralized config, DB merge, CPT detection, helpers
│   ├── roles-and-access.php         ← site_editor role, redirects, admin lockout, caching
│   ├── dashboard-renderer.php       ← [client_dashboard] shortcode, ACF form CRUD, filtering/pagination
│   ├── login.php                    ← [cd_login_form] shortcode, auth handler, logout, password reset
│   ├── styles.php                   ← Enqueues dashboard.css (just the loader)
│   └── admin-settings.php           ← Settings → Client Dashboard (CPT toggles, slugs)
└── assets/
    ├── css/
    │   ├── dashboard.css            ← ~1100+ lines, full dashboard styling with ACSS vars
    │   └── login.css                ← 421 lines, login page + glassmorphism + mesh gradient
    └── js/
        └── dashboard.js             ← 99 lines, auto-grow textareas, delete confirm, card handlers
```

### Origin Story

The plugin was migrated from 4 WPCodeBox2 snippets (total ~3,000 lines):
- **Snippet 1** (302 lines) → `roles-and-access.php` — Role, redirects, admin lockout
- **Snippet 2** (794 lines) → `dashboard-renderer.php` + `dashboard.js` — ACF form renderer
- **Snippet 3** (1,066 lines) → `dashboard.css` — All dashboard styles
- **Snippet 4** (890 lines) → `login.php` + `login.css` — Custom login page

Original snippet export is available as `snippet-export-2026-02-28t030239729z.json`.

---

## How It Works

### User Flow
1. Client visits `/capitan/` (login page) → sees glassmorphism login card
2. Submits credentials → `cfd_handle_login_post()` authenticates via `wp_signon()` on the page itself (NOT wp-login.php)
3. Redirected to `/mi-espacio/` (dashboard) → sees editable pages + CPT cards
4. Clicks a page → ACF form renders with that page's fields
5. Clicks a CPT → sees list with sort/search toolbar + pagination → can create/edit/delete entries
6. Clicks logout → redirected back to `/capitan/`

### Key Shortcodes
| Shortcode | Where | What it does |
|-----------|-------|--------------|
| `[client_dashboard]` | Dashboard page | Renders full dashboard (page list, CPT list, editors) |
| `[cd_login_form]` | Login page | Smart form: login / lost password / reset password |
| `[cd_login_error]` | Login page | Error/success messages |
| `[cd_logout_url]` | Any template | Outputs the logout URL for Bricks link fields |

### URL Routing (dashboard)
- `/mi-espacio/` → Dashboard home (page cards + CPT cards)
- `/mi-espacio/?edit=page&id=12` → Edit page 12
- `/mi-espacio/?manage=retreats` → List retreats (with filtering/pagination)
- `/mi-espacio/?manage=retreats&orderby=title&buscar=yoga&pag=2` → Filtered/sorted/paginated
- `/mi-espacio/?edit=retreats&id=45` → Edit retreat 45
- `/mi-espacio/?create=retreats` → New retreat

### URL Routing (login)
- `/capitan/` → Login form
- `/capitan/?action=lostpassword` → Password reset email form
- `/capitan/?action=rp&key=...&login=...` → Set new password form
- `/capitan/?action=logout` → Logout handler

---

## Configuration

### Hybrid Config System
`config.php` has hardcoded defaults that work out of the box. The admin settings page (Settings → Client Dashboard) saves overrides to `wp_options` as `cfd_settings`. The `cfd_get_config()` function merges both, with DB values taking priority. Results are cached per-request via a static variable.

### Per-Site Defaults (in config.php)
```php
'dashboard_slug'  => 'mi-espacio',
'login_slug'      => 'capitan',
'login_redirect'  => '/mi-espacio/',
'editable_pages'  => array(),  // empty = auto-detect pages with ACF field groups
'manageable_cpts' => array( 'retreats', 'testimonials', 'faq' ),
```

### Helper Functions in config.php
- `cfd_get_config()` — Merged config with static caching
- `cfd_is_bricks_builder()` — Shared Bricks editor detection (4 methods)
- `cfd_get_no_cache_slugs()` — Returns dashboard + login slugs for cache exclusion
- `cfd_detect_available_cpts()` — Finds all public non-built-in CPTs (used by settings page)
- `CFD_POSTS_PER_PAGE` — Constant (default: 20) for pagination

### Admin Settings Page
Located at Settings → Client Dashboard. Provides:
- Slug configuration (dashboard, login, redirect path)
- CPT toggle checkboxes (auto-detects all public non-built-in CPTs)
- Role status display
- Shortcode reference

Saving auto-syncs `site_editor` role capabilities.

---

## Auto-Update System

Uses **plugin-update-checker** (YahnisElsts v5.6) in **GitHub Releases mode**:
- Compares GitHub release tags (e.g., `v2.2.2`) against the plugin's `Version` header
- No GitHub token needed (repo is public)
- WordPress shows native update notices when a new release exists
- Wrapped in try-catch so a library error can never crash the site
- Parsedown calls guarded with `class_exists()` to prevent conflicts with Perfmatters' bundled copy

### Release Workflow
1. Bump version in `client-frontend-dashboard.php` (both header and `CFD_VERSION` constant)
2. Commit and push via GitHub Desktop
3. Create GitHub release with tag `vX.X.X`
4. WordPress will detect the update automatically

---

## Security Model

- **Custom role:** `site_editor` with minimal caps (edit pages, upload media, CPT-specific CRUD)
- **Nonce protection:** Login form, delete actions, password reset all use nonces
- **Capability checks:** `current_user_can()` before every edit/render
- **Input sanitization:** `sanitize_key()`, `absint()`, `sanitize_text_field()` throughout
- **Trash not delete:** CPT deletion moves to trash (recoverable)
- **wp-admin blocked:** site_editor users redirected to dashboard (admin-ajax.php allowed for ACF)
- **Admin bar hidden:** For site_editor users
- **Cache prevention:** `DONOTCACHEPAGE` set on both `init` and `template_redirect`

---

## Issues Fixed During Development

### 1. Login form not authenticating (FIXED in v2.0.1)
**Cause:** `wp_login_form()` posts to `wp-login.php`. Bricks' "Custom Login URL" feature blocks that endpoint.
**Fix:** Custom form that posts to `/capitan/` itself. `cfd_handle_login_post()` on `template_redirect` calls `wp_signon()` directly.
**Important:** Bricks Custom Login URL setting must be **DISABLED** — the plugin handles all login routing itself.

### 2. Logout not working (FIXED in v2.1.0)
**Cause:** Logout button in Bricks pointed to `wp-login.php?action=logout` (blocked by Bricks). Even with `[cd_logout_url]`, nonce got baked into LiteSpeed-cached HTML and went stale.
**Fix:** Nonce-free logout via `/capitan/?action=logout`. Handler on `init` hook (fires before LiteSpeed). Safe because logout is non-destructive.

### 3. LiteSpeed caching login page (PARTIALLY FIXED)
**Cause:** LiteSpeed server cache serves pages before WordPress hooks fire.
**Fix:** Added `init`-level `DONOTCACHEPAGE` definition via URI matching. May need LiteSpeed exclusion rule as belt-and-suspenders.

### 4. delete_others_posts in base role (FIXED in v2.0.0)
**Cause:** Original snippet granted `delete_others_posts` to the base role — allowed deleting any blog post.
**Fix:** Removed from base role. CPT-specific delete caps granted separately.

### 5. 24 DB writes per page load (FIXED in v2.0.0)
**Cause:** Original called `$role->add_cap()` in a loop on every request (3 CPTs × 8 caps).
**Fix:** Version-flagged sync — only writes when CPT config changes.

### 6. Update checker Parsedown crash (FIXED in v2.2.2)
**Cause:** `Parsedown::instance()` in plugin-update-checker crashed when Perfmatters' bundled copy of the library conflicted. `PMCS` prefix in error log confirmed Perfmatters was catching the error.
**Fix:** Guarded both Parsedown calls with `class_exists('Parsedown')` (in `GitHubApi.php` and `Api.php`). Also wrapped entire update checker init in try-catch. Removed `setBranch('main')` to use GitHub Releases mode instead of branch mode.

### 7. Admin settings page missing after deploy (FIXED in v2.2.1)
**Cause:** `admin-settings.php` existed on the server but was never committed to Git. When user uploaded the GitHub zip, the file was lost. Also, `config.php` in Git lacked the DB merge logic the server version had.
**Fix:** Restored `admin-settings.php`, merged `config.php` with DB merge logic + `cfd_detect_available_cpts()`, wired settings page into bootstrap with `is_admin()` guard.

---

## Known Issues / TODO

### Bugs to Investigate
- [ ] **LiteSpeed cache on login page** — `DONOTCACHEPAGE` on `init` should work, but may need a LiteSpeed-specific exclusion rule in `.htaccess` or LSCWP settings. Test after purging cache post-update.

### Features Planned
- [ ] **Bricks layout integration (v3.0)** — Move layout/chrome into Bricks templates, keep ACF form rendering in plugin. Best of both worlds. Deferred from v2.2.

### Code Quality Items
- [ ] Debug mode in `cfd_render_dashboard_home()` uses `WP_DEBUG` constant — should verify this works correctly
- [ ] Login mesh gradient (3 rotating 200%×200% conic gradients + blur(80px)) is GPU-intensive — test on older iPads
- [ ] Repeated `get_page_by_path()` calls are cached in `cfd_get_dashboard_url()` but the static var resets per request — consider transient for heavier pages

---

## Version History

| Version | Changes |
|---------|---------|
| **2.0.0** | Initial plugin release (migrated from 4 WPCodeBox snippets) |
| **2.0.1** | Fixed login form authentication |
| **2.1.0** | Fixed logout, nonce-free logout handler |
| **2.2.0** | CPT filtering/sorting/pagination, removed WP Code Snippets refs, Bricks helper, author update |
| **2.2.1** | Restored admin settings page, DB config merge, update checker try-catch |
| **2.2.2** | Parsedown class_exists guard, version bump for clean release |

---

## Git Repository

**GitHub:** https://github.com/helmer-creando/client-frontend-dashboard (public)
**Local path:** `/Volumes/Ikigai/#HelpingOthers/AutentiWeb/dev/client-frontend-dashboard`
**License:** GPL-2.0-or-later

The developer uses GitHub Desktop (visual, no terminal). Current workflow:
- Commit after each working change
- Descriptive commit messages
- No branching yet — single `main` branch
- Releases created via GitHub web UI → auto-updater detects them

---

## System Prompt for Claude Code

When working on this project, use this as your guiding context:

```
You are a calm, experienced senior WordPress developer doing pair-programming.
Stack: Bricks Builder + ACF Pro + AutomaticCSS (ACSS) + Frames + Perfmatters on WordPress.

Core principles:
1. Restate before answering — summarize understanding, ask 1-2 clarifying questions if ambiguous.
2. Small and composable over big and magic.
3. Always explain the "why" — trade-offs, edge cases, security, performance, maintainability, UX.
4. Commented, readable code with meaningful names.
5. Respect existing work — improve before rewriting.
6. Be honest about bad ideas.

Rules:
- Custom code goes in the plugin or Perfmatters code snippets (never functions.php or child themes).
- Use ACSS utility classes and variables — only write custom CSS when ACSS doesn't cover it.
- Don't suggest manual performance tweaks that would conflict with Perfmatters.
- The client site is in Spanish — all user-facing strings should be in Spanish.
- All functions are prefixed cfd_ (plugin text domain).
- CSS classes use the cd- prefix (legacy from the original snippets, kept for consistency).
```
