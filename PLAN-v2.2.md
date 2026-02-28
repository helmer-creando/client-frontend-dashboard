# Client Frontend Dashboard — v2.2 Plan

## Scope

Three categories of work:

1. **Stack reference update** — Remove WP Code Snippets references (Perfmatters now handles code snippets)
2. **CPT list filtering & pagination** — New feature
3. **Code quality fixes** — Extract Bricks builder detection helper, move debug inline styles to CSS, version bump

---

## 1. Stack Reference Update

**File: `includes/config.php` (line 10-11)**
- Update the comment that says "In your original snippets, these values were duplicated" — rephrase to remove ambiguity about the old WPCodeBox/Code Snippets origin. The comment is historical context and still valid (it describes the migration), so just clean up the wording.

**File: `client-frontend-dashboard.php` (lines 8-9)**
- Bump version from `2.0.0` to `2.2.0` (both in the header and the `CFD_VERSION` constant)

No code changes needed — WP Code Snippets was never a dependency, just the workflow tool.

---

## 2. CPT List Filtering & Pagination

### 2a. PHP changes — `includes/dashboard-renderer.php`

**Modify `cfd_render_cpt_list()`** (currently lines 317-365):

- **Read URL params** for sorting, searching, and pagination:
  - `?orderby=title|date|modified` (default: `title`)
  - `?order=ASC|DESC` (default: `ASC`, but `DESC` when orderby is `date` or `modified`)
  - `?buscar=searchterm` (Spanish for "search")
  - `?pag=2` (page number, default: 1)

- **Render a toolbar** between the header and the grid:
  ```
  [Ordenar: Alfabético ▼]  [🔍 Buscar...]
  ```
  - Orderby dropdown with 3 options: Alfabético (title ASC), Más recientes (date DESC), Última modificación (modified DESC)
  - Search input with current value preserved
  - Wrapped in `<form>` that GETs to the same URL (preserving `?manage=slug`)

- **Update `get_posts()` args**:
  - `posts_per_page` → configurable constant `CFD_POSTS_PER_PAGE` (default: 20)
  - Add `orderby`, `order` from sanitized URL params
  - Add `s` (WordPress search param) from `buscar` if present

- **Pagination logic**:
  - Use `WP_Query` instead of `get_posts()` to get `$query->found_posts` and `$query->max_num_pages`
  - Calculate `offset` from `pag` param: `($pag - 1) * CFD_POSTS_PER_PAGE`
  - Render pagination UI below the grid:
    ```
    ← Anterior  Página 2 de 5  Siguiente →
    ```
  - Preserve all current query params (manage, orderby, order, buscar) in pagination links

- **Post count display**: Show "Mostrando X–Y de Z" above or below the grid

### 2b. CSS changes — `assets/css/dashboard.css`

Add styles for:
- `.cd-cpt-toolbar` — flex container for the filter bar
- `.cd-cpt-toolbar__select` — styled dropdown (matching existing select styles)
- `.cd-cpt-toolbar__search` — search input
- `.cd-cpt-pagination` — pagination wrapper
- `.cd-cpt-pagination__link` — prev/next links
- `.cd-cpt-pagination__current` — current page indicator
- `.cd-cpt-count` — "Mostrando X de Y" text
- Responsive adjustments for the toolbar at existing breakpoints (780px, 480px)

### 2c. No JS changes needed

The filtering/sorting/pagination is server-side (URL params + form submission). No client-side JS required for Phase 1. This keeps it simple, cacheable, and works without JS.

---

## 3. Code Quality Fixes

### 3a. Extract Bricks builder detection helper

**New helper in `includes/dashboard-renderer.php`** (or a shared helpers section):

```php
function cfd_is_bricks_builder(): bool {
    return (
        ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) ||
        ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ||
        isset( $_GET['bricks'] ) ||
        ( defined( 'DOING_AJAX' ) && DOING_AJAX )
    );
}
```

**File: `includes/login.php`** (lines 49-54) — Replace the inline check with `cfd_is_bricks_builder()`.

Place the helper in `includes/config.php` (it's the shared utilities file loaded first) so both login.php and dashboard-renderer.php can use it.

### 3b. Move debug info inline styles to CSS class

**File: `includes/dashboard-renderer.php`** (lines 198-202):
- Replace the inline `style="..."` with a class: `cd-debug-info`

**File: `assets/css/dashboard.css`**:
- Add `.cd-debug-info` styles (background, border, padding, radius, font-size)

### 3c. Version bump

**File: `client-frontend-dashboard.php`**:
- Header `Version:` → `2.2.0`
- `CFD_VERSION` constant → `'2.2.0'`

---

## Files Modified (summary)

| File | Changes |
|------|---------|
| `client-frontend-dashboard.php` | Version bump to 2.2.0 |
| `includes/config.php` | Clean up snippet comment, add `cfd_is_bricks_builder()` helper |
| `includes/dashboard-renderer.php` | Rewrite `cfd_render_cpt_list()` with filtering/pagination/sorting, use debug CSS class |
| `includes/login.php` | Use `cfd_is_bricks_builder()` helper |
| `assets/css/dashboard.css` | Add toolbar, pagination, debug styles + responsive rules |

## Files NOT modified

- `roles-and-access.php` — no changes needed
- `styles.php` — no changes needed
- `login.css` — no changes needed
- `dashboard.js` — no JS needed for server-side filtering

---

## UX Notes

- All new UI text is in Spanish (consistent with existing strings)
- Toolbar uses existing ACSS variables for spacing/colors
- Pagination links preserve all filter state (no "lost filters" when paging)
- Empty search results show a helpful message: "No se encontraron resultados para '[term]'"
- Default sort changed from 50-all to 20-per-page alphabetical (more practical default)
