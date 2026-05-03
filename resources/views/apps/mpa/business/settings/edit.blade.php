@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-4">
        <x-page-title titleText="{{ __('business/settings.edit_title') }}"></x-page-title>
    </div>
	<x-content>
		@livewire('business.account.edit')
	</x-content>
@endsection
