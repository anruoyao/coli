<div @if($this->restoreStatus === 'running') wire:poll.5s="refreshRestoreStatus" @endif>
    @if($this->restoreStatus === 'running')
        <div class="mb-4 rounded-xl px-4 py-3 bg-yellow-500/10 text-yellow-900">
            {{ __('admin/config.restore.running') }}
        </div>
    @elseif($this->restoreStatus === 'completed')
        <div class="mb-4 rounded-xl px-4 py-3 bg-green-500/10 text-green-900">
            {{ __('admin/config.restore.completed') }}
        </div>
    @elseif($this->restoreStatus === 'failed')
        <div class="mb-4 rounded-xl px-4 py-3 bg-red-500/10 text-red-900">
            {{ __('admin/config.restore.failed') }} {{ $this->restoreError }}
        </div>
    @endif

    <div wire:click="createBackup" @if($this->isProcessing) wire:poll.60s="loadBackups" @endif class="border-2 border-dashed border-bord-sc hover:border-bord-card smoothing cursor-pointer rounded-2xl px-4 py-6 mb-8">
        <div class="text-center">
            <div class="text-lab-pr flex justify-center mb-2">
                <div class="size-6 text-green-900 {{ $this->isProcessing ? 'animate-pulse' : '' }}">
                    <x-ui-icon name="database-01" type="solar"></x-ui-icon>
                </div>
            </div>
            <h5 class="text-lab-pr2 font-medium text-title-3 mb-1 {{ $this->isProcessing ? 'animate-pulse' : '' }}">
                @if($this->isProcessing)
                    {{ __('admin/config.callout.backup_processing.title') }}
                @else
                    {{ __('admin/config.callout.new_backup.title') }}
                @endif
            </h5>
            <p class="text-lab-sc text-par-n tracking-normal">
                @if($this->isProcessing)
                    {{ __('admin/config.callout.backup_processing.caption') }}
                @else
                    {{ __('admin/config.callout.new_backup.caption') }}
                @endif
            </p>
        </div>
    </div>

    @if($this->hasError)
        <div class="mb-4">
            <x-form.valerr>{{ $this->error }}</x-form.valerr>
        </div>
    @endif

    <div class="mb-2">
        <h5 class="text-lab-pr2 font-semibold text-par-l">{{ __('admin/config.backup_history') }}</h5>
    </div>

    <x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.name') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
            <x-table.th>{{ __('table.labels.size') }}</x-table.th>
            <x-table.th></x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($this->backups->isNotEmpty())
				@foreach ($this->backups as $backupData)
                    <x-table.tr>
                        <x-table.td>
                            {{ $backupData['name'] }}
                        </x-table.td>
                        <x-table.td>
                            {{ $backupData['date'] }}
                        </x-table.td>
                        <x-table.td variant="strong" weight="medium">
                            {{ $backupData['size'] }}
                        </x-table.td>
                        <x-table.td>
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.config.backup.download', ['backupName' => $backupData['name']]) }}" class="size-8 rounded-full inline-flex-center outline-hidden hover:bg-fill-tr cursor-pointer text-lab-pr3" aria-label="{{ __('labels.download') }}">
                                    <span class="size-icon-normal">
                                        <x-ui-icon name="download-01" type="line"></x-ui-icon>
                                    </span>
                                </a>
                                <x-ui.buttons.icon :disabled="$this->isDownloading" wire:click="restoreBackup('{{ $backupData['name'] }}')" wire:confirm="{{ __('admin/config.restore.confirm') }}" iconName="refresh-cw-04" iconType="line"></x-ui.buttons.icon>
                                <x-ui.buttons.icon :disabled="$this->isDownloading" wire:click="deleteBackup('{{ $backupData['name'] }}')" iconName="trash-04" color="danger" iconType="line"></x-ui.buttons.icon>
                            </div>
                        </x-table.td>
                    </x-table.tr>
				@endforeach
			@else
				<x-table.empty colspan="4"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

    <div class="mt-8 border border-fill-tr rounded-2xl p-5" wire:ignore>
        <h5 class="text-lab-pr2 font-semibold text-par-l mb-1">{{ __('admin/config.restore.title') }}</h5>
        <p class="text-lab-sc text-par-s mb-4">{{ __('admin/config.restore.caption') }}</p>
        <div x-data="databaseBackupUploader()">
            <input type="file" accept=".zip" x-ref="file" class="hidden" x-on:change="pick($event)">
            <div class="border-2 border-dashed border-bord-sc rounded-2xl px-4 py-6 text-center cursor-pointer" x-on:click="$refs.file.click()">
                <p class="text-lab-pr2" x-text="name || '{{ __('admin/config.restore.select') }}'"></p>
                <p class="text-lab-sc text-par-s mt-1" x-show="uploading" x-text="progress + '%'" x-cloak></p>
                <p class="text-red-900 text-par-s mt-2" x-show="error" x-text="error" x-cloak></p>
            </div>
            <div class="flex gap-3 mt-4" x-show="name && !uploading && !done" x-cloak>
                <x-ui.buttons.pill size="xs" btnText="{{ __('admin/config.restore.upload') }}" x-on:click="start()" type="button"></x-ui.buttons.pill>
                <x-ui.buttons.pill size="xs" variant="outline" btnText="{{ __('admin/config.restore.reset') }}" x-on:click="reset()" type="button"></x-ui.buttons.pill>
            </div>
            <p class="text-green-900 text-par-s mt-3" x-show="done" x-text="uploaded" x-cloak></p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('databaseBackupUploader', () => ({
                file: null, name: '', uploading: false, progress: 0, error: '', done: false, uploaded: '',
                pick(event) {
                    this.file = event.target.files[0] || null;
                    this.name = this.file?.name || '';
                    this.error = '';
                    this.done = false;
                },
                async start() {
                    if (!this.file || this.uploading) return;
                    this.uploading = true;
                    this.error = '';
                    this.progress = 0;
                    const chunkSize = 8 * 1024 * 1024;
                    const total = Math.ceil(this.file.size / chunkSize);
                    const uploadId = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())).replace(/[^a-zA-Z0-9]/g, '').slice(0, 32);
                    const url = @json(route('admin.config.backup.upload'));
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    try {
                        for (let index = 0; index < total; index++) {
                            const body = new FormData();
                            body.append('action', 'chunk');
                            body.append('upload_id', uploadId);
                            body.append('chunk_index', index);
                            body.append('total_chunks', total);
                            body.append('file', this.file.slice(index * chunkSize, (index + 1) * chunkSize));
                            const response = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body });
                            const data = await response.json();
                            if (!response.ok || !data.ok) throw new Error(data.message || 'Upload failed.');
                            this.progress = Math.round(((index + 1) / total) * 95);
                        }
                        const body = new FormData();
                        body.append('action', 'complete');
                        body.append('upload_id', uploadId);
                        body.append('filename', this.file.name);
                        const response = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body });
                        const data = await response.json();
                        if (!response.ok || !data.ok) throw new Error(data.message || 'Archive validation failed.');
                        this.progress = 100;
                        this.done = true;
                        this.uploaded = data.name;
                        window.Livewire?.dispatch('backup-uploaded');
                    } catch (error) {
                        this.error = error.message || 'Upload failed.';
                    } finally {
                        this.uploading = false;
                    }
                },
                reset() {
                    this.file = null; this.name = ''; this.uploading = false; this.progress = 0;
                    this.error = ''; this.done = false; this.uploaded = '';
                },
            }));
        });
    </script>
</div>
