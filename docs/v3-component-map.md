# v3.0 Component Map — Bricks Integration Audit

Every HTML block rendered by `dashboard-renderer.php` and every CSS section in `dashboard.css`, categorized for the Bricks split.

> [!NOTE]
> 🟢 = **→ Bricks template** (pure layout/chrome)
> 🔴 = **→ Plugin** (functional core, must stay in PHP)
> 🟡 = **→ Hybrid** (logic in plugin, visual shell in Bricks)

---

## PHP Functions — [dashboard-renderer.php](file:///Volumes/Ikigai/%23HelpingOthers/AutentiWeb/dev/client-frontend-dashboard/includes/dashboard-renderer.php)

### Infrastructure (stays in plugin — no HTML output)

| Function | Lines | What it does | Category |
|----------|-------|-------------|----------|
| `cfd_get_dashboard_url()` | 28–36 | Cached dashboard permalink lookup | 🔴 Plugin |
| `cfd_maybe_load_acf_form_head()` | 44–56 | Calls `acf_form_head()` on `wp` hook | 🔴 Plugin |
| `cfd_handle_cpt_delete()` | 64–107 | Nonce-verified trash handler on `template_redirect` | 🔴 Plugin |
| `cfd_enqueue_dashboard_assets()` | 115–135 | `wp_enqueue_media()` + dashboard.js | 🔴 Plugin |
| `cfd_get_editable_pages()` | 631–656 | Finds pages with ACF field groups | 🔴 Plugin |
| `cfd_get_field_groups_for_post()` | 658–695 | ACF field group detection per post | 🔴 Plugin |

### The Router — `cfd_render_dashboard()` (L143–175)

Currently a monolithic shortcode that reads URL params and dispatches to sub-renderers. **This is the function that gets decomposed in v3.**

| URL Pattern | Dispatches to | v3 Becomes |
|-------------|--------------|------------|
| No params | `cfd_render_dashboard_home()` | `[cfd_home]` shortcode |
| `?edit=page&id=X` | `cfd_render_page_editor()` | `[cfd_editor]` shortcode |
| `?manage=slug` | `cfd_render_cpt_list()` | `[cfd_cpt_list]` shortcode |
| `?edit=slug&id=X` | `cfd_render_cpt_editor()` | `[cfd_editor]` shortcode |
| `?create=slug` | `cfd_render_cpt_creator()` | `[cfd_editor]` shortcode |

---

### Render Functions — HTML Output Breakdown

#### 1. `cfd_render_dashboard_home()` — Lines 181–246

| HTML Block | CSS Classes | Category | Rationale |
|-----------|------------|----------|-----------|
| Hero greeting (`Hola, Name ☀️`) | `.cd-hero`, `.cd-hero__greeting`, `.cd-hero__sub` | 🟢 Bricks | Pure text/layout. Bricks dynamic data can pull `{user_first_name}` |
| "Tus Páginas" section title | `.cd-home__title`, `.cd-home__subtitle` | 🟢 Bricks | Static heading + decorative line |
| Debug info block | `.cd-debug-info` | 🔴 Plugin | Admin-only conditional, tied to `WP_DEBUG` |
| **Page card grid** | `.cd-page-grid` → `.cd-page-card` (icon, title, hint) | 🟢 Bricks | A Bricks query loop over editable pages. Each card is a Bricks element. Plugin provides a helper endpoint/shortcode to supply the page list. |
| "Tu Contenido" section title | `.cd-home__title` | 🟢 Bricks | Static heading |
| **CPT card grid** | `.cd-page-grid` → `.cd-page-card` (icon, title, hint) | 🟡 Hybrid | Cards are visual → Bricks. But the CPT list comes from config → plugin provides data. Could be a Bricks "nestable" with a shortcode data source. |

#### 2. `cfd_render_page_editor()` — Lines 252–311

| HTML Block | CSS Classes | Category | Rationale |
|-----------|------------|----------|-----------|
| Back link ("← Volver al inicio") | `.cd-back-link` | 🟢 Bricks | Simple navigation link |
| Editor card wrapper | `.cd-editor` | 🟢 Bricks | Container div with border/radius/shadow |
| Editor header (title + preview link) | `.cd-editor__header`, `.cd-editor__title`, `.cd-preview-link` | 🟡 Hybrid | Title is dynamic (post title). Preview link needs post permalink. Bricks dynamic data could handle this. |
| Success message ("✅ ¡Cambios guardados!") | `.cd-success` | 🔴 Plugin | Depends on `?updated=true` URL param — conditional logic |
| Editor subtitle | `.cd-editor__sub` | 🟢 Bricks | Static instructional text |
| **ACF form** | `.cd-acf-form` via `acf_form()` | 🔴 Plugin | Core functional output — `acf_form()` call with field groups, return URL, custom submit button |
| Bottom back link | `.cd-back-link--bottom` | 🟢 Bricks | Simple navigation link |

#### 3. `cfd_render_cpt_list()` — Lines 317–495

| HTML Block | CSS Classes | Category | Rationale |
|-----------|------------|----------|-----------|
| Back link | `.cd-back-link` | 🟢 Bricks | Navigation |
| Trashed success message | `.cd-success` | 🔴 Plugin | Conditional on `?trashed=true` |
| List header (CPT name + "Agregar nuevo" button) | `.cd-cpt-list__header`, `.cd-cpt-list__title`, `.cd-add-btn` | 🟡 Hybrid | Title from `$cpt_obj->labels->name`. Button URL includes CPT slug. Bricks dynamic data might handle this. |
| **Toolbar** (sort dropdown + search input + filter button) | `.cd-cpt-toolbar`, `.cd-cpt-toolbar__group`, `.cd-cpt-toolbar__select`, `.cd-cpt-toolbar__search`, `.cd-cpt-toolbar__submit` | 🔴 Plugin | Heavy logic: form action URL, sort options, preserving `?manage=` param, search value persistence. Keep as shortcode. |
| Active search indicator + clear link | `.cd-cpt-search-status`, `.cd-cpt-search-clear` | 🔴 Plugin | Conditional on `$search !== ''`, constructs clear URL |
| **WP_Query** (fetch posts) | — | 🔴 Plugin | Core data layer |
| Post count ("Mostrando 1–20 de 45") | `.cd-cpt-count` | 🔴 Plugin | Calculated from query results |
| **CPT card grid** | `.cd-cpt-grid` → `.cd-cpt-card` (title, view link) | 🟡 Hybrid | Loop output is data-driven. In Bricks this could be a query loop, but the query params (sort/search/pagination) come from plugin logic. Tricky to split. |
| **Pagination nav** | `.cd-cpt-pagination`, `__link`, `__link--disabled`, `__current` | 🔴 Plugin | Complex URL construction preserving all filter params, page clamping, prev/next logic |
| Empty state messages | `.cd-cpt-list__empty` | 🔴 Plugin | Conditional based on query results |

#### 4. `cfd_render_cpt_editor()` — Lines 501–583

| HTML Block | CSS Classes | Category | Rationale |
|-----------|------------|----------|-----------|
| Back link ("← Volver a la lista") | `.cd-back-link` | 🟢 Bricks | Navigation |
| Editor wrapper + header | `.cd-editor`, `.cd-editor__header`, `.cd-editor__title` | 🟡 Hybrid | Same as page editor — dynamic post title |
| Actions bar (preview + delete) | `.cd-editor__actions`, `.cd-preview-link`, `.cd-delete-wrap`, `.cd-delete-link`, `.cd-delete-confirm` | 🔴 Plugin | Delete has nonce URL, inline confirmation JS, cap check |
| Success message | `.cd-success` | 🔴 Plugin | Conditional |
| Editor subtitle | `.cd-editor__sub` | 🟢 Bricks | Static text |
| **ACF form** | `.cd-acf-form` via `acf_form()` | 🔴 Plugin | Core functional output |
| Bottom back link | `.cd-back-link--bottom` | 🟢 Bricks | Navigation |

#### 5. `cfd_render_cpt_creator()` — Lines 589–625

| HTML Block | CSS Classes | Category | Rationale |
|-----------|------------|----------|-----------|
| Back link | `.cd-back-link` | 🟢 Bricks | Navigation |
| Editor wrapper + title | `.cd-editor`, `.cd-editor__title` | 🟡 Hybrid | Title includes CPT singular name |
| Editor subtitle | `.cd-editor__sub` | 🟢 Bricks | Static text |
| **ACF form** (new_post mode) | `.cd-acf-form` via `acf_form()` | 🔴 Plugin | Core functional output |
| Bottom back link | `.cd-back-link--bottom` | 🟢 Bricks | Navigation |

---

## CSS Sections — [dashboard.css](file:///Volumes/Ikigai/%23HelpingOthers/AutentiWeb/dev/client-frontend-dashboard/assets/css/dashboard.css) (1,215 lines)

| CSS Section | Lines | Category | v3 Fate |
|-------------|-------|----------|---------|
| Animations (`cd-fadeUp`, `cd-slideDown`, `cd-rowIn`, `cd-spin`) | 7–25 | 🟡 Split | `fadeUp`/`slideDown` move to ACSS. `cd-spin` stays (spinner is plugin-rendered). |
| **Dashboard wrapper** (`.cd-dashboard`) | 27–42 | 🟢 Bricks | Max-width + padding → Bricks section/container settings |
| **Hero greeting** (`.cd-hero`, `__greeting`, `__sub`) | 44–64 | 🟢 Bricks | Heading + text styles → Bricks elements with ACSS classes |
| **Section labels** (`.cd-home__title`, `__subtitle`) | 66–91 | 🟢 Bricks | Bricks heading elements with ACSS |
| **Page grid** (`.cd-page-grid`) | 93–98 | 🟢 Bricks | Grid layout → Bricks grid element |
| **Page cards** (`.cd-page-card` + children + hover) | 100–156 | 🟢 Bricks | Card styling → Bricks element styles or ACSS custom class |
| **Back link** (`.cd-back-link`) | 158–178 | 🟢 Bricks | Link styling → ACSS utility |
| **Editor wrapper** (`.cd-editor` + header/title/sub) | 180–213 | 🟡 Hybrid | Container styling → Bricks. But editor wraps ACF form, so some stays. |
| **Editor actions** (`.cd-editor__actions`) | 215–221 | 🔴 Plugin | Stays — wraps delete/preview functionality |
| **Delete UI** (`.cd-delete-*`) | 223–278 | 🔴 Plugin | Functional — inline confirm pattern |
| **Glow button** (`.cd-preview-link`, `.cd-glow-btn`, shimmer animation) | 280–358 | 🟡 Split | `.cd-glow-btn` is shared with Bricks logout button → stays as shared class. `.cd-preview-link` inside editor → stays. |
| **Success/error messages** (`.cd-success`, `.cd-error`) | 360–382 | 🔴 Plugin | Plugin renders these conditionally |
| **Save button** (`.cd-save-btn`, `.cd-spinner`) | 384–429 | 🔴 Plugin | Part of ACF form submit |
| **Debug info** (`.cd-debug-info`) | 431–439 | 🔴 Plugin | Admin-only |
| **CPT list header** (`.cd-cpt-list__header`, `__title`, `.cd-add-btn`) | 441–469 | 🟡 Hybrid | Could go Bricks but tightly coupled to CPT context |
| **CPT card grid** (`.cd-cpt-grid`, `.cd-cpt-card` + children) | 471–524 | 🟡 Hybrid | Visual → Bricks, but rendered by plugin query loop |
| **CPT toolbar** (`.cd-cpt-toolbar` + all children) | 526–614 | 🔴 Plugin | Complex form styling — stays with toolbar shortcode |
| **Search status** (`.cd-cpt-search-status`, `__clear`) | 616–640 | 🔴 Plugin | Conditional rendering |
| **Post count** (`.cd-cpt-count`) | 642–647 | 🔴 Plugin | Calculated value |
| **Pagination** (`.cd-cpt-pagination` + children) | 649–688 | 🔴 Plugin | Complex state-dependent rendering |
| **ACF form overrides** (entire `.cd-acf-form` section) | 690–1073 | 🔴 Plugin | All 383 lines stay — these override ACF's default styles for the form the plugin renders |
| **Media modal overrides** | 1075–1095 | 🔴 Plugin | Overrides WP media modal for site_editor users |
| **Responsive rules** | 1097–1215 | 🟡 Split | Layout responsive rules → Bricks. ACF form responsive rules → plugin. |

---

## JS — [dashboard.js](file:///Volumes/Ikigai/%23HelpingOthers/AutentiWeb/dev/client-frontend-dashboard/assets/js/dashboard.js) (100 lines)

| Feature | Lines | Category | Rationale |
|---------|-------|----------|-----------|
| CPT card "Ver ↗" click handler | 1–9 | 🟡 Hybrid | If cards become Bricks elements, link behavior is native. If cards stay plugin-rendered, JS stays. |
| Auto-grow textareas | 11–77 | 🔴 Plugin | Tied to `.cd-acf-form textarea` — ACF form rendering |
| Delete confirmation toggle | 79–100 | 🔴 Plugin | Tied to inline delete UI in CPT editor |

---

## Summary Scorecard

| Category | PHP blocks | CSS sections | Approx CSS lines |
|----------|-----------|-------------|-------------------|
| 🟢 → Bricks | 12 blocks | 7 sections | ~250 lines |
| 🔴 → Plugin | 15 blocks | 10 sections | ~650 lines |
| 🟡 → Hybrid | 6 blocks | 5 sections | ~315 lines |

### Key Takeaway

**The dashboard home page is ~80% Bricks-ready.** The hero, page cards grid, CPT cards grid, section titles, and back links are all pure layout that Bricks handles natively.

**The CPT list view is ~80% plugin.** The toolbar, query, pagination, and conditional states are deeply functional. The card rendering loop is the only hybrid piece.

**The editors (page/CPT/creator) are ~70% plugin.** The ACF form is the core. The wrapper chrome (editor card, header, subtitle) could be Bricks, but the savings are small — a few `<div>` wrappers around the ACF shortcode.

### Recommended v3 Strategy

1. **Start with the dashboard home** — highest visual impact, easiest to extract. Replace `cfd_render_dashboard_home()` with a Bricks template using query loops.
2. **Keep the CPT list + editors as plugin shortcodes** — too much logic to extract. Instead, make them more composable (smaller shortcodes) so Bricks templates can position them.
3. **Split `dashboard.css`** — move ~250 lines of layout CSS to Bricks/ACSS. Keep ~650 lines of ACF form overrides in the plugin.
