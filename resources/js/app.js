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

// Back/Forward restores the page from Livewire's history cache without asking the server, so data
// changed on another page (e.g. a category just set) would show stale. Re-render after a restore.
let restoredFromHistory = false;

document.addEventListener('livewire:navigate', (event) => {
    restoredFromHistory = event.detail.history && event.detail.cached;
});

// Browsers only honour `autofocus` on a full page load, not after wire:navigate swaps the page.
document.addEventListener('livewire:navigated', () => {
    document.querySelector('[autofocus]')?.focus();
    keyboardPlaceholder?.remove();
    keyboardPlaceholder = null;

    if (restoredFromHistory) {
        Livewire.all().forEach((component) => component.$wire.$refresh());
    }
});

// Homepage example cards: stacked like a deck; click (or the button) sends the top card to the back.
document.querySelectorAll('[data-deck]').forEach((deck) => {
    const cards = [...deck.children];
    const order = [...cards];
    const dots = [...deck.parentElement.querySelectorAll('[data-deck-dots] > *')];
    const behind = 'translateY(-16px) scale(0.94)';
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let shuffling = false;

    const place = () => cards.forEach((card, index) => {
        card.style.zIndex = cards.length - index;
        card.style.transform = index ? behind : '';
        card.inert = index > 0;
        dots[order.indexOf(card)]?.toggleAttribute('data-active', index === 0);
    });

    const shuffle = () => {
        if (shuffling) return;

        if (reduceMotion) {
            cards.push(cards.shift());
            place();

            return;
        }

        shuffling = true;
        const [top, next] = cards;

        next.animate([{ transform: behind }, { transform: 'none' }], { duration: 500, easing: 'cubic-bezier(.2,.8,.2,1)' });

        top.animate([
            { transform: 'none' },
            { transform: 'translateX(60%) rotate(6deg)', offset: 0.45 },
            { transform: behind },
        ], { duration: 500, easing: 'ease-in-out' }).finished.then(() => shuffling = false);

        setTimeout(() => {
            cards.push(cards.shift());
            place();
        }, 225);
    };

    place();
    deck.addEventListener('click', shuffle);
    deck.parentElement.querySelector('[data-deck-next]')?.addEventListener('click', shuffle);
});
