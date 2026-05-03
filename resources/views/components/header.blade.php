@props([
	'controls' => null
])

<header class="mb-6 fixed top-0 right-0 left-sidebar-width z-50 bg-transparent backdrop-blur-md">
    <div class="flex items-center py-3 h-16">
		@if(isset($controls))
			<div class="pr-16 ml-auto">
				<div class="flex items-center gap-4 leading-none">
					{{ $controls }}

					<a target="_blank" href="{{ me()->profile_url }}" class="shrink-0 cursor-pointer items-center">
						<div class="size-[40px] rounded-full overflow-hidden">
							<img class="w-full" src="{{ me()->avatar_url }}" alt="Image">
						</div>
					</a>
				</div>
			</div>
		@endif
    </div>
</header>