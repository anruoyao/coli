<?php

namespace App\Livewire\Admin\Config;

use App\Settings\UploadSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Upload extends Component
{
    public array $formData;

    public function mount(): void
    {
        $uploadSettings = app(UploadSettings::class);

        $this->formData = [
            'image_max_mb' => $uploadSettings->image_max_mb,
            'video_max_mb' => $uploadSettings->video_max_mb,
            'audio_max_mb' => $uploadSettings->audio_max_mb,
            'gif_max_mb' => $uploadSettings->gif_max_mb,
            'document_max_mb' => $uploadSettings->document_max_mb,
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.image_max_mb' => ['required', 'integer', 'min:1', 'max:8192'],
            'formData.video_max_mb' => ['required', 'integer', 'min:1', 'max:8192'],
            'formData.audio_max_mb' => ['required', 'integer', 'min:1', 'max:8192'],
            'formData.gif_max_mb' => ['required', 'integer', 'min:1', 'max:8192'],
            'formData.document_max_mb' => ['required', 'integer', 'min:1', 'max:8192'],
        ], attributes: [
            'formData.image_max_mb' => __('admin/config.form.image_max_mb'),
            'formData.video_max_mb' => __('admin/config.form.video_max_mb'),
            'formData.audio_max_mb' => __('admin/config.form.audio_max_mb'),
            'formData.gif_max_mb' => __('admin/config.form.gif_max_mb'),
            'formData.document_max_mb' => __('admin/config.form.document_max_mb'),
        ]);

        $uploadSettings = app(UploadSettings::class);

        $uploadSettings->image_max_mb = $this->formData['image_max_mb'];
        $uploadSettings->video_max_mb = $this->formData['video_max_mb'];
        $uploadSettings->audio_max_mb = $this->formData['audio_max_mb'];
        $uploadSettings->gif_max_mb = $this->formData['gif_max_mb'];
        $uploadSettings->document_max_mb = $this->formData['document_max_mb'];
        $uploadSettings->save();

        return redirect()->route('admin.config.upload')->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get());
    }

    public function render()
    {
        return view('livewire.admin.config.upload');
    }
}
