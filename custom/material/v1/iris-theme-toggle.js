(function () {
    'use strict';

    var storageKey = 'iris-material-theme';
    var root = document.documentElement;

    function readTheme() {
        try {
            return window.localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light';
        } catch (error) {
            return 'light';
        }
    }

    function storeTheme(theme) {
        try {
            window.localStorage.setItem(storageKey, theme);
        } catch (error) {
            // El tema sigue funcionando aunque localStorage no esté disponible.
        }
    }

    function applyTheme(theme) {
        var dark = theme === 'dark';
        var button = document.getElementById('iris-theme-toggle');

        root.classList.toggle('iris-theme-dark', dark);
        root.style.colorScheme = dark ? 'dark' : 'light';

        if (!button)
            return;

        var label = button.querySelector('.iris-theme-label');
        var icon = button.querySelector('i');
        var action = dark ? 'Activar tema claro' : 'Activar tema oscuro';

        button.setAttribute('aria-label', action);
        button.setAttribute('title', action);
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        if (label)
            label.textContent = dark ? 'Tema claro' : 'Tema oscuro';
        if (icon)
            icon.className = dark ? 'icon-sun' : 'icon-moon';
    }

    // Aplicación temprana para evitar un destello del tema claro.
    applyTheme(readTheme());

    if (window.IRISThemeToggleInitialized)
        return;
    window.IRISThemeToggleInitialized = true;

    function initializeToggle() {
        var button = document.getElementById('iris-theme-toggle');
        if (!button || button.getAttribute('data-theme-ready') === 'true')
            return;

        button.setAttribute('data-theme-ready', 'true');
        applyTheme(readTheme());
        button.addEventListener('click', function () {
            var theme = root.classList.contains('iris-theme-dark') ? 'light' : 'dark';
            storeTheme(theme);
            applyTheme(theme);
        });
    }

    if (document.readyState === 'loading')
        document.addEventListener('DOMContentLoaded', initializeToggle);
    else
        initializeToggle();
}());
