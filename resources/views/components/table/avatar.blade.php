@props([
    'avatarSrc' => null,
	'name' => null,
	'link' => '#',
])

<a href="{{ $link }}" class="flex items-center gap-2 max-w-40">
	<div class="size-7 overflow-hidden shrink-0 rounded-full border border-bord-pr">
		<x-general.avatars.img src="{{ $avatarSrc }}" alt="{{ $name }}" class="size-full object-cover" />
	</div>
	<div class="text-par-s text-lab-pr2 truncate">{{ $name }}</div>
</a>
