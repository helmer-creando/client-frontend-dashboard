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

/* ── Duplicate confirmation ─────────────────────────────
   Intercepts clicks on .cd-duplicate-btn and shows a
   native confirm() dialog before proceeding. */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cd-duplicate-btn');
    if (btn) {
        e.preventDefault();
        if (confirm('¿Crear una copia de este contenido?')) {
            window.location.href = btn.href;
        }
    }
});

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
        var previewUrl = null;
        var hasUpdatedMsg = false;

        successEls.forEach(function (el) {
            var msg = document.createElement('div');
            msg.className = 'cd-modal__message';

            var textSpan = el.querySelector('span');
            var link = el.querySelector('a');

            if (textSpan) {
                var p = document.createElement('p');
                p.className = 'cd-modal__text';
                
                var rawText = textSpan.textContent.toLowerCase();
                if (rawText.indexOf('actualizad') !== -1 || rawText.indexOf('guardad') !== -1 || rawText.indexOf('check_circle') !== -1) {
                    p.textContent = '¡Guardado con éxito!';
                    hasUpdatedMsg = true;
                } else {
                    p.textContent = textSpan.textContent.replace(/^[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE00}-\u{FE0F}\u{200D}\u{20E3}\u{E0020}-\u{E007F}✅🗑️\s]+/u, '').trim();
                }
                msg.appendChild(p);
            }
            if (link) {
                previewUrl = link.href;
            }

            modal.appendChild(msg);
            el.remove();
        });

        // Button Container
        var btnContainer = document.createElement('div');
        btnContainer.className = 'cd-modal__actions';

        // Ver página button (if URL exists)
        if (previewUrl) {
            var viewBtn = document.createElement('a');
            viewBtn.href = previewUrl;
            viewBtn.target = '_blank';
            viewBtn.className = 'cd-modal__btn cd-modal__btn--primary';
            viewBtn.textContent = 'Ver página';
            // Also dismiss modal when clicking "Ver página"
            viewBtn.addEventListener('click', function() {
                setTimeout(dismiss, 100);
            });
            btnContainer.appendChild(viewBtn);
        }

        // Accept button
        var acceptBtn = document.createElement('button');
        acceptBtn.className = 'cd-modal__btn cd-modal__btn--secondary';
        acceptBtn.textContent = 'Aceptar';
        acceptBtn.addEventListener('click', dismiss);
        btnContainer.appendChild(acceptBtn);

        modal.appendChild(btnContainer);

        // Close button (top-right ✕)
        var closeBtn = document.createElement('button');
        closeBtn.className = 'cd-modal__close';
        closeBtn.innerHTML = '&times;';
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
            ['updated', 'trashed', 'created', 'duplicated'].forEach(function (param) {
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

/* ── Mobile sidebar toggle ─────────────────────────────
   Creates hamburger toggle button + overlay for mobile.
   Works with Bricks sidebar that has .cfd-sidebar class.    */
(function () {
    var isMobile = function () {
        return window.innerWidth <= 991;
    };

    // Only initialize on mobile
    if (!isMobile()) return;

    var sidebar = document.querySelector('.cfd-sidebar');
    if (!sidebar) return;

    // Create toggle button
    var toggleBtn = document.createElement('button');
    toggleBtn.className = 'cfd-mobile-toggle';
    toggleBtn.setAttribute('aria-label', 'Abrir menú');
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.innerHTML = '<span class="cfd-mobile-toggle__icon">' +
        '<span class="cfd-mobile-toggle__line"></span>' +
        '<span class="cfd-mobile-toggle__line"></span>' +
        '<span class="cfd-mobile-toggle__line"></span>' +
        '</span>';

    // Create overlay
    var overlay = document.createElement('div');
    overlay.className = 'cfd-sidebar-overlay';

    // Insert into DOM
    document.body.appendChild(toggleBtn);
    document.body.appendChild(overlay);

    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        toggleBtn.classList.add('is-active');
        toggleBtn.setAttribute('aria-expanded', 'true');
        toggleBtn.setAttribute('aria-label', 'Cerrar menú');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        toggleBtn.classList.remove('is-active');
        toggleBtn.setAttribute('aria-expanded', 'false');
        toggleBtn.setAttribute('aria-label', 'Abrir menú');
        document.body.style.overflow = '';
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    // Event listeners
    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });

    // Close sidebar when clicking a nav link (for SPA-like feel)
    sidebar.addEventListener('click', function (e) {
        var link = e.target.closest('.cfd-sidebar-nav__link');
        if (link) {
            // Small delay to let the click register
            setTimeout(closeSidebar, 150);
        }
    });

    // Handle resize — close sidebar if window becomes desktop
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!isMobile() && sidebar.classList.contains('is-open')) {
                closeSidebar();
            }
        }, 150);
    });
})();

/* ── Form submit loading state ─────────────────────────
   Adds spinner + "Guardando..." text to submit button
   when ACF form is submitted. Prevents double-clicks.       */
(function () {
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('.cd-acf-form');
        if (!form) return;

        var btn = form.querySelector('input[type="submit"], button[type="submit"]');
        if (!btn || btn.classList.contains('cd-btn--loading')) return;

        // Disable and add loading state
        btn.disabled = true;
        btn.classList.add('cd-btn--loading');

        if (btn.tagName === 'INPUT') {
            // Store original value
            btn.dataset.originalValue = btn.value;
            btn.value = 'Guardando...';
        } else {
            // For button elements, we can add the spinner
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="cd-spinner"></span> Guardando...';
        }
    });

    // If form submission fails (client-side validation), reset button
    // ACF triggers 'acf/validate_field' and can prevent submission
    if (typeof acf !== 'undefined') {
        acf.addAction('validation_failure', function () {
            var btns = document.querySelectorAll('.cd-btn--loading');
            btns.forEach(function (btn) {
                btn.disabled = false;
                btn.classList.remove('cd-btn--loading');
                if (btn.tagName === 'INPUT' && btn.dataset.originalValue) {
                    btn.value = btn.dataset.originalValue;
                } else if (btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                }
            });
        });
    }
})();