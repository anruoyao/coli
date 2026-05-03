@extends('businessLayout::index')

@section('pageContent')
	<div class="mb-6">
		<x-page-title titleText="{{ __('business/wallet.index_title') }}"></x-page-title>
	</div>
	<div class="block bg-bg-pr rounded-2xl p-6">
		<div class="mb-4 flex">
			<div class="flex-1">
				<h2 class="text-5xl 2xl:text-7xl leading-none tracking-tighter font-bold text-lab-pr2">
					{{ $walletData->balance->getFormattedAmount() }}
				</h2>
				<p class="text-par-n text-lab-sc">
					{{ __('business/wallet.balance_desc') }}
				</p>
			</div>
			<div class="shrink-0 text-right">
				<p class="text-par-n font-semibold text-lab-pr2">{{ $walletData->wallet_number }}</p>
				<p class="text-par-s text-lab-sc">
					{{ __('business/wallet.wallet_number') }}
				</p>
			</div>
		</div>

		<p class="text-par-s text-lab-sc">
			{!! __('business/wallet.about_wallet_text', [
				'wallet_name' => config('wallet.name'),
				'about_link' => config('wallet.about_link')
			]) !!}
		</p>

		<div class="block mt-12">
			<a href="{{ url('wallet') }}">
				<x-ui.buttons.pill
					type="button"
				btnText="{{ __('business/wallet.open_wallet_btn') }}"></x-ui.buttons.pill>
			</a>
		</div>
	</div>
@endsection
