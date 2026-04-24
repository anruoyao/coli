@props([
    'textLength' => null,
    'inputType' => 'text',
    'isPassword' => false,
    'placeholder' => '',
    'classes' => '',
    'name' => '',
    'defaultValue' => '',
])

<div class="block" x-data="{ inputText: '{{ $defaultValue }}', inputType: '{{ $inputType }}',  maxLength: {{ (empty($textLength)) ? 0 : intval($textLength) }} }">
    <div class="block relative">
        <input
            x-model.trim="inputText"
            class="block w-full bg-input-pr border-none h-12 rounded-lg outline-hidden text-par-s md:text-par-m text-lab-pr px-4 {{ $classes }}"
            placeholder="{{ $placeholder }}"
            name="{{ $name }}"
            x-bind:type="inputType"
        value="{{ $defaultValue }}" {{ $attributes }}>

        @if (isset($inputIcon))
            <span class="absolute right-0 top-0">
                {{ $inputIcon }}
            </span>
        @endif

        @if ($isPassword)
            <span class="absolute right-0 top-0 bottom-0 inline-flex-center px-4">
                <button x-on:click="inputType = (inputType == 'password' ? 'text' : 'password')" class="size-6 cursor-pointer text-bord-sc outline-hidden" type="button">
                    <template x-if="inputType == 'password'">
                        <x-ui-icon name="eye" type="solid" />
                    </template>
                    <template x-if="inputType == 'text'">
                        <x-ui-icon name="eye-off" type="solid"/>
                    </template>
                </button>
            </span>
        @endif
    </div>

    @if (isset($feedbackInfo))
        <div class="flex justify-between mt-0.5">
            @if (isset($feedbackIcon))
                <span class="mr-2.5">
                    {{ $feedbackIcon }}
                </span>
            @endif
            <span class="text-cap-l text-lab-sc font-normal w-9/12">
                {{ $feedbackInfo }}
            </span>
            @if($textLength)
                <span class="text-cap-l text-lab-sc ml-auto">
                    <span x-text="inputText.length" x-bind:class="(inputText.length > maxLength ? 'text-red-900' : '')"></span>/{{ $textLength }}
                </span>
            @endif
        </div>
    @endif

    @error($name)
        <div class="text-cap-l text-red-900 mt-1">
            {{ $message }}
        </div>
    @enderror
</div>
