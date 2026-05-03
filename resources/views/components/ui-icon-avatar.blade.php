@props([
	'name' => '',
])

<div class="size-8 rounded-full overflow-hidden">
	<img src="{{ asset("assets/icons/avatar/{$name}.png") }}" alt="Icon" class="size-full">
</div>