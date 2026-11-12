@props(['src', 'alt' => '', 'sizes' => '(max-width: 880px) 100vw, 50vw', 'eager' => false])
@php
    $src = ltrim((string) $src, '/');
    $small = preg_replace('/\.webp$/', '-640.webp', $src);
    $hasSmall = $small !== $src && is_file(public_path($small));
    $dims = is_file(public_path($src)) ? @getimagesize(public_path($src)) : null;
@endphp
@if ($src !== '')
<img src="{{ asset($src) }}" @if ($hasSmall) srcset="{{ asset($small) }} 640w, {{ asset($src) }} {{ $dims[0] ?? 1600 }}w" sizes="{{ $sizes }}" @endif
     alt="{{ $alt }}" @if ($dims) width="{{ $dims[0] }}" height="{{ $dims[1] }}" @endif
     @if ($eager) fetchpriority="high" decoding="sync" @else loading="lazy" decoding="async" @endif {{ $attributes }}>
@endif
