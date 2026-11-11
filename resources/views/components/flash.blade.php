@if(session('status'))
    <div role="status" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('status') }}
    </div>
@endif
