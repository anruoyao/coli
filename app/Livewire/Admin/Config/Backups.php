<?php

namespace App\Livewire\Admin\Config;
use App\Jobs\System\CreateBackup;
use App\Jobs\System\RestoreDatabaseBackup;
use App\Services\System\DatabaseRestoreService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class Backups extends Component
{
    public Collection $backups;
    public string $error = '';
    public bool $hasError = false;
    public bool $isProcessing = false;
    public bool $isDownloading = false;
    public ?string $backupStatus = null;
    public string $backupError = '';
    public ?string $restoreStatus = null;
    public string $restoreError = '';

    public function mount()
    {
        try {
            $this->loadBackups();
        } catch (Throwable $th) {
            $this->error = $th->getMessage();
            $this->backups = collect([]);
            $this->hasError = true;
        }
    }

    public function loadBackups()
    {
        $state = cache()->get('system:backup:last');
        $this->backupStatus = $state['status'] ?? null;
        $this->backupError = $state['error'] ?? '';

        $this->backups = collect(Storage::disk('local')->files('backups'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'path' => $file,
                'size' => file_size_format(Storage::disk('local')->size($file)),
                'date' => Carbon::parse(Storage::disk('local')->lastModified($file))->format('d M Y, H:i:s'),
            ])
            ->sortByDesc('date')
            ->values();

        $this->checkBackupProcessing();
    }

    public function createBackup()
    {
        try {
            if ($this->isProcessing) {
                $this->error = __('admin/config.validation.backup_processing');
                $this->hasError = true;

                return;
            }

            cache()->forget('system:backup:last');
            cache()->forever('system:backup:processing', true);
            $this->backupStatus = null;
            $this->backupError = '';
            CreateBackup::dispatch();

            $this->error = '';
            $this->hasError = false;
            $this->checkBackupProcessing();
        } catch (Throwable $th) {
            cache()->forget('system:backup:processing');
            $this->backupStatus = 'failed';
            $this->backupError = $th->getMessage();
            $this->error = $th->getMessage();
            $this->hasError = true;
        }
    }

    public function deleteBackup(string $backupName)
    {
        abort_unless(auth_check() && (me()->isAdmin() || me()->isRoot()), 403);

        try {
            $backupName = basename($backupName);
            if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\.zip\z/i', $backupName)) {
                throw new \RuntimeException('Invalid backup filename.');
            }

            $path = "backups/{$backupName}";
            Storage::disk('local')->delete($path);
            $this->backups = $this->backups->filter(fn ($backup) => $backup['name'] !== $backupName);
        } catch (Throwable $th) {
            $this->error = $th->getMessage();
            $this->hasError = true;
        }
    }

    #[On('backup-uploaded')]
    public function backupUploaded(): void
    {
        $this->loadBackups();
    }

    public function restoreBackup(string $backupName): void
    {
        abort_unless(auth_check() && (me()->isAdmin() || me()->isRoot()), 403);

        try {
            if (cache()->has('system:backup:processing') || cache()->has('system:database:restore:last')) {
                $last = cache()->get('system:database:restore:last');
                if (($last['status'] ?? null) === 'running') {
                    throw new \RuntimeException('A database restore is already running.');
                }
            }

            $service = app(DatabaseRestoreService::class);
            $details = $service->validateArchive($service->candidatePath($backupName));
            cache()->put('system:database:restore:last', [
                'status' => 'running',
                'backup' => basename($backupName),
                'database' => $details['database'],
                'started_at' => now()->toIso8601String(),
            ], now()->addDay());
            RestoreDatabaseBackup::dispatch(basename($backupName));
            $this->refreshRestoreStatus();
            $this->restoreStatus ??= 'running';
            $this->restoreError = '';
        } catch (Throwable $th) {
            $this->restoreStatus = 'failed';
            $this->restoreError = $th->getMessage();
        }
    }

    public function refreshRestoreStatus(): void
    {
        $state = cache()->get('system:database:restore:last');
        $this->restoreStatus = $state['status'] ?? null;
        $this->restoreError = $state['error'] ?? '';
    }

    public function render()
    {
        $this->refreshRestoreStatus();

        return view('livewire.admin.config.backups');
    }

    private function checkBackupProcessing()
    {
        $this->isProcessing = cache()->has('system:backup:processing');
    }
}
