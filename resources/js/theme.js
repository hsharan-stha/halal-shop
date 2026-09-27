/*
 * Theme preference: "light" | "dark" | "system". Applied before paint by the
 * inline snippet in the layout head; this module handles runtime changes.
 */
const root = document.documentElement;

export function applyTheme(preference) {
    if (root.dataset.darkMode !== 'enabled') {
        root.classList.remove('dark');
        return;
    }

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = preference === 'dark' || (preference === 'system' && prefersDark);
    root.classList.toggle('dark', dark);
}

window.setTheme = (preference) => {
    localStorage.setItem('theme', preference);
    applyTheme(preference);
};

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    applyTheme(localStorage.getItem('theme') ?? root.dataset.theme ?? 'system');
});
