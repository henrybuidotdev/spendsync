@props(['transaction'])

<span {{ $attributes->class(['whitespace-nowrap font-semibold tabular-nums', 'text-emerald-600' => $transaction->isIncome()]) }}>
    {{ $transaction->amount_estimated ? '≈ ' : '' }}{{ \App\Support\Format::money($transaction->signedAmount(), signed: true) }}
    @if($transaction->currency)
        <span class="block text-xs font-normal text-slate-500">{{ \App\Support\Format::foreign($transaction->original_amount, $transaction->currency) }}</span>
    @endif
</span>
