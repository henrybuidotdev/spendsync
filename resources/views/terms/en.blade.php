@php
    $sections = [
        'What SpendSync does' => [
            'SpendSync reads balance-change emails from supported banks in the Gmail inbox you connect, and records them as transactions you can categorize and see in reports.',
            'You connect with a Google app password. The app is read-only: it never sends, deletes or changes any email.',
        ],
        'Your account' => [
            'Only connect your own Gmail inbox, or one you are allowed to use.',
            'You are responsible for keeping your login password and app password safe. If you think either has leaked, change your password and revoke the app password at Google right away.',
        ],
        'Numbers are for reference' => [
            'Transactions are read automatically from emails, so they can be wrong or missing, for example when a bank changes its email format or when a foreign-currency conversion is estimated.',
            'Check against your bank statement before relying on the numbers for anything important.',
            'SpendSync is not financial, tax or accounting advice.',
        ],
        'What you must not do' => [
            'Access, or try to access, other people\'s data.',
            'Overload, probe or interfere with the service.',
            'Use the app for anything unlawful.',
        ],
        'Stopping' => [
            'You can disconnect Gmail, delete your data or delete your account at any time in Settings. See the Privacy page for details.',
            'We may suspend accounts that break these terms, or shut the service down. When we can, we will tell you in advance so you have time to delete your data.',
        ],
        'Limitation of liability' => [
            'The service is provided as is. To the extent the law allows, we are not liable for losses caused by wrong or missing numbers or by the service being unavailable.',
        ],
        'Changes to these terms' => [
            'We may update these terms. Important changes will be announced in the app. Continuing to use the app means you accept the new version.',
        ],
    ];
@endphp

<x-privacy-layout title="Terms of use" :sections="$sections">
    <x-slot:intro>
        <p class="text-slate-600 text-sm">Last update: 20/11/2026.</p>
    </x-slot:intro>
</x-privacy-layout>
