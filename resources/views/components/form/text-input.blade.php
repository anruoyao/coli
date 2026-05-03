@props([
    'asText' => false,
    'hasLabel' => true,
    'labelText' => '',
    'labelTextBrackets' => '',
    'isReadonly' => false,
    'inputType' => 'text',
    'isPassword' => false,
    'placeholder' => '',
    'classes' => '',
    'name' => '',
    'errorKey' => null,
    'value' => ''
])

<div class="block">
    @if ($hasLabel)
        <label class="mb-1 font-normal block text-lab-pr3 text-par-s">
            {{ $labelText }}
            @if (!empty($labelTextBrackets))
                <span class="text-lab-sc">({{ $labelTextBrackets }})</span>
            @endif
        </label>
    @endif

    @if ($asText)
        <div class="block relative group">
            <textarea
                x-ref="input"
                class="block w-full bg-input-pr read-only:opacity-90 tracking-normal min-h-24 placeholder:font-light outline-hidden text-par-s text-lab-pr px-5 py-4 rounded-xl {{ $classes }}"
                placeholder="{{ $placeholder }}"
            name="{{ $name }}" {{ $attributes }}>{{ $value }}</textarea>

            <div class="size-4 absolute right-0 bottom-px bg-input-pr text-lab-tr inline-block group-hover:hidden">
                <x-ui-icon name="dots-arrow" type="solid"></x-ui-icon>
            </div>
        </div>
    @else
        <div class="block relative">
            <input
                x-ref="input"
                class="block w-full bg-input-pr read-only:opacity-50 read-only:cursor-not-allowed tracking-normal placeholder:font-light outline-hidden text-par-s text-lab-pr px-5 py-4 rounded-xl {{ $classes }}"
                placeholder="{{ $placeholder }}"
                name="{{ $name }}"
                type="{{ $inputType }}"
                @if ($isReadonly)
                    readonly
                @endif
                x-on:input="inputTextLength = $event.target.value.length"
            value="{{ $value }}" {{ $attributes }}>

            @if (isset($inputIcon))
                <span class="absolute right-0 top-0">
                    {{ $inputIcon }}
                </span>
            @endif

            @if ($isPassword)
                <span class="absolute right-0 top-0">
                    <button class="size-6 cursor-pointer" type="button">
                        @if ($inputType == 'password')
                            <x-ui-icon name="eye" type="solid" classes="size-full text-bord-sc" />
                        @else
                            <x-ui-icon name="eye-off" type="solid" classes="size-full text-bord-sc" />
                        @endif
                    </button>
                </span>
            @endif
        </div>
    @endif

    @if($errors->has($errorKey ?? $name))
        @error($errorKey ?? $name)
            <x-form.valerr>  
                {{ $message }}
            </x-form.valerr>
        @enderror
    @else
        @if (isset($feedbackInfo))
            <div class="flex justify-between mt-0.5 px-1">
                @if (isset($feedbackIcon))
                    <span class="mr-2.5">
                        {{ $feedbackIcon }}
                    </span>
                @endif
                <span class="text-cap-l text-lab-sc font-normal tracking-normal w-9/12">
                    {{ $feedbackInfo }}
                </span>
            </div>
        @endif
    @endif
</div>
