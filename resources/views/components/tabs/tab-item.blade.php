@props([
	'active' => false,
	'textLabel' => '',
	'link' => ''
])

<a {{ $attributes }} class="rounded-full px-5 py-4.5 leading-zero cursor-pointer {{ $active ? 'bg-lab-pr2 text-bg-pr' : 'bg-badge-pr text-lab-pr2'}}">
	<span class="text-par-n font-medium">
		{{ $textLabel }}
	</span>
</a>