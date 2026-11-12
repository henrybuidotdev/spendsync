@php
    $banks = collect(config('banks'))->pluck('name')->join(', ');

    $sections = [
        'What we read' => [
            "Only emails sent from the addresses of supported banks ({$banks}). The sender filter runs on Gmail's own servers, so other emails are never downloaded.",
            'Read-only. The app never marks emails as read, moves, deletes or sends any email.',
        ],
        'What we store' => [
            'Your Gmail address and app password. The app password is encrypted and is never shown or written to logs.',
            'The content of those bank emails and the transactions read from them: amount, time, recipient/sender, transfer description, balance.',
            'Categories, rules and notes you create.',
            'We do not sell or share your data with anyone.',
        ],
        'How to disconnect and delete data' => [
            'Disconnect: Settings → Disconnect. The app password is deleted immediately; past transactions are kept.',
            'Revoke at Google: go to myaccount.google.com/apppasswords and delete the "SpendSync" password. The app loses read access immediately.',
            'Delete data: Settings → Danger Zone → Delete data. Emails, transactions, categories, rules and the Gmail connection are permanently deleted; your login account is kept.',
            'Delete account: Settings → Danger Zone → Delete Account. Your login account and all of its data are permanently deleted.',
        ],
    ];
@endphp

<x-privacy-layout title="Privacy" :sections="$sections">
    <x-slot:intro>
        <p class="text-slate-600">Short and plain: what we read, what we store, and how you delete it all.</p>
    </x-slot:intro>

    <p class="text-sm">
        <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium text-emerald-700 underline">Revoke the app password at Google ↗</a>
    </p>
</x-privacy-layout>
