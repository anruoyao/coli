@extends('adminLayout::index')

@section('pageContent')
    <div class="mb-6">
        <x-page-title titleText=" {{ __('admin/storage.show_title') }} ({{ $diskId }})"></x-page-title>
        <x-page-desc>
            {{ __('admin/storage.show_desc') }}
        </x-page-desc>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-4">
        <x-metric.card title="{{ __('admin/storage.metrics.image.title') }}" desc="{{ __('admin/storage.metrics.image.description') }}" value="{{ file_size_format($diskStats['image']['content_size']) }}" iconName="image-05"/>
        <x-metric.card title="{{ __('admin/storage.metrics.video.title') }}" desc="{{ __('admin/storage.metrics.video.description') }}" value="{{ file_size_format($diskStats['video']['content_size']) }}" iconName="video-recorder"/>
        <x-metric.card title="{{ __('admin/storage.metrics.audio.title') }}" desc="{{ __('admin/storage.metrics.audio.description') }}" value="{{ file_size_format($diskStats['audio']['content_size']) }}" iconName="recording-03"/>
        <x-metric.card title="{{ __('admin/storage.metrics.document.title') }}" desc="{{ __('admin/storage.metrics.document.description') }}" value="{{ file_size_format($diskStats['document']['content_size']) }}" iconName="file-attachment-05"/>
    </div>
@endsection