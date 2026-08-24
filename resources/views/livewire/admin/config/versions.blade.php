<div>
    @if ($mode === 'list')
        <div class="mb-4 flex items-center justify-between">
            <p class="text-par-n text-lab-sc">{{ __('admin/version.list_helper') }}</p>
            <x-ui.buttons.pill size="sm" btnText="{{ __('admin/version.actions.create') }}" wire:click="openCreate">
            </x-ui.buttons.pill>
        </div>

        {{-- 最低安全版本配置：低于该线的客户端请求将被 426 强制更新 --}}
        <div class="mb-6 p-4 rounded-2xl bg-input-pr border border-bord-sc">
            <form wire:submit.prevent="saveMinVersion">
                <div class="mb-3">
                    <p class="text-par-m font-medium text-lab-pr">{{ __('admin/version.min_supported.title') }}</p>
                    <p class="text-par-s text-lab-sc mt-1">{{ __('admin/version.min_supported.helper') }}</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <x-form.text-input
                        labelText="{{ __('admin/version.min_supported.android') }}"
                        type="text"
                        wire:model="minVersionData.android"
                        name="minVersionData.android"
                        :placeholder="'1.5.0'">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.min_supported.android_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                    <x-form.text-input
                        labelText="{{ __('admin/version.min_supported.ios') }}"
                        type="text"
                        wire:model="minVersionData.ios"
                        name="minVersionData.ios"
                        :placeholder="'1.5.0'">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.min_supported.ios_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </div>
                <div class="mt-3">
                    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('admin/version.min_supported.save') }}"></x-ui.buttons.pill>
                </div>
            </form>
        </div>

        <x-table.table>
            <x-table.thead>
                <x-table.th>{{ __('admin/version.table.version') }}</x-table.th>
                <x-table.th>{{ __('admin/version.table.platform') }}</x-table.th>
                <x-table.th>{{ __('admin/version.table.download') }}</x-table.th>
                <x-table.th>{{ __('admin/version.table.forced') }}</x-table.th>
                <x-table.th>{{ __('admin/version.table.status') }}</x-table.th>
                <x-table.th>{{ __('admin/version.table.released_at') }}</x-table.th>
                <x-table.th>{{ __('labels.table.actions') }}</x-table.th>
            </x-table.thead>
            <x-table.tbody>
                @forelse($versions as $version)
                    <x-table.tr>
                        <x-table.td variant="strong" weight="medium">
                            <span class="flex items-center gap-2">
                                <span class="font-mono">v{{ $version->code }}</span>
                            </span>
                        </x-table.td>
                        <x-table.td>
                            <x-badge :variant="$version->platform === 'ios' ? 'warning' : 'success'">
                                {{ $version->platform === 'ios' ? 'iOS' : 'Android' }}
                            </x-badge>
                        </x-table.td>
                        <x-table.td variant="muted">
                            <a href="{{ $version->download_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 text-brand-900 hover:underline break-all max-w-64 truncate">
                                <span class="size-icon-small shrink-0">
                                    <x-ui-icon name="download-01" type="line"></x-ui-icon>
                                </span>
                                {{ $version->download_url }}
                            </a>
                        </x-table.td>
                        <x-table.td>
                            <x-form.switcher
                                :checked="$version->is_forced"
                                wire:click="toggleForced({{ $version->id }})">
                            </x-form.switcher>
                        </x-table.td>
                        <x-table.td>
                            <div class="flex items-center gap-2">
                                <x-form.switcher
                                    :checked="$version->is_active"
                                    wire:click="toggleActive({{ $version->id }})">
                                </x-form.switcher>
                                <x-badge :variant="$version->is_active ? 'success' : 'default'">
                                    {{ $version->is_active ? __('admin/version.table.online') : __('admin/version.table.offline') }}
                                </x-badge>
                            </div>
                        </x-table.td>
                        <x-table.td variant="muted">
                            {{ $version->released_at?->format('Y-m-d H:i') ?? __('admin/version.table.immediate') }}
                        </x-table.td>
                        <x-table.td>
                            <div class="flex justify-end gap-1">
                                <x-ui.buttons.icon iconName="edit-03" iconType="line" :color="'strong'"
                                    wire:click="openEdit({{ $version->id }})">
                                </x-ui.buttons.icon>
                                <x-ui.buttons.icon iconName="trash-04" iconType="line" :color="'danger'"
                                    wire:click="delete({{ $version->id }})"
                                    wire:confirm="{{ __('admin/version.prompts.delete.content') }}">
                                </x-ui.buttons.icon>
                            </div>
                        </x-table.td>
                    </x-table.tr>
                @empty
                    <x-table.empty colspan="7" :message="__('admin/version.table.empty')"></x-table.empty>
                @endforelse
            </x-table.tbody>
        </x-table.table>

        <div class="mt-4">
            <x-info.cache-notice></x-info.cache-notice>
        </div>
    @else
        <form wire:submit.prevent="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form.group>
                    <x-form.text-input
                        labelText="{{ __('admin/version.form.code') }}"
                        type="text"
                        wire:model="formData.code"
                        name="formData.code"
                        :placeholder="'2.1.0'">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.form.code_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </x-form.group>

                <x-form.group>
                    <x-form.select
                        labelText="{{ __('admin/version.form.platform') }}"
                        wire:model="formData.platform"
                        name="formData.platform"
                        :options="[['key' => 'android', 'value' => 'Android'], ['key' => 'ios', 'value' => 'iOS']]">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.form.platform_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.select>
                </x-form.group>

                <x-form.group class="md:col-span-2">
                    <x-form.text-input
                        labelText="{{ __('admin/version.form.download_url') }}"
                        type="text"
                        wire:model="formData.download_url"
                        name="formData.download_url"
                        :placeholder="'https://...'">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.form.download_url_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </x-form.group>

                <x-form.group class="md:col-span-2">
                    <x-form.text-input
                        asText
                        labelText="{{ __('admin/version.form.notes') }}"
                        wire:model="formData.notes"
                        name="formData.notes"
                        :placeholder="__('admin/version.form.notes_placeholder')">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.form.notes_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </x-form.group>

                <x-form.group>
                    <x-form.text-input
                        labelText="{{ __('admin/version.form.released_at') }}"
                        inputType="datetime-local"
                        wire:model="formData.released_at"
                        name="formData.released_at">
                        <x-slot:feedbackInfo>
                            {{ __('admin/version.form.released_at_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </x-form.group>

                <x-form.group>
                    <div class="flex flex-col gap-4 pt-7">
                        <x-form.switcher
                            :labelText="__('admin/version.form.is_forced')"
                            :checked="$formData['is_forced']"
                            wire:model="formData.is_forced">
                        </x-form.switcher>
                        <p class="text-par-s text-lab-sc -mt-2">{{ __('admin/version.form.is_forced_helper') }}</p>
                        <x-form.switcher
                            :labelText="__('admin/version.form.is_active')"
                            :checked="$formData['is_active']"
                            wire:model="formData.is_active">
                        </x-form.switcher>
                        <p class="text-par-s text-lab-sc -mt-2">{{ __('admin/version.form.is_active_helper') }}</p>
                    </div>
                </x-form.group>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-ui.buttons.pill size="sm" type="submit" btnText="{{ $editingId ? __('buttons.update') : __('buttons.save_changes') }}"></x-ui.buttons.pill>
                <x-ui.buttons.pill size="sm" variant="outline" type="button" btnText="{{ __('admin/version.actions.back') }}" wire:click="backToList"></x-ui.buttons.pill>
            </div>
        </form>
    @endif
</div>