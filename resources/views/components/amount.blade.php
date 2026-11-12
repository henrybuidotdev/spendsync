@props(['transaction'])

<span {{ $attributes->class(['whitespace-nowrap font-semibold tabular-nums', 'text-emerald-600' => $transaction->isIncome()]) }}>
    {{ \App\Support\Format::money($transaction->signedAmount(), signed: true) }}
</span>
