// FILE: .\web\js\color-mode.js
/*!
 * Color mode toggler for Bootstrap 5.3+
 */

(() => {
    'use strict';

    const getStoredTheme = () => localStorage.getItem('theme');
    const setStoredTheme = theme => localStorage.setItem('theme', theme);

    const getPreferredTheme = () => {
        const stored = getStoredTheme();
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    const setTheme = theme => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        // Для Bootstrap 5.3+ также устанавливаем атрибут на body
        document.body.setAttribute('data-bs-theme', theme);
    };

    const updateToggle = theme => {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;
        
        // Используем разные иконки для тем
        if (theme === 'dark') {
            toggle.textContent = '☀️';
            toggle.setAttribute('aria-label', 'Переключить на светлую тему');
        } else {
            toggle.textContent = '🌙';
            toggle.setAttribute('aria-label', 'Переключить на темную тему');
        }
    };

    // Применяем сохраненную тему
    setTheme(getPreferredTheme());

    // Следим за системными настройками
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (!getStoredTheme()) {
            const theme = getPreferredTheme();
            setTheme(theme);
            updateToggle(theme);
        }
    });

    // Инициализация после загрузки DOM
    document.addEventListener('DOMContentLoaded', () => {
        const initialTheme = getPreferredTheme();
        updateToggle(initialTheme);

        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                setStoredTheme(newTheme);
                setTheme(newTheme);
                updateToggle(newTheme);
            });
        }
    });
})();