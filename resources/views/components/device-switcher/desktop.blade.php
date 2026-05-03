<div class="block fixed bg-bg-pr inset-0 overflow-hidden lg:hidden z-[1000]">
	<div class="flex flex-col h-full">
		<div class="flex items-center justify-center mb-6 h-14 border-b border-bord-pr">
			<div class="w-7">
				<img src="{{ $logotypeUrl }}" alt="{{ config('app.name') }}" class="w-full">
			</div>
		</div>
		
		<div class="flex-1 px-6 text-center mt-12">
			<div class="flex justify-center mb-2">
				<div class="size-icon-medium text-lab-pr2">
					<x-ui-icon name="tablet-02" type="line"></x-ui-icon>
				</div>
			</div>
			<h4 class="text-title-2 text-lab-pr2 font-bold tracking-tighter mb-1">
				{{ __('switcher.desktop.title') }}
			</h4>
			<p class="text-par-l text-lab-sc mb-4">
				{{ __('switcher.desktop.description') }}
			</p>
		</div>
		<div class="px-6 pb-6">
			<a href="{{ route('device.switch', 'mobile') }}" class="block max-w-[400px] mx-auto">
				<x-ui.buttons.pill variant="accent" btnText="{{ __('switcher.desktop.button') }}" width="w-full"></x-ui.buttons.pill>
			</a>
		</div>
	</div>
</div>