# Client Frontend Dashboard — Project Brief & Current State

## What This Is

A WordPress plugin called **Client Frontend Dashboard** that gives non-technical clients a beautiful, self-contained frontend dashboard to edit pages, images, and CPT content — without ever touching wp-admin. Built for wellness/therapy/holistic sites.

**Live staging site:** `blueprint.co-creador.com`
**Plugin version:** 3.0.0
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

## v3.0 Architecture: The Hybrid Approach

The biggest change in v3.0 is moving away from the plugin dictating the entire HTML wrapper. We adopted a **Hybrid Approach**:
- **Bricks Builder:** Handles high-level layout, Chrome (sidebar container, main content area, mobile offcanvas), typography, and global CSS via ACSS.
- **CFD Plugin:** Handles backend logic, user sessions, database queries, and outputs the "meat" of the UI (post grids, forms, modals) via composable shortcodes.

### Core Composable Shortcodes (v3.0)
Instead of one massive `[client_dashboard]` shortcode, layout is now handled by Bricks dropping these pieces:
- `[cfd_sidebar_nav]` — Dynamic sidebar nav (Inicio + manageable CPTs with Dashicons). Also available as a Bricks Query Loop (`CFD Sidebar Nav` type).
- `[cfd_view_router]` — Main content area, dynamically loads Dashboard Home, CPT List, CPT Editor, or CPT Creator based on URL params (`?manage=`, `?edit=`, `?create=`).
- `[cfd_client_logo max_width="150px"]` — Renders the custom client logo uploaded via the CFD settings page.
- `[cd_login_form]` — Smart login/lost-password/reset-password form.
- `[cd_logout_url]` — Outputs the logout URL for Bricks link fields.

> The original `[client_dashboard]` shortcode is kept for backward compatibility.

---

## Plugin Architecture

```
client-frontend-dashboard/
├── client-frontend-dashboard.php    ← Bootstrap: constants, auto-updater, requires, hooks
├── plugin-update-checker/           ← YahnisElsts v5.6 library (GitHub Releases auto-updates)
├── includes/
│   ├── config.php                   ← Config, DB merge, CPT detection, helpers, Bricks dynamic tags
│   ├── roles-and-access.php         ← site_editor role, redirects, admin lockout, caching
│   ├── dashboard-renderer.php       ← Shortcodes, ACF form CRUD, filtering/pagination, composable renderer
│   ├── login.php                    ← [cd_login_form] shortcode, auth handler, logout, password reset
│   ├── styles.php                   ← Enqueues dashboard.css (just the loader)
│   └── admin-settings.php           ← Settings → Client Dashboard (CPT toggles, slugs, logo upload)
├── assets/
│   ├── css/
│   │   ├── dashboard.css            ← ~1500+ lines, full dashboard styling with ACSS vars, BEM naming
│   │   └── login.css                ← 421 lines, login page + glassmorphism + mesh gradient
│   └── js/
│       └── dashboard.js             ← Auto-grow textareas, delete confirm, card handlers, modal auto-dismiss, filter accordion
└── docs/                            ← Developer documentation (see below)
```

### Key Helper Functions (config.php)
- `cfd_get_config()` — Merged config with static caching
- `cfd_is_bricks_builder()` — Shared Bricks editor detection (4 methods)
- `cfd_get_no_cache_slugs()` — Returns dashboard + login slugs for cache exclusion
- `cfd_detect_available_cpts()` — Finds all public non-built-in CPTs (used by settings page)
- `CFD_POSTS_PER_PAGE` — Constant (default: 20) for pagination

---

## URL Routing

### Dashboard
- `/mi-espacio/` → Dashboard home (page cards + CPT cards)
- `/mi-espacio/?edit=page&id=12` → Edit page 12
- `/mi-espacio/?manage=retreats` → List retreats (with filtering/pagination)
- `/mi-espacio/?manage=retreats&orderby=title&buscar=yoga&pag=2` → Filtered/sorted/paginated
- `/mi-espacio/?edit=retreats&id=45` → Edit retreat 45
- `/mi-espacio/?create=retreats` → New retreat

### Login
- `/capitan/` → Login form
- `/capitan/?action=lostpassword` → Password reset email form
- `/capitan/?action=rp&key=...&login=...` → Set new password form
- `/capitan/?action=logout` → Logout handler

---

## Bricks Template Structure (v3.0)

### Desktop Layout
- **Dashboard Layout** (Section) → flex row
  - **Sidebar** (Div, `#brxe-qpknaz`) — Sticky, 260px, contains logo + `[cfd_sidebar_nav]` + Logout link
  - **Main Content Area** (Div) → contains `[cfd_view_router]`

### Mobile Layout
- Original Sidebar: `display: none` at ≤991px breakpoint
- **Offcanvas** (Bricks native) — duplicated sidebar content, triggered by hamburger toggle
- **Hamburger toggle** (`.cfd-mobile-toggle`) — `display: none` on desktop, `display: flex` at ≤991px

### Dynamic Data Tags (Bricks)
| Tag | Output |
|---|---|
| `{cfd_nav_label}` | Display name (e.g., "Retreats") |
| `{cfd_nav_url}` | Full URL (e.g., `/mi-espacio/?manage=retreats`) |
| `{cfd_nav_icon}` | Dashicon CSS class (e.g., `dashicons dashicons-calendar`) |
| `{cfd_nav_active_class}` | `is-active` or empty |
| `{cfd_client_logo}` | Client logo image URL |
| `{cfd_logout_url}` | Logout URL |

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

## Auto-Update System

Uses **plugin-update-checker** (YahnisElsts v5.6) in **GitHub Releases mode**:
- Compares GitHub release tags (e.g., `v3.0.0`) against the plugin's `Version` header
- `enablePreReleaseCheck()` enabled — beta tags are detected too
- No GitHub token needed (repo is public)
- Wrapped in try-catch so a library error can never crash the site
- Parsedown calls guarded with `class_exists()` to prevent conflicts with Perfmatters' bundled copy

### Release Workflow
1. Bump version in `client-frontend-dashboard.php` (both header and `CFD_VERSION` constant)
2. Commit and push
3. Create GitHub release with tag `vX.X.X`
4. WordPress will detect the update automatically

---

## CSS Conventions

- **BEM naming:** `.cd-page-card__icon`, `.cd-cpt-toolbar__search`, etc.
- **Prefix:** All CSS classes use `cd-` prefix (legacy from original snippets, kept for consistency)
- **ACSS variables:** All spacing, colors, typography reference ACSS vars with fallbacks: `var(--space-m, 1.5rem)`, `var(--radius-l, 14px)`, `var(--text-color, #2C2825)`
- **Dashicons:** CPT and Page cards use native WordPress Dashicons (`dashicons-admin-page`, `dashicons-calendar`, etc.), dynamically read from `$cpt_obj->menu_icon`
- **PHP function prefix:** `cfd_`

---

## Version History

| Version | Changes |
|---------|---------|
| **2.0.0** | Initial plugin release (migrated from 4 WPCodeBox snippets) |
| **2.0.1** | Fixed login form authentication |
| **2.1.0** | Fixed logout, nonce-free logout handler |
| **2.2.0** | CPT filtering/sorting/pagination, Bricks helper, author update |
| **2.2.1** | Restored admin settings page, DB config merge |
| **2.2.2** | Parsedown class_exists guard |
| **3.0.0-beta1–beta9** | Full v3 refactor: composable shortcodes, Bricks integration, modal rewrite, filter accordion, logo upload, dynamic tags, Dashicon cards |
| **3.0.0** | Stable release — hybrid Bricks + Plugin architecture |

---

## Known Issues / TODO

- [ ] **LiteSpeed cache on login page** — May need a LiteSpeed-specific exclusion rule
- [ ] Login mesh gradient (3 rotating conic gradients + blur) — test on older iPads
- [ ] Consider transient caching for `get_page_by_path()` calls in heavy pages

---

## System Prompt for AI Assistants

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
- v3.0 uses a Hybrid approach: Bricks handles layout chrome, plugin handles functional UI.
- Never hardcode global layout HTML into the plugin if Bricks can handle it.
```

---

## Git Repository

**GitHub:** https://github.com/helmer-creando/client-frontend-dashboard (public)
**Local path:** `/Volumes/Ikigai/#HelpingOthers/AutentiWeb/dev/client-frontend-dashboard`
**License:** GPL-2.0-or-later
