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

})(window);
