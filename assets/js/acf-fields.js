/**
 * ============================================================
 * ACF Fields — Mobile-First Frontend Enhancements
 * ============================================================
 *
 * Enhances ACF Pro field types rendered via acf_form() on the
 * frontend dashboard. Main features:
 *
 * 1. Color Picker — Replaces WordPress Iris with iro.js
 *    (touch-friendly wheel + slider) with PORTAL pattern
 *    to definitively solve stacking context issues
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
    // 1. COLOR PICKER — Replace Iris with iro.js (PORTAL PATTERN)
    // ═══════════════════════════════════════════════════════

    var COLOR_PICKER_WIDTH = 280;
    var iroInstances = [];
    var activePickerData = null; // Track the currently open picker

    /**
     * Calculate optimal contrast color (black or white) for text on a given background
     */
    function getContrastColor(hexColor) {
        if (!hexColor || hexColor === 'transparent') return '#000000';
        var hex = hexColor.replace('#', '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        var r = parseInt(hex.substr(0, 2), 16);
        var g = parseInt(hex.substr(2, 2), 16);
        var b = parseInt(hex.substr(4, 2), 16);
        // YIQ formula for perceived brightness
        var yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return yiq >= 128 ? '#000000' : '#FFFFFF';
    }

    /**
     * Position the portal panel relative to the toggle button
     */
    function positionPortalPanel(panel, toggle) {
        var rect = toggle.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        var viewportHeight = window.innerHeight;
        var viewportWidth = window.innerWidth;
        
        // Panel dimensions (estimate before render, then adjust)
        var panelHeight = 420; // Approximate height
        var panelWidth = Math.min(COLOR_PICKER_WIDTH + 48, viewportWidth - 32);
        
        // Default: position below the toggle
        var top = rect.bottom + scrollTop + 8;
        var left = rect.left + scrollLeft;
        
        // Check if panel would overflow bottom of viewport
        if (rect.bottom + panelHeight > viewportHeight) {
            // Position above the toggle instead
            top = rect.top + scrollTop - panelHeight - 8;
            // If still overflowing top, just position at top of viewport
            if (top < scrollTop + 16) {
                top = scrollTop + 16;
            }
        }
        
        // Check horizontal overflow
        if (left + panelWidth > viewportWidth + scrollLeft - 16) {
            left = viewportWidth + scrollLeft - panelWidth - 16;
        }
        if (left < scrollLeft + 16) {
            left = scrollLeft + 16;
        }
        
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.width = panelWidth + 'px';
    }

    /**
     * Update the HEX display with dynamic background color and contrast text
     */
    function updateHexDisplay(panel, hexColor) {
        var hexInput = panel.querySelector('.cfd-color-picker__input');
        var swatchPreview = panel.querySelector('.cfd-color-picker__swatch-preview');
        
        if (hexInput) {
            hexInput.value = hexColor || '';
        }
        
        if (swatchPreview) {
            swatchPreview.style.backgroundColor = hexColor || 'transparent';
            // Add checkerboard pattern for transparency indication when no color
            if (!hexColor || hexColor === '') {
                swatchPreview.classList.add('cfd-color-picker__swatch-preview--empty');
            } else {
                swatchPreview.classList.remove('cfd-color-picker__swatch-preview--empty');
            }
        }
    }

    /**
     * Close any open color picker portal
     */
    function closeActivePortal() {
        if (!activePickerData) return;
        
        var panel = activePickerData.panel;
        var wrapper = activePickerData.wrapper;
        
        // Animate out
        panel.classList.remove('cfd-color-picker__portal--visible');
        panel.classList.add('cfd-color-picker__portal--closing');
        
        setTimeout(function() {
            if (panel.parentNode === document.body) {
                document.body.removeChild(panel);
            }
            panel.classList.remove('cfd-color-picker__portal--closing');
        }, 200);
        
        wrapper.classList.remove('is-open');
        activePickerData = null;
    }

    /**
     * Initialize iro.js color pickers for all ACF color picker fields.
     * Uses PORTAL PATTERN: Panel is appended to <body> when opened
     * and positioned absolutely using getBoundingClientRect().
     * This definitively solves all stacking context issues.
     */
    function initColorPickers(container) {
        if (typeof iro === 'undefined') {
            console.warn('iro is not defined');
            return;
        }

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
            var hasColor = Boolean(acfInput.value);

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
                '<span class="cfd-color-picker__hex">' + (hasColor ? currentColor : 'Sin color') + '</span>' +
                '<span class="cfd-color-picker__chevron"></span>';
            toggle.querySelector('.cfd-color-picker__swatch').style.backgroundColor = hasColor ? currentColor : 'transparent';

            wrapper.appendChild(toggle);

            // Insert after the wp-picker-container (or after the input)
            var insertAfter = wpContainer || acfInput;
            insertAfter.parentNode.insertBefore(wrapper, insertAfter.nextSibling);

            // Create the portal panel (will be appended to body when opened)
            var panel = document.createElement('div');
            panel.className = 'cfd-color-picker__portal';
            
            // Build panel contents
            var panelInner = document.createElement('div');
            panelInner.className = 'cfd-color-picker__panel-inner';
            
            // HEX display with live swatch preview
            var hexDisplay = document.createElement('div');
            hexDisplay.className = 'cfd-color-picker__value-display';
            hexDisplay.innerHTML = 
                '<span class="cfd-color-picker__swatch-preview"></span>' +
                '<span class="cfd-color-picker__label">HEX</span>' +
                '<input type="text" class="cfd-color-picker__input" readonly value="' + (hasColor ? currentColor : '') + '">';
            panelInner.appendChild(hexDisplay);
            
            // Initialize the swatch preview
            var initialSwatch = hexDisplay.querySelector('.cfd-color-picker__swatch-preview');
            if (initialSwatch) {
                initialSwatch.style.backgroundColor = hasColor ? currentColor : 'transparent';
                if (!hasColor) {
                    initialSwatch.classList.add('cfd-color-picker__swatch-preview--empty');
                }
            }

            var pickerMount = document.createElement('div');
            pickerMount.className = 'cfd-color-picker__mount';
            panelInner.appendChild(pickerMount);

            // Clear button (styled as red underlined link)
            var clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'cfd-color-picker__clear';
            clearBtn.textContent = 'Borrar color';
            clearBtn.style.display = hasColor ? 'inline-block' : 'none';
            panelInner.appendChild(clearBtn);
            
            panel.appendChild(panelInner);

            // Create iro.js instance (deferred until first open for performance)
            var picker = null;
            var pickerInitialized = false;
            
            function initPicker() {
                if (pickerInitialized) return;
                pickerInitialized = true;
                
                picker = new iro.ColorPicker(pickerMount, {
                    width: COLOR_PICKER_WIDTH,
                    color: acfInput.value || '#3a86ff',
                    borderWidth: 2,
                    borderColor: 'rgba(0,0,0,0.08)',
                    handleRadius: 14,
                    padding: 6,
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

                // Sync iro → ACF input (fires on every color change including drag)
                picker.on('color:change', function (color) {
                    var hex = color.hexString;
                    acfInput.value = hex;

                    // Trigger change event so ACF registers the value
                    var evt = new Event('input', { bubbles: true });
                    acfInput.dispatchEvent(evt);
                    var changeEvt = new Event('change', { bubbles: true });
                    acfInput.dispatchEvent(changeEvt);

                    // Update toggle swatch + hex display
                    toggle.querySelector('.cfd-color-picker__swatch').style.backgroundColor = hex;
                    toggle.querySelector('.cfd-color-picker__hex').textContent = hex;
                    
                    // Update panel HEX display with dynamic background
                    updateHexDisplay(panel, hex);
                    
                    clearBtn.style.display = 'inline-block';
                });
            }

            // Toggle panel open/close
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                
                var isCurrentlyOpen = wrapper.classList.contains('is-open');
                
                // Close any other open picker first
                if (activePickerData && activePickerData.wrapper !== wrapper) {
                    closeActivePortal();
                }
                
                if (isCurrentlyOpen) {
                    // Close this picker
                    closeActivePortal();
                } else {
                    // Open this picker (PORTAL PATTERN)
                    wrapper.classList.add('is-open');
                    
                    // Append panel to body
                    document.body.appendChild(panel);
                    
                    // Position panel relative to toggle
                    positionPortalPanel(panel, toggle);
                    
                    // Initialize picker if not yet done
                    initPicker();
                    
                    // Set current color in picker
                    var currentVal = acfInput.value || '#3a86ff';
                    if (picker) {
                        picker.color.hexString = currentVal;
                    }
                    
                    // Update HEX display
                    updateHexDisplay(panel, acfInput.value || '');
                    
                    // Show clear button only if there's a color
                    clearBtn.style.display = acfInput.value ? 'inline-block' : 'none';
                    
                    // Trigger animation
                    requestAnimationFrame(function() {
                        panel.classList.add('cfd-color-picker__portal--visible');
                    });
                    
                    // Track active picker
                    activePickerData = {
                        panel: panel,
                        wrapper: wrapper,
                        picker: picker,
                        toggle: toggle,
                        acfInput: acfInput,
                        clearBtn: clearBtn
                    };
                    
                    // Resize picker after DOM is ready
                    setTimeout(function() {
                        if (picker) {
                            picker.resize(Math.min(COLOR_PICKER_WIDTH, panel.offsetWidth - 48));
                        }
                    }, 50);
                }
            });

            // Clear button
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                
                acfInput.value = '';
                var evt = new Event('change', { bubbles: true });
                acfInput.dispatchEvent(evt);
                
                toggle.querySelector('.cfd-color-picker__swatch').style.backgroundColor = 'transparent';
                toggle.querySelector('.cfd-color-picker__hex').textContent = 'Sin color';
                
                updateHexDisplay(panel, '');
                clearBtn.style.display = 'none';
                
                // Close the portal after clearing
                closeActivePortal();
            });
        });
    }

    // ─── Global: Close color picker portal on click outside ───────
    document.addEventListener('click', function (e) {
        if (!activePickerData) return;
        
        var panel = activePickerData.panel;
        var toggle = activePickerData.toggle;
        
        // Check if click was inside the portal panel or the toggle
        if (!panel.contains(e.target) && !toggle.contains(e.target)) {
            closeActivePortal();
        }
    });
    
    // ─── Close portal on Escape key ───────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activePickerData) {
            closeActivePortal();
        }
    });
    
    // ─── Reposition portal on scroll/resize ───────
    var repositionTimeout = null;
    function handleScrollResize() {
        if (!activePickerData) return;
        
        clearTimeout(repositionTimeout);
        repositionTimeout = setTimeout(function() {
            if (activePickerData) {
                positionPortalPanel(activePickerData.panel, activePickerData.toggle);
            }
        }, 10);
    }
    
    window.addEventListener('scroll', handleScrollResize, { passive: true });
    window.addEventListener('resize', handleScrollResize, { passive: true });

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
