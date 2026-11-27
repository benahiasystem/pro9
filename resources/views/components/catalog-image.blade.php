@props(['small' => null, 'original', 'eager', 'missing' => null])
@php($src = $small ?: $original)
<img {{ $attributes }} data-catalog-src="{{ $src }}" data-catalog-original="{{ $original }}"
     data-catalog-missing="{{ $missing }}" data-catalog-eager="{{ $eager ? 1 : 0 }}"
     @if($eager) src="{{ $src }}" @endif>
@if(!$eager)<noscript><img {{ $attributes }} src="{{ $original }}" loading="lazy"></noscript>@endif
