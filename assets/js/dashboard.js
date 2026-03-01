/* ── CPT card "Ver ↗" link handler ── */
document.addEventListener('click', function (e) {
    var viewBtn = e.target.closest('.cd-cpt-card__view[data-href]');
    if (viewBtn) {
        e.preventDefault();
        e.stopPropagation();
        window.open(viewBtn.getAttribute('data-href'), '_blank');
    }
});

/* ── Auto-grow textareas (especially rows="1") ──────────
   Makes textarea fields expand vertically as the user types
   instead of showing a scrollbar or clipping. Runs multiple
   times to catch ACF's delayed field rendering. */
(function () {
    function autoGrow(el) {
        // Remove any inline height ACF may have set
        el.style.removeProperty('height');
        // Reset to auto so scrollHeight is accurate
        el.style.height = 'auto';
        // Set to the content's actual height
        var newHeight = el.scrollHeight;
        if (newHeight > 0) {
            el.style.height = newHeight + 'px';
        }
    }

    function initAutoGrow() {
        var textareas = document.querySelectorAll('.cd-acf-form textarea');
        textareas.forEach(function (ta) {
            autoGrow(ta);
            // Only bind once
            if (!ta.dataset.cdAutoGrow) {
                ta.dataset.cdAutoGrow = '1';
                ta.addEventListener('input', function () {
                    autoGrow(this);
                });
            }
        });
    }

    // Run at multiple points to guarantee we catch the fields:
    // 1. DOMContentLoaded
    document.addEventListener('DOMContentLoaded', initAutoGrow);
    // 2. window.load (after all assets including ACF scripts)
    window.addEventListener('load', function () {
        initAutoGrow();
        // 3. Small delay after load — ACF sometimes renders
        //    field values slightly after window.load
        setTimeout(initAutoGrow, 300);
        setTimeout(initAutoGrow, 800);
    });

    // 4. ACF's own ready event (if ACF JS is available)
    function waitForAcf() {
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', initAutoGrow);
            acf.addAction('append', function (el) {
                var container = el.jquery ? el[0] : el;
                var newTas = container.querySelectorAll('textarea');
                newTas.forEach(function (ta) {
                    autoGrow(ta);
                    if (!ta.dataset.cdAutoGrow) {
                        ta.dataset.cdAutoGrow = '1';
                        ta.addEventListener('input', function () {
                            autoGrow(this);
                        });
                    }
                });
            });
        } else {
            // ACF JS not loaded yet — retry once
            setTimeout(waitForAcf, 500);
        }
    }
    waitForAcf();
})();

/* ── Delete confirmation toggle ────────────────────────
   Shows inline "¿Segura? Sí / Cancelar" when clicking
   "Eliminar". No browser confirm() dialogs. */
(function () {
    var trigger = document.getElementById('cd-delete-trigger');
    var confirm = document.getElementById('cd-delete-confirm');
    var cancel = document.getElementById('cd-delete-cancel');

    if (trigger && confirm && cancel) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            trigger.style.display = 'none';
            confirm.style.display = 'inline-flex';
        });

        cancel.addEventListener('click', function (e) {
            e.preventDefault();
            confirm.style.display = 'none';
            trigger.style.display = 'inline';
        });
    }
})();

/* ── Success modal ─────────────────────────────────────
   Converts inline .cd-success messages into a centered
   modal with a backdrop overlay. User can dismiss via
   "Aceptar" button, ✕, clicking outside, or it auto-
   dismisses after 5 seconds. */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var successEls = document.querySelectorAll('.cd-success');
        if (!successEls.length) return;

        // Build overlay + modal
        var overlay = document.createElement('div');
        overlay.className = 'cd-modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'cd-modal';

        // SVG checkmark icon (no emoji)
        var icon = document.createElement('div');
        icon.className = 'cd-modal__icon';
        icon.innerHTML = '<svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="28" cy="28" r="28" fill="#2D5A3D"/><path d="M17 28.5L24.5 36L39 21" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        modal.appendChild(icon);

        // Collect all success messages
        successEls.forEach(function (el) {
            var msg = document.createElement('div');
            msg.className = 'cd-modal__message';

            // Extract text and link separately (skip emoji prefixes)
            var textSpan = el.querySelector('span');
            var link = el.querySelector('a');

            if (textSpan) {
                var p = document.createElement('p');
                p.className = 'cd-modal__text';
                // Strip leading emoji characters from the span text
                p.textContent = textSpan.textContent.replace(/^[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE00}-\u{FE0F}\u{200D}\u{20E3}\u{E0020}-\u{E007F}✅🗑️\s]+/u, '').trim();
                msg.appendChild(p);
            }
            if (link) {
                var a = document.createElement('a');
                a.href = link.href;
                a.target = '_blank';
                a.className = 'cd-modal__link';
                a.textContent = link.textContent;
                msg.appendChild(a);
            }

            modal.appendChild(msg);
            el.remove();
        });

        // Accept button
        var acceptBtn = document.createElement('button');
        acceptBtn.className = 'cd-modal__accept';
        acceptBtn.textContent = 'Aceptar';
        acceptBtn.addEventListener('click', dismiss);
        modal.appendChild(acceptBtn);

        // Close button (top-right ✕)
        var closeBtn = document.createElement('button');
        closeBtn.className = 'cd-modal__close';
        closeBtn.textContent = '✕';
        closeBtn.setAttribute('aria-label', 'Cerrar');
        closeBtn.addEventListener('click', dismiss);
        modal.appendChild(closeBtn);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Animate in
        requestAnimationFrame(function () {
            overlay.classList.add('cd-modal-overlay--visible');
        });

        // Auto-dismiss after 5 seconds
        var autoTimer = setTimeout(function () {
            dismiss();
        }, 5000);

        // Close on overlay click (outside modal)
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) dismiss();
        });

        function dismiss() {
            clearTimeout(autoTimer);
            if (overlay.classList.contains('cd-modal-overlay--dismissed')) return;
            overlay.classList.remove('cd-modal-overlay--visible');
            overlay.classList.add('cd-modal-overlay--dismissed');
            setTimeout(function () {
                overlay.remove();
            }, 350);
        }

        // Clean URL params so refresh doesn't re-trigger
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            var changed = false;
            ['updated', 'trashed', 'created'].forEach(function (param) {
                if (url.searchParams.has(param)) {
                    url.searchParams.delete(param);
                    changed = true;
                }
            });
            if (changed) {
                window.history.replaceState({}, '', url.toString());
            }
        }
    });
})();

/* ── Auto-close filter accordion on mobile/tablet ──────
   The <details> has `open` in markup so desktop always shows
   content. On ≤780px we remove the attribute on page load so
   the accordion starts collapsed, saving vertical space.       */
(function () {
    if (window.innerWidth > 780) return;

    var toggle = document.querySelector('.cd-cpt-filter-toggle');
    if (toggle) {
        toggle.removeAttribute('open');
    }
})();