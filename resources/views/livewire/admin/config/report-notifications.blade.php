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

    {{-- 通知总开关 --}}
    <x-form.group>
        <x-callout.default
            iconName="bell-01"
            titleText="{{ __('admin/config.callout.report_notification_enabled.title') }}"
        captionText="{{ __('admin/config.callout.report_notification_enabled.caption') }}" />

        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model.live="featureEnabled"
            wire:change="toggleFeature"
            name="featureEnabled">
        </x-form.switcher>
    </x-form.group>

    <div class="mb-2">
        <x-div/>
    </div>

    {{-- 举报限流规则说明（只读） --}}
    <x-form.group>
        <x-callout.default
            iconName="alert-01"
            titleText="{{ __('admin/config.callout.report_rate_limit.title') }}"
        captionText="{{ __('admin/config.callout.report_rate_limit.caption', ['limit' => $rateLimit['max'], 'window' => $rateLimit['window']]) }}" />
    </x-form.group>

    <div class="mb-2">
        <x-div/>
    </div>

    {{-- 管理员邮箱配置 --}}
    <div class="block">
        <div class="mb-4">
            <x-form.label>
                {{ __('admin/config.form.report_admin_emails') }}
            </x-form.label>
            <x-form.helper-text>
                {{ __('admin/config.form.report_admin_emails_helper') }}
            </x-form.helper-text>
        </div>

        <div class="flex items-start gap-3">
            <div class="flex-1">
                <input
                    type="email"
                    class="block w-full bg-input-pr tracking-normal outline-hidden text-par-m text-lab-pr px-4 h-12 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing"
                    placeholder="{{ __('admin/config.form.report_admin_email_placeholder') }}"
                    wire:model="newEmail"
                    wire:keydown.enter.prevent="addEmail"
                    name="newEmail">

                @error('newEmail')
                    <div class="mt-2">
                        <x-form.valerr>
                            {{ $message }}
                        </x-form.valerr>
                    </div>
                @enderror
            </div>
            <x-ui.buttons.pill
                size="sm"
                type="button"
                btnText="{{ __('admin/config.form.report_add_email') }}"
                wire:click="addEmail"
                wire:loading.attr="disabled">
            </x-ui.buttons.pill>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            @forelse($emails as $email)
                <div class="flex items-center gap-4 rounded-xl border border-bord-pr px-4 py-3">
                    @if($editingId === $email->id)
                        <div class="flex-1">
                            <input
                                type="email"
                                class="block w-full bg-input-pr tracking-normal outline-hidden text-par-m text-lab-pr px-4 h-10 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing"
                                wire:model="editingEmail"
                                wire:keydown.enter.prevent="updateEmail"
                                name="editingEmail">

                            @error('editingEmail')
                                <div class="mt-2">
                                    <x-form.valerr>
                                        {{ $message }}
                                    </x-form.valerr>
                                </div>
                            @enderror
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.buttons.pill size="xs" type="button" btnText="{{ __('buttons.save_changes') }}" wire:click="updateEmail"></x-ui.buttons.pill>
                            <x-ui.buttons.pill size="xs" type="button" variant="outline" btnText="{{ __('buttons.cancel') }}" wire:click="cancelEditing"></x-ui.buttons.pill>
                        </div>
                    @else
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-par-n text-lab-pr truncate">{{ $email->email }}</span>
                                @unless($email->enabled)
                                    <span class="text-par-s text-lab-tr">({{ __('admin/config.form.report_email_disabled') }})</span>
                                @endunless
                            </div>
                            <div class="text-par-s text-lab-tr truncate">
                                {{ __('admin/config.form.report_email_masked') }}: {{ $email->masked_email }}
                            </div>
                        </div>
                        <x-form.switcher
                            wire:click="toggleEmail({{ $email->id }})"
                            name="enabled_{{ $email->id }}"
                            :checked="$email->enabled"
                            class="shrink-0">
                        </x-form.switcher>
                        <div class="flex items-center gap-1 shrink-0">
                            <x-ui.buttons.pill size="xs" type="button" variant="outline" btnText="{{ __('admin/config.form.report_test_email') }}" wire:click="sendTestEmail({{ $email->id }})"></x-ui.buttons.pill>
                            <x-ui.buttons.pill size="xs" type="button" variant="outline" btnText="{{ __('buttons.edit') }}" wire:click="startEditing({{ $email->id }})"></x-ui.buttons.pill>
                            <x-ui.buttons.pill size="xs" type="button" variant="danger" btnText="{{ __('buttons.delete') }}" wire:click="removeEmail({{ $email->id }})" wire:confirm="{{ __('admin/config.form.report_email_delete_confirm') }}"></x-ui.buttons.pill>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-bord-pr px-4 py-8 text-center">
                    <span class="text-par-n text-lab-sc">{{ __('admin/config.form.report_emails_empty') }}</span>
                </div>
            @endforelse
        </div>
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
                            {{ __('admin/config.form.report_log_action_' . $log->action) }}
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
