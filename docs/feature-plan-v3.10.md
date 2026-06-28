# Feature Plan — v3.10

Seven UX polish items identified from annotated screenshots. No new features —
all improvements to existing UI.

---

## A — Save bar

### A1. Remove "Guardar y ocultar" secondary button

**Status:** Ready to implement  
**File:** `includes/dashboard-renderer.php` ~line 1896

The secondary button that renders as "Guardar y ocultar" (when post is visible)
or "Guardar y publicar" (when post is hidden) is removed. Visibility is already
managed via the dedicated "Ocultar" action in the editor header, so the button
adds confusion without value.

**Changes:**
- Delete the `if ($is_hidden)` / `else` block that sets `$secondary_intent`,
  `$secondary_label`, `$secondary_icon`, and `$save_hint`.
- Remove the `if ($can_edit)` secondary button from `$submit_html`.
- Remove the `$save_hint` `<span>` from `$submit_html`.
- The `$submit_html` becomes just the single primary `<button>`.
- Keep `$is_hidden` variable if it's used elsewhere (e.g., CSS class on wrapper).

---

## B — Navigation / back link

### B1. Style `.cd-back-link` as an outline button

**Status:** Ready to implement  
**File:** `assets/css/dashboard.css` line 213

Both "Volver a la lista" (CPT editor) and "Volver a mis páginas" (pages editor)
use `.cd-back-link`. Currently styled as a small text link. Target: visible
pill-shaped outline button that reads as a real interactive element.

**CSS target — replace `.cd-back-link` block:**
```css
.cd-back-link {
    display: inline-flex;
    align-items: center;
    gap: calc(var(--space-xs) * 0.5);
    padding: calc(var(--space-xs) * 0.6) var(--space-s);
    border: 1.5px solid var(--primary-dark);
    border-radius: var(--radius-m);
    color: var(--primary-dark);
    text-decoration: none;
    font-size: var(--text-s, 0.875rem);
    font-weight: 500;
    transition: background-color 180ms ease, color 180ms ease;
    margin-bottom: var(--space-m);
}
.cd-back-link:hover {
    background-color: var(--primary-ultra-light);
    color: var(--primary-ultra-dark);
}
```

---

## C — List view

### C1. Make post title/name clickable in CPT list cards

**Status:** Ready to implement  
**File:** `includes/dashboard-renderer.php` line 1662

**Root cause:** `.kh-content-item__link` is `position: absolute; z-index: 1`
(covers entire card). `.kh-content-item__info` is `position: relative; z-index: 2`
(sits above the link). Clicking the title hits the info wrapper — not the link —
and does nothing.

**Fix:** Wrap the title text in an `<a>` pointing to `$edit_url` (line 1662):
```php
echo '<h3 class="kh-content-item__title"><a href="' . esc_url($edit_url) . '">' . esc_html($p->post_title) . '</a></h3>';
```
Add CSS so the title link inherits text styling and doesn't double-underline:
```css
.kh-content-item__title a {
    color: inherit;
    text-decoration: none;
}
.kh-content-item__title a:hover {
    text-decoration: underline;
}
```
The same fix may be needed in the pages list (line ~1256 area). Check both list
renderers: `cfd_render_cpt_list` and `cfd_render_pages_list`.

---

## D — ACF field UX

### D1. Relationship field — increase visible item count

**Status:** Ready to implement  
**File:** `assets/css/dashboard.css` line 1940

ACF renders the relationship chooser at ~150px tall (its default), showing ~3
items before scrolling. Increase to show ~5–6 items.

```css
/* Add to the existing .acf-relationship block: */
.cd-acf-form .acf-relationship .acf-rel-wrap {
    height: 220px !important;
}
```

Also increase the right (selected) column to match:
```css
.cd-acf-form .acf-relationship .selection .choices,
.cd-acf-form .acf-relationship .selection .values {
    height: 220px !important;
}
```

---

### D2. Gallery — collapse excess whitespace + remove Bulk Actions

**Status:** Ready to implement  
**File:** `assets/css/dashboard.css` line 1742

**Whitespace:** ACF gallery sets a fixed `min-height` on `.acf-gallery-main`
(default 200px), causing large empty space when fewer images than max are chosen.
Override to let the attachments grid size to its content:

```css
.cd-acf-form .acf-gallery .acf-gallery-main {
    min-height: unset !important;
}
.cd-acf-form .acf-gallery .acf-gallery-attachments {
    min-height: unset !important;
    /* Keep auto-grid sizing; ACF uses float-based layout */
}
```

**Bulk Actions:** The dropdown in `.acf-gallery-toolbar` is never used.
Hide it with CSS (non-destructive — JS still works if needed):

```css
.cd-acf-form .acf-gallery .acf-gallery-toolbar .acf-gallery-bulk-actions,
.cd-acf-form .acf-gallery .acf-gallery-toolbar .bulk-actions-placeholder {
    display: none !important;
}
```
> Inspect ACF's rendered HTML to confirm the exact class name for the bulk
> dropdown. Common selectors: `.acf-gallery-toolbar select`,
> `.acf-gallery-side-data` or `.bulk`. Adjust selector to match.

---

### D3. Icon picker — fix SVG preview + restyle button

**Status:** Needs inspection of rendered HTML (inspect on Local)  
**File:** `assets/css/dashboard.css` line 2009 (existing Icon Picker block)

**Problem:** The field uses ACF's "Icon Picker" add-on, Media Library tab.
When an SVG icon is selected it renders as a tiny black square against a white
background — unreadable. The "Browse Media Library" button uses default
WordPress blue styling, inconsistent with the plugin.

**Approach:**

1. **Preview fix** — give the preview wrapper a light neutral background so
   the black SVG is visible:
```css
/* Target varies by plugin — inspect to confirm class */
.cd-acf-form .acf-field-icon_picker .acf-input > .preview,
.cd-acf-form .acf-field-icon_picker .icon-preview,
.cd-acf-form .acf-field-icon_picker img[src$=".svg"] {
    background: var(--kh-surface-variant, #F0EDE8) !important;
    border-radius: var(--radius-s) !important;
    padding: var(--space-xs) !important;
    width: 64px !important;
    height: 64px !important;
    object-fit: contain !important;
}
```

2. **Button restyle** — make the "Browse Media Library" button match the
   plugin's outline button pattern:
```css
.cd-acf-form .acf-field-icon_picker .button,
.cd-acf-form .acf-field-icon_picker .acf-button {
    background: transparent !important;
    border: 1.5px solid var(--primary-dark) !important;
    border-radius: 100px !important;
    color: var(--primary-dark) !important;
    font-family: inherit !important;
    font-size: var(--text-s, 0.875rem) !important;
    font-weight: 500 !important;
    padding: calc(var(--space-xs) * 0.6) var(--space-s) !important;
    cursor: pointer !important;
    transition: background-color 180ms ease !important;
    box-shadow: none !important;
}
.cd-acf-form .acf-field-icon_picker .button:hover,
.cd-acf-form .acf-field-icon_picker .acf-button:hover {
    background-color: var(--primary-ultra-light) !important;
    color: var(--primary-ultra-dark) !important;
    border-color: var(--primary-ultra-dark) !important;
}
```

3. **Button label** — The text "Browse Media Library" is rendered by the
   ACF Icon Picker plugin. If it supports a filter/label option, change it
   to "Elegir icono" via the ACF field settings or a PHP filter. If not,
   use a JS text swap on `DOMContentLoaded` (scoped to `.cd-acf-form` only):
```js
document.querySelectorAll(
    '.cd-acf-form .acf-field-icon_picker .button, ' +
    '.cd-acf-form .acf-field-icon_picker .acf-button'
).forEach(btn => {
    if (btn.textContent.includes('Browse Media Library')) {
        btn.textContent = 'Elegir icono';
    }
});
```

> **Action before implementing:** Inspect the Icon Picker field on Local.
> Open browser DevTools on the "Icono representativo" field, note the actual
> class names on (a) the preview element, (b) the button. Update selectors
> to match. Takes 2 minutes and prevents selector guessing.

---

### D4. Repeater rows — alternating background for visual separation

**Status:** Ready to implement  
**File:** `assets/css/dashboard.css` line 1222

Multi-field repeater rows all share the same `#FDFCFA` background, making it
hard to see where one item ends and the next begins.

Add alternating background on even rows and a stronger top border:

```css
/* Even rows get a slightly warmer tint */
.cd-acf-form .acf-repeater .acf-row:nth-child(even) {
    background: var(--primary-ultra-light, #FAF7F2) !important;
}

/* Stronger separator between items */
.cd-acf-form .acf-repeater .acf-row + .acf-row {
    border-top: 2px solid rgba(0, 0, 0, 0.06) !important;
}
```

> Test on Local with a multi-field repeater (e.g., "Cómo es la sesión" with
> Título + Descripción sub-fields). If the alternating tint is too subtle,
> step up to `--kh-surface-variant` or `color-mix(in srgb, var(--primary) 6%, white)`.
> If row handle (drag icon) color clashes, also update `.acf-row-handle` bg.

---

## Implementation status — all 7 built (unreleased)

| # | Item | Files touched | Notes |
|---|------|---------------|-------|
| A1 | Remove "Guardar y ocultar" | `dashboard-renderer.php` ~1896 | Handler `cfd_apply_save_intent` kept (still used by new-post "Guardar borrador"). State-aware hint retained. |
| B1 | Back link → outline button | `dashboard.css` 213 | Covers both editor + creator + bottom back links. |
| C1 | Title clickable | `dashboard-renderer.php` 1662 + `dashboard.css` ~3704 | Title wrapped in own `<a>`; trash list (line ~2099) intentionally left alone (no edit link). |
| D1 | Relationship height | `dashboard.css` 1958 | Lifted parent `.selection` to 300px (~6 rows). |
| D2 | Gallery whitespace + hide Bulk Actions | `dashboard.css` 1806 | **Needs Local check — see caveat.** |
| D3 | Icon picker preview + button + label | `dashboard.css` 2045 + `acf-fields.js` | Confirmed selectors below. |
| D4 | Repeater alternating bg | `dashboard.css` 1232 | `:nth-child(even)` tint. Explicitly a "test & see". |

### Confirmed icon-picker selectors (from DevTools)
- Field row: `.acf-icon-picker-media-library`
- Preview square: `button.acf-icon-picker-media-library-preview`
  > preview wrapper `.acf-icon-picker-media-library-preview-img` had a dark
  > `#191E23` background → black `<img src="*.svg">` invisible. Fixed with a
  > light `--kh-surface-variant` panel. (img can't be recoloured via CSS.)
- Browse button: `button.acf-icon-picker-media-library-button` (was WP blue
  `#0783BE`); label is in a child `<span>` → JS `textContent` swap to "Elegir icono".

### Round-2 fixes (after first Local test)
- **D2 gallery whitespace — REVERTED** (CSS overrides collapsed the field).
- **D4 repeater label column** — made `.acf-label` transparent.
- **D3 icon picker** — removed the outer `.acf-icon-picker` border via `:has()`.

### Round-3 fixes (after DOM dumps)
- **D2 gallery whitespace — SOLVED in JS.** ACF sets a fixed inline `height:400px`
  on `.acf-gallery` (resizable) over an absolute layout. `fitGalleries()` in
  `acf-fields.js` now measures the actual thumbnails and sets that same inline
  height to hug content (clamped 150–640px), re-fitting on add/remove (MutationObserver)
  and window resize, and backing off once the user drags ACF's resize handle.
  Truly responsive across CPTs with different max-image counts. No CSS overrides
  (those break it). Bulk-Actions dropdown is ACF's `.acf-gallery-sort` — confirmed.
- **D3 icon picker border — real culprit found.** The redundant `1px #8c8f94`
  border is ACF core on `.acf-icon-picker-media-library` itself (not the outer
  `.acf-icon-picker` I removed in round 2). Added `border: none` there. Both the
  outer-frame removal and this now apply.
- **D4 repeater — table `-row` layout confirmed.** Tint now paints the `<td>`
  cells directly (`.acf-repeater.-row > table > tbody > tr.acf-row:nth-child(even) > td`)
  since the `<tr>` tint sits behind cell fills, plus the `-left` `.acf-label` is
  forced transparent. Whole row (number + label + inputs) carries one colour.

### Round-4 fixes (final polish)
- **D4 repeater — actual gray source found.** It was a `.acf-field::before`
  pseudo-element fill (`#F9F9F9`), not `.acf-label`. Now
  `.acf-repeater .acf-fields.-left > .acf-field::before { background: transparent }`
  so the label column finally inherits the row tint.
- **D3 icon picker preview** — removed the unnecessary inner padding on
  `.acf-icon-picker-media-library-preview-img` (now `padding: 0`).
- **B1 back link** — rebuilt to mirror `.kh-editor__action` exactly (100px pill,
  `--text-m`, weight 500, 44px height, neutral `--kh-*` palette) so "Volver a la
  lista" matches the Ocultar / Duplicar / Eliminar button family.

## Smoke-test checklist
- [ ] CPT list: clicking the **title text** navigates to editor
- [ ] CPT editor + creator: back link looks like an outline button (hover works)
- [ ] CPT editor save bar: only "Guardar cambios" (no "Guardar y ocultar")
- [ ] New-post creator: "Guardar borrador" still present (must NOT be removed)
- [ ] Saving a hidden post keeps it hidden; saving a visible post keeps it visible
- [ ] Repeater (e.g. "Cómo es la sesión"): alternating tint reads as distinct items
- [ ] Relationship field: ~6 items visible before scrolling
- [ ] Gallery: less dead whitespace **and drag-reorder still works**; Bulk Actions gone
- [ ] Icon picker: black SVG preview legible on light panel; button matches plugin; label = "Elegir icono"

## Not done (deliberately)
- **Version bump** — `CFD_VERSION` left at 3.9.1. There are parked v3.9.1 changes
  in the tree; bump to **3.10.0** as the first step of the release flow once
  smoke-tested.
