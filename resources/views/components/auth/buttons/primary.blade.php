@props([
    'variant' => 'default',
    'variantOptions' => [
        'default' => 'bg-brand-900 hover:bg-brand-900/90 text-white',
        'outline' => 'bg-transparent border border-bord-pr hover:bg-fill-fv text-lab-pr2 hover:text-lab-pr',
    ],
    'tag' => 'button'
])

<{{ $tag }} class="block w-full leading-none smoothing rounded-full {{ $variantOptions[$variant] }}" {{ $attributes }}>
    <div class="flex items-center justify-center gap-2 h-12">
        <span class="font-medium text-par-s md:text-par-m">{{ $slot }}</span>

        @if(isset($icon))
            <span class="shrink-0 size-icon">
                {{ $icon }}
            </span>
        @endif
    </div>
</{{ $tag }}>