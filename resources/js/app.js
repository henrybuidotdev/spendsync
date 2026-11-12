if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}

// Browsers only honour `autofocus` on a full page load, not after wire:navigate swaps the page.
document.addEventListener('livewire:navigated', () => document.querySelector('[autofocus]')?.focus());
