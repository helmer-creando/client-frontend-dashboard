# CFD Feature Plan — v3.1 & Beyond

> **Design filter:** Every feature must pass the "grandma test." If it adds cognitive load for a non-techy client, it doesn't ship — or it ships hidden behind a toggle that only the admin sees.

---

## Table of Contents

1. [Core Features (v3.1)](#1-core-features-v31)
   - 1A. Accent Text Markers
   - 1B. Role-Based Page/CPT Visibility
   - 1C. Custom Dashboard Accent Color
   - 1D. Personalized Welcome & Help Hints
   - 1E. Visual CPT Cards (Thumbnails + Timestamps)
   - 1F. One-Click Content Duplication
   - 1G. Quick Access Links (External Plugin Doorways)
2. [Addon: CFD Polylang (v1.0)](#2-addon-cfd-polylang-v10)
3. [Priority & Sequencing](#3-priority--sequencing)
4. [Architecture Notes](#4-architecture-notes)

---

## 1. Core Features (v3.1)

### 1A. Accent Text Markers — `{curly brace}` syntax

**Problem:** Clients need styled "accent words" in headings (e.g., "Viaje a **Bolivia**" with a highlight class). Currently hardcoded in Bricks — clients can't change them.

**Solution:** Simple marker syntax in ACF text fields.

Client types:
```
Viaje a {Bolivia}
```
Plugin renders:
```html
Viaje a <span class="text--accent">Bolivia</span>
```

#### Implementation

**PHP helper** (add to `config.php`):
```php
function cfd_render_accent_text( $text ) {
    if ( strpos( $text, '{' ) === false ) return $text;
    return preg_replace( '/\{([^}]+)\}/', '<span class="text--accent">$1</span>', $text );
}
```

**Where to apply it:**
- Hook into ACF's output filters: `acf/format_value/type=text` and `acf/format_value/type=textarea`
- Only run on frontend (not in wp-admin or Bricks editor) to avoid corrupting the stored value
- Optionally: a `cfd_accent_text` Bricks dynamic tag for use in Bricks elements

**CSS** (add to `dashboard.css` — but this class is really site-wide, so it could also be a Perfmatters snippet or ACSS custom class):
```css
.text--accent {
    color: var(--accent, #A69279);
    /* Optional: font-style, background highlight, underline, etc. — site-specific */
}
```

**UX hint in the editor form:**
- Below ACF text fields that support it, show a muted hint:
  `💡 Usa {llaves} para resaltar una palabra. Ejemplo: Viaje a {Bolivia}`
- Implemented via `acf/render_field` hook — appends a `<p class="description">` after the field

**Scope control (Settings page):**
- New setting: **"Accent text fields"** — multi-select of ACF field keys where the marker is active
- Default: all text/textarea fields (opt-out model, simpler for most setups)
- Alternatively, skip the setting entirely and just apply it globally — YAGNI

**Edge cases:**
- Nested braces `{foo {bar}}` — regex won't match inner braces, which is fine (just renders literally)
- Empty braces `{}` — skip via regex (nothing between braces = no match)
- Multiple markers `{one} and {two}` — works naturally with `preg_replace`
- Client forgets closing brace — renders literally, no breakage

**Grandma test:** ✅ Client just types curly braces around a word. No menus, no buttons, no mode switching.

---

### 1B. Role-Based Page/CPT Visibility

**Problem:** All `site_editor` users see the same dashboard. Some clients need assistants or junior editors with access to fewer pages/CPTs.

**Solution:** Per-role visibility settings, managed entirely by the admin in the Settings page.

#### Role Model

Keep it simple — two tiers to start:

| Role | Display Name (Spanish) | Default Access |
|------|----------------------|----------------|
| `site_editor` | Editor del sitio | Full dashboard (current behavior) |
| `site_editor_limited` | Editor limitado | Only what admin assigns |

> Why not 5 roles? Because the client won't understand the difference between "Editor," "Contributor," and "Author." Two tiers covers 95% of cases: the owner, and their assistant.

#### Settings UI

Extend the existing Settings page with a new section:

**"Permisos por rol" (Permissions by role)**

For each role (`site_editor_limited`), show:
- **Pages:** Checkboxes of all editable pages → which ones this role can see/edit
- **CPTs:** Checkboxes of all manageable CPTs → which ones this role can manage
- Store as: `cfd_settings[role_access][site_editor_limited][pages]` = array of page IDs
- Store as: `cfd_settings[role_access][site_editor_limited][cpts]` = array of CPT slugs

`site_editor` always has full access (no UI needed — it's the "owner" role).

#### Implementation

**Filter dashboard output (dashboard-renderer.php):**
```php
function cfd_user_can_see_page( $page_id ) {
    if ( current_user_can( 'manage_options' ) ) return true; // Admin sees all
    $role = cfd_get_user_dashboard_role();
    if ( $role === 'site_editor' ) return true; // Full access
    $allowed = cfd_get_config()['role_access'][ $role ]['pages'] ?? [];
    return empty( $allowed ) || in_array( $page_id, $allowed );
}

function cfd_user_can_manage_cpt( $cpt_slug ) {
    if ( current_user_can( 'manage_options' ) ) return true;
    $role = cfd_get_user_dashboard_role();
    if ( $role === 'site_editor' ) return true;
    $allowed = cfd_get_config()['role_access'][ $role ]['cpts'] ?? [];
    return empty( $allowed ) || in_array( $cpt_slug, $allowed );
}
```

**Apply in:**
- `cfd_render_page_cards_shortcode()` — filter page cards array
- `cfd_render_cpt_cards_shortcode()` — filter CPT cards array
- `cfd_build_nav_items()` — filter sidebar nav
- `cfd_render_view_router()` — block access to `?edit=` / `?manage=` / `?create=` for unauthorized CPTs
- `cfd_protect_dashboard_page()` — redirect if user tries to access a page they can't edit

**Role registration (roles-and-access.php):**
- `site_editor_limited` gets the same base caps as `site_editor`
- CPT caps sync respects the role_access settings — only grant caps for allowed CPTs
- On settings save, re-sync caps for both roles

**Grandma test:** ✅ The client never sees this. It's admin-only config. The limited user just sees fewer cards — clean, simple, no "access denied" messages.

---

### 1C. Custom Dashboard Accent Color

**Problem:** The dashboard uses `--accent: #A69279` (warm tan). Every client gets the same look. It doesn't feel like *their* brand.

**Solution:** One color picker in Settings that themes the entire dashboard.

#### Implementation

**Settings page:**
- New field: **"Color de acento"** — a single color picker (`<input type="color">`)
- Stored as: `cfd_settings[accent_color]` = hex string (e.g., `#A69279`)
- Default: empty (falls back to current CSS defaults)

**CSS injection (styles.php):**
```php
function cfd_inline_accent_color() {
    $settings = get_option( 'cfd_settings', [] );
    $color = $settings['accent_color'] ?? '';
    if ( empty( $color ) ) return;

    $css = ":root { --accent: {$color}; --primary: {$color}; }";
    wp_add_inline_style( 'cfd-dashboard', $css );
}
```

Because the entire `dashboard.css` already uses `var(--accent)` and `var(--primary)` throughout, changing the root variable automatically recolors:
- Section title lines
- Card hover borders
- Button gradients
- Focus states
- Preview link styling

**One color = maximum impact, zero complexity.**

**Grandma test:** ✅ Client never sees it — admin picks a color once during setup. Dashboard instantly feels branded.

---

### 1D. Personalized Welcome & Help Hints

**Problem:** The dashboard hero says "Hola, [name]" — good. But beyond that, new clients don't know what each section does or what they *can* do.

**Solution:** Two small additions that make the dashboard feel welcoming and self-explanatory.

#### 1D-i. Custom Welcome Subtitle

Currently the hero shows just the greeting. Add a configurable subtitle below it.

**Settings page:**
- New field: **"Mensaje de bienvenida"** — single text input
- Default: `Aquí puedes gestionar el contenido de tu sitio web.`
- Stored as: `cfd_settings[welcome_message]`

**Render in `dashboard-renderer.php`:**
- In the dashboard home view, below the `<h1>` greeting, output:
  ```html
  <p class="cd-hero__subtitle">Aquí puedes gestionar el contenido de tu sitio web.</p>
  ```

#### 1D-ii. Contextual Help Hints

Small, unobtrusive hint text on key screens:

| Screen | Hint |
|--------|------|
| Dashboard Home | *(welcome subtitle above)* |
| Page editor | `Edita el contenido y haz clic en "Guardar cambios" cuando termines.` |
| CPT list | `Aquí puedes ver, buscar y editar tus [CPT label plural].` |
| CPT editor | `Modifica los campos y guarda. Los cambios se publican de inmediato.` |
| CPT creator | `Rellena los campos para crear un nuevo [CPT label singular].` |

**Implementation:**
- Function `cfd_get_view_hint( $view, $cpt_label = '' )` returns the hint string
- Rendered as `<p class="cd-view-hint">` below the view title
- CSS: small, muted text (`--text-dark-muted`), `font-size: var(--text-s, 0.875rem)`

**Settings toggle (optional):**
- **"Mostrar mensajes de ayuda"** — checkbox, default ON
- Lets admin disable hints for experienced clients who find them redundant

**Grandma test:** ✅ This is *for* grandma. She opens the dashboard and immediately knows what to do.

---

### 1E. Visual CPT Cards — Thumbnails + Last-Edited Timestamps

**Problem:** The CPT list (`?manage=retreats`) shows text-only cards: title + "Editar →". For a client with 15 retreats, it's a wall of identical-looking rectangles. The client doesn't *feel* their content — they see a list.

**Solution:** Enrich CPT cards with the post's featured image and a human-readable "last edited" timestamp.

#### Current card structure (dashboard-renderer.php)
```html
<div class="cd-cpt-card">
    <div class="cd-cpt-card__body">
        <h3 class="cd-cpt-card__title">Retiro de Yoga en Bolivia</h3>
    </div>
    <div class="cd-cpt-card__actions">
        <a href="?edit=retreats&id=45">Editar →</a>
        <a href="/retreats/yoga-bolivia/" target="_blank">Ver ↗</a>
    </div>
</div>
```

#### Proposed card structure
```html
<div class="cd-cpt-card cd-cpt-card--has-thumb">
    <div class="cd-cpt-card__thumb">
        <img src="thumb-url.jpg" alt="" loading="lazy">
    </div>
    <div class="cd-cpt-card__body">
        <h3 class="cd-cpt-card__title">Retiro de Yoga en Bolivia</h3>
        <span class="cd-cpt-card__meta">Editado hace 3 días</span>
    </div>
    <div class="cd-cpt-card__actions">
        <a href="?edit=retreats&id=45">Editar →</a>
        <a href="/retreats/yoga-bolivia/" target="_blank">Ver ↗</a>
    </div>
</div>
```

#### Implementation

**Thumbnail (dashboard-renderer.php):**
```php
// Inside the CPT card loop
$thumb = '';
if ( has_post_thumbnail( $post->ID ) ) {
    $thumb = get_the_post_thumbnail( $post->ID, 'thumbnail', [
        'class'   => 'cd-cpt-card__img',
        'loading' => 'lazy',
        'alt'     => '',
    ] );
}
```

- Only renders the `__thumb` div if a featured image exists
- Uses WordPress `thumbnail` size (150×150 by default) — small, cached, fast
- `loading="lazy"` for lists with many items
- Add modifier class `cd-cpt-card--has-thumb` when image exists (CSS layout adapts)

**Timestamp (dashboard-renderer.php):**
```php
$modified = get_the_modified_date( 'U', $post );
$human_time = human_time_diff( $modified, current_time( 'U' ) );
$meta = sprintf( 'Editado hace %s', $human_time );
```

- Uses WordPress's `human_time_diff()` — outputs "3 días", "2 horas", "1 semana"
- Falls back to the modified date if the post was never edited (same as created date)

**CSS (dashboard.css):**
```css
.cd-cpt-card--has-thumb {
    display: grid;
    grid-template-columns: 80px 1fr auto;
    align-items: center;
}

.cd-cpt-card__thumb {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-s, 8px);
    overflow: hidden;
}

.cd-cpt-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cd-cpt-card__meta {
    font-size: var(--text-xs, 0.75rem);
    color: var(--text-dark-muted, #8A817A);
    margin-top: 4px;
}
```

- Cards without thumbnails keep the current layout (no grid, no empty space)
- On mobile (≤780px), thumbnail shrinks to 60px or hides entirely

**Grandma test:** ✅ Zero interaction. Client opens their retreat list and *sees* their retreats — photos, titles, and when they last touched each one. It's their content, not a spreadsheet.

---

### 1F. One-Click Content Duplication

**Problem:** A client has a retreat template they reuse. To create a similar one, they start from scratch every time — re-entering the same location, the same pricing structure, the same ACF fields. It's tedious and error-prone.

**Solution:** A "Duplicar" button on the CPT editor that clones the post (all fields, featured image, taxonomies) and opens the new draft for editing.

#### UX Flow

1. Client is viewing/editing a retreat
2. They see a "Duplicar" button in the editor actions bar (next to "Eliminar")
3. Click → confirmation: "¿Crear una copia de este contenido?"
4. Confirmed → new post created as draft, all fields copied, redirect to `?edit=retreats&id=NEW`
5. Client edits the copy, changes what's different, saves

#### Implementation

**Button (dashboard-renderer.php):**
```html
<a href="?duplicate=retreats&id=45&_wpnonce=XXX" class="cd-duplicate-btn">
    <span class="dashicons dashicons-admin-page"></span> Duplicar
</a>
```

- Placed in the editor actions bar, styled as a secondary/neutral action (not primary green)
- Nonce-protected like delete actions

**Handler (dashboard-renderer.php, early in the router):**
```php
function cfd_handle_duplicate() {
    if ( empty( $_GET['duplicate'] ) || empty( $_GET['id'] ) ) return;
    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'cfd_duplicate_' . $_GET['id'] ) ) {
        wp_die( 'Enlace no válido.' );
    }

    $original_id = absint( $_GET['id'] );
    $original    = get_post( $original_id );
    if ( ! $original || ! current_user_can( 'edit_post', $original_id ) ) return;

    $cpt_slug = sanitize_key( $_GET['duplicate'] );

    // Clone the post
    $new_id = wp_insert_post( [
        'post_type'    => $original->post_type,
        'post_title'   => $original->post_title . ' (copia)',
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
    ] );

    if ( is_wp_error( $new_id ) ) return;

    // Clone all post meta (includes ACF fields)
    $meta = get_post_meta( $original_id );
    foreach ( $meta as $key => $values ) {
        if ( str_starts_with( $key, '_edit_' ) ) continue; // skip lock meta
        foreach ( $values as $value ) {
            add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
        }
    }

    // Clone featured image
    if ( has_post_thumbnail( $original_id ) ) {
        set_post_thumbnail( $new_id, get_post_thumbnail_id( $original_id ) );
    }

    // Clone taxonomies
    $taxonomies = get_object_taxonomies( $original->post_type );
    foreach ( $taxonomies as $tax ) {
        $terms = wp_get_object_terms( $original_id, $tax, [ 'fields' => 'ids' ] );
        wp_set_object_terms( $new_id, $terms, $tax );
    }

    // Redirect to editor
    $dashboard_url = cfd_get_config()['dashboard_slug'];
    wp_safe_redirect( home_url( "/{$dashboard_url}/?edit={$cpt_slug}&id={$new_id}&duplicated=true" ) );
    exit;
}
```

**JS confirmation (dashboard.js):**
```js
// Similar pattern to delete confirmation
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.cd-duplicate-btn');
    if ( btn ) {
        e.preventDefault();
        if ( confirm('¿Crear una copia de este contenido?') ) {
            window.location.href = btn.href;
        }
    }
});
```

**Success feedback:**
- The `?duplicated=true` param triggers the existing success modal system
- Modal message: "✅ Contenido duplicado. Puedes editarlo a continuación."
- Then the client is already in the editor with all fields pre-filled

**Security:**
- Nonce verification (like delete)
- `current_user_can('edit_post')` check
- New post set to `draft` — never auto-publishes a duplicate
- Title appended with " (copia)" so client knows to rename

**What gets cloned:**
| Data | Cloned? | Notes |
|------|---------|-------|
| Title | ✅ | + " (copia)" suffix |
| ACF fields | ✅ | All post meta copied |
| Featured image | ✅ | Reference only (no file duplication) |
| Taxonomies | ✅ | Categories, tags, custom taxonomies |
| Post content | ❌ | Not used (ACF-driven) |
| Post status | ❌ | Always `draft` |
| Author | ❌ | Set to current user |
| Comments | ❌ | Fresh start |

**Grandma test:** ✅ "Quiero hacer otro retiro parecido" → click "Duplicar" → change what's different → save. She doesn't need to understand what "cloning" or "templates" mean.

---

### 1G. Quick Access Links — Curated Doorways to External Plugins

**Problem:** Some clients use complex plugins (FluentCRM, WooCommerce, Bookly, etc.) whose UIs are too deeply embedded to recreate inside CFD. But the client still needs to get there — and currently that means navigating raw wp-admin, which is exactly what we're trying to avoid.

**Insight:** We don't need to *replace* these plugin UIs. We need to make them **reachable** from the dashboard without the client feeling lost. The gap isn't the plugin's editor — it's the journey to get there.

**Solution:** Admin-configurable "Quick Access" links that appear on the dashboard home as their own card section, styled like page/CPT cards but linking directly to specific wp-admin pages (or external URLs).

#### How it works for the admin (you, setting up a client site)

In Settings → Client Dashboard, a new repeater-style section:

| Field | Example |
|-------|---------|
| **Label** | Emails automáticos |
| **Icon** | `dashicons-email-alt` (dropdown or text input) |
| **URL** | `/wp-admin/admin.php?page=fluent-crm#/automations/42/edit` |
| **Hint** (optional) | Edita el contenido de los emails de bienvenida |
| **Open in** | Same tab / New tab |

Stored as:
```php
cfd_settings[quick_links][] = [
    'label'  => 'Emails automáticos',
    'icon'   => 'dashicons-email-alt',
    'url'    => '/wp-admin/admin.php?page=fluent-crm#/automations/42/edit',
    'hint'   => 'Edita el contenido de los emails de bienvenida',
    'target' => '_blank',  // or '_self'
]
```

#### How it looks for the client

On the dashboard home, a new section appears (if any quick links are configured):

```
━━ Herramientas ━━━━━━━━━━━━━━━━━━━━━━━━━

┌─────────────────┐  ┌─────────────────┐
│  📧              │  │  🛒              │
│  Emails          │  │  Pedidos         │
│  automáticos     │  │  recientes       │
│  Editar →        │  │  Ver →           │
└─────────────────┘  └─────────────────┘
```

Same card grid as pages/CPTs. Consistent visual language. The client doesn't know (or care) that clicking opens a wp-admin page — it's just another card.

#### Implementation

**Shortcode (dashboard-renderer.php):**
```php
function cfd_render_quick_links_shortcode() {
    $settings = get_option( 'cfd_settings', [] );
    $links = $settings['quick_links'] ?? [];
    if ( empty( $links ) ) return '';

    ob_start();
    echo '<div class="cd-section">';
    echo '<h2 class="cd-home__title"><span>Herramientas</span></h2>';
    echo '<div class="cd-page-grid">';

    foreach ( $links as $link ) {
        $target = ( $link['target'] ?? '_blank' ) === '_blank' ? ' target="_blank" rel="noopener"' : '';
        $url    = esc_url( home_url( $link['url'] ) );
        $icon   = esc_attr( $link['icon'] ?? 'dashicons-admin-generic' );
        $label  = esc_html( $link['label'] );
        $hint   = esc_html( $link['hint'] ?? 'Abrir →' );

        echo '<a href="' . $url . '" class="cd-page-card"' . $target . '>';
        echo '  <span class="cd-page-card__icon"><span class="dashicons ' . $icon . '"></span></span>';
        echo '  <span class="cd-page-card__title">' . $label . '</span>';
        echo '  <span class="cd-page-card__hint">' . $hint . '</span>';
        echo '</a>';
    }

    echo '</div></div>';
    return ob_get_clean();
}
add_shortcode( 'cfd_quick_links', 'cfd_render_quick_links_shortcode' );
```

**Also rendered automatically** in the view router's home view, after CPT cards (no extra shortcode needed unless using Bricks layout).

#### Why this is the right approach

| Approach | Effort | Result |
|----------|--------|--------|
| ❌ Recreate FluentCRM's editor in CFD | Weeks/months | Fragile, breaks on plugin updates |
| ❌ iFrame the plugin page | Medium | CORS issues, broken styling, feels janky |
| ❌ Do nothing — tell client to use wp-admin | Zero | Defeats the purpose of CFD |
| ✅ **Deep-link with context** | Small | Client clicks a card, lands exactly where they need to be |

**The philosophy:** CFD doesn't need to *be* every plugin. It needs to be the **home base** — the one place the client starts from, with clear paths to everything they need. Some paths stay inside CFD (pages, CPTs). Some paths lead outside (FluentCRM, WooCommerce). But the client always starts from the same familiar place.

#### Security considerations

- Quick links are admin-configured only (not client-editable)
- URLs are escaped with `esc_url()` — XSS-safe
- wp-admin pages still require the user to be logged in (WordPress handles auth)
- `site_editor` role may need specific caps added for the target plugin (e.g., FluentCRM's `fcrm_manage_emails`). Document this in the settings UI with a note.

#### For "new tab" links: breadcrumb back

When `target="_blank"`, the plugin page opens in a new tab. The client's dashboard stays open in the original tab — they can always get back.

When `target="_self"`, consider injecting a small "← Volver al dashboard" floating button on wp-admin pages (via `admin_footer` hook) so the client doesn't get stuck. This is optional but nice polish.

**Grandma test:** ✅ She sees a card that says "Emails automáticos" with an envelope icon. She clicks it. She's editing her email. She closes the tab, and her dashboard is still there. No wp-admin navigation, no confusion.

---

## 2. Addon: CFD Polylang (v1.0)

### Why an Addon?

| Factor | Core | Addon ✅ |
|--------|------|---------|
| Polylang Pro is a paid dependency | Forces unused code on 90% of installs | Only loads when needed |
| Multilang touches every layer (nav, queries, editor, URLs) | Bloats core with conditionals | Clean separation |
| Polylang updates could break things | Core release needed for every Polylang change | Independent release cycle |
| Future: WPML support? | Even more conditionals | Separate `cfd-wpml` addon |

### Plugin Structure

```
cfd-polylang/
├── cfd-polylang.php               ← Bootstrap, dependency check (CFD + Polylang active?)
├── includes/
│   ├── language-switcher.php      ← Sidebar language switcher widget/shortcode
│   ├── query-filters.php          ← Filter CPT queries by current language
│   ├── editor-integration.php     ← Translation links in editor, language badge on cards
│   └── url-routing.php            ← Language-aware URL generation
├── assets/
│   ├── css/polylang-dashboard.css ← Language switcher & badge styling
│   └── js/polylang-dashboard.js   ← Switcher interactivity (if needed)
└── readme.txt
```

### Core Hooks Required (add to CFD v3.1)

Before the addon can work cleanly, CFD core needs these filter hooks:

```php
// config.php — Nav items
$nav_items = apply_filters( 'cfd_nav_items', $nav_items );

// dashboard-renderer.php — CPT query args
$query_args = apply_filters( 'cfd_cpt_query_args', $query_args, $cpt_slug );

// dashboard-renderer.php — Page cards array
$pages = apply_filters( 'cfd_page_cards', $pages );

// dashboard-renderer.php — Editor: after title, before form
do_action( 'cfd_editor_before_form', $post, $cpt_slug );

// dashboard-renderer.php — Editor: after form, before actions
do_action( 'cfd_editor_after_form', $post, $cpt_slug );

// dashboard-renderer.php — CPT card: extra badges/info
$card_badges = apply_filters( 'cfd_cpt_card_badges', [], $post, $cpt_slug );

// config.php — Dashboard URL generation
$url = apply_filters( 'cfd_dashboard_url', $url, $params );
```

These hooks are cheap (no-ops when no addon is active) and make CFD extensible for *any* future addon, not just Polylang.

### Feature Breakdown

#### 2A. Language Switcher in Sidebar

- Shortcode: `[cfd_language_switcher]` (or hooks into `cfd_nav_items`)
- Shows flags/labels for available languages
- Clicking switches the dashboard language context
- Stores current language in URL param: `?lang=es` or uses Polylang's native cookie/URL method
- Position: below the nav items, above the logout link

#### 2B. Language-Filtered CPT Lists

- Hooks into `cfd_cpt_query_args` to add Polylang's `lang` parameter
- CPT list only shows posts in the current language
- Language badge on each card (small flag icon) via `cfd_cpt_card_badges`

#### 2C. Translation Links in Editor

- Hooks into `cfd_editor_before_form`
- Shows: "Traducción: 🇬🇧 English (editar) | 🇫🇷 Français (crear)"
- Links go to `?edit=retreats&id=XX` for existing translations or `?create=retreats&translation_of=YY&lang=fr` for new ones
- Uses Polylang's `pll_get_post_translations()` API

#### 2D. Language-Aware Page Cards

- Hooks into `cfd_page_cards`
- Only shows pages in the current language
- Optional: translation status indicator (✅ translated / ⚠️ missing)

#### 2E. Dashboard Home Language Context

- All content on dashboard home respects current language
- Hero greeting could adapt: "Hola" / "Hello" / "Bonjour" based on language
- Falls back gracefully if Polylang is deactivated (addon deactivates itself)

### Dependency Safety

```php
// cfd-polylang.php bootstrap
function cfd_polylang_check_dependencies() {
    if ( ! defined( 'CFD_VERSION' ) || version_compare( CFD_VERSION, '3.1.0', '<' ) ) {
        add_action( 'admin_notices', 'cfd_polylang_missing_cfd_notice' );
        return false;
    }
    if ( ! function_exists( 'pll_current_language' ) ) {
        add_action( 'admin_notices', 'cfd_polylang_missing_polylang_notice' );
        return false;
    }
    return true;
}
```

**Grandma test:** ✅ Client sees a small language switcher (flags). Click flag → everything switches. No settings, no "language" concept to learn — just "click the flag."

---

## 3. Priority & Sequencing

All core features ship in **v3.1.0**. The addon follows as a separate plugin.

| # | Feature | Effort | Impact | Depends on |
|---|---------|--------|--------|------------|
| 1 | 1A. Accent text markers | Small | High — client-editable headings | — |
| 2 | 1C. Accent color picker | Small | High — instant branding | — |
| 3 | 1E. Visual CPT cards | Small | High — content feels real, not a spreadsheet | — |
| 4 | 1F. Content duplication | Medium | High — massive time saver for repetitive CPTs | — |
| 5 | 1D. Welcome message + hints | Small | Medium — onboarding polish | — |
| 6 | 1B. Role-based visibility | Medium | High — multi-user sites | — |
| 7 | 1G. Quick Access links | Small | High — solves the "external plugin" problem universally | — |
| 8 | Core extensibility hooks | Small | Critical — enables all addons | — |
| 9 | 2. CFD Polylang addon | Large | High for multilang clients | #8 (hooks) |

### Implementation order (suggested):

Features 1–8 are independent and can be built in any order. Suggested sequence optimizes for quick wins first, then building toward the more complex pieces:

1. **1A. Accent markers** — one function, one CSS class. Done in 15 min.
2. **1C. Accent color** — one setting, one `wp_add_inline_style`. Quick win.
3. **1E. Visual CPT cards** — thumbnail + timestamp in the card loop. Straightforward.
4. **1D. Welcome + hints** — settings field + render logic. Light.
5. **1G. Quick Access links** — settings repeater + card rendering. Reuses page card markup.
6. **Core hooks** — 7 `apply_filters`/`do_action` calls sprinkled into existing functions.
7. **1F. Duplication** — new handler, button, JS confirm. Medium scope.
8. **1B. Role-based visibility** — new role, settings UI section, filtering in 4 places. Largest core feature.
9. **CFD Polylang v1.0** — separate repo, develops against the hooks from #6.

### v3.1.0 Release checklist

- [ ] All 7 core features implemented and tested
- [ ] Extensibility hooks in place
- [ ] Settings page reorganized into sections (General / Apariencia / Contenido / Permisos)
- [ ] Version bump: header + `CFD_VERSION` constant
- [ ] Test on staging (blueprint.co-creador.com)
- [ ] GitHub release tagged `v3.1.0`

---

## 4. Architecture Notes

### Adding hooks without breaking things

All proposed `apply_filters()` and `do_action()` calls are no-ops when no callback is registered. Zero performance cost, zero behavior change for existing installs.

### Settings page growth

v3.1 adds 3 new settings (accent color, welcome message, help hints toggle). The current settings page has 5 fields. To keep it clean:

- Group into sections with `<h2>` dividers:
  - **General** — slugs, redirect
  - **Apariencia** — logo, accent color
  - **Contenido** — CPT toggles, welcome message, help hints
  - **Permisos** (v3.2) — role-based access

### CSS class naming

- New classes follow existing BEM convention with `cd-` prefix
- `text--accent` is the exception — it's a site-wide utility class (like ACSS classes), not a dashboard component. Uses `text--` prefix to match ACSS naming patterns.

### File changes summary

| File | Changes |
|------|---------|
| `config.php` | `cfd_render_accent_text()`, extensibility filters, accent text filter on ACF output |
| `dashboard-renderer.php` | Extensibility hooks, view hints, role filtering, visual CPT cards (thumb + meta), duplicate handler, duplicate button in editor |
| `admin-settings.php` | New fields: accent color, welcome message, help hints toggle, role access. Reorganized into sections |
| `roles-and-access.php` | `site_editor_limited` role registration & cap sync |
| `styles.php` | Inline accent color CSS injection |
| `dashboard.css` | `.text--accent`, `.cd-view-hint`, `.cd-cpt-card--has-thumb`, `.cd-cpt-card__thumb`, `.cd-cpt-card__meta`, `.cd-duplicate-btn` |
| `dashboard.js` | Duplicate confirmation handler |
