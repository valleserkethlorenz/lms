/**
 * LMS floating toast notifications
 * ----------------------------------------------------------------------------
 * One implementation of the little floating confirmation message used by every
 * role (admin, organizer, committee, team representative) and by the public
 * pages. Requires SweetAlert2 and is styled by assets/css/lms-toast.css.
 *
 * Usage (unchanged for existing call sites):
 *     Toast.fire({ icon: 'success', title: 'Link copied!' });
 *     Toast.fire({ icon: 'success', title: res.msg }).then(() => location.reload());
 *
 * Shorthands:
 *     Toast.success('Account created successfully.');
 *     Toast.error('Unable to save the result.', 'Please try again.');
 *
 * Server rendered confirmations:
 *     Wrap the server message in one element carrying [data-lms-toast-source]
 *     and the shared component will lift every success/info alert inside it
 *     into a floating toast, leaving error alerts anchored in the page.
 */
(function (window, document) {
    'use strict';

    // Loaded twice? Never re-declare the component.
    if (window.Toast && window.Toast.isLmsToast) {
        return;
    }

    // SweetAlert2 is a hard dependency; bail out quietly if it is missing.
    if (typeof window.Swal === 'undefined') {
        return;
    }

    var Swal = window.Swal;

    var TYPES = {
        success: { timer: 4000, icon: 'bi-check-lg', dismissible: false },
        error: { timer: 8000, icon: 'bi-exclamation-lg', dismissible: true },
        warning: { timer: 6000, icon: 'bi-exclamation-lg', dismissible: true },
        info: { timer: 4500, icon: 'bi-info-lg', dismissible: false },
        question: { timer: 6000, icon: 'bi-question-lg', dismissible: false }
    };

    var SERVER_CONFIRMATIONS = '.alert-success, .alert-info, .settings-alert-success';

    function isSmallScreen() {
        return !!(window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches);
    }

    function mergeClasses(base, extra) {
        return (base + ' ' + (extra || '')).replace(/\s+/g, ' ').trim();
    }

    /**
     * Turns caller parameters into a toast configuration that always carries
     * the shared look, position, timers and hover behaviour.
     */
    function buildConfig(params) {
        if (typeof params === 'string') {
            params = { title: params };
        }
        params = params || {};

        var icon = TYPES[params.icon] ? params.icon : 'info';
        var meta = TYPES[icon];
        var custom = params.customClass || {};

        var customClass = {
            popup: mergeClasses('lms-toast-popup lms-toast--' + icon, custom.popup),
            title: mergeClasses('lms-toast__title', custom.title),
            htmlContainer: mergeClasses('lms-toast__text', custom.htmlContainer),
            icon: mergeClasses('lms-toast__icon', custom.icon),
            closeButton: mergeClasses('lms-toast__close', custom.closeButton),
            timerProgressBar: mergeClasses('lms-toast__progress', custom.timerProgressBar)
        };

        var config = {
            toast: true,
            position: isSmallScreen() ? 'top' : 'top-end',
            icon: icon,
            iconHtml: '<i class="bi ' + meta.icon + '" aria-hidden="true"></i>',
            showConfirmButton: false,
            showCloseButton: meta.dismissible,
            timer: meta.timer,
            timerProgressBar: true,
            customClass: customClass,
            didOpen: function (element) {
                // Keep the toast on screen while the user reads it.
                element.addEventListener('mouseenter', function () {
                    Swal.stopTimer();
                });
                element.addEventListener('mouseleave', function () {
                    Swal.resumeTimer();
                });
                element.addEventListener('focusin', function () {
                    Swal.stopTimer();
                });
                element.addEventListener('focusout', function () {
                    Swal.resumeTimer();
                });
            }
        };

        Object.keys(params).forEach(function (key) {
            if (key === 'customClass') {
                return;
            }
            config[key] = params[key];
        });

        // The shared look must survive a caller that passes its own customClass.
        config.customClass = customClass;

        return config;
    }

    var Toast = {
        isLmsToast: true,

        fire: function (params) {
            return Swal.fire(buildConfig(params));
        },

        success: function (title, text) {
            return Toast.fire({ icon: 'success', title: title, text: text });
        },

        error: function (title, text) {
            return Toast.fire({ icon: 'error', title: title, text: text });
        },

        warning: function (title, text) {
            return Toast.fire({ icon: 'warning', title: title, text: text });
        },

        info: function (title, text) {
            return Toast.fire({ icon: 'info', title: title, text: text });
        }
    };

    /**
     * Lifts server rendered confirmations (post-submit success/info messages
     * printed by PHP) into floating toasts instead of leaving them inline.
     * Error and warning alerts stay in the page so they can still be read
     * after the toast has faded away.
     */
    function floatServerConfirmations() {
        var sources = document.querySelectorAll('[data-lms-toast-source]');

        Array.prototype.forEach.call(sources, function (source) {
            var confirmations = source.querySelectorAll(SERVER_CONFIRMATIONS);
            var keptInline = 0;

            Array.prototype.forEach.call(confirmations, function (element) {
                var message = (element.textContent || '').replace(/\s+/g, ' ').trim();

                element.style.display = 'none';
                element.setAttribute('hidden', 'hidden');

                if (message) {
                    Toast.fire({
                        icon: element.classList.contains('alert-info') ? 'info' : 'success',
                        title: message
                    });
                }
            });

            // Keep the wrapper only while it still has something to show.
            Array.prototype.forEach.call(source.querySelectorAll('.alert, .settings-alert'), function (element) {
                if (element.style.display !== 'none') {
                    keptInline++;
                }
            });

            if (!keptInline && source.parentNode) {
                source.parentNode.removeChild(source);
            }
        });
    }

    // Exposed so pages that inject markup later can run the conversion again.
    Toast.showServerConfirmations = floatServerConfirmations;

    window.Toast = Toast;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', floatServerConfirmations);
    } else {
        floatServerConfirmations();
    }
})(window, document);
