/**
 * LMS modal dialogs
 * ----------------------------------------------------------------------------
 * One place for the centred confirmation / alert dialogs used by every role:
 * admin, organizer, committee, team representative and the public pages.
 * Requires SweetAlert2, styled by assets/css/lms-modal.css.
 *
 * Usage
 * -----
 *     LmsDialog.confirm({
 *         title: 'Delete this utility?',
 *         text: 'This action cannot be undone.',
 *         confirmText: 'Yes, delete it',
 *         tone: 'danger'
 *     }).then(function (confirmed) {
 *         if (!confirmed) return;
 *         // …
 *     });
 *
 *     LmsDialog.alert({ title: 'League archived', text: res.msg });
 *     LmsDialog.success('Saved', 'Your changes are live.');
 *     LmsDialog.error('Unable to save', res.msg);
 *     LmsDialog.loading('Submitting result', 'Please wait…');   // LmsDialog.close();
 *
 * Every helper accepts the full SweetAlert2 option set on top of the LMS
 * defaults, so existing custom dialogs can be migrated one option at a time.
 */
(function (window) {
    'use strict';

    if (window.LmsDialog && window.LmsDialog.isLmsDialog) {
        return;
    }

    if (typeof window.Swal === 'undefined') {
        return;
    }

    var Swal = window.Swal;

    var TONES = {
        primary: { icon: 'question', confirm: '#123f70' },
        info: { icon: 'info', confirm: '#2f6fed' },
        success: { icon: 'success', confirm: '#157f45' },
        warning: { icon: 'warning', confirm: '#b45309' },
        danger: { icon: 'warning', confirm: '#b02a37' }
    };

    function resolveTone(name) {
        return TONES[name] || TONES.primary;
    }

    function isPlainObject(value) {
        return !!value && typeof value === 'object' && !Array.isArray(value);
    }

    /**
     * Keeps the shared popup class on the dialog without duplicating classes
     * when the helpers chain into each other.
     */
    function popupClasses(extra) {
        var classes = ['lms-dialog-popup'];

        String(extra || '').split(/\s+/).forEach(function (name) {
            if (name && classes.indexOf(name) === -1) {
                classes.push(name);
            }
        });

        return classes.join(' ');
    }

    /**
     * Merges caller options over the LMS dialog defaults without dropping the
     * per-type colours or the shared popup class.
     */
    function build(options, defaults) {
        options = options || {};

        var base = {
            reverseButtons: true,
            focusCancel: true,
            buttonsStyling: true,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'lms-dialog-popup' }
        };

        var layers = [base, defaults, options];
        var merged = {};
        var layer;
        var key;
        var i;

        for (i = 0; i < layers.length; i++) {
            layer = layers[i];
            if (!layer) {
                continue;
            }
            for (key in layer) {
                if (!Object.prototype.hasOwnProperty.call(layer, key)) {
                    continue;
                }
                if (key === 'customClass' && isPlainObject(layer.customClass)) {
                    merged.customClass = { popup: popupClasses(layer.customClass.popup) };
                    continue;
                }
                merged[key] = layer[key];
            }
        }

        return merged;
    }

    var LmsDialog = {
        isLmsDialog: true,

        /**
         * Yes/no question. Resolves with true only when the user confirms.
         */
        confirm: function (options) {
            var tone = resolveTone((options && options.tone) || 'primary');

            return Swal.fire(build(options, {
                icon: tone.icon,
                showCancelButton: true,
                confirmButtonColor: tone.confirm
            })).then(function (result) {
                return !!(result && result.isConfirmed);
            });
        },

        /**
         * Message with a single acknowledgement button.
         */
        alert: function (options) {
            var tone = resolveTone((options && options.tone) || 'info');

            return Swal.fire(build(options, {
                icon: tone.icon,
                confirmButtonColor: tone.confirm,
                confirmButtonText: 'Got it',
                focusCancel: false
            }));
        },

        success: function (title, text, options) {
            return LmsDialog.alert(build(options, {
                title: title,
                text: text,
                tone: 'success'
            }));
        },

        error: function (title, text, options) {
            return LmsDialog.alert(build(options, {
                title: title,
                text: text,
                tone: 'danger'
            }));
        },

        warning: function (title, text, options) {
            return LmsDialog.alert(build(options, {
                title: title,
                text: text,
                tone: 'warning'
            }));
        },

        /**
         * Blocking progress dialog. Dismiss it with LmsDialog.close().
         */
        loading: function (title, text) {
            return Swal.fire({
                title: title || 'Working…',
                html: LmsDialog.loadingHtml(text),
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showConfirmButton: false,
                showCancelButton: false,
                customClass: { popup: 'lms-dialog-popup' }
            });
        },

        /**
         * Loading markup for pages that build their own dialog.
         */
        loadingHtml: function (text) {
            return '<div class="lms-dialog-loading" role="status" aria-live="polite">'
                + '<span class="spinner-border" aria-hidden="true"></span>'
                + (text ? '<p class="lms-dialog-loading-text">' + text + '</p>' : '')
                + '</div>';
        },

        close: function () {
            return Swal.close();
        }
    };

    window.LmsDialog = LmsDialog;
})(window);
