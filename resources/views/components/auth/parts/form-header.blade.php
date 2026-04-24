@props([
    'title' => ''
])

<div class="block">
    @if (isset($icon))
        <div class="size-8 overflow-hidden text-lab-pr2 mb-2">
            {!! $icon !!}
        </div>
    @endif

    <h1 class="text-title-2 text-lab-pr2 font-semibold tracking-tighter">
        {!! $title !!}
    </h1>

    @if(isset($caption))
        <p class="text-par-m text-lab-sc">
            {!! $caption !!}
        </p>
    @endif
</div>