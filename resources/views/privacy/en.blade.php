@php
    $banks = collect(config('banks'))->pluck('name')->join(', ');

    $sections = [
        'What we read' => [
            "Only emails sent from the addresses of supported banks ({$banks}). The sender filter runs on Gmail's own servers, so other emails are never downloaded.",
            'Read-only. The app never marks emails as read, moves, deletes or sends any email.',
        ],
        'What we store' => [
            'Your Gmail address and app password. The app password is encrypted and is never shown or written to logs.',
            'The content of those bank emails and the transactions read from them: amount, time, recipient/sender, transfer description. We do not read your account balance.',
            'Categories, rules and notes you create.',
            'We do not sell or share your data with anyone.',
        ],
        'How to disconnect and delete data' => [
            'Disconnect: Settings → Gmail → Disconnect. The app password is deleted immediately; past transactions are kept.',
            'Revoke at Google: go to myaccount.google.com/apppasswords and delete the "SpendSync" password. The app loses read access immediately.',
            'Delete data: Settings → Profile & password → Danger Zone → Delete data. Emails, transactions, categories, rules and the Gmail connection are permanently deleted; your login account is kept.',
            'Delete account: Settings → Profile & password → Danger Zone → Delete Account. Your login account and all of its data are permanently deleted.',
        ],
    ];
@endphp

<x-privacy-layout title="Privacy" :sections="$sections">
    <x-slot:intro>
        <p class="text-slate-600 text-sm">Last update: 21/11/2026.</p>
    </x-slot:intro>
</x-privacy-layout>
