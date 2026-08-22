@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/wallet.index_title') }}"></x-page-title>
	<div class="bg-bg-pr rounded-2xl p-2 border border-bord-sc">
        <div class="bg-fill-qt rounded-xl p-6">
            <div class="mb-4 flex">
                <div class="flex-1">
                    <p class="text-par-m text-lab-sc">
                        {{ __('business/wallet.balance_desc') }}
                    </p>
                    <h2 class="text-5xl leading-none tracking-tighter font-bold text-mint mt-1">
                        {{ $walletData->balance->getFormattedAmount() }}
                    </h2>
                </div>
                <div class="shrink-0" x-data="colibriUICode">
                    <div class="text-right flex gap-3">
                        <div class="flex-1">
                            <h4 class="text-par-n font-bold text-lab-pr2" x-ref="code">{{ $walletData->wallet_number }}</h4>
                            <p class="text-par-s text-lab-sc">
                                {{ __('business/wallet.wallet_number') }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            <x-ui.buttons.icon x-show="! copying" iconName="copy-06" iconType="line" color="muted" x-on:click="copy"></x-ui.buttons.icon>
                            <x-ui.buttons.icon x-show="copying" iconName="check-circle" iconType="line" color="success"></x-ui.buttons.icon>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-12">
                <a href="{{ url('wallet') }}">
                    <x-ui.buttons.pill
                        size="xs"
                        variant="accent"
                        type="button"
                    btnText="{{ __('business/wallet.open_wallet_btn') }}"></x-ui.buttons.pill>
                </a>
                <a href="{{ route('business.wallet.create-cashout') }}">
                    <x-ui.buttons.pill
                        size="xs"
                        type="button"
                    btnText="{{ __('business/wallet.request_withdrawal') }}"></x-ui.buttons.pill>
                </a>
            </div>
        </div>
        <div class="px-4 py-4">
            <p class="text-par-s text-lab-sc text-center">
                {!! __('business/wallet.about_wallet_text', [
                    'wallet_name' => config('wallet.name'),
                    'about_link' => config('wallet.about_link')
                ]) !!}
            </p>
        </div>
	</div>

    <div class="mt-6">
        @include('business::wallet.overview.parts.cashouts-table', [
            'cashouts' => $cashouts
        ])
    </div>
@endsection
