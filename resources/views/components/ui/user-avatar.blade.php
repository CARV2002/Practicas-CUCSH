@props(['user', 'size' => 'h-10 w-10 text-sm'])

<span {{ $attributes->class(['bg-brand-50 text-brand-600 inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-bold', $size]) }}>
    @if ($user->foto_url)
        <img src="{{ $user->foto_url }}" alt="{{ $user->name }}" loading="lazy" class="h-full w-full object-cover" />
    @else
        {{ $user->iniciales }}
    @endif
</span>