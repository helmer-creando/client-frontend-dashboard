/**
 * ============================================================
 * ACF Fields — Mobile-First Frontend Enhancements
 * ============================================================
 *
 * Enhances ACF Pro field types rendered via acf_form() on the
 * frontend dashboard. Main features:
 *
 * 1. Color Picker — Replaces WordPress Iris with iro.js
 *    (touch-friendly wheel + slider)
 * 2. Date/Time Pickers — Enlarges jQuery UI touch targets
 * 3. Select2 — Adjusts dropdown for mobile usability
 *
 * Only runs inside .cd-acf-form on the frontend dashboard.
 * Does NOT affect wp-admin.
 * ============================================================
 */

(function () {
    'use strict';

    // ─── Guard: only run inside the dashboard ───────────────
    function hasDashboardForm() {
        return document.querySelector('.cd-acf-form') !== null;
    }

    // ═══════════════════════════════════════════════════════
    // 1. COLOR PICKER — Replace Iris with iro.js
    // ═══════════════════════════════════════════════════════

    var COLOR_PICKER_WIDTH = 260;
    var iroInstances = [];

    /**
     * Initialize iro.js color pickers for all ACF color picker fields.
     *
     * ACF renders color pickers as:
     *   .acf-color-picker > input[type="text"].acf-color-picker (the hidden value)
     *   jQuery initializes wp-color-picker on it, which creates .wp-picker-container
     *
     * We hide wp-picker-container and create our own iro.js UI that syncs
     * the value back to ACF's hidden input.
     */
    function initColorPickers(container) {
        if (typeof iro === 'undefined') return;

        var scope = container || document;
        var fields = scope.querySelectorAll('.cd-acf-form .acf-field-color-picker');

        fields.forEach(function (field) {
            // Skip if already processed
            if (field.dataset.cfdIro === '1') return;
            field.dataset.cfdIro = '1';

            // Find ACF's color input
            var acfInput = field.querySelector('input.wp-color-picker, input[data-type="colorpicker"], input[type="text"][name*="acf"]');
            if (!acfInput) return;

            // Get current color value (default to a neutral color)
            var currentColor = acfInput.value || '#3a86ff';

            // Hide the native Iris picker entirely
            var wpContainer = field.querySelector('.wp-picker-container');
            if (wpContainer) {
                wpContainer.style.display = 'none';
            }

            // Build our custom UI
            var wrapper = document.createElement('div');
            wrapper.className = 'cfd-color-picker';

            // Toggle button (swatch + hex)
            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'cfd-color-picker__toggle';
            toggle.innerHTML = '<span class="cfd-color-picker__swatch"></span>' +
                '<span class="cfd-color-picker__hex">' + currentColor + '</span>' +
                '<span class="cfd-color-picker__chevron"></span>';
            toggle.querySelector('.cfd-color-picker__swatch').style.backgroundColor = currentColor;

            // Expandable panel
            var panel = document.createElement('div');
            panel.className = 'cfd-color-picker__panel';
            panel.style.display = 'none';

            var pickerMount = document.createElement('div');
            pickerMount.className = 'cfd-color-picker__mount';
            panel.appendChild(pickerMount);

            // Clear button
            var clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'cfd-color-picker__clear';
            clearBtn.textContent = 'Borrar color';
            clearBtn.style.display = currentColor ? 'inline-block' : 'none';
            panel.appendChild(clearBtn);

            wrapper.appendChild(toggle);
            wrapper.appendChild(panel);

            // Insert after the wp-picker-container (or after the input)
            var insertAfter = wpContainer || acfInput;
            insertAfter.parentNode.insertBefore(wrapper, insertAfter.nextSibling);

            // Create iro.js instance
            var picker = new iro.ColorPicker(pickerMount, {
                width: COLOR_PICKER_WIDTH,
                color: currentColor || '#3a86ff',
                borderWidth: 2,
                borderColor: 'rgba(0,0,0,0.08)',
                handleRadius: 12,
                padding: 4,
                layout: [
                    {
                        component: iro.ui.Wheel,
                        options: {
                            wheelLightness: true
                        }
                    },
                    {
                        component: iro.ui.Slider,
                        options: {
                            sliderType: 'value'
                        }
                    }
                ]
            });

            iroInstances.push(picker);

            // Sync iro → ACF input
            picker.on('color:change', function (color) {
                var hex = color.hexString;
                acfInput.value = hex;

                // Trigger change event so ACF registers the value
                var evt = new Event('input', { bubbles: true });
                acfInput.dispatchEvent(evt);
                var changeEvt = new Event('change', { bubbles: true });
                acfInput.dispatchEvent(changeEvt);

                // Update our swatch + hex display
                toggle.querySelector('.cfd-color-picker__swatch').style.backgroundColor = hex;
                toggle.querySelector('.cfd-color-picker__hex').textContent = hex;
                clearBtn.style.display = 'inline-block';
            });

            // Toggle panel open/close
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                var isOpen = panel.style.display !== 'none';
                panel.style.display = isOpen ? 'none' : 'block';
                wrapper.classList.toggle('is-open', !isOpen);

                // Resize the picker after opening (iro.js needs this)
                if (!isOpen) {
                    picker.resize(Math.min(COLOR_PICKER_WIDTH, wrapper.offsetWidth - 32));
                }
            });

            // Clear button
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                acfInput.value = '';
                var evt = new Event('change', { bubbles: true });
                acfInput.dispatchEvent(evt);
                toggle.querySelector('.cfd-color-picker__swatch').style.backgroundColor = 'transparent';
                toggle.querySelector('.cfd-color-picker__hex').textContent = 'Sin color';
                clearBtn.style.display = 'none';
            });
        });
    }

    // ═══════════════════════════════════════════════════════
    // 2. DATE/TIME PICKERS — Enlarge touch targets
    // ═══════════════════════════════════════════════════════

    /**
     * jQuery UI datepicker renders a .ui-datepicker popup.
     * We add a body class so our CSS can target it when the
     * dashboard is active.
     */
    function markDatePickerBody() {
        if (!hasDashboardForm()) return;
        document.body.classList.add('cfd-has-dashboard');
    }

    // ═══════════════════════════════════════════════════════
    // 3. SELECT2 — Mobile adjustments
    // ═══════════════════════════════════════════════════════

    /**
     * ACF uses Select2 for relational fields (Post Object,
     * Page Link, Relationship, Taxonomy, User).
     * We add touch-friendly adjustments via CSS class.
     */
    function adjustSelect2(container) {
        var scope = container || document;
        var selects = scope.querySelectorAll('.cd-acf-form .select2-container');
        selects.forEach(function (el) {
            el.classList.add('cfd-select2');
        });
    }

    // ═══════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════

    function initAll(container) {
        if (!hasDashboardForm()) return;
        markDatePickerBody();
        initColorPickers(container);
        adjustSelect2(container);
    }

    // Run on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        initAll();
    });

    // Run on window.load (ACF may finish rendering after DOM ready)
    window.addEventListener('load', function () {
        initAll();
        // Delayed retry — ACF sometimes initializes fields after load
        setTimeout(function () { initAll(); }, 500);
        setTimeout(function () { initAll(); }, 1200);
    });

    // Hook into ACF's JS API for dynamically added fields
    // (repeater add row, flexible content add layout, etc.)
    function waitForAcf() {
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', function () {
                initAll();
            });
            acf.addAction('append', function (el) {
                var container = el.jquery ? el[0] : el;
                initColorPickers(container);
                adjustSelect2(container);
            });
            // When a new repeater row is added, re-init after a tick
            acf.addAction('new_field', function (field) {
                if (field && field.$el) {
                    var el = field.$el.jquery ? field.$el[0] : field.$el;
                    setTimeout(function () {
                        initColorPickers(el);
                        adjustSelect2(el);
                    }, 100);
                }
            });
        } else {
            setTimeout(waitForAcf, 500);
        }
    }
    waitForAcf();

})();
