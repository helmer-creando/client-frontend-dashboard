# Sidebar Nav — Bricks Query Loop Guide

## Two Approaches

| Approach | Pros | Cons |
|---|---|---|
| **Query Loop** (recommended) | Style everything visually in Bricks, no plugin update for style changes | Slightly more setup in Bricks |
| **Shortcode** `[cfd_sidebar_nav]` | Drop-in, one element | Styles are locked in `dashboard.css` |

---

## Query Loop Setup (Recommended)

### Step 1: Create a Div for the nav items

1. Inside your **Sidebar** div, add a **Div** element
2. Name it "Nav Items"

### Step 2: Enable Query Loop

1. Select the "Nav Items" div
2. Click the ♾️ **Query Loop** icon in the toolbar
3. In the Query control panel:
   - **Type**: select **CFD Sidebar Nav** from the dropdown
   - That's it — no other settings needed!

### Step 3: Design a single nav item (inside the loop)

Inside the "Nav Items" div (which is now a loop), add these child elements:

#### 3a. Link wrapper
1. Add a **Div** element, change tag to `<a>` 
2. Link → Dynamic Data → ⚡ → **CFD Nav: URL**
3. Set class: `cfd-nav-link`
4. Style it:
   - Display: `Flex`, Align-items: `Center`, Gap: `var(--space-xs)`
   - Padding: `var(--space-xs) var(--space-s)`
   - Border-radius: `var(--radius-s)`
   - Color: `rgba(255,255,255,0.75)`
   - Hover → Background: `rgba(255,255,255,0.1)`, Color: `#fff`

#### 3b. Dashicon (inside the link)
1. Add a **Div** element, tag `<span>`
2. In **Attributes** → add attribute: `class` = ⚡ **CFD Nav: Dashicon class**
3. Width: `20px`, Height: `20px`

#### 3c. Label (inside the link)
1. Add a **Basic Text** element
2. Content → ⚡ → **CFD Nav: Label**
3. Font-size: `var(--text-s)`

### Step 4: Active state styling

1. On the **link wrapper** (`<a>` div), go to **Attributes**
2. Add another class attribute → ⚡ **CFD Nav: Active CSS class**
   - This outputs `is-active` when the item matches the current view
3. In Bricks' Style panel, add a CSS rule for `.is-active`:
   - Background: `rgba(255,255,255,0.15)`
   - Color: `#fff`
   - Font-weight: `600`

> [!TIP]
> The Query Loop auto-populates: Inicio, Páginas (if editable pages exist), and every manageable CPT. When you add/remove CPTs from plugin settings, the sidebar updates automatically.

---

## Dynamic Data Tags Reference

| Tag | Output | Example |
|---|---|---|
| `{cfd_nav_label}` | Display name | "Retreats", "Inicio" |
| `{cfd_nav_url}` | Full URL | `/mi-espacio/?manage=retreats` |
| `{cfd_nav_icon}` | Dashicon CSS class | `dashicons dashicons-calendar` |
| `{cfd_nav_active_class}` | Active CSS class | `is-active` or empty |
| `{cfd_logout_url}` | Logout URL with nonce | (use for logout link) |

---

## Responsive Behavior

Same as before — follow the sidebar guide breakpoints:

| Breakpoint | Sidebar |
|---|---|
| **XL/XXL** | Sticky 260px sidebar |
| **L** (≤991px) | Fixed overlay + hamburger toggle |
| **M** (≤767px) | Tighter padding, 240px |
| **S** (≤479px) | Full-width overlay |

---

## Creating the Beta Release

1. **Commit** all changes:
   ```
   git add -A && git commit -m "feat: v3.0 query loop sidebar nav, toast notifications, Z-A sort"
   ```
2. **Tag** the release:
   ```
   git tag v3.0.0-beta1
   ```
3. **Push** tag + code:
   ```
   git push origin main --tags
   ```
4. Go to **GitHub → Releases → Draft a new release**
   - Tag: `v3.0.0-beta1`
   - Title: `v3.0.0-beta1 — Dashboard UX Improvements`
   - Check **"Set as a pre-release"**
   - Publish
5. In WordPress → **Plugins → Check for updates** → the beta will appear!
