/**
 * FreshToast - Floating Notification & Toast System
 * Floating on bottom-left for web, UI/UX friendly bottom-docked for mobile.
 */
(function(window) {
    'use strict';

    var container = null;

    function getContainer() {
        if (!container || !document.body.contains(container)) {
            container = document.getElementById('freshToastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'freshToastContainer';
                container.className = 'fresh-toast-container';
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'true');
                document.body.appendChild(container);
            }
        }
        return container;
    }

    var ICONS = {
        success: '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>',
        danger: '<i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>',
        error: '<i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>',
        warning: '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>',
        info: '<i class="bi bi-info-circle-fill" aria-hidden="true"></i>'
    };

    function show(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        options = options || {};
        var type = options.type || 'success';
        var defaultTitle = type === 'error' || type === 'danger' ? 'Notice' : (type === 'warning' ? 'Warning' : 'Success');
        var title = options.title || defaultTitle;
        var message = options.message || '';
        var duration = options.duration !== undefined ? options.duration : 4500;

        var parent = getContainer();

        var toast = document.createElement('div');
        toast.className = 'fresh-toast fresh-toast-' + type;
        toast.setAttribute('role', 'alert');

        var iconHtml = ICONS[type] || ICONS.success;

        toast.innerHTML = 
            '<div class="fresh-toast-icon">' + iconHtml + '</div>' +
            '<div class="fresh-toast-body">' +
                '<div class="fresh-toast-title">' + escapeHtml(title) + '</div>' +
                '<div class="fresh-toast-message">' + escapeHtml(message) + '</div>' +
            '</div>' +
            '<button type="button" class="fresh-toast-close" aria-label="Close notification">' +
                '<i class="bi bi-x-lg" aria-hidden="true"></i>' +
            '</button>' +
            (duration > 0 ? '<div class="fresh-toast-progress"><div class="fresh-toast-progress-bar"></div></div>' : '');

        parent.appendChild(toast);

        // Force reflow for entrance transition
        void toast.offsetWidth;
        toast.classList.add('is-visible');

        var hideTimer = null;
        var progressBar = toast.querySelector('.fresh-toast-progress-bar');

        function startTimer() {
            if (duration > 0) {
                if (progressBar) {
                    progressBar.style.transition = 'transform ' + duration + 'ms linear';
                    progressBar.style.transform = 'scaleX(0)';
                }
                hideTimer = setTimeout(dismiss, duration);
            }
        }

        function pauseTimer() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            if (progressBar) {
                var computed = window.getComputedStyle(progressBar);
                var transform = computed.getPropertyValue('transform');
                progressBar.style.transition = 'none';
                progressBar.style.transform = transform;
            }
        }

        function dismiss() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            toast.classList.remove('is-visible');
            toast.classList.add('is-hiding');
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        var closeBtn = toast.querySelector('.fresh-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', dismiss);
        }

        toast.addEventListener('mouseenter', pauseTimer);
        toast.addEventListener('mouseleave', startTimer);
        toast.addEventListener('touchstart', pauseTimer, { passive: true });
        toast.addEventListener('touchend', startTimer, { passive: true });

        startTimer();
        return toast;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.FreshToast = {
        show: show,
        success: function(msg, title, dur) {
            return show({ message: msg, title: title || 'Success', type: 'success', duration: dur });
        },
        error: function(msg, title, dur) {
            return show({ message: msg, title: title || 'Notice', type: 'danger', duration: dur || 5000 });
        },
        warning: function(msg, title, dur) {
            return show({ message: msg, title: title || 'Warning', type: 'warning', duration: dur });
        },
        info: function(msg, title, dur) {
            return show({ message: msg, title: title || 'Info', type: 'info', duration: dur });
        }
    };

    // Auto-detect flash query parameters on load
    document.addEventListener('DOMContentLoaded', function() {
        if (!window.location.search) return;
        var params = new URLSearchParams(window.location.search);
        var msg = params.get('msg');
        var error = params.get('error');

        // Check if page already rendered an inline toast for this
        if (document.getElementById('freshToastContainer') && document.querySelector('.fresh-toast')) {
            return;
        }

        if (msg) {
            var text = '';
            var title = 'Record Saved';
            var type = 'success';

            if (msg === 'updated') {
                text = 'The requested record has been saved successfully.';
                title = 'Record Saved';
            } else if (msg === 'added') {
                text = 'The record has been published successfully.';
                title = 'Record Published';
            } else if (msg === 'deleted') {
                text = 'The record has been deleted successfully.';
                title = 'Record Removed';
            } else if (msg === 'cancelled') {
                text = 'Your order has been cancelled.';
                title = 'Order Cancelled';
                type = 'warning';
            }

            if (text) {
                window.FreshToast.show({ title: title, message: text, type: type });
                params.delete('msg');
                var newQuery = params.toString();
                var cleanUrl = window.location.pathname + (newQuery ? '?' + newQuery : '') + window.location.hash;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        } else if (error) {
            // Note: Exclude coupons which are handled inside the coupon form box
            if (error === 'cannot_cancel') {
                window.FreshToast.error('This order is being processed and cannot be cancelled.', 'Notice');
            } else if (error === 'cancellation_failed') {
                window.FreshToast.error('Could not cancel this order. Please contact support.', 'Notice');
            }
        }
    });

    // ============================================================
    // FRESHCONFIRM - GLOBAL CONFIRMATION MODAL SYSTEM
    // ============================================================
    var confirmBackdrop = null;
    var activeResolver = null;

    function getConfirmModal() {
        if (!confirmBackdrop || !document.body.contains(confirmBackdrop)) {
            confirmBackdrop = document.createElement('div');
            confirmBackdrop.className = 'fc-confirm-backdrop';
            confirmBackdrop.setAttribute('role', 'dialog');
            confirmBackdrop.setAttribute('aria-modal', 'true');
            confirmBackdrop.setAttribute('tabindex', '-1');

            confirmBackdrop.innerHTML = 
                '<div class="fc-confirm-dialog" role="document">' +
                    '<div class="fc-confirm-content">' +
                        '<div class="fc-confirm-icon fc-confirm-icon-danger" id="fcConfirmIcon">' +
                            '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>' +
                        '</div>' +
                        '<h4 class="fc-confirm-title" id="fcConfirmTitle">Confirmation Required</h4>' +
                        '<p class="fc-confirm-message" id="fcConfirmMessage">Are you sure you want to proceed?</p>' +
                    '</div>' +
                    '<div class="fc-confirm-actions">' +
                        '<button type="button" class="fc-confirm-btn fc-confirm-btn-cancel" id="fcConfirmBtnCancel">Cancel</button>' +
                        '<button type="button" class="fc-confirm-btn fc-confirm-btn-danger" id="fcConfirmBtnConfirm">Confirm</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(confirmBackdrop);

            var cancelBtn = confirmBackdrop.querySelector('#fcConfirmBtnCancel');
            var confirmBtn = confirmBackdrop.querySelector('#fcConfirmBtnConfirm');

            cancelBtn.addEventListener('click', function() {
                closeConfirm(false);
            });

            confirmBtn.addEventListener('click', function() {
                closeConfirm(true);
            });

            confirmBackdrop.addEventListener('click', function(e) {
                if (e.target === confirmBackdrop) {
                    closeConfirm(false);
                }
            });

            document.addEventListener('keydown', function(e) {
                if (!confirmBackdrop || !confirmBackdrop.classList.contains('is-open')) return;
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeConfirm(false);
                }
            });
        }
        return confirmBackdrop;
    }

    function closeConfirm(result) {
        if (!confirmBackdrop) return;
        confirmBackdrop.classList.remove('is-open');
        document.body.style.overflow = '';
        if (typeof activeResolver === 'function') {
            var res = activeResolver;
            activeResolver = null;
            res(result);
        }
    }

    var CONFIRM_ICONS = {
        danger: '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>',
        warning: '<i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>',
        info: '<i class="bi bi-info-circle-fill" aria-hidden="true"></i>',
        success: '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>'
    };

    function confirmDialog(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        options = options || {};
        var type = options.type || 'danger';
        var title = options.title || (type === 'danger' ? 'Confirm Action' : 'Are you sure?');
        var message = options.message || 'Are you sure you want to proceed?';
        var confirmText = options.confirmText || 'Confirm';
        var cancelText = options.cancelText || 'Cancel';
        var confirmClass = options.confirmClass || ('fc-confirm-btn-' + (type === 'danger' ? 'danger' : 'primary'));

        var modal = getConfirmModal();
        var iconEl = modal.querySelector('#fcConfirmIcon');
        var titleEl = modal.querySelector('#fcConfirmTitle');
        var msgEl = modal.querySelector('#fcConfirmMessage');
        var confirmBtn = modal.querySelector('#fcConfirmBtnConfirm');
        var cancelBtn = modal.querySelector('#fcConfirmBtnCancel');

        iconEl.className = 'fc-confirm-icon fc-confirm-icon-' + type;
        iconEl.innerHTML = CONFIRM_ICONS[type] || CONFIRM_ICONS.danger;

        titleEl.textContent = title;
        msgEl.textContent = message;

        confirmBtn.textContent = confirmText;
        confirmBtn.className = 'fc-confirm-btn ' + confirmClass;
        cancelBtn.textContent = cancelText;

        document.body.style.overflow = 'hidden';
        modal.classList.add('is-open');
        confirmBtn.focus();

        return new Promise(function(resolve) {
            activeResolver = resolve;
        });
    }

    window.FreshConfirm = confirmDialog;

    // Upgrades any legacy onsubmit="return confirm(...)" or onclick="return confirm(...)"
    function upgradeNativeConfirms(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('form[onsubmit*="confirm("]');
        forms.forEach(function(f) {
            var attr = f.getAttribute('onsubmit');
            var m = attr.match(/confirm\(\s*(['"])(.*?)\1\s*\)/);
            if (m && m[2]) {
                f.removeAttribute('onsubmit');
                f.setAttribute('data-confirm', m[2]);
            }
        });

        var elements = scope.querySelectorAll('[onclick*="confirm("]');
        elements.forEach(function(el) {
            var attr = el.getAttribute('onclick');
            var m = attr.match(/confirm\(\s*(['"])(.*?)\1\s*\)/);
            if (m && m[2]) {
                el.removeAttribute('onclick');
                el.setAttribute('data-confirm', m[2]);
            }
        });
    }

    // Global Delegated Click Handler for [data-confirm]
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-confirm]');
        if (!trigger) return;

        // If it is a submit button inside a form, let form submit handler manage it
        if (trigger.tagName === 'BUTTON' && trigger.type === 'submit' && trigger.form) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var msg = trigger.getAttribute('data-confirm');
        var title = trigger.getAttribute('data-confirm-title') || 'Confirm Action';
        var confirmText = trigger.getAttribute('data-confirm-btn') || 'Confirm';
        var type = trigger.getAttribute('data-confirm-type') || 'danger';

        confirmDialog({
            message: msg,
            title: title,
            confirmText: confirmText,
            type: type
        }).then(function(approved) {
            if (approved) {
                if (trigger.tagName === 'A' && trigger.href) {
                    window.location.href = trigger.href;
                } else if (typeof trigger.onclick === 'function') {
                    trigger.onclick();
                }
            }
        });
    }, true);

    // Global Delegated Submit Handler for forms with [data-confirm]
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || !form.hasAttribute('data-confirm')) return;

        if (form.__freshConfirmApproved) {
            delete form.__freshConfirmApproved;
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var msg = form.getAttribute('data-confirm');
        var title = form.getAttribute('data-confirm-title') || 'Confirm Action';
        var confirmText = form.getAttribute('data-confirm-btn') || 'Confirm';
        var type = form.getAttribute('data-confirm-type') || 'danger';

        confirmDialog({
            message: msg,
            title: title,
            confirmText: confirmText,
            type: type
        }).then(function(approved) {
            if (approved) {
                form.__freshConfirmApproved = true;
                form.submit();
            }
        });
    }, true);

    // Initialize auto-upgrade on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { upgradeNativeConfirms(); });
    } else {
        upgradeNativeConfirms();
    }

    // Auto-upgrade inside any dynamic mutations (e.g. admin AJAX container updates)
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mut) {
                if (mut.addedNodes && mut.addedNodes.length > 0) {
                    mut.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) upgradeNativeConfirms(node);
                    });
                }
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

})(window);
