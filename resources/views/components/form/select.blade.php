@props([
    'hasLabel' => true,
    'multiple' => false,
    'labelText' => '',
    'labelTextBrackets' => '',
    'classes' => '',
    'name' => '',
    'defaultValue' => '',
    'placeholder' => '',
    'options' => [],
])

<div class="block">
    @if ($hasLabel)
        <label class="mb-1 font-normal block text-lab-pr3 text-par-s px-1">
            {{ $labelText }}
            @if (!empty($labelTextBrackets))
                <span class="text-lab-sc">({{ $labelTextBrackets }})</span>
            @endif
        </label>
    @endif

    <div class="block relative">
        <select
            class="block w-full {{ $multiple ? 'h-52' : '' }} cursor-pointer bg-input-pr outline-hidden text-par-s text-lab-pr2 px-5 py-4 rounded-xl {{ $classes }}"
            name="{{ $name }}"
        {{ $multiple ? 'multiple' : '' }} {{ $attributes }}>
            @if ($placeholder && empty($defaultValue))
                <option value="" disabled selected>{{ $placeholder }}</option>
            @endif
            
            @foreach ($options as $optionItem)
                <option value="{{ $optionItem['key'] }}" {{ ($defaultValue == $optionItem['key']) ? 'selected' : ''}}>{!! $optionItem['value'] !!}</option>
            @endforeach
        </select>

        @unless($multiple)
            <span class="absolute right-0 top-1 bottom-1 inline-flex-center text-lab-sc opacity-60 px-4">
                <span class="size-4">
                    <x-ui-icon name="chevron-selector-vertical" type="solid"></x-ui-icon>
                </span>
            </span>
        @endif
    </div>

    @if (isset($feedbackInfo))
        <div class="flex justify-between mt-0.5">
            <span class="inline-flex">
                @if (isset($feedbackIcon))
                    <span class="mr-2.5">
                        {{ $feedbackIcon }}
                    </span>
                @endif
                <span class="text-cap-l text-lab-sc font-normal">
                    {{ $feedbackInfo }}
                </span>
            </span>
        </div>
    @else
        @error($name)
            <div class="text-cap-l text-red-900 mt-1">
                {{ $message }}
            </div>
        @enderror
    @endif
</div>
