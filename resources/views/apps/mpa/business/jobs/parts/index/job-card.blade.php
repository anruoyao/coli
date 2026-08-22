
<x-cards.timeline.card>
    <div class="flex flex-col h-full">
        <div class="mb-4">
            <x-cards.timeline.content
                content="{{ $jobData->overview }}"
                link="{{ route('business.jobs.show', ['jobId' => $jobData->id]) }}"
            title="{{ $jobData->title }}"></x-cards.timeline.content>
        </div>
        
        <div class="flex flex-col gap-1 mb-6">
            @if($jobData->approval->isPending())
                <x-cards.timeline.value value="{{ $jobData->approval->label() }} {{ $jobData->approval->emoji() }}"></x-cards.timeline.value>
            @else
                <x-cards.timeline.value value="{{ $jobData->status->label() }} {{ $jobData->status->emoji() }}"></x-cards.timeline.value>
            @endif
    
            @if($jobData->is_start_income)
                <x-cards.timeline.value value="{{ __('labels.income_from', ['amount' => $jobData->formatted_income]) }}"></x-cards.timeline.value>
            @else
                <x-cards.timeline.value value="{{ __('labels.income_to', ['amount' => $jobData->formatted_income]) }}"></x-cards.timeline.value>
            @endif
    
            <x-cards.timeline.value value="{{ $jobData->category_name }}"></x-cards.timeline.value>
            <x-cards.timeline.value value="{{ $jobData->is_remote ? __('business/jobs.remote_work') : $jobData->locations }}"></x-cards.timeline.value>
        </div>
    
        <div class="grid grid-cols-2 gap-4 mt-auto">
            <div class="flex flex-col gap-1">
                <x-cards.timeline.value value="{{ $jobData->created_at->getIso() }}"></x-cards.timeline.value>
            </div>
            <div class="flex flex-col gap-1">
                <x-cards.timeline.value value="{{ __('labels.views') }} {{ $jobData->views_count }}" rightAlign="true"></x-cards.timeline.value>
            </div>
        </div>
    </div>
</x-cards.timeline.card>