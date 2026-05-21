@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidthPx = [
    'sm' => '384px',
    'md' => '448px',
    'lg' => '512px',
    'xl' => '576px',
    '2xl' => '672px',
][$maxWidth] ?? '672px';
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.style.overflow = 'hidden';
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.style.overflow = '';
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    class="modal-overlay"
    style="display: {{ $show ? 'flex' : 'none' }}; opacity: 1; visibility: visible;"
>
    <div
        x-show="show"
        x-on:click="show = false"
        style="position: absolute; inset: 0;"
    ></div>

    <div
        x-show="show"
        class="modal-container"
        style="max-width: {{ $maxWidthPx }}; transform: translateY(0) scale(1);"
        x-transition:enter="transition-fast"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-fast"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        {{ $slot }}
    </div>
</div>
