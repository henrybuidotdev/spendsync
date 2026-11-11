@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-11 rounded-xl border-slate-300 text-base shadow-sm focus:border-emerald-500 focus:ring-emerald-500']) }}>
