/* ── CPT card "Ver ↗" link handler ── */
    document.addEventListener('click', function(e) {
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
    (function() {
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
            textareas.forEach(function(ta) {
                autoGrow(ta);
                // Only bind once
                if (!ta.dataset.cdAutoGrow) {
                    ta.dataset.cdAutoGrow = '1';
                    ta.addEventListener('input', function() {
                        autoGrow(this);
                    });
                }
            });
        }

        // Run at multiple points to guarantee we catch the fields:
        // 1. DOMContentLoaded
        document.addEventListener('DOMContentLoaded', initAutoGrow);
        // 2. window.load (after all assets including ACF scripts)
        window.addEventListener('load', function() {
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
                acf.addAction('append', function(el) {
                    var container = el.jquery ? el[0] : el;
                    var newTas = container.querySelectorAll('textarea');
                    newTas.forEach(function(ta) {
                        autoGrow(ta);
                        if (!ta.dataset.cdAutoGrow) {
                            ta.dataset.cdAutoGrow = '1';
                            ta.addEventListener('input', function() {
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
    (function() {
        var trigger = document.getElementById('cd-delete-trigger');
        var confirm = document.getElementById('cd-delete-confirm');
        var cancel  = document.getElementById('cd-delete-cancel');

        if (trigger && confirm && cancel) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                trigger.style.display = 'none';
                confirm.style.display = 'inline-flex';
            });

            cancel.addEventListener('click', function(e) {
                e.preventDefault();
                confirm.style.display = 'none';
                trigger.style.display = 'inline';
            });
        }
    })();