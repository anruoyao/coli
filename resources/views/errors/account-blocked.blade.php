@extends('documentLayout::index')

@section('pageContent')
	<div class="flex justify-center">
		<div class="w-full max-w-lg text-center py-20">
			{{-- 红色警示图标 --}}
			<div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center mb-8" style="background: rgba(239,68,68,.12); border: 1.5px solid rgba(239,68,68,.35);">
				<svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 2 1 21h22L12 2z"></path>
					<line x1="12" y1="9" x2="12" y2="14"></line>
					<circle cx="12" cy="17.5" r="0.4" fill="#ef4444"></circle>
				</svg>
			</div>

			<h1 class="text-title-1 font-bold text-lab-pr2 mb-3">
				{{ $userStatus === 'suspended' ? __('errors.account_suspended.title') : __('errors.account_blocked.title') }}
			</h1>

			<p class="text-par-n text-lab-sc leading-relaxed mb-2">
				{{ $reason ?: ($userStatus === 'suspended' ? __('errors.account_suspended.desc') : __('errors.account_blocked.desc')) }}
			</p>

			@if($reason)
				<p class="text-par-s text-lab-tr">{{ $userStatus === 'suspended' ? __('errors.account_suspended.desc') : __('errors.account_blocked.desc') }}</p>
			@endif

			<div class="flex justify-center gap-3 mt-8">
				<a href="{{ route('user.auth.logout') }}">
					<x-ui.buttons.pill variant="danger" size="sm" btnText="{{ __('labels.logout') }}"></x-ui.buttons.pill>
				</a>
				@if(config('mail.from.address'))
					<a href="mailto:{{ config('mail.from.address') }}?subject={{ __('errors.account_blocked.subject') }}">
						<x-ui.buttons.pill variant="outline" size="sm" btnText="{{ __('errors.account_blocked.contact') }}"></x-ui.buttons.pill>
					</a>
				@endif
			</div>
		</div>
	</div>
@endsection