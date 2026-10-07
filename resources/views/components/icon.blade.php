@props(['name', 'size' => 18])
@switch($name)
    @case('eye')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" fill="currentColor"/></svg>
        @break
    @case('search')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        @break
    @case('pin')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
        @break
    @case('chat')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
        @break
    @case('phone')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        @break
    @case('mail')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><rect x="2.5" y="4.5" width="19" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m3 6 9 7 9-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        @break
    @case('clock')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('check')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><path d="M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('camera')
        <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" {{ $attributes }}><path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="13" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
        @break
@endswitch
