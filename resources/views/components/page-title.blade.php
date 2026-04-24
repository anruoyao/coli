@props([
    'titleText' => '',
    'backButton' => true
])

<div class="block">
    @if($backButton)
        <a href="{{ url()->previous() }}" class="flex-center size-12 bg-bg-pr shadow-lg rounded-full mb-4 smoothing hover:text-brand-900">
            <div class="size-icon-normal text-lab-sc">
                <x-ui-icon name="arrow-left" type="solid"></x-ui-icon>
            </div>
        </a>
    @endif
    <h2 class="text-title-1 tracking-tight text-lab-pr2 font-bold">
        {!! $titleText !!}
    </h2>
</div>