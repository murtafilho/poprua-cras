@props(['active'])

<a {{ $attributes->merge(['class' => 'nav-item' . (($active ?? false) ? ' active' : '')]) }}>
    {{ $slot }}
</a>
