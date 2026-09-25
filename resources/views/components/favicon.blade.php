@props(['url'])

@php
    $src = ! empty($url) ? app(\App\Services\FaviconService::class)->url($url) : null;
@endphp

@if ($src)
    <img src="{{ $src }}" width="16" height="16" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" {{ $attributes->class('inline-block size-4 shrink-0 rounded-sm align-[-2px] me-1') }}>
@endif
