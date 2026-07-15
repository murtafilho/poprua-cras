@props([
    'alt' => '',
])

{{-- Logo canônica SIZEM Campo (v2 icon-a2) — mesma do ícone Android / splash. --}}
<img
    src="{{ asset(config('app.logo', 'images/brand/v2/sizem-icon-a2.svg')) }}"
    alt="{{ $alt }}"
    decoding="async"
    {{ $attributes->class('app-logo') }}
>
