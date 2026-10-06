<?php

return [
    'index_title' => 'Media Migration',

    'sections' => [
        'local' => [
            'title' => 'Local Migration (Server Move)',
            'caption' => 'Pack the entire media disk into a downloadable zip archive. On the new server, upload the archive and extract it — files are verified by checksum after extraction.',
        ],
        'cloud' => [
            'title' => 'Disk Migration (S3 / Cloud Storage)',
            'caption' => 'One-click migration of all media files from one disk to another (e.g. local → S3). Database references and storage usage stats are updated automatically. Supports resume and retry.',
        ],
        'static_disk' => [
            'title' => 'Primary Storage Disk',
            'caption' => 'Current primary storage disk: :disk. Avatars, covers and other static resources follow this disk. After migrating to another disk you must switch it here.',
        ],
        'history' => [
            'title' => 'Migration History',
        ],
    ],

    'types' => [
        'archive' => 'Local Archive',
        'extract' => 'Extract & Restore',
        'disk_transfer' => 'Disk Migration',
    ],

    'statuses' => [
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'progress_card' => [
        'caption' => 'Migration in progress. This page refreshes automatically every 3 seconds. Interrupted migrations can be resumed from where they stopped.',
        'percent' => 'Progress',
        'files' => 'Files',
        'bytes' => 'Data',
        'failed' => 'Failed',
        'cancel' => 'Cancel',
        'confirm_cancel' => 'Cancel this migration? Files already migrated are kept.',
    ],

    'local_form' => [
        'disk_label' => 'Disk to archive',
        'create_button' => 'Create Archive',
        'note' => 'The archive uses store (no compression) mode for speed. Files uploaded after the archive starts are not included — run again if needed.',
    ],

    'archives' => [
        'title' => 'Archives',
        'confirm_extract' => 'Extract this archive to the selected disk? Existing files with the same path will be overwritten.',
        'confirm_delete' => 'Delete this archive? This cannot be undone.',
    ],

    'restore' => [
        'title' => 'Upload & Restore (on the new server)',
        'caption' => 'Upload an archive created by this tool. Large files are transferred in 8MB chunks, so archives of any size can be uploaded. After uploading, click the extract icon in the archives list to restore.',
        'disk_placeholder' => 'Extract target disk',
        'select_file' => 'Click to select a .zip archive',
        'select_caption' => 'The archive must be created by this tool (contains an embedded manifest for verification).',
        'uploading' => 'Uploading…',
        'upload_button' => 'Start Upload',
        'reset_button' => 'Reset',
        'done' => 'Upload complete',
    ],

    'cloud_form' => [
        'source_label' => 'Source disk',
        'target_label' => 'Target disk',
        'checksum_label' => 'Full checksum verification (sha1)',
        'checksum_helper' => 'Slower but verifies every copied file byte-by-byte. Enable for critical migrations.',
        'start_button' => 'Start Migration',
        'confirm_start' => 'Start migrating all files from the source disk to the target disk? New uploads landing on the source disk during migration are not included.',
        'note' => 'Target disks must be configured first in <code>var/config/filesystems/disks.php</code> (e.g. an S3 disk). After migration completes: 1) switch the primary storage disk below if needed; 2) optionally remove the old disk from the config so new uploads stop using it. Interrupted or failed migrations resume from where they stopped — just start the same migration again.',
    ],

    'static_disk' => [
        'switch_to' => 'Switch primary disk to',
        'switch_button' => 'Switch',
        'confirm' => 'Switch the primary storage disk? Make sure all media files have been migrated to the new disk first, otherwise avatars and covers will break.',
        'note' => 'Switching updates the <code>STATIC_STORAGE_DISK</code> environment variable and rebuilds the config cache. If URLs do not update within a few minutes, restart php-fpm / Horizon.',
    ],

    'history_table' => [
        'type' => 'Type',
        'status' => 'Status',
        'files' => 'Files',
        'failed' => 'Failed',
        'started_at' => 'Started',
        'confirm_retry' => 'Retry the failed files of this migration?',
    ],

    'report' => [
        'title' => 'Migration Report · #:id',
        'files_done' => 'Files migrated',
        'bytes_done' => 'Data migrated',
        'files_failed' => 'Failed files',
        'duration' => 'Duration',
        'verify_missing' => 'Missing after extract',
        'verify_mismatch' => 'Checksum mismatch',
        'db_rows_updated' => 'DB rows updated',
        'stats_moved' => 'Usage stats moved',
        'failures_sample' => 'Failed files (sample)',
        'failures_full_note' => 'Only the first 50 failures are shown. Download the full report (JSON) via the download button in the history table.',
        'no_failures' => 'No failed files. 🎉',
        'not_available' => 'Report is generated when the migration finishes.',
        'failure_stage' => [
            'scan' => 'Scan',
            'copy' => 'Copy',
            'verify' => 'Verify',
            'extract' => 'Extract',
        ],
    ],

    'flash' => [
        'archive_started' => 'Archive creation started. Progress is tracked below.',
        'extract_started' => 'Extraction started. Progress is tracked below.',
        'transfer_started' => 'Disk migration started. Progress is tracked below.',
        'migration_running' => 'Another migration is already running. Wait for it to finish or cancel it first.',
        'resumed' => 'Migration resumed from where it stopped.',
        'cancelled' => 'Migration cancelled.',
        'retry_started' => 'Retry of failed files started.',
        'archive_uploaded' => 'Archive ":name" uploaded. Click the extract icon to restore it.',
        'archive_deleted' => 'Archive deleted.',
        'archive_not_found' => 'Archive not found.',
        'env_not_writable' => 'The .env file is not writable. Update STATIC_STORAGE_DISK manually.',
        'static_disk_switched' => 'Primary storage disk switched to :disk. Config cache is being rebuilt.',
    ],
];
