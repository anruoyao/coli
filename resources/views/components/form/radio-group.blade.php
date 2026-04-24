@props([
    'hasLabel' => true,
    'labelText' => ''
])

<div class="block">
    @if ($hasLabel)
        <label class="mb-2 font-normal block text-lab-pr3 text-par-s">
            {{ $labelText }}
            @if (!empty($labelTextBrackets))
                <span class="text-lab-sc">({{ $labelTextBrackets }})</span>
            @endif
        </label>
    @endif
    <div class="bg-transparent border border-edge-tr rounded-lg overflow-hidden inline-flex leading-none">
        {{ $slot }}
    </div>
</div>