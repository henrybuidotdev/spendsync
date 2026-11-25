@props(['url'])
@php([$brandHead, $brandTail] = array_pad(\Illuminate\Support\Str::ucsplit(config('app.name')), 2, ''))
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; color: #020617; font-size: 22px; letter-spacing: -0.05em; text-decoration: none;">
<img src="{{ asset('apple-touch-icon.png') }}" width="32" height="32" alt="" style="border-radius: 22%; height: 32px; vertical-align: middle; width: 32px;">
<span style="vertical-align: middle; margin-left: 8px;"><span style="font-weight: 600;">{{ $brandHead }}</span><span style="font-weight: normal;">{{ $brandTail }}</span></span>
</a>
</td>
</tr>
