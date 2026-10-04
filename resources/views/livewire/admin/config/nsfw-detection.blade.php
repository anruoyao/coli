<div class="flex flex-col gap-6">
    {{-- 操作反馈 --}}
    @if($flashContent)
        <div class="flex items-center gap-2 rounded-xl px-4 py-3 {{ $flashType === 'error' ? 'bg-red-500/10 text-red-900' : 'bg-green-500/10 text-green-900' }}">
            <span class="size-4 shrink-0">
                <x-ui-icon name="{{ $flashType === 'error' ? 'alert-triangle' : 'check-circle' }}" type="line" class="size-full"></x-ui-icon>
            </span>
            <span class="text-par-n">
                {{ $flashContent }}
            </span>
        </div>
    @endif

    {{-- 功能总开关 --}}
    <x-form.group>
        <x-callout.default
            iconName="shield-02"
            titleText="{{ __('admin/config.callout.nsfw_detection_enabled.title') }}"
        captionText="{{ __('admin/config.callout.nsfw_detection_enabled.caption') }}" />

        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model="featureEnabled"
            name="featureEnabled">
        </x-form.switcher>
    </x-form.group>

    <div class="mb-2">
        <x-div/>
    </div>

    {{-- 检测服务连通性测试 --}}
    <x-form.group>
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <x-form.label>
                    {{ __('admin/config.form.nsfw_service_status') }}
                </x-form.label>
                <x-form.helper-text>
                    {{ __('admin/config.form.nsfw_service_status_helper', ['url' => config('services.nsfw_detection.url')]) }}
                </x-form.helper-text>
            </div>
            <x-ui.buttons.pill
                size="sm"
                type="button"
                btnText="{{ __('admin/config.form.nsfw_test_connection') }}"
                wire:click="testConnection"
                wire:loading.attr="disabled">
            </x-ui.buttons.pill>
        </div>
    </x-form.group>

    <div class="mb-2">
        <x-div/>
    </div>

    {{-- 检测参数 --}}
    <x-form.group>
        <x-form.label>
            {{ __('admin/config.form.nsfw_threshold') }}
        </x-form.label>
        <x-form.helper-text>
            {{ __('admin/config.form.nsfw_threshold_helper') }}
        </x-form.helper-text>

        <input
            type="number"
            step="0.05"
            min="0.1"
            max="0.99"
            class="block w-40 bg-input-pr tracking-normal outline-hidden text-par-m text-lab-pr px-4 h-12 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing"
            wire:model="threshold"
            name="threshold">

        @error('threshold')
            <div class="mt-2">
                <x-form.valerr>
                    {{ $message }}
                </x-form.valerr>
            </div>
        @enderror
    </x-form.group>

    <x-form.group>
        <x-form.label>
            {{ __('admin/config.form.nsfw_trigger_labels') }}
        </x-form.label>
        <x-form.helper-text>
            {{ __('admin/config.form.nsfw_trigger_labels_helper') }}
        </x-form.helper-text>

        <textarea
            rows="6"
            class="block w-full bg-input-pr tracking-normal outline-hidden text-par-m text-lab-pr px-4 py-3 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing font-mono"
            wire:model="triggerLabels"
            name="triggerLabels">{{ $triggerLabels }}</textarea>

        @error('triggerLabels')
            <div class="mt-2">
                <x-form.valerr>
                    {{ $message }}
                </x-form.valerr>
            </div>
        @enderror
    </x-form.group>

    <x-form.group>
        <x-callout.default
            iconName="bell-01"
            titleText="{{ __('admin/config.callout.nsfw_notify_author.title') }}"
        captionText="{{ __('admin/config.callout.nsfw_notify_author.caption') }}" />

        <x-form.switcher
            labelText="{{ __('admin/config.form.nsfw_notify_author') }}"
            wire:model="notifyAuthor"
            name="notifyAuthor">
        </x-form.switcher>
    </x-form.group>

    <div class="flex justify-start">
        <x-ui.buttons.pill
            size="sm"
            type="button"
            btnText="{{ __('buttons.save_changes') }}"
            wire:click="saveSettings"
            wire:loading.attr="disabled">
        </x-ui.buttons.pill>
    </div>

    <div class="mb-2">
        <x-div/>
    </div>

    {{-- 配置修改日志 --}}
    <div class="block">
        <div class="mb-4">
            <x-form.label>
                {{ __('admin/config.form.report_change_logs') }}
            </x-form.label>
            <x-form.helper-text>
                {{ __('admin/config.form.report_change_logs_helper') }}
            </x-form.helper-text>
        </div>

        <div class="flex flex-col gap-2">
            @forelse($changeLogs as $log)
                <div class="flex items-center justify-between gap-4 rounded-xl bg-fill-fv px-4 py-3">
                    <div class="min-w-0">
                        <span class="text-par-n text-lab-pr">
                            {{ __('admin/config.form.nsfw_log_action_' . $log->action) }}
                        </span>
                        @if(! empty($log->changes))
                            <span class="text-par-s text-lab-tr break-all">
                                {{ json_encode($log->changes, JSON_UNESCAPED_UNICODE) }}
                            </span>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-par-s text-lab-sc">{{ $log->admin?->username ?? __('admin/config.form.report_log_system') }}</div>
                        <div class="text-par-s text-lab-tr">{{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-bord-pr px-4 py-6 text-center">
                    <span class="text-par-n text-lab-sc">{{ __('admin/config.form.report_logs_empty') }}</span>
                </div>
            @endforelse
        </div>
    </div>
</div>
