@props([
    'hasLabel' => true,
    'labelText' => '',
    'name' => '',
    'action' => '',
    'defaultValue' => '',
    'placeholder' => '',
    'options' => [],
])

<div class="block">
    @if ($hasLabel)
        <label class="mb-2 font-medium block text-lab-pr2 text-par-s">
            {{ $labelText }}
        </label>
    @endif

    <div class="block relative">
        <div x-data="{ isOpen: false }" class="cursor-pointer relative {{ (count($options) < 1) ? 'opacity-60 cursor-default' : '' }}">
            <div x-on:click="isOpen = !isOpen" x-on:click.away="isOpen = false" class="w-full bg-input-pr border-none h-12 rounded-lg flex items-center px-4">
                <span class="truncate text-lab-sc text-par-s md:text-par-n">{{ $placeholder }}</span>
                <span class="size-4 text-lab-sc shrink-0 ml-auto">
                    <x-ui-icon name="chevron-selector-vertical" type="solid"></x-ui-icon>
                </span>
            </div>
            @if(count($options))
                <div x-show="isOpen" class="absolute shadow-md py-2 rounded-md top-full left-auto bg-bg-pr/80 backdrop-blur-xs w-60 overflow-y-auto max-h-96">
                    @foreach ($options as $optionItem)
                        <div wire:click="saveSelectOption('{{ $action }}', '{{ $optionItem['key'] }}')" class="px-4 py-2 text-par-n md:text-par-n text-lab-sc hover:bg-fill-qt smoothing">
                            {{ $optionItem['value'] }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
