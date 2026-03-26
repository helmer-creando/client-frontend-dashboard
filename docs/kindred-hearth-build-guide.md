# Kindred Hearth — Bricks Builder Build Guide

> Reference for building the "Empathetic Guardian" redesign in Bricks Builder.
> Design source: Stitch 2.0 screens in `stitch-design/`.
> "News & Updates" tab in designs = **Content** (CPTs) in the real app.

---

## 0. Design Tokens

### ACSS Configuration

Set these in the AutomaticCSS 3.3.6 dashboard:

| Setting | Value | ACSS Variable |
|---------|-------|---------------|
| Primary color | `#1352a7` | `--primary` |
| Secondary color | `#1b6d24` | `--secondary` |
| Danger color | `#ba1a1a` | `--danger` |
| Heading font | Plus Jakarta Sans | `--heading-font-family` |
| Body font | Lexend | `--body-font-family` |
| Base spacing | 24px (mobile) / 30px (desktop) | `--space-m` = base |
| Spacing scale | 1.333 (mobile) / 1.5 (desktop) | generates `--space-xs` … `--space-xl` |
| Radius S | 0.5rem | `--radius-s` |
| Radius M | 0.75rem | `--radius-m` |
| Radius L | 1rem | `--radius-l` |

> **Spacing note:** ACSS generates spacing from base × scale. On desktop (30px base, 1.5 scale):
> `--space-xs` ≈ 13px, `--space-s` = 20px, `--space-m` = 30px, `--space-l` = 45px, `--space-xl` ≈ 68px.
> Use these for structural gaps. Use hardcoded values for tight component internals (e.g., `0.5rem` icon gap).

### Custom MD3 Tokens (add to Perfmatters or root CSS)

These are Material Design 3 tokens that ACSS doesn't cover. Define on `:root`:

```css
:root {
  /* ── Surfaces (warm paper hierarchy) ── */
  --kh-surface:                 #f3faff;  /* Base "desk" bg */
  --kh-surface-container-low:   #e6f6ff;  /* Sidebar bg, large sections */
  --kh-surface-container:       #dbf1fe;
  --kh-surface-container-high:  #d5ecf8;  /* Hover states */
  --kh-surface-container-highest: #cfe6f2; /* Input bg, active states */
  --kh-surface-container-lowest: #ffffff;  /* Card "paper" */
  --kh-surface-variant:         #cfe6f2;

  /* ── Primary containers (gradient end, tinted bgs) ── */
  --kh-primary-container:       #376bc2;  /* CTA gradient end */
  --kh-on-primary-container:    #ecf0ff;  /* Text on primary container */

  /* ── Secondary containers (success/growth) ── */
  --kh-secondary-container:     #a0f399;
  --kh-on-secondary-container:  #217128;

  /* ── Tertiary (warm gold — tip cards) ── */
  --kh-tertiary:                #735c00;
  --kh-tertiary-fixed:          #ffe087;  /* Tip card bg */
  --kh-on-tertiary-fixed:       #241a00;  /* Tip card heading */
  --kh-on-tertiary-fixed-variant: #574500; /* Tip card body */

  /* ── Error containers ── */
  --kh-error-container:         #ffdad6;
  --kh-on-error-container:      #93000a;

  /* ── Text / on-surface ── */
  --kh-on-surface:              #071e27;  /* Primary text */
  --kh-on-surface-variant:      #434656;  /* Secondary text */

  /* ── Outlines ── */
  --kh-outline:                 #747688;  /* Tertiary text, labels */
  --kh-outline-variant:         #c4c5d9;  /* Dividers, subtle borders */

  /* ── Extra radii (ACSS stops at --radius-l) ── */
  --kh-radius-xl:               1.5rem;   /* CTAs, large cards */
  --kh-radius-2xl:              2rem;      /* Hero cards, major sections */

  /* ── Gradients ── */
  --kh-gradient-cta:            linear-gradient(135deg, var(--primary) 0%, var(--kh-primary-container) 100%);
  --kh-glass-bg:                rgba(243, 250, 255, 0.7);
}
```

### Typography

| Role | Font Family | Min Size | Weight |
|------|------------|----------|--------|
| Headlines (h1–h4) | `var(--heading-font-family)` | — | 600–800 |
| Body text | `var(--body-font-family)` | `1rem` | 300–500 |
| Labels / metadata | `var(--body-font-family)` | `0.875rem` | 400–500 |
| Page titles (display) | `var(--heading-font-family)` | `2.75rem` | 800 |

### Shadows

| Name | Value | Use |
|------|-------|-----|
| Ambient (light) | `0 4px 12px rgba(7,30,39,0.06)` | Active nav items |
| Card rest | `0 1px 3px rgba(7,30,39,0.04)` | Cards at rest |
| Card hover | `0 12px 24px rgba(7,30,39,0.06)` | Cards on hover |
| Bottom nav | `0 -8px 30px rgba(7,30,39,0.04)` | Mobile bottom bar |
| Float | `0 12px 24px rgba(7,30,39,0.06)` | Floating elements |

### Gradients

| Name | CSS | Use |
|------|-----|-----|
| CTA gradient | `var(--kh-gradient-cta)` | Primary buttons |
| Glass bg | `var(--kh-glass-bg)` + `backdrop-filter: blur(20px)` | Top nav, mobile bottom nav |

---

## 1. BEM Class Naming Convention

All new Kindred Hearth classes use the `kh-` prefix:

```
kh-{block}
kh-{block}__{element}
kh-{block}--{modifier}
```

Existing plugin shortcode output keeps `cd-` and `cfd-` prefixes.
New Bricks-built elements use `kh-`.

### Class Inventory

**Layout:**
- `kh-layout` — Outer flex row (sidebar + main)
- `kh-sidebar` — Left sidebar
- `kh-main` — Main content area
- `kh-topbar` — Fixed top navigation
- `kh-bottomnav` — Mobile bottom navigation

**Sidebar:**
- `kh-sidebar__avatar` — Concierge avatar area
- `kh-sidebar__welcome` — "Welcome back" text
- `kh-sidebar__nav` — Nav list container
- `kh-sidebar__item` — Single nav item
- `kh-sidebar__item--active` — Active state
- `kh-sidebar__icon` — Material Symbol icon
- `kh-sidebar__label` — Text label
- `kh-sidebar__divider` — Section divider
- `kh-sidebar__cta` — "New Page" button

**Dashboard Home:**
- `kh-hero` — Welcome hero section
- `kh-hero__title` — "Hello! Your site is looking wonderful today."
- `kh-hero__accent` — Accent-colored word ("wonderful")
- `kh-hero__status` — Green status pill
- `kh-cta-card` — Main "Edit My Site" card
- `kh-cta-card__title` — Card headline
- `kh-cta-card__text` — Card description
- `kh-cta-card__btn` — Gradient CTA button
- `kh-tip-card` — Concierge tip (gold)
- `kh-tip-card__icon` — Lightbulb circle
- `kh-tip-card__title` — "Concierge Tip"
- `kh-tip-card__text` — Tip body
- `kh-quick-actions` — Quick actions section
- `kh-quick-actions__title` — Section heading
- `kh-quick-actions__subtitle` — "What would you like to do?"
- `kh-quick-actions__grid` — 4-column grid
- `kh-action-card` — Single action card
- `kh-action-card__icon` — Icon circle (colored)
- `kh-action-card__title` — Action name
- `kh-action-card__hint` — Description
- `kh-recent` — Recent changes section
- `kh-recent__title` — "Recent Changes"
- `kh-recent__list` — Card containing list
- `kh-recent__item` — Single change row
- `kh-recent__item-icon` — Colored circle
- `kh-recent__item-title` — Change description
- `kh-recent__item-time` — Timestamp
- `kh-guide` — "How to get started" section
- `kh-guide__title` — Section heading
- `kh-guide__step` — Single step row
- `kh-guide__step-num` — Number circle (blue)
- `kh-guide__step-title` — Step heading
- `kh-guide__step-text` — Step description

**My Pages:**
- `kh-pages` — Page section wrapper
- `kh-pages__title` — "My Pages"
- `kh-pages__subtitle` — Description text
- `kh-pages__grid` — 2-column card grid
- `kh-page-card` — Single page card
- `kh-page-card__image` — Thumbnail area
- `kh-page-card__badge` — "Main Page" badge
- `kh-page-card__title` — Page name
- `kh-page-card__meta` — "Last changed: ..."
- `kh-page-card__view` — "View Online" link
- `kh-page-card__btn` — "Edit This Page" gradient button
- `kh-page-card__add` — "Add a New Page" dashed button

**Content (CPT List):**
- `kh-content` — Content section wrapper
- `kh-content__header` — Title + Add button row
- `kh-content__title` — CPT name heading
- `kh-content__add` — "Add a New Story" gradient button
- `kh-content__list` — List of items
- `kh-content-item` — Single content item row
- `kh-content-item__date` — Date badge (month + day)
- `kh-content-item__date-month` — Month text
- `kh-content-item__date-day` — Day number
- `kh-content-item__info` — Title + meta
- `kh-content-item__title` — Item title
- `kh-content-item__meta` — "Updated 2 days ago"
- `kh-content-item__actions` — Edit + Delete buttons
- `kh-content-item__edit` — "Change This Story" button
- `kh-content-item__delete` — Delete icon button
- `kh-help-banner` — "Need a hand?" CTA card

**Editor:**
- `kh-editor` — Editor page wrapper
- `kh-editor__back` — Back link
- `kh-editor__title` — Page/post title
- `kh-editor__subtitle` — Description text
- `kh-editor__success` — Success notification
- `kh-editor__section` — White card section for a field group
- `kh-editor__section-icon` — Circle icon
- `kh-editor__section-label` — Field name
- `kh-editor__section-hint` — Helper text
- `kh-editor__actions` — Save/Preview button row
- `kh-editor__save` — "Save My Changes" gradient button
- `kh-editor__preview` — "Preview Online" secondary button
- `kh-editor__tip` — Concierge tip (gold)

**Mobile Bottom Nav:**
- `kh-bottomnav` — Fixed bottom bar
- `kh-bottomnav__item` — Single tab
- `kh-bottomnav__item--active` — Active tab (gradient bg)
- `kh-bottomnav__icon` — Material Symbol
- `kh-bottomnav__label` — Tab label

**Mobile Menu (Drawer):**
- `kh-drawer` — Slide-out drawer
- `kh-drawer__overlay` — Backdrop overlay
- `kh-drawer__panel` — White panel
- `kh-drawer__avatar` — User photo
- `kh-drawer__name` — "Welcome back, Arthur"
- `kh-drawer__subtitle` — "Your information is safe"
- `kh-drawer__badge` — "Premium Member" pill
- `kh-drawer__nav` — Nav links
- `kh-drawer__link` — Single link
- `kh-drawer__link--active` — Active link
- `kh-drawer__logout` — Logout button

---

## 2. Shared Components — CSS Snippets

### 2A. Glassmorphism Top Bar

```
Bricks: Section → fixed, top: 0, z-index: 50, height: 5rem
Class: kh-topbar
```

```css
.kh-topbar {
  position: fixed;
  top: 0;
  width: 100%;
  z-index: 50;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 var(--space-m);
  height: 5rem;
  background: var(--kh-glass-bg);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: 0 4px 20px -10px rgba(19, 82, 167, 0.1);
}

.kh-topbar__brand {
  font-family: var(--heading-font-family);
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--primary);
  letter-spacing: -0.02em;
}

.kh-topbar__actions {
  display: flex;
  align-items: center;
  gap: var(--space-s);
}

.kh-topbar__link {
  font-family: var(--heading-font-family);
  font-weight: 600;
  font-size: 1.125rem;
  color: var(--kh-on-surface-variant);
  padding: 0.5rem 1rem;
  border-radius: var(--radius-m);
  transition: background 0.2s;
}

.kh-topbar__link:hover {
  background: rgba(19, 82, 167, 0.05);
}

.kh-topbar__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  border: 2px solid rgba(19, 82, 167, 0.15);
  object-fit: cover;
}
```

### 2B. Sidebar (Desktop)

```
Bricks: Div → fixed, left: 0, width: 18rem, height: 100vh
Class: kh-sidebar
```

```css
.kh-sidebar {
  position: fixed;
  left: 0;
  top: 0;
  height: 100%;
  width: 18rem;
  display: flex;
  flex-direction: column;
  padding-top: 6rem; /* below topbar */
  background: var(--kh-surface-container-low);
  z-index: 40;
}

.kh-sidebar__avatar-area {
  padding: 0 var(--space-s);
  margin-bottom: var(--space-m);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.kh-sidebar__avatar-img {
  width: 5rem;
  height: 5rem;
  border-radius: 50%;
  border: 2px solid var(--kh-surface-container-lowest);
  object-fit: cover;
  box-shadow: 0 4px 12px rgba(7, 30, 39, 0.06);
  margin-bottom: 1rem;
}

.kh-sidebar__welcome {
  font-family: var(--heading-font-family);
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--primary);
}

.kh-sidebar__role {
  font-family: var(--body-font-family);
  font-size: 0.875rem;
  color: var(--kh-outline);
}

/* Nav items */
.kh-sidebar__nav {
  flex: 1;
  padding: 0 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.kh-sidebar__item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-radius: var(--radius-m);
  font-family: var(--body-font-family);
  font-size: 1.125rem;
  color: var(--kh-outline);
  text-decoration: none;
  transition: all 0.2s;
}

.kh-sidebar__item:hover {
  background: var(--kh-surface-container-highest);
}

.kh-sidebar__item--active {
  background: var(--kh-surface-container-lowest);
  color: var(--primary);
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(7, 30, 39, 0.06);
}

.kh-sidebar__icon {
  font-size: 1.5rem;
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.kh-sidebar__item--active .kh-sidebar__icon {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.kh-sidebar__divider {
  height: 1px;
  background: rgba(196, 197, 217, 0.15);
  margin: 0.5rem 1rem;
}

/* CTA Button */
.kh-sidebar__cta {
  margin: 1.5rem;
}

.kh-sidebar__cta-btn {
  width: 100%;
  padding: 1rem 1.5rem;
  background: var(--kh-gradient-cta);
  color: #fff;
  border: none;
  border-radius: var(--radius-m);
  font-family: var(--body-font-family);
  font-weight: 700;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(19, 82, 167, 0.25);
  transition: transform 0.15s;
}

.kh-sidebar__cta-btn:active {
  transform: scale(0.95);
}
```

### 2C. Mobile Bottom Navigation

```
Bricks: Div → fixed bottom, z-index: 50
Show: ≤1024px only (L breakpoint, hide on XL+)
Class: kh-bottomnav
```

```css
.kh-bottomnav {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  z-index: 50;
  display: flex;
  justify-content: space-around;
  align-items: center;
  padding: 1rem 1rem 2rem; /* extra bottom for safe area */
  background: rgba(243, 250, 255, 0.8);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  box-shadow: 0 -8px 30px rgba(7, 30, 39, 0.04);
  border-radius: 2.5rem 2.5rem 0 0;
}

.kh-bottomnav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0.5rem 1rem;
  color: var(--primary);
  opacity: 0.6;
  text-decoration: none;
  transition: opacity 0.3s;
  font-family: var(--body-font-family);
}

.kh-bottomnav__item:hover {
  opacity: 1;
}

.kh-bottomnav__item--active {
  background: var(--kh-gradient-cta);
  color: #fff;
  opacity: 1;
  border-radius: var(--kh-radius-xl);
  padding: 0.5rem 1.5rem;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.kh-bottomnav__icon {
  font-size: 1.5rem;
  margin-bottom: 0.125rem;
}

.kh-bottomnav__item--active .kh-bottomnav__icon {
  font-variation-settings: 'FILL' 1;
}

.kh-bottomnav__label {
  font-size: 0.75rem;
  font-weight: 500;
}
```

### 2D. Footer

```css
.kh-footer {
  margin-left: 18rem; /* sidebar width */
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-l);
  border-top: 1px solid rgba(196, 197, 217, 0.15);
  font-family: var(--body-font-family);
  font-size: 0.9375rem;
  color: var(--kh-outline);
}

.kh-footer__links {
  display: flex;
  gap: var(--space-m);
}

.kh-footer__link {
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
  opacity: 0.8;
}

.kh-footer__link:hover {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-thickness: 2px;
}

@media (max-width: 1024px) {
  .kh-footer {
    margin-left: 0;
  }
}
```

---

## 3. Dashboard Home — Element Tree

```
Section (kh-main, ml: 18rem, pt: 7rem, pb: var(--space-l), px: var(--space-l))
  Div (max-width: 1000px, mx: auto, space-y: var(--space-l))

    ── HERO ──────────────────────────────────────────
    Div (kh-hero, space-y: 1rem)
      Heading h1 (kh-hero__title)
        "Hola! Tu sitio se ve {increíble} hoy."
        ↳ Span (kh-hero__accent, color: var(--primary)) → "increíble"
        Font: var(--heading-font-family), 3rem, 800, tracking: -0.02em
      Div (kh-hero__status, inline-flex, gap: 0.75rem)
        bg: #a3f69c33, color: var(--kh-on-secondary-container)
        px: 1.5rem, py: 0.75rem, radius: 9999px
        Icon: check_circle (FILL 1, color: var(--secondary))
        Text: "Tu sitio está en línea y seguro."
          Font: var(--body-font-family), 1.125rem, 600

    ── CTA + TIP ROW ────────────────────────────────
    Div (grid, cols: 3, gap: var(--space-m))

      Div (kh-cta-card, col-span: 2)
        bg: var(--kh-surface-container-lowest), p: 2.5rem, radius: var(--kh-radius-2xl)
        border: 2px solid #fff, shadow: card rest
        Heading h2 (kh-cta-card__title)
          "Edita Mi Sitio"
          Font: var(--heading-font-family), 1.875rem, 700
        Text p (kh-cta-card__text)
          "Haz clic aquí para cambiar tus textos, fotos y actualizar tu página."
          Font: var(--body-font-family), 1.25rem, 400, color: var(--kh-on-surface-variant), max-w: 28rem
        Button (kh-cta-card__btn)
          bg: var(--kh-gradient-cta), color: #fff
          px: 2.5rem, py: 1.25rem, radius: var(--radius-l)
          Font: var(--body-font-family), 1.25rem, 700
          Icon: arrow_forward (after text)
          shadow: 0 8px 20px rgba(19,82,167,0.25)
          Text: "Empezar a editar mis páginas"

      Div (kh-tip-card)
        bg: #fff9e6, p: 2rem, radius: var(--kh-radius-2xl)
        border: 2px solid #fff1c2
        Div (kh-tip-card__icon)
          w: 3rem, h: 3rem, bg: var(--kh-tertiary-fixed), radius: 50%
          Icon: lightbulb (color: var(--kh-on-tertiary-fixed))
        Heading h3 (kh-tip-card__title)
          "Consejo"
          Font: var(--heading-font-family), 1.25rem, 700, color: var(--kh-on-tertiary-fixed)
        Text p (kh-tip-card__text)
          Font: var(--body-font-family), 1.125rem, 400, color: var(--kh-on-tertiary-fixed-variant)
        Link a (kh-tip-card__link)
          color: var(--kh-on-tertiary-fixed), font-weight: 700
          underline, underline-offset: 4px, decoration: 2px

    ── QUICK ACTIONS ────────────────────────────────
    Div (kh-quick-actions)
      Div (flex, justify: between, items: center, mb: var(--space-m))
        Heading h2 (kh-quick-actions__title)
          "Acciones rápidas"
          Font: var(--heading-font-family), 1.875rem, 700
        Text span (kh-quick-actions__subtitle)
          "¿Qué te gustaría hacer?"
          Font: var(--body-font-family), 1rem, 400, color: var(--kh-outline)

      Div (grid, cols: 4, gap: var(--space-s))
        ↳ Repeat 4x: Div (kh-action-card)
          bg: var(--kh-surface-container-lowest), p: 1.5rem, radius: var(--radius-l)
          border: 2px solid transparent
          shadow: card rest
          hover → border: rgba(19,82,167,0.2), transform: none
          Div (kh-action-card__icon)
            w: 3.5rem, h: 3.5rem, radius: var(--radius-m)
            flex, center
            ↳ Card 1: bg: #eff6ff, icon: home (color: var(--primary))
            ↳ Card 2: bg: #f0fdf4, icon: edit_note (color: var(--secondary))
            ↳ Card 3: bg: #faf5ff, icon: palette (color: #6d28d9)
            ↳ Card 4: bg: #fff7ed, icon: contact_page (color: #ea580c)
          Heading h4 (kh-action-card__title)
            Font: var(--heading-font-family), 1.125rem, 700
          Text p (kh-action-card__hint)
            Font: var(--body-font-family), 0.875rem, 400, color: var(--kh-outline)

    ── RECENT + GUIDE ROW ───────────────────────────
    Div (grid, cols: 2, gap: var(--space-l))

      ── Recent Changes ──
      Div (kh-recent)
        Heading h3 (kh-recent__title)
          "Cambios recientes"
          Font: var(--heading-font-family), 1.5rem, 700
        Div (kh-recent__list)
          bg: var(--kh-surface-container-lowest), radius: var(--kh-radius-2xl), p: 1rem, shadow: card rest
          ↳ Repeat 3x: Div (kh-recent__item)
            flex, items: center, gap: 1rem
            p: 1rem, radius: var(--radius-m)
            hover → bg: #f8fafc
            Div (kh-recent__item-icon)
              w: 3rem, h: 3rem, radius: 50%, flex, center
              ↳ Item 1: bg: #dbeafe, icon: history (color: var(--primary))
              ↳ Item 2: bg: #dcfce7, icon: image (color: var(--secondary))
              ↳ Item 3: bg: #ffedd5, icon: person_add (color: #ea580c)
            Div (flex-1)
              Text p (kh-recent__item-title)
                Font: var(--body-font-family), 1.125rem, 600
              Text p (kh-recent__item-time)
                Font: var(--body-font-family), 0.875rem, 400, color: var(--kh-outline)

      ── Getting Started ──
      Div (kh-guide)
        Heading h3 (kh-guide__title)
          "Cómo empezar"
          Font: var(--heading-font-family), 1.5rem, 700
        Div (space-y: 1rem)
          ↳ Repeat 3x: Div (kh-guide__step)
            flex, items: start, gap: 1.25rem
            p: 1.5rem, bg: var(--kh-surface-container-low), radius: var(--radius-l)
            Span (kh-guide__step-num)
              w: 2.5rem, h: 2.5rem, bg: var(--primary), color: #fff
              radius: 50%, flex, center
              Font: var(--body-font-family), 1.125rem, 700
            Div
              Heading h4 (kh-guide__step-title)
                Font: var(--heading-font-family), 1.125rem, 700
              Text p (kh-guide__step-text)
                Font: var(--body-font-family), 1rem, 400, color: var(--kh-on-surface-variant)
```

### Dashboard Home — Responsive (≤1024px)

```
- Hide sidebar, show bottom nav
- Main: ml: 0, px: var(--space-s), pt: 5rem, pb: 8rem (above bottom nav)
- Hero title: 2.5rem
- CTA + Tip row → single column (cols: 1)
- Quick actions grid → cols: 2
- Recent + Guide row → single column (cols: 1)
```

---

## 4. My Pages — Element Tree

```
Section (kh-main)
  Div (max-width: 1200px, mx: auto)

    ── HEADER ───────────────────────────────────────
    Div (kh-pages, mb: var(--space-l))
      Heading h1 (kh-pages__title)
        "Mis Páginas"
        Font: var(--heading-font-family), 3rem, 800, color: var(--primary)
      Text p (kh-pages__subtitle)
        "Haz clic en una página para empezar a editarla."
        Font: var(--body-font-family), 1.25rem, 400, color: var(--kh-on-surface-variant)

    ── PAGE CARDS GRID ──────────────────────────────
    Div (kh-pages__grid, grid, cols: 2, gap: var(--space-m))

      ↳ Repeat per page: Div (kh-page-card)
        bg: var(--kh-surface-container-lowest), radius: var(--kh-radius-2xl), p: 2rem
        shadow: card rest
        border: 1px solid transparent
        hover → shadow: card hover, border: rgba(216,226,255,0.6)
        transition: all 0.3s

        Div (kh-page-card__image)
          w: 100%, h: 12rem, radius: var(--radius-l)
          overflow: hidden, mb: 1.5rem
          Img (object-fit: cover, w: 100%, h: 100%)
            hover → transform: scale(1.1), transition: 0.5s
          Div (kh-page-card__badge) — optional
            position: absolute, top: 1rem, left: 1rem
            bg: var(--primary), color: #fff
            text: 0.75rem, 700, uppercase, tracking: 0.1em
            px: 0.75rem, py: 0.25rem, radius: 9999px

        Div (flex, justify: between, items: start, mb: 1rem)
          Heading h2 (kh-page-card__title)
            Font: var(--heading-font-family), 1.875rem, 700
          Link a (kh-page-card__view)
            flex, items: center, gap: 0.25rem
            color: var(--primary), font-weight: 700
            Icon: open_in_new (0.875rem)
            Text: "Ver en línea"

        Text p (kh-page-card__meta)
          Font: var(--body-font-family), 1rem, 500, color: var(--kh-on-surface-variant)
          mb: 2rem
          "Último cambio: hace 2 horas"

        Button (kh-page-card__btn)
          w: 100%, h: 4rem
          bg: var(--kh-gradient-cta), color: #fff
          radius: var(--radius-l), font-weight: 700, font-size: 1.25rem
          flex, center, gap: 0.75rem
          shadow: 0 8px 16px rgba(19,82,167,0.2)
          Icon: edit (FILL 1)
          Text: "Editar esta página"

    ── ADD PAGE ─────────────────────────────────────
    Div (flex, center, mt: var(--space-xl))
      Button (kh-page-card__add)
        h: 4rem, px: var(--space-l)
        border: 4px dashed rgba(19,82,167,0.2)
        bg: transparent, color: var(--primary)
        radius: var(--radius-l), font-weight: 700, font-size: 1.25rem
        flex, center, gap: 0.75rem
        hover → bg: var(--kh-surface-container-lowest), border-color: var(--primary)
        Icon: add (1.875rem)
        Text: "+ Agregar nueva página"
```

### My Pages — Mobile (≤1024px)

```
- Grid → single column (cols: 1)
- Cards: horizontal layout (flex row)
  Left: title + description + Edit button
  Right: thumbnail (5rem x 5rem, radius: var(--radius-m))
- Page title: 2.5rem
- Card padding: 1.5rem
- Image height: auto
- "Add Page" → green pill button (var(--kh-secondary-container))
```

---

## 5. Content (CPT List) — Element Tree

> This maps to "News & Updates" in the Stitch designs.
> In the real app, the plugin renders this via `cfd_render_cpt_list()`.
> The Bricks template wraps it; the CSS classes apply to plugin output.

```
Section (kh-main)
  Div (max-width: 1000px, mx: auto)

    ── HEADER ───────────────────────────────────────
    Div (kh-content__header, flex, justify: between, items: end, mb: var(--space-l))
      Div
        Heading h1 (kh-content__title)
          Font: var(--heading-font-family), 3rem, 800, color: var(--primary)
        Text p
          Font: var(--body-font-family), 1.25rem, 400, color: var(--kh-on-surface-variant)
      Button (kh-content__add)
        flex, items: center, gap: 0.75rem
        px: 2rem, py: 1.25rem
        bg: var(--kh-gradient-cta), color: #fff
        radius: var(--radius-m), font-weight: 700, font-size: 1.25rem
        shadow: 0 12px 24px rgba(19,82,167,0.2)
        Icon: add_circle (FILL 1, 1.875rem)
        Text: "Agregar nuevo"

    ── CONTENT ITEMS ────────────────────────────────
    Div (kh-content__list, space-y: var(--space-s))

      ↳ Repeat per item: Div (kh-content-item)
        bg: var(--kh-surface-container-lowest), radius: var(--radius-m), p: 2rem
        shadow: card rest
        flex, items: center, justify: between
        hover → bg: var(--kh-surface-container-high)
        transition: all 0.2s

        Div (flex, items: center, gap: var(--space-m))
          Div (kh-content-item__date)
            flex-col, center
            bg: var(--kh-surface-container-highest), w: 6rem, h: 6rem, radius: var(--radius-m)
            color: var(--primary)
            border: 2px solid #d8e2ff
            Span (kh-content-item__date-month)
              font-size: 0.875rem, 700, uppercase, tracking: 0.1em
            Span (kh-content-item__date-day)
              font-size: 1.875rem, 900

          Div
            Heading h3 (kh-content-item__title)
              Font: var(--heading-font-family), 1.5rem, 700
            Text p (kh-content-item__meta)
              Font: var(--body-font-family), 1rem, 500, color: var(--kh-on-surface-variant)

        Div (kh-content-item__actions, flex, items: center, gap: 1rem)
          Button (kh-content-item__edit)
            px: 1.5rem, py: 1rem
            bg: var(--kh-surface-container-highest), color: var(--primary)
            radius: var(--radius-m), font-weight: 700
            hover → bg: var(--primary), color: #fff
            flex, center, gap: 0.5rem
            Icon: edit
            Text: "Editar"
          Button (kh-content-item__delete)
            p: 1rem, color: var(--danger)
            radius: var(--radius-m)
            hover → bg: var(--kh-error-container)
            Icon: delete

    ── HELP BANNER ──────────────────────────────────
    Div (kh-help-banner, mt: var(--space-xl))
      bg: var(--kh-primary-container), color: var(--kh-on-primary-container)
      p: 2.5rem, radius: var(--radius-m)
      overflow: hidden, position: relative

      Div (flex, items: center, gap: var(--space-m), z: 10)
        Div (w: 5rem, h: 5rem, bg: rgba(236,240,255,0.1), radius: 50%, flex, center)
          Icon: auto_awesome (FILL 1, 3rem)
        Div
          Heading h4
            Font: var(--heading-font-family), 1.875rem, 700, mb: 0.5rem
            "¿Necesitas ayuda con tu contenido?"
          Text p
            Font: var(--body-font-family), 1.25rem, 400, opacity: 0.9
            "Nuestro equipo puede ayudarte."

      Div (decorative circle, absolute, -right: 2.5rem, -bottom: 2.5rem)
        w: 16rem, h: 16rem, bg: rgba(255,255,255,0.05), radius: 50%
```

### Content — Mobile (≤1024px)

```
- Items stack vertically
- Date badge: smaller (w: 4rem, h: 4rem)
- Actions: stack below content (flex-col)
- Edit button: full width
- Delete: text button below
- Help banner: p: 1.5rem, stack vertical
```

---

## 6. Editor (Edit Page / Edit CPT) — Element Tree

> The ACF form itself is rendered by the plugin shortcode.
> Bricks wraps the page chrome; CSS enhances the plugin output.

```
Section (kh-main)
  Div (max-width: 960px, mx: auto)

    ── BACK LINK ────────────────────────────────────
    Link a (kh-editor__back)
      flex, items: center, gap: 0.5rem
      color: var(--primary), font-weight: 600, mb: 1rem
      Icon: arrow_back
      Text: "Volver a mis páginas"

    ── HEADER ───────────────────────────────────────
    Heading h1 (kh-editor__title)
      Font: var(--heading-font-family), 3rem, 800, tracking: -0.02em
      mb: 1rem

    Text p (kh-editor__subtitle)
      Font: var(--body-font-family), 1.25rem, 400, color: var(--kh-on-surface-variant)
      mb: 2rem, max-w: 40rem

    ── SUCCESS MESSAGE ──────────────────────────────
    Div (kh-editor__success) — shown when ?updated=true
      bg: #a3f69c40, radius: var(--radius-l)
      flex, items: center, gap: 1rem, p: 1.5rem
      Icon: check_circle (FILL 1, color: var(--kh-on-secondary-container), 1.875rem)
      Text: "¡Tus cambios han sido guardados!"
        Font: var(--body-font-family), 1.125rem, 600, color: var(--kh-on-secondary-container)

    ── FORM SECTIONS ────────────────────────────────
    Div (space-y: var(--space-l))

      ↳ Each field group renders as: Div (kh-editor__section)
        bg: var(--kh-surface-container-lowest), p: 2rem, radius: var(--kh-radius-xl)
        shadow: card rest

        Div (flex, items: start, gap: 1.5rem)
          Div (kh-editor__section-icon)
            w: 3rem, h: 3rem, radius: 50%
            bg: var(--kh-surface-container-highest), flex, center, shrink: 0
            Icon (color: var(--primary)) — varies per field type:
              title → "title"
              textarea → "edit_note"
              image → "image"
              url → "link"
              select → "tune"
          Div (flex-1)
            Label (kh-editor__section-label)
              Font: var(--heading-font-family), 1.5rem, 700, mb: 0.5rem
            Text p (kh-editor__section-hint)
              Font: var(--body-font-family), 1rem, 500, color: var(--kh-outline), mb: 1.5rem
            ↳ ACF field renders here

    ── ACTION BUTTONS ───────────────────────────────
    Div (kh-editor__actions, flex, items: center, gap: 1.5rem, pt: 2rem)

      Button (kh-editor__save)
        px: 3rem, py: 1.5rem
        bg: var(--kh-gradient-cta), color: #fff
        radius: var(--radius-l), font-weight: 700, font-size: 1.5rem
        flex, center, gap: 0.75rem
        shadow: 0 12px 24px rgba(19,82,167,0.2)
        Icon: save (1.875rem)
        Text: "Guardar mis cambios"

      Button (kh-editor__preview)
        px: 3rem, py: 1.5rem
        bg: var(--kh-surface-container-high), color: var(--primary)
        radius: var(--radius-l), font-weight: 700, font-size: 1.5rem
        flex, center, gap: 0.75rem
        Icon: visibility (1.875rem)
        Text: "Vista previa"

    ── CONCIERGE TIP ────────────────────────────────
    Div (kh-editor__tip, mt: var(--space-l))
      bg: var(--kh-tertiary-fixed), p: 2rem, radius: var(--kh-radius-xl)
      flex, items: center, gap: 1.5rem
      Div (w: 4rem, h: 4rem, bg: #fff, radius: 50%, flex, center, shadow: card rest)
        Icon: lightbulb (FILL 1, color: var(--kh-tertiary), 2.5rem)
      Div
        Heading h3
          Font: var(--heading-font-family), 1.25rem, 700, color: var(--kh-on-tertiary-fixed)
          "Consejo de tu Asistente Digital"
        Text p
          Font: var(--body-font-family), 1.125rem, 400, color: var(--kh-on-tertiary-fixed-variant)
```

### Editor — Mobile (≤1024px)

```
- Title: 2.5rem
- Section cards: p: 1.5rem
- Icon + label: stack vertical (flex-col)
- Buttons: full width, stack vertical (flex-col)
- Save button first, Preview below
- Add "Discard Changes" text link (color: var(--danger)) below buttons
- Floating help button: fixed bottom-right, w: 4rem, h: 4rem
  bg: #fff, shadow, radius: 50%, icon: help (FILL 1)
```

---

## 7. Mobile Drawer Menu — Element Tree

```
Div (kh-drawer__overlay, fixed inset, z: 40)
  bg: rgba(7,30,39,0.4), backdrop-filter: blur(8px)
  click → close drawer

Aside (kh-drawer__panel, fixed left, z: 50)
  w: 20rem, h: 100%, radius-r: var(--kh-radius-2xl)
  bg: var(--kh-surface)
  shadow: 12px 0 24px rgba(7,30,39,0.06)
  flex-col, py: 2rem, px: 1rem

  ── USER SECTION ──
  Div (px: 1rem, mb: 2.5rem)
    Div (relative, mb: 1.5rem)
      Img (kh-drawer__avatar)
        w: 5rem, h: 5rem, radius: 50%
        border: 4px solid var(--kh-surface-container-highest), shadow: card rest
      Div (online dot, absolute -bottom: 0.25rem -right: 0.25rem)
        w: 1.5rem, h: 1.5rem, bg: var(--secondary)
        radius: 50%, border: 4px solid var(--kh-surface)

    Heading h2 (kh-drawer__name)
      Font: var(--heading-font-family), 1.5rem, 700, color: var(--primary)
      "Hola de nuevo, [Name]"

    Text p (kh-drawer__subtitle)
      Font: var(--body-font-family), 1rem, 400, color: var(--kh-on-surface-variant)
      "Tu información está segura."

    Div (kh-drawer__badge, mt: 1rem)
      inline-flex, items: center, gap: 0.5rem
      bg: var(--kh-surface-container-highest), px: 1rem, py: 0.375rem, radius: 9999px
      Icon: verified (FILL 1, color: var(--primary), 1.2rem)
      Text: "Miembro Premium"
        Font: var(--body-font-family), 0.875rem, 600, color: var(--primary)

  ── NAV LINKS ──
  Nav (kh-drawer__nav, flex-1, flex-col, gap: 0.75rem, py: 2rem, px: 1rem)
    ↳ Repeat: Link a (kh-drawer__link)
      flex, items: center, gap: 1rem
      p: 1.25rem, mx: 0.5rem, radius: var(--radius-l)
      font-family: var(--body-font-family), font-size: 1.25rem, font-weight: 500
      color: #5c727d
      transition: all 0.2s

    Link a (kh-drawer__link--active)
      bg: var(--kh-surface-container-highest), color: var(--primary), font-weight: 700

    Icon styles: font-size: 2rem
      Active icon: FILL 1

    Nav items (from designs):
      dashboard → "Mi Panel"
      auto_stories → "Mis Páginas"
      newspaper → "Contenido"
      contact_support → "Ayuda"
      settings → "Ajustes"

  ── LOGOUT ──
  Div (mt: auto, pt: 1.5rem, px: 1.5rem)
    border-top: 1px solid rgba(196,197,217,0.1)
    Button (kh-drawer__logout)
      w: 100%, flex, center, gap: 0.75rem
      py: 1.25rem, bg: var(--kh-surface-container-low), color: var(--danger)
      radius: var(--radius-l), font-weight: 700, font-size: 1.25rem
      hover → bg: rgba(255,218,214,0.4)
      Icon: logout
      Text: "Cerrar sesión"
```

---

## 8. CSS for Plugin Shortcode Output (ACF Forms)

These styles override the default ACF form rendering inside the plugin's shortcodes. Add to Perfmatters or the plugin's `dashboard.css`.

```css
/* ── ACF Form — Kindred Hearth overrides ─────────── */
.cd-acf-form .acf-field {
  padding: 1.5rem 0;
  border-top: none;
  border-bottom: none;
}

.cd-acf-form .acf-label label {
  font-family: var(--heading-font-family);
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--kh-on-surface);
  margin-bottom: 0.5rem;
}

.cd-acf-form .acf-label .description {
  font-family: var(--body-font-family);
  font-size: 0.9375rem;
  color: var(--kh-on-surface-variant);
  margin-top: 0.25rem;
}

.cd-acf-form .acf-input input[type="text"],
.cd-acf-form .acf-input input[type="email"],
.cd-acf-form .acf-input input[type="url"],
.cd-acf-form .acf-input input[type="number"],
.cd-acf-form .acf-input textarea,
.cd-acf-form .acf-input select {
  background: var(--kh-surface-container-highest);
  border: none;
  border-radius: var(--radius-l);
  padding: 1.25rem 1.5rem;
  font-family: var(--body-font-family);
  font-size: 1.125rem;
  color: var(--kh-on-surface);
  transition: box-shadow 0.2s;
  min-height: 3.5rem;
}

.cd-acf-form .acf-input input:focus,
.cd-acf-form .acf-input textarea:focus,
.cd-acf-form .acf-input select:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(19, 82, 167, 0.2);
}

.cd-acf-form .acf-input textarea {
  border-radius: var(--radius-l);
  padding: 1.5rem;
  line-height: 1.6;
  resize: vertical;
}

/* ── Save button ─────────────────────────────────── */
.cd-save-btn {
  background: var(--kh-gradient-cta);
  color: #fff;
  border: none;
  border-radius: var(--radius-l);
  padding: 1.25rem 3rem;
  font-family: var(--body-font-family);
  font-size: 1.25rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(19, 82, 167, 0.2);
  transition: transform 0.15s;
  min-height: 3.5rem;
}

.cd-save-btn:hover {
  transform: scale(1.02);
}

.cd-save-btn:active {
  transform: scale(0.95);
}

/* ── Success message ─────────────────────────────── */
.cd-success {
  background: rgba(163, 246, 156, 0.25);
  border: none;
  border-radius: var(--radius-l);
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.cd-success span {
  font-family: var(--body-font-family);
  font-weight: 600;
  color: var(--kh-on-secondary-container);
}

/* ── Error message ───────────────────────────────── */
.cd-error {
  background: var(--kh-error-container);
  border: none;
  border-radius: var(--radius-l);
  padding: 1.25rem 1.5rem;
  font-family: var(--body-font-family);
  font-weight: 600;
  color: var(--kh-on-error-container);
}

/* ── Back link ───────────────────────────────────── */
.cd-back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--primary);
  font-family: var(--body-font-family);
  font-weight: 600;
  text-decoration: none;
  margin-bottom: 1rem;
}

.cd-back-link:hover {
  text-decoration: underline;
  text-underline-offset: 4px;
}

/* ── Editor wrapper ──────────────────────────────── */
.cd-editor {
  background: var(--kh-surface-container-lowest);
  border: none;
  border-radius: var(--kh-radius-xl);
  padding: 2.5rem;
  box-shadow: 0 1px 3px rgba(7, 30, 39, 0.04);
}

.cd-editor__title {
  font-family: var(--heading-font-family);
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--kh-on-surface);
}

.cd-editor__sub {
  font-family: var(--body-font-family);
  font-size: 1.125rem;
  color: var(--kh-on-surface-variant);
  margin-bottom: 1.5rem;
}

/* ── Preview link ────────────────────────────────── */
.cd-preview-link {
  color: var(--primary);
  font-family: var(--body-font-family);
  font-weight: 700;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-s);
  transition: background 0.2s;
}

.cd-preview-link:hover {
  background: rgba(19, 82, 167, 0.05);
}

/* ── Add button ──────────────────────────────────── */
.cd-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 2rem;
  background: var(--kh-gradient-cta);
  color: #fff;
  border-radius: var(--radius-m);
  font-family: var(--body-font-family);
  font-weight: 700;
  font-size: 1.125rem;
  text-decoration: none;
  box-shadow: 0 8px 20px rgba(19, 82, 167, 0.2);
  transition: transform 0.15s;
}

.cd-add-btn:hover {
  transform: scale(1.02);
  color: #fff;
}

/* ── CPT card grid ───────────────────────────────── */
.cd-cpt-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-s);
}

.cd-cpt-card {
  background: var(--kh-surface-container-lowest);
  border-radius: var(--radius-m);
  padding: 2rem;
  box-shadow: 0 1px 3px rgba(7, 30, 39, 0.04);
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-decoration: none;
  color: var(--kh-on-surface);
  transition: background 0.2s;
}

.cd-cpt-card:hover {
  background: var(--kh-surface-container-high);
}

.cd-cpt-card__title {
  font-family: var(--heading-font-family);
  font-size: 1.25rem;
  font-weight: 700;
}

.cd-cpt-card__meta {
  font-family: var(--body-font-family);
  font-size: 0.9375rem;
  color: var(--kh-on-surface-variant);
}

/* ── Toolbar ─────────────────────────────────────── */
.cd-cpt-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: end;
  padding: 1.5rem;
  background: var(--kh-surface-container-low);
  border-radius: var(--radius-l);
  margin-bottom: 1.5rem;
}

.cd-cpt-toolbar__select,
.cd-cpt-toolbar__search {
  background: var(--kh-surface-container-highest);
  border: none;
  border-radius: var(--radius-m);
  padding: 0.75rem 1rem;
  font-family: var(--body-font-family);
  font-size: 1rem;
  color: var(--kh-on-surface);
  min-height: 3rem;
}

.cd-cpt-toolbar__select:focus,
.cd-cpt-toolbar__search:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(19, 82, 167, 0.2);
}

.cd-cpt-toolbar__submit {
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: var(--radius-m);
  padding: 0.75rem 1.5rem;
  font-family: var(--body-font-family);
  font-weight: 600;
  cursor: pointer;
  min-height: 3rem;
}

/* ── Pagination ──────────────────────────────────── */
.cd-cpt-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  padding: 2rem 0;
  font-family: var(--body-font-family);
}

.cd-cpt-pagination__link {
  color: var(--primary);
  font-weight: 600;
  text-decoration: none;
  padding: 0.75rem 1.25rem;
  border-radius: var(--radius-m);
  transition: background 0.2s;
}

.cd-cpt-pagination__link:hover {
  background: rgba(19, 82, 167, 0.05);
}

.cd-cpt-pagination__link--disabled {
  color: var(--kh-outline-variant);
  pointer-events: none;
}

.cd-cpt-pagination__current {
  color: var(--kh-on-surface-variant);
  font-weight: 500;
}

/* ── View hint ───────────────────────────────────── */
.cd-view-hint {
  font-family: var(--body-font-family);
  font-size: 1rem;
  color: var(--kh-on-surface-variant);
  background: var(--kh-surface-container-low);
  border-radius: var(--radius-m);
  padding: 1rem 1.5rem;
  margin-bottom: 1.5rem;
}

/* ── Duplicate button ────────────────────────────── */
.cd-duplicate-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--kh-surface-container-highest);
  color: var(--primary);
  border-radius: var(--radius-s);
  font-family: var(--body-font-family);
  font-weight: 600;
  text-decoration: none;
  transition: background 0.2s;
}

.cd-duplicate-btn:hover {
  background: var(--kh-surface-container-high);
}

/* ── Delete UI ───────────────────────────────────── */
.cd-delete-link {
  color: var(--danger);
  font-family: var(--body-font-family);
  font-weight: 600;
  text-decoration: none;
}

.cd-delete-confirm {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
}

.cd-delete-confirm__text {
  color: var(--kh-on-error-container);
  font-weight: 600;
}

.cd-delete-confirm__yes {
  background: var(--danger);
  color: #fff;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-s);
  font-weight: 600;
  text-decoration: none;
}

.cd-delete-confirm__no {
  color: var(--kh-on-surface-variant);
  font-weight: 600;
  text-decoration: none;
}
```

---

## 9. Material Symbols Quick Reference

Icons used across the designs:

| Icon name | Where used | Fill? |
|-----------|-----------|-------|
| `dashboard` / `grid_view` | Sidebar, bottom nav | Active: FILL 1 |
| `description` / `auto_stories` | My Pages nav | Active: FILL 1 |
| `newspaper` | Content/News nav | Active: FILL 1 |
| `help` / `contact_support` | Help nav, floating btn | FILL 1 for floating |
| `check_circle` | Status badge, success | FILL 1 |
| `arrow_forward` | CTA buttons | No |
| `arrow_back` | Back links | No |
| `lightbulb` | Tip cards | FILL 1 |
| `home` | Quick action | No |
| `edit_note` | Quick action, edit CTA | No |
| `palette` | Quick action | No |
| `contact_page` | Quick action | No |
| `history` | Recent changes | No |
| `image` | Recent changes, photo field | No |
| `person_add` | Recent changes | No |
| `open_in_new` | View online links | No |
| `edit` | Edit buttons | FILL 1 |
| `add_circle` | Add buttons | FILL 1 |
| `add` | New page | No |
| `delete` | Delete buttons | No |
| `save` | Save buttons | No |
| `visibility` | Preview buttons | No |
| `title` | Heading field icon | No |
| `cloud_upload` / `upload` | Photo upload | No |
| `photo_camera` | Photo replace overlay | No |
| `settings` | Settings nav | No |
| `logout` | Logout button | No |
| `shield_with_heart` | Brand logo (mobile) | FILL 1 |
| `verified` | Premium badge | FILL 1 |
| `auto_awesome` | Help banner | FILL 1 |
| `filter` *(dashicon)* | Filter toggle | — |

### Enqueue in Bricks (head)

```html
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
```

### Usage in Bricks

Add a **Div** element with tag `<span>`, then:
- Class: `material-symbols-outlined`
- Content: the icon name (e.g., `dashboard`)
- Custom CSS on the span for `font-variation-settings`:

```css
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  font-size: 1.5rem;
  vertical-align: middle;
}
```

For filled icons, add a class `.kh-icon--filled`:
```css
.kh-icon--filled {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
```

---

## 10. Responsive Breakpoints (Bricks Builder)

| Bricks BP | Type | Value | Sidebar | Bottom Nav | Grid Cols |
|-----------|------|-------|---------|-----------|-----------|
| **XXL** | min-width | 1536px | Visible (18rem) | Hidden | 4 (actions), 2 (pages) |
| **XL** | Base | — | Visible (18rem) | Hidden | 4 (actions), 2 (pages) |
| **L** | max-width | 1024px | Hidden | Visible | 2 (actions), 1 (pages, content) |
| **M** | max-width | 768px | Hidden | Visible | 2 (actions) |
| **S** | max-width | 480px | Hidden | Visible | 1 (everything) |

### Main content margin

```css
/* XL Base: offset by sidebar */
.kh-main {
  margin-left: 18rem;
  padding-top: 7rem;
  padding-bottom: var(--space-l);
  padding-inline: var(--space-l);
  min-height: 100vh;
  background: var(--kh-surface);
}

/* L (≤1024px): hide sidebar, show bottom nav */
@media (max-width: 1024px) {
  .kh-main {
    margin-left: 0;
    padding-top: 5rem;
    padding-bottom: 8rem; /* above bottom nav */
    padding-inline: var(--space-s);
  }
}
```
