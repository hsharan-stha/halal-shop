const secureContext = window.location.protocol === 'https:' || window.location.hostname === 'localhost';

if ('serviceWorker' in navigator && secureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
