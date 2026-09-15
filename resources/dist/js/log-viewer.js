/*!
 * jeeven/log-viewer — self-hosted script (vanilla JS, no dependencies).
 * Publish with: php artisan vendor:publish --tag=log-viewer-assets
 */
(function () {
    'use strict';

    var ROOT_ATTR = 'data-lv-theme';
    var STORAGE_KEY = 'lv-theme';

    function initTheme() {
        var root = document.documentElement;
        var toggle = document.querySelector('[data-lv-theme-toggle]');
        var icon = document.querySelector('[data-lv-theme-icon]');
        var label = document.querySelector('[data-lv-theme-label]');
        var saved = localStorage.getItem(STORAGE_KEY) ||
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        function apply(theme) {
            root.setAttribute(ROOT_ATTR, theme);
            localStorage.setItem(STORAGE_KEY, theme);
            if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
            if (label) label.textContent = theme === 'dark' ? 'Light' : 'Dark';
            if (toggle) toggle.setAttribute('aria-pressed', theme === 'dark');
        }

        apply(saved);

        if (toggle) {
            toggle.addEventListener('click', function () {
                apply(root.getAttribute(ROOT_ATTR) === 'dark' ? 'light' : 'dark');
            });
        }
    }

    function initNavbarShadow() {
        var nav = document.querySelector('.lv-navbar');
        if (!nav) return;
        var onScroll = function () {
            nav.classList.toggle('is-scrolled', window.scrollY > 4);
        };
        document.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // Expand/collapse log entry rows. Delegated at the document level (not
    // bound per-button) so rows swapped in by auto-refresh work without
    // needing to be re-initialized.
    function initRowToggles() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-lv-toggle]');
            if (!btn) return;
            var row = btn.closest('.lv-row');
            if (!row) return;
            var open = row.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Auto-submit the filter form when the level select changes, and
    // debounce the search input so it doesn't fire on every keystroke.
    function initFilterForm() {
        var form = document.querySelector('[data-lv-filters]');
        if (!form) return;

        var select = form.querySelector('select[name="level"]');
        if (select) {
            select.addEventListener('change', function () { form.submit(); });
        }

        var search = form.querySelector('input[name="search"]');
        if (search) {
            var timer = null;
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { form.submit(); }, 600);
            });
        }
    }

    // Live-tail: periodically re-fetches the current URL and swaps in just
    // the #lv-refresh-target panel (entries + pagination), leaving the
    // filter form untouched. Works identically for every channel type
    // (file, database, group/sidebar) since it's purely URL-driven — no
    // channel-specific logic here at all.
    function initAutoRefresh() {
        var target = document.getElementById('lv-refresh-target');
        if (!target) return; // not a log entries page (e.g. dashboard)

        var body = document.body;
        var defaultEnabled = body.getAttribute('data-lv-refresh-enabled') === '1';
        var interval = parseInt(body.getAttribute('data-lv-refresh-interval'), 10) || 10000;
        var toggleAllowed = body.getAttribute('data-lv-refresh-allow-toggle') === '1';

        // Per-log storage key so the on/off choice is remembered per
        // channel/sub-channel rather than globally.
        var storageKey = 'lv-autorefresh:' + location.pathname + ':' +
            (new URLSearchParams(location.search).get('sub') || '');

        var btn = document.querySelector('[data-lv-refresh-toggle]');
        var label = document.querySelector('[data-lv-refresh-label]');
        var dot = document.querySelector('[data-lv-refresh-dot]');
        var timer = null;

        var saved = localStorage.getItem(storageKey);
        var enabled = saved === null ? defaultEnabled : saved === '1';

        function setUI() {
            if (btn) btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            if (label) label.textContent = enabled ? 'On' : 'Off';
            if (dot) dot.classList.toggle('is-live', enabled);
        }

        function refreshNow() {
            if (document.hidden) return; // don't burn requests on a background tab
            fetch(location.href, { credentials: 'same-origin' })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var fresh = doc.getElementById('lv-refresh-target');
                    if (fresh) target.innerHTML = fresh.innerHTML;
                })
                .catch(function () { /* silent — network hiccup, next tick retries */ });
        }

        function start() {
            stop();
            timer = setInterval(refreshNow, interval);
        }

        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        setUI();
        if (enabled) start();

        if (btn && toggleAllowed) {
            btn.addEventListener('click', function () {
                enabled = !enabled;
                localStorage.setItem(storageKey, enabled ? '1' : '0');
                setUI();
                enabled ? start() : stop();
            });
        }

        // Catch up immediately when the tab regains focus, instead of
        // waiting for the next interval tick.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && enabled) {
                refreshNow();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initNavbarShadow();
        initRowToggles();
        initFilterForm();
        initAutoRefresh();
    });
})();
