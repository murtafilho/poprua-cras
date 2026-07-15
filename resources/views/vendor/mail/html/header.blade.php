@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
{{-- PNG em vez do SVG canônico: Gmail e Outlook não renderizam SVG. --}}
<img src="{{ asset('icons/icon-192x192.png') }}" class="logo" alt="{{ config('app.brand', 'SIZEM') }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
