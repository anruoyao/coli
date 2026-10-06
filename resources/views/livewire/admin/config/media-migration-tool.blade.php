<div @if($activeMigration) wire:poll.3s @endif class="flex flex-col gap-8">

    {{-- 操作反馈 --}}
    @if($flashContent)
        <div class="flex items-center gap-2 rounded-xl px-4 py-3 {{ $flashType === 'error' ? 'bg-red-500/10 text-red-900' : 'bg-green-500/10 text-green-900' }}">
            <span class="size-4 shrink-0">
                <x-ui-icon name="{{ $flashType === 'error' ? 'alert-triangle' : 'check-circle' }}" type="line" class="size-full"></x-ui-icon>
            </span>
            <span class="text-par-n">{{ $flashContent }}</span>
        </div>
    @endif

    {{-- 迁移进行中：进度卡片 --}}
    @if($activeMigration)
        <div class="border border-brand-900/30 rounded-2xl p-5">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="size-6 text-brand-900 animate-pulse shrink-0">
                        <x-ui-icon name="refresh-cw-04" type="line"></x-ui-icon>
                    </span>
                    <div class="min-w-0">
                        <h5 class="text-lab-pr2 font-semibold text-par-l truncate">
                            {{ __('admin/media-migration.types.'.$activeMigration->type->value) }}
                            @if($activeMigration->type->isDiskTransfer())
                                · {{ $activeMigration->source_disk }} → {{ $activeMigration->target_disk }}
                            @endif
                        </h5>
                        <p class="text-lab-sc text-par-s">{{ __('admin/media-migration.progress_card.caption') }}</p>
                    </div>
                </div>
                <div class="shrink-0">
                    <x-ui.buttons.pill
                        size="xs"
                        variant="danger"
                        btnText="{{ __('admin/media-migration.progress_card.cancel') }}"
                        wire:click="cancelMigration({{ $activeMigration->id }})"
                        wire:confirm="{{ __('admin/media-migration.progress_card.confirm_cancel') }}">
                    </x-ui.buttons.pill>
                </div>
            </div>

            <div class="w-full h-3 bg-fill-tr rounded-full overflow-hidden mb-3">
                <div class="h-full bg-brand-900 smoothing" style="width: {{ $activeMigration->progress_percent }}%"></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-par-s">
                <div>
                    <span class="text-lab-sc block">{{ __('admin/media-migration.progress_card.percent') }}</span>
                    <span class="text-lab-pr2 font-semibold">{{ $activeMigration->progress_percent }}%</span>
                </div>
                <div>
                    <span class="text-lab-sc block">{{ __('admin/media-migration.progress_card.files') }}</span>
                    <span class="text-lab-pr2 font-semibold">{{ number_format($activeMigration->files_done) }} / {{ number_format($activeMigration->files_total) }}</span>
                </div>
                <div>
                    <span class="text-lab-sc block">{{ __('admin/media-migration.progress_card.bytes') }}</span>
                    <span class="text-lab-pr2 font-semibold">{{ file_size_format($activeMigration->bytes_done) }} / {{ file_size_format($activeMigration->bytes_total) }}</span>
                </div>
                <div>
                    <span class="text-lab-sc block">{{ __('admin/media-migration.progress_card.failed') }}</span>
                    <span class="font-semibold {{ $activeMigration->files_failed > 0 ? 'text-red-900' : 'text-lab-pr2' }}">{{ number_format($activeMigration->files_failed) }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- 场景一：本地迁移（换服务器） --}}
    <div>
        <div class="mb-2">
            <h5 class="text-lab-pr2 font-semibold text-par-l">{{ __('admin/media-migration.sections.local.title') }}</h5>
            <p class="text-lab-sc text-par-n tracking-normal">{{ __('admin/media-migration.sections.local.caption') }}</p>
        </div>

        <div class="border border-fill-tr rounded-2xl p-5 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <x-form.select
                    :labelText="__('admin/media-migration.local_form.disk_label')"
                    :options="collect($disks)->where('is_local')->map(fn($disk) => ['key' => $disk['id'], 'value' => $disk['id'].($disk['name'] ? ' ('.$disk['name'].')' : '')])->values()->all()"
                    wire:model="archiveDisk"
                    name="archiveDisk">
                </x-form.select>

                <div>
                    <x-ui.buttons.pill
                        size="sm"
                        btnText="{{ __('admin/media-migration.local_form.create_button') }}"
                        wire:click="createArchive"
                        wire:loading.attr="disabled"
                        wire:target="createArchive">
                    </x-ui.buttons.pill>
                    <p class="text-lab-sc text-par-s mt-2">{{ __('admin/media-migration.local_form.note') }}</p>
                </div>
            </div>
        </div>

        {{-- 压缩包列表 --}}
        <div class="border border-fill-tr rounded-2xl p-5 mb-4">
            <div class="flex items-center justify-between gap-4 mb-4">
                <h6 class="text-lab-pr2 font-semibold text-par-m">{{ __('admin/media-migration.archives.title') }}</h6>
                <div class="w-56">
                    <x-form.select
                        :hasLabel="false"
                        placeholder="{{ __('admin/media-migration.restore.disk_placeholder') }}"
                        :options="collect($disks)->where('is_local')->map(fn($disk) => ['key' => $disk['id'], 'value' => $disk['id']])->values()->all()"
                        wire:model="extractDisk"
                        name="extractDisk">
                    </x-form.select>
                </div>
            </div>

            <x-table.table>
                <x-table.thead>
                    <x-table.th>{{ __('table.labels.name') }}</x-table.th>
                    <x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
                    <x-table.th>{{ __('table.labels.size') }}</x-table.th>
                    <x-table.th></x-table.th>
                </x-table.thead>
                <x-table.tbody>
                    @if(! empty($archives))
                        @foreach ($archives as $archive)
                            <x-table.tr>
                                <x-table.td>{{ $archive['name'] }}</x-table.td>
                                <x-table.td>{{ $archive['date'] }}</x-table.td>
                                <x-table.td variant="strong" weight="medium">{{ $archive['size'] }}</x-table.td>
                                <x-table.td>
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.buttons.icon wire:click="downloadArchive('{{ $archive['name'] }}')" iconName="download-01" iconType="line"></x-ui.buttons.icon>
                                        <x-ui.buttons.icon wire:click="extractArchive('{{ $archive['name'] }}')" wire:confirm="{{ __('admin/media-migration.archives.confirm_extract') }}" iconName="refresh-cw-04" iconType="line"></x-ui.buttons.icon>
                                        <x-ui.buttons.icon wire:click="deleteArchive('{{ $archive['name'] }}')" wire:confirm="{{ __('admin/media-migration.archives.confirm_delete') }}" iconName="trash-04" color="danger" iconType="line"></x-ui.buttons.icon>
                                    </div>
                                </x-table.td>
                            </x-table.tr>
                        @endforeach
                    @else
                        <x-table.empty colspan="4"></x-table.empty>
                    @endif
                </x-table.tbody>
            </x-table.table>
        </div>

        {{-- 上传恢复（新服务器上操作） --}}
        <div class="border border-fill-tr rounded-2xl p-5">
            <h6 class="text-lab-pr2 font-semibold text-par-m mb-1">{{ __('admin/media-migration.restore.title') }}</h6>
            <p class="text-lab-sc text-par-s mb-4">{{ __('admin/media-migration.restore.caption') }}</p>

            <div wire:ignore>
                <div x-data="mediaMigrationUploader()">
                    <div class="border-2 border-dashed border-bord-sc hover:border-bord-card smoothing cursor-pointer rounded-2xl px-4 py-6"
                        x-on:click="$refs.fileInput.click()">
                        <input type="file" accept=".zip" class="hidden" x-ref="fileInput" x-on:change="pick($event)">

                        <div class="text-center" x-show="!uploading && !done">
                            <div class="text-lab-pr flex justify-center mb-2">
                                <div class="size-6 text-green-900">
                                    <x-ui-icon name="cloud-blank-01" type="line"></x-ui-icon>
                                </div>
                            </div>
                            <h5 class="text-lab-pr2 font-medium text-title-3 mb-1" x-text="fileName || '{{ __('admin/media-migration.restore.select_file') }}'"></h5>
                            <p class="text-lab-sc text-par-n tracking-normal">{{ __('admin/media-migration.restore.select_caption') }}</p>
                        </div>

                        <div class="text-center" x-show="uploading" x-cloak>
                            <h5 class="text-lab-pr2 font-medium text-title-3 mb-2">{{ __('admin/media-migration.restore.uploading') }}</h5>
                            <div class="w-full max-w-md mx-auto h-3 bg-fill-tr rounded-full overflow-hidden mb-2">
                                <div class="h-full bg-brand-900" :style="`width: ${progress}%`"></div>
                            </div>
                            <p class="text-lab-sc text-par-s" x-text="progress + '%'"></p>
                        </div>

                        <div class="text-center" x-show="done" x-cloak>
                            <div class="text-lab-pr flex justify-center mb-2">
                                <div class="size-6 text-green-900">
                                    <x-ui-icon name="check-circle" type="line"></x-ui-icon>
                                </div>
                            </div>
                            <h5 class="text-lab-pr2 font-medium text-title-3 mb-1">{{ __('admin/media-migration.restore.done') }}</h5>
                            <p class="text-lab-sc text-par-n tracking-normal" x-text="uploadedName"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mt-4" x-show="fileName && !uploading && !done" x-cloak>
                        <x-ui.buttons.pill size="xs" btnText="{{ __('admin/media-migration.restore.upload_button') }}" x-on:click="start()" type="button"></x-ui.buttons.pill>
                        <x-ui.buttons.pill size="xs" variant="outline" btnText="{{ __('admin/media-migration.restore.reset_button') }}" x-on:click="reset()" type="button"></x-ui.buttons.pill>
                    </div>

                    <p class="text-red-900 text-par-s mt-3" x-show="error" x-text="error" x-cloak></p>
                </div>
            </div>
        </div>
    </div>

    {{-- 场景二：磁盘迁移（S3） --}}
    <div>
        <div class="mb-2">
            <h5 class="text-lab-pr2 font-semibold text-par-l">{{ __('admin/media-migration.sections.cloud.title') }}</h5>
            <p class="text-lab-sc text-par-n tracking-normal">{{ __('admin/media-migration.sections.cloud.caption') }}</p>
        </div>

        <div class="border border-fill-tr rounded-2xl p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form.select
                    :labelText="__('admin/media-migration.cloud_form.source_label')"
                    :options="collect($disks)->map(fn($disk) => ['key' => $disk['id'], 'value' => $disk['id'].($disk['name'] ? ' ('.$disk['name'].')' : '')])->values()->all()"
                    wire:model="sourceDisk"
                    name="sourceDisk">
                </x-form.select>

                <x-form.select
                    :labelText="__('admin/media-migration.cloud_form.target_label')"
                    :options="collect($disks)->reject(fn($disk) => $disk['id'] === $sourceDisk)->map(fn($disk) => ['key' => $disk['id'], 'value' => $disk['id'].($disk['name'] ? ' ('.$disk['name'].')' : '').($disk['is_s3'] ? ' · S3' : '')])->values()->all()"
                    wire:model="targetDisk"
                    name="targetDisk">
                </x-form.select>
            </div>

            <div class="mt-4">
                <x-form.switcher
                    labelText="{{ __('admin/media-migration.cloud_form.checksum_label') }}"
                    wire:model="checksumVerify"
                    name="checksumVerify">
                </x-form.switcher>
                <p class="text-lab-sc text-par-s mt-1">{{ __('admin/media-migration.cloud_form.checksum_helper') }}</p>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-ui.buttons.pill
                    size="sm"
                    variant="accent"
                    btnText="{{ __('admin/media-migration.cloud_form.start_button') }}"
                    wire:click="startTransfer"
                    wire:confirm="{{ __('admin/media-migration.cloud_form.confirm_start') }}"
                    wire:loading.attr="disabled"
                    wire:target="startTransfer">
                </x-ui.buttons.pill>
            </div>

            <div class="mt-4 rounded-xl bg-fill-fv px-4 py-3">
                <p class="text-lab-sc text-par-s leading-relaxed">{!! __('admin/media-migration.cloud_form.note') !!}</p>
            </div>
        </div>
    </div>

    {{-- 主存储磁盘切换 --}}
    <div>
        <div class="mb-2">
            <h5 class="text-lab-pr2 font-semibold text-par-l">{{ __('admin/media-migration.sections.static_disk.title') }}</h5>
            <p class="text-lab-sc text-par-n tracking-normal">{{ __('admin/media-migration.sections.static_disk.caption', ['disk' => $staticDisk]) }}</p>
        </div>

        <div class="border border-fill-tr rounded-2xl p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <x-form.select
                    :labelText="__('admin/media-migration.static_disk.switch_to')"
                    :options="collect($disks)->map(fn($disk) => ['key' => $disk['id'], 'value' => $disk['id'].($disk['is_s3'] ? ' · S3' : '')])->values()->all()"
                    wire:model="staticDiskChoice"
                    name="staticDiskChoice">
                </x-form.select>

                <div>
                    <x-ui.buttons.pill
                        size="sm"
                        btnText="{{ __('admin/media-migration.static_disk.switch_button') }}"
                        wire:click="switchStaticDisk"
                        wire:confirm="{{ __('admin/media-migration.static_disk.confirm') }}"
                        wire:loading.attr="disabled"
                        wire:target="switchStaticDisk">
                    </x-ui.buttons.pill>
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-fill-fv px-4 py-3">
                <p class="text-lab-sc text-par-s leading-relaxed">{!! __('admin/media-migration.static_disk.note') !!}</p>
            </div>
        </div>
    </div>

    {{-- 迁移历史 --}}
    <div>
        <div class="mb-2">
            <h5 class="text-lab-pr2 font-semibold text-par-l">{{ __('admin/media-migration.sections.history.title') }}</h5>
        </div>

        <x-table.table>
            <x-table.thead>
                <x-table.th>#</x-table.th>
                <x-table.th>{{ __('admin/media-migration.history_table.type') }}</x-table.th>
                <x-table.th>{{ __('admin/media-migration.history_table.status') }}</x-table.th>
                <x-table.th>{{ __('admin/media-migration.history_table.files') }}</x-table.th>
                <x-table.th>{{ __('admin/media-migration.history_table.failed') }}</x-table.th>
                <x-table.th>{{ __('admin/media-migration.history_table.started_at') }}</x-table.th>
                <x-table.th></x-table.th>
            </x-table.thead>
            <x-table.tbody>
                @if($history->isNotEmpty())
                    @foreach ($history as $item)
                        <x-table.tr>
                            <x-table.td>{{ $item->id }}</x-table.td>
                            <x-table.td>
                                {{ __('admin/media-migration.types.'.$item->type->value) }}
                                @if($item->type->isDiskTransfer())
                                    <span class="text-lab-sc text-par-s block">{{ $item->source_disk }} → {{ $item->target_disk }}</span>
                                @endif
                            </x-table.td>
                            <x-table.td>
                                @if($item->status->isRunning())
                                    <span class="text-green-900 font-medium">{{ __('admin/media-migration.statuses.running') }}</span>
                                @elseif($item->status->isCompleted())
                                    <span class="font-medium">{{ __('admin/media-migration.statuses.completed') }}</span>
                                @elseif($item->status === \App\Enums\Media\MediaMigrationStatus::FAILED)
                                    <span class="text-red-900 font-medium">{{ __('admin/media-migration.statuses.failed') }}</span>
                                @else
                                    <span class="text-lab-sc font-medium">{{ __('admin/media-migration.statuses.cancelled') }}</span>
                                @endif
                            </x-table.td>
                            <x-table.td>
                                <span class="block">{{ number_format($item->files_done) }} / {{ number_format($item->files_total) }}</span>
                                <span class="text-lab-sc text-par-s block">{{ $item->progress_percent }}%</span>
                            </x-table.td>
                            <x-table.td>
                                <span class="{{ $item->files_failed > 0 ? 'text-red-900 font-semibold' : '' }}">{{ number_format($item->files_failed) }}</span>
                            </x-table.td>
                            <x-table.td>
                                {{ optional($item->started_at)->format('d M Y, H:i') }}
                            </x-table.td>
                            <x-table.td>
                                <div class="flex items-center justify-end gap-2">
                                    @if($item->status->isRunning())
                                        <x-ui.buttons.icon wire:click="cancelMigration({{ $item->id }})" wire:confirm="{{ __('admin/media-migration.progress_card.confirm_cancel') }}" iconName="x-close" color="danger" iconType="line"></x-ui.buttons.icon>
                                    @endif

                                    @if($item->status === \App\Enums\Media\MediaMigrationStatus::FAILED && $item->type->isDiskTransfer())
                                        <x-ui.buttons.icon wire:click="resumeMigration({{ $item->id }})" iconName="refresh-cw-04" iconType="line"></x-ui.buttons.icon>
                                    @endif

                                    @if($item->status->isCompleted() && $item->type->isDiskTransfer() && $item->hasFailures())
                                        <x-ui.buttons.icon wire:click="retryFailed({{ $item->id }})" wire:confirm="{{ __('admin/media-migration.history_table.confirm_retry') }}" iconName="refresh-cw-04" iconType="line"></x-ui.buttons.icon>
                                    @endif

                                    <x-ui.buttons.icon wire:click="showReport({{ $item->id }})" iconName="eye" iconType="line"></x-ui.buttons.icon>
                                    <x-ui.buttons.icon wire:click="downloadReport({{ $item->id }})" iconName="download-01" iconType="line"></x-ui.buttons.icon>
                                </div>
                            </x-table.td>
                        </x-table.tr>
                    @endforeach
                @else
                    <x-table.empty colspan="7"></x-table.empty>
                @endif
            </x-table.tbody>
        </x-table.table>

        {{-- 报告详情 --}}
        @if($reportMigration)
            <div class="border border-fill-tr rounded-2xl p-5 mt-4">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <h6 class="text-lab-pr2 font-semibold text-par-m">
                        {{ __('admin/media-migration.report.title', ['id' => $reportMigration->id]) }}
                    </h6>
                    <x-ui.buttons.icon wire:click="showReport({{ $reportMigration->id }})" iconName="x-close" iconType="line"></x-ui.buttons.icon>
                </div>

                @if($reportMigration->error)
                    <div class="mb-4 rounded-xl bg-red-500/10 px-4 py-3">
                        <p class="text-red-900 text-par-s break-all">{{ $reportMigration->error }}</p>
                    </div>
                @endif

                @if($reportMigration->report)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-par-s mb-4">
                        <div>
                            <span class="text-lab-sc block">{{ __('admin/media-migration.report.files_done') }}</span>
                            <span class="text-lab-pr2 font-semibold">{{ number_format($reportMigration->report['files_done'] ?? 0) }} / {{ number_format($reportMigration->report['files_total'] ?? 0) }}</span>
                        </div>
                        <div>
                            <span class="text-lab-sc block">{{ __('admin/media-migration.report.bytes_done') }}</span>
                            <span class="text-lab-pr2 font-semibold">{{ file_size_format($reportMigration->report['bytes_done'] ?? 0) }}</span>
                        </div>
                        <div>
                            <span class="text-lab-sc block">{{ __('admin/media-migration.report.files_failed') }}</span>
                            <span class="font-semibold {{ ($reportMigration->report['files_failed'] ?? 0) > 0 ? 'text-red-900' : 'text-lab-pr2' }}">{{ number_format($reportMigration->report['files_failed'] ?? 0) }}</span>
                        </div>
                        <div>
                            <span class="text-lab-sc block">{{ __('admin/media-migration.report.duration') }}</span>
                            <span class="text-lab-pr2 font-semibold">{{ ($reportMigration->report['duration_seconds'] ?? 0) >= 60 ? floor(($reportMigration->report['duration_seconds']) / 60).'m '.($reportMigration->report['duration_seconds'] % 60).'s' : ($reportMigration->report['duration_seconds'] ?? 0).'s' }}</span>
                        </div>

                        @if($reportMigration->type->isExtract())
                            <div>
                                <span class="text-lab-sc block">{{ __('admin/media-migration.report.verify_missing') }}</span>
                                <span class="font-semibold {{ ($reportMigration->report['verify_missing'] ?? 0) > 0 ? 'text-red-900' : 'text-lab-pr2' }}">{{ number_format($reportMigration->report['verify_missing'] ?? 0) }}</span>
                            </div>
                            <div>
                                <span class="text-lab-sc block">{{ __('admin/media-migration.report.verify_mismatch') }}</span>
                                <span class="font-semibold {{ ($reportMigration->report['verify_mismatch'] ?? 0) > 0 ? 'text-red-900' : 'text-lab-pr2' }}">{{ number_format($reportMigration->report['verify_mismatch'] ?? 0) }}</span>
                            </div>
                        @endif

                        @if($reportMigration->type->isDiskTransfer())
                            <div>
                                <span class="text-lab-sc block">{{ __('admin/media-migration.report.db_rows_updated') }}</span>
                                <span class="text-lab-pr2 font-semibold">{{ number_format($reportMigration->report['db_rows_updated'] ?? 0) }}</span>
                            </div>
                            <div>
                                <span class="text-lab-sc block">{{ __('admin/media-migration.report.stats_moved') }}</span>
                                <span class="text-lab-pr2 font-semibold">{{ ! empty($reportMigration->report['stats_moved']) ? __('labels.yes') : __('labels.no') }}</span>
                            </div>
                        @endif
                    </div>

                    @if(! empty($reportMigration->report['failures_sample']))
                        <div class="mb-2">
                            <h6 class="text-lab-pr2 font-semibold text-par-s">{{ __('admin/media-migration.report.failures_sample') }}</h6>
                        </div>
                        <div class="max-h-64 overflow-y-auto rounded-xl border border-bord-tr divide-y divide-bord-tr">
                            @foreach ($reportMigration->report['failures_sample'] as $failure)
                                <div class="px-4 py-2">
                                    <p class="text-par-s text-lab-pr break-all">{{ $failure['p'] ?? '' }}</p>
                                    <p class="text-par-s text-lab-sc">{{ __('admin/media-migration.report.failure_stage.'.($failure['stage'] ?? 'copy')) }}: {{ $failure['e'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-lab-sc text-par-s mt-2">{{ __('admin/media-migration.report.failures_full_note') }}</p>
                    @else
                        <p class="text-lab-sc text-par-s">{{ __('admin/media-migration.report.no_failures') }}</p>
                    @endif
                @else
                    <p class="text-lab-sc text-par-s">{{ __('admin/media-migration.report.not_available') }}</p>
                @endif
            </div>
        @endif
    </div>
</div>
