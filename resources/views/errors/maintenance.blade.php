@extends('documentLayout::index')

@section('pageContent')
	<div class="flex justify-center">
		<div class="w-full max-w-lg text-center py-20">
			{{-- 维护图标 --}}
			<div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center mb-8" style="background: rgba(64,227,120,.10); border: 1.5px solid rgba(64,227,120,.35);">
				<svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="#40E378" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
				</svg>
			</div>

			<h1 class="text-title-1 font-bold text-lab-pr2 mb-3">
				{{ __('errors.maintenance.title') }}
			</h1>

			<p class="text-par-n text-lab-sc leading-relaxed mb-2">
				{{ $message ?: __('errors.maintenance.default_desc') }}
			</p>

			@if($until)
				<p class="text-par-s text-lab-tr mb-2">
					{{ __('errors.maintenance.until', ['until' => $until]) }}
				</p>
			@endif

			<div class="flex justify-center mt-8">
				<a href="javascript:location.reload()">
					<x-ui.buttons.pill size="sm" btnText="{{ __('errors.maintenance.retry') }}"></x-ui.buttons.pill>
				</a>
			</div>
		</div>
	</div>
@endsection