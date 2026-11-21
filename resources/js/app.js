if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}

// Mobile browsers only open the on-screen keyboard when focus happens during a tap, but after
// wire:navigate the next page arrives later. Links marked data-keyboard="<inputmode>" focus a
// hidden input during the tap so the keyboard opens, then hand focus to the page's [autofocus]
// field. The placeholder sits outside <body> because wire:navigate replaces the body.
let keyboardPlaceholder = null;

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[wire\\:navigate][data-keyboard]');
    if (! link || ! event.isTrusted) return;

    keyboardPlaceholder?.remove();
    keyboardPlaceholder = Object.assign(document.createElement('input'), { type: 'text', inputMode: link.dataset.keyboard });
    keyboardPlaceholder.setAttribute('aria-hidden', 'true');
    keyboardPlaceholder.tabIndex = -1;
    keyboardPlaceholder.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;font-size:16px;border:0;padding:0;';
    document.documentElement.append(keyboardPlaceholder);
    keyboardPlaceholder.focus({ preventScroll: true });
}, true);

// Browsers only honour `autofocus` on a full page load, not after wire:navigate swaps the page.
document.addEventListener('livewire:navigated', () => {
    document.querySelector('[autofocus]')?.focus();
    keyboardPlaceholder?.remove();
    keyboardPlaceholder = null;
});
