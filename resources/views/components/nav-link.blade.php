@props(['href', 'icon' => null, 'active' => false, 'target' => null])

<a href="{{ $href }}"
   @if ($target) target="{{ $target }}" @endif
   {{ $active ? 'aria-current=page' : '' }}
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-2 text-sm no-underline transition-colors border-b-2 ' . ($active
        ? 'border-topbar-text text-topbar-text'
        : 'border-transparent text-topbar-muted hover:text-topbar-text')]) }}>
    @if ($icon)
        <i class="ti {{ $icon }}" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</a>
