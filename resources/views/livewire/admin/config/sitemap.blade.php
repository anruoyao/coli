<div>
    @if($actionMessage)
        <div class="mb-6 p-4 rounded-2xl bg-input-pr border border-brand-900">
            <p class="text-par-m text-lab-pr">{{ $actionMessage }}</p>
        </div>
    @endif

    {{-- ============ 总览 ============ --}}
    <div class="mb-6 p-5 rounded-2xl bg-input-pr border border-bord-sc">
        <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
            <div>
                <p class="text-par-m font-semibold text-lab-pr">{{ __('admin/sitemap.overview.title') }}</p>
                <p class="text-par-s text-lab-sc mt-1">{{ __('admin/sitemap.overview.helper') }}</p>
            </div>
            <a href="{{ $stats['index'] }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-brand-900 hover:underline text-par-s">
                <span class="size-icon-small shrink-0">
                    <x-ui-icon name="external-link" type="line"></x-ui-icon>
                </span>
                {{ $stats['index'] }}
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl bg-bg-pr border border-bord-sc p-3">
                <p class="text-par-s text-lab-sc">{{ __('admin/sitemap.overview.status') }}</p>
                <p class="text-par-m font-semibold text-lab-pr mt-1">
                    {{ $stats['enabled'] ? __('admin/sitemap.overview.on') : __('admin/sitemap.overview.off') }}
                </p>
            </div>
            <div class="rounded-xl bg-bg-pr border border-bord-sc p-3">
                <p class="text-par-s text-lab-sc">{{ __('admin/sitemap.overview.last_generated') }}</p>
                <p class="text-par-m font-semibold text-lab-pr mt-1">
                    {{ $stats['last_generated_at'] ? \Illuminate\Support\Carbon::parse($stats['last_generated_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i') : __('admin/sitemap.overview.never') }}
                </p>
            </div>
            <div class="rounded-xl bg-bg-pr border border-bord-sc p-3">
                <p class="text-par-s text-lab-sc">{{ __('admin/sitemap.overview.total_urls') }}</p>
                <p class="text-par-m font-semibold text-lab-pr mt-1">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="rounded-xl bg-bg-pr border border-bord-sc p-3">
                <p class="text-par-s text-lab-sc">{{ __('admin/sitemap.overview.chunks') }}</p>
                <p class="text-par-m font-semibold text-lab-pr mt-1">{{ array_sum($stats['chunks']) }}</p>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-4 flex-wrap">
            @foreach($stats['type_labels'] as $type => $label)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-bg-pr border border-bord-sc px-3 py-1 text-par-s text-lab-sc">
                    {{ $label }}
                    <b class="text-lab-pr">{{ number_format($stats['per_type'][$type] ?? 0) }}</b>
                </span>
            @endforeach
        </div>
    </div>

    {{-- ============ 设置表单 ============ --}}
    <form wire:submit.prevent="submitForm">
            {{-- 基础设置 --}}
            <p class="text-par-m font-semibold text-lab-pr mb-4">{{ __('admin/sitemap.sections.basic') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form.group>
                    <x-form.switcher
                        labelText="{{ __('admin/sitemap.form.enabled') }}"
                        wire:model="formData.enabled"
                        name="formData.enabled">
                        <x-slot:feedbackInfo>
                            {{ __('admin/sitemap.form.enabled_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.switcher>
                </x-form.group>

                <x-form.group>
                    <x-form.switcher
                        labelText="{{ __('admin/sitemap.form.seo_head_enabled') }}"
                        wire:model="formData.seo_head_enabled"
                        name="formData.seo_head_enabled">
                        <x-slot:feedbackInfo>
                            {{ __('admin/sitemap.form.seo_head_enabled_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.switcher>
                </x-form.group>

                <x-form.group>
                    <x-form.text-input
                        labelText="{{ __('admin/sitemap.form.per_page') }}"
                        inputType="number"
                        wire:model="formData.per_page"
                        name="formData.per_page">
                        <x-slot:feedbackInfo>
                            {{ __('admin/sitemap.form.per_page_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </x-form.group>

                <x-form.group>
                    <x-form.text-input
                        labelText="{{ __('admin/sitemap.form.cache_ttl') }}"
                        inputType="number"
                        wire:model="formData.cache_ttl"
                        name="formData.cache_ttl">
                        <x-slot:feedbackInfo>
                            {{ __('admin/sitemap.form.cache_ttl_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </x-form.group>
            </div>

            <div class="my-6">
                <x-div/>
            </div>

            {{-- 收录内容设置 --}}
            <p class="text-par-m font-semibold text-lab-pr mb-4">{{ __('admin/sitemap.sections.inclusion') }}</p>
            <p class="text-par-s text-lab-sc mb-6">{{ __('admin/sitemap.sections.inclusion_helper') }}</p>

            @foreach($typeRows as $row)
                <div class="mb-6 p-4 rounded-2xl bg-input-pr border border-bord-sc">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <x-form.switcher
                                :labelText="$row['label']"
                                wire:model="formData.{{ $row['include_key'] }}">
                            </x-form.switcher>
                        </div>
                        <div class="md:col-span-3">
                            <x-form.text-input
                                :labelText="__('admin/sitemap.form.limit')"
                                inputType="number"
                                wire:model="formData.{{ $row['limit_key'] }}"
                                :name="$row['limit_key']">
                            </x-form.text-input>
                        </div>
                        <div class="md:col-span-3">
                            <x-form.select
                                :labelText="__('admin/sitemap.form.changefreq')"
                                :options="$changefreqOptions"
                                wire:model="formData.{{ $row['changefreq_key'] }}"
                                :name="$row['changefreq_key']">
                            </x-form.select>
                        </div>
                        <div class="md:col-span-3">
                            <x-form.text-input
                                :labelText="__('admin/sitemap.form.priority')"
                                wire:model="formData.{{ $row['priority_key'] }}"
                                :name="$row['priority_key']">
                            </x-form.text-input>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="my-6">
                <x-div/>
            </div>

            {{-- 排除规则 --}}
            <p class="text-par-m font-semibold text-lab-pr mb-4">{{ __('admin/sitemap.sections.exclusion') }}</p>

            <x-form.group>
                <x-form.text-input
                    asText
                    labelText="{{ __('admin/sitemap.form.excluded_paths') }}"
                    wire:model="formData.excluded_paths"
                    name="formData.excluded_paths"
                    :placeholder="'/settings'">
                    <x-slot:feedbackInfo>
                        {{ __('admin/sitemap.form.excluded_paths_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </x-form.group>

            <div class="my-6">
                <x-div/>
            </div>

            {{-- robots.txt --}}
            <p class="text-par-m font-semibold text-lab-pr mb-4">{{ __('admin/sitemap.sections.robots') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form.group>
                    <x-form.switcher
                        labelText="{{ __('admin/sitemap.form.robots_sitemap_line') }}"
                        wire:model="formData.robots_sitemap_line"
                        name="formData.robots_sitemap_line">
                        <x-slot:feedbackInfo>
                            {{ __('admin/sitemap.form.robots_sitemap_line_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.switcher>
                </x-form.group>
            </div>

            <x-form.group class="mt-4">
                <x-form.text-input
                    asText
                    labelText="{{ __('admin/sitemap.form.robots_custom') }}"
                    wire:model="formData.robots_custom"
                    name="formData.robots_custom"
                    :placeholder="'User-agent: *'">
                    <x-slot:feedbackInfo>
                        {{ __('admin/sitemap.form.robots_custom_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </x-form.group>

            @if($robotsContent !== '')
                <div class="mt-4">
                    <p class="text-par-s text-lab-sc mb-2">{{ __('admin/sitemap.form.robots_preview') }}</p>
                    <pre class="rounded-xl bg-bg-pr border border-bord-sc p-4 text-par-s text-lab-pr overflow-x-auto">{{ $robotsContent }}</pre>
                </div>
            @endif

            <div class="mt-8">
                <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
            </div>
    </form>

    {{-- ============ 操作区（重生成 / 搜索引擎推送） ============ --}}
    <div class="mt-8 p-5 rounded-2xl bg-input-pr border border-bord-sc">
        <p class="text-par-m font-semibold text-lab-pr mb-1">{{ __('admin/sitemap.sections.actions') }}</p>
        <p class="text-par-s text-lab-sc mb-4">{{ __('admin/sitemap.sections.actions_helper') }}</p>

        <div class="flex items-center gap-3 flex-wrap">
            <x-ui.buttons.pill
                size="sm"
                variant="outline"
                type="button"
                btnText="{{ __('admin/sitemap.actions.regenerate') }}"
                wire:click="regenerate">
            </x-ui.buttons.pill>

            <x-ui.buttons.pill
                size="sm"
                variant="outline"
                type="button"
                btnText="{{ __('admin/sitemap.actions.ping_google') }}"
                wire:click="pingGoogle">
            </x-ui.buttons.pill>

            <x-ui.buttons.pill
                size="sm"
                variant="outline"
                type="button"
                btnText="{{ __('admin/sitemap.actions.ping_bing') }}"
                wire:click="pingBing">
            </x-ui.buttons.pill>
        </div>

        <div class="mt-4 space-y-1 text-par-s text-lab-sc">
            <p>{{ __('admin/sitemap.actions.google_pinged') }}: {{ $stats['settings_google_pinged'] ?? '—' }}</p>
            <p>{{ __('admin/sitemap.actions.bing_pinged') }}: {{ $stats['settings_bing_pinged'] ?? '—' }}</p>
        </div>
    </div>
</div>