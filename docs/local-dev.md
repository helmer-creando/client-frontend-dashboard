# Local development setup

Smoke-test every change against a Local by Flywheel site before pushing to
GitHub. Auto-updater pushes releases to client sites immediately on tag,
so regressions are expensive to roll back live.

## One-time symlink (this machine)

The Local site lives at `/Volumes/Ikigai/Local Dev Sites/` (single-site
folder, not a multi-site parent). Symlink the repo into its plugins dir
so edits in this working tree show up on the site without any sync step:

```bash
ln -s "/Volumes/Ikigai/#HelpingOthers/AutentiWeb/dev/client-frontend-dashboard" \
      "/Volumes/Ikigai/Local Dev Sites/app/public/wp-content/plugins/client-frontend-dashboard"
```

Verify:

```bash
ls -la "/Volumes/Ikigai/Local Dev Sites/app/public/wp-content/plugins/client-frontend-dashboard"
```

You should see a single line starting with `lrwxr-xr-x` pointing at the
repo. If a real directory is there instead (e.g. after a `.wpress`
re-import), `rm -rf` it and re-run the `ln -s` above.

## Per-session checklist

1. Start the site in Local (Local app → "Start site").
2. Open WP Admin → Plugins → confirm **Client Frontend Dashboard** is
   active. If it lost activation after the symlink swap, reactivate.
3. Visit the dashboard at `/mi-espacio/` (or whatever `dashboard_slug`
   is set to in `cfd_settings`).
4. Log in as the `editor` user (Site Editor role) in an incognito window
   to test from a non-admin perspective. The role-and-access guard
   blocks wp-admin for them.

## Smoke-test path before every release

Hit each of these on Local before tagging:

- **Cards list** for a manageable CPT — chips render, taxonomy facets
  work, pagination/filters, draft cards show the "Oculto" pill + tinted
  bg + reduced opacity.
- **Hide/Show toggle** (eye icon on cards) — page reloads with the
  status flipped.
- **Editor** — open a CPT entry. Header actions (Ocultar, Duplicar,
  Eliminar) should be 3 pill buttons at XL widths and collapse to a `⋯`
  menu at ≤1280px. Sticky save bar at the bottom — buttons centered,
  help text on its own line below.
- **Save as draft / publish** — "Guardar y ocultar" or "Guardar y
  publicar" depending on current status. Round trip the post once.
- **Trash → Papelera badge** — delete a post, return to the CPT list,
  click the `[🗑 N]` pill on the count row. Papelera view should load
  with the trashed entry, Restaurar + Eliminar buttons in pill style.
- **Restore + type-ELIMINAR modal** — Restaurar moves the post back to
  draft. Eliminar opens the destructive confirm modal; only enables
  after typing `ELIMINAR`.
- **Undo toast** — trash-delete from the listing, the toast appears
  with an 8-second window to Deshacer. Click it; post returns to draft.
- **Mobile** — resize to <480px. Cards stack readably; trashed cards
  stack info-above-actions; save bar buttons go to a single column.

## Cache busting

Bumping `CFD_VERSION` in `client-frontend-dashboard.php` busts the CSS
and JS query strings (`?ver=…`) automatically. After CSS changes that
don't include a version bump, hard-reload (`Cmd+Shift+R`) to bypass the
browser cache.

## Releasing

See the bottom of `PROJECT-BRIEF.md` for the release flow. Short form:

```bash
# 1. Bump version in client-frontend-dashboard.php (both `Version:`
#    header AND CFD_VERSION constant).
# 2. Commit.
git add -A
git commit -m "vX.X.X: short summary"

# 3. Push and tag.
git push origin main
git tag vX.X.X && git push origin vX.X.X

# 4. Create the GitHub release. Auto-updater on client sites picks it
#    up within ~12h, or immediately via Plugins → "Check again".
gh release create vX.X.X --title "vX.X.X — short summary" --notes "$(cat <<'EOF'
## What's new

- …
EOF
)"
```

Never push a release that hasn't been smoke-tested on Local first.
