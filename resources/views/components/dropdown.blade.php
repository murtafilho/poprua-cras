@props(['align' => 'right', 'width' => '48', 'contentClasses' => ''])

<div class="dropdown" x-data="{ open: false }" x-cloak @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition-fast"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-fast"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="dropdown-menu {{ $align === 'left' ? 'left: 0; right: auto;' : '' }}"
            style="display: none; {{ $align === 'left' ? 'left: 0; right: auto;' : '' }} opacity: 1; visibility: visible; transform: translateY(0);"
            @click="open = false">
        {{ $content }}
    </div>
</div>
