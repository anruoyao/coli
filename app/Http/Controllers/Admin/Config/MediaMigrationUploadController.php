<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\MediaMigration\MediaArchiveService;
use App\Services\MediaMigration\MediaMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 媒体迁移压缩包分片上传（换服务器场景：新服务器上传大体积 zip）。
 *
 * 普通表单/Livewire 上传受 PHP upload_max_filesize 限制（700MB），
 * 分片上传（默认 8MB/片）可传输任意体积的压缩包。
 * 路由位于 admin-area 中间件组（web + admin），自动受 CSRF 与管理员权限保护。
 */
class MediaMigrationUploadController extends Controller
{
    // 单片最大 20MB
    private const MAX_CHUNK_SIZE = 20 * 1024 * 1024;

    private MediaMigrationService $migrationService;

    private MediaArchiveService $archiveService;

    public function __construct(MediaMigrationService $migrationService, MediaArchiveService $archiveService)
    {
        $this->migrationService = $migrationService;
        $this->archiveService = $archiveService;
    }

    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required', 'in:chunk,complete'],
            'upload_id' => ['required', 'alpha_num', 'max:64'],
            'filename' => ['required_if:action,complete', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            return $request->input('action') === 'complete'
                ? $this->completeUpload($request)
                : $this->storeChunk($request);
        } catch (Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    private function storeChunk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chunk_index' => ['required', 'integer', 'min:0', 'max:999999'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $chunk = $request->file('file');

        if (! $chunk || ! $chunk->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid chunk file.',
            ], 422);
        }

        if ($chunk->getSize() > self::MAX_CHUNK_SIZE) {
            return response()->json([
                'ok' => false,
                'message' => 'Chunk exceeds size limit.',
            ], 422);
        }

        $uploadDir = MediaMigrationService::WORK_DIR.'/uploads/'.$request->input('upload_id');

        $disk = Storage::disk('local');

        if (! $disk->exists($uploadDir)) {
            $disk->makeDirectory($uploadDir);
        }

        $chunk->storeAs($uploadDir, $request->input('chunk_index').'.part', 'local');

        return response()->json(['ok' => true]);
    }

    private function completeUpload(Request $request): JsonResponse
    {
        $filename = basename($request->input('filename'));

        if (! str_ends_with(strtolower($filename), '.zip')) {
            return response()->json([
                'ok' => false,
                'message' => 'Only .zip archives are supported.',
            ], 422);
        }

        $uploadDir = MediaMigrationService::WORK_DIR.'/uploads/'.$request->input('upload_id');
        $disk = Storage::disk('local');

        if (! $disk->exists($uploadDir)) {
            return response()->json([
                'ok' => false,
                'message' => 'Upload session not found.',
            ], 422);
        }

        $parts = collect($disk->files($uploadDir))
            ->filter(fn ($file) => str_ends_with($file, '.part'))
            ->sortBy(fn ($file) => intval(basename($file, '.part')))
            ->values();

        if ($parts->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No uploaded chunks found.',
            ], 422);
        }

        // 归档目录 + 防重名
        $archivesDir = $this->migrationService->archivesDir();

        if (! $disk->exists($archivesDir)) {
            $disk->makeDirectory($archivesDir);
        }

        $finalName = $filename;
        $counter = 1;

        while ($disk->exists($archivesDir.'/'.$finalName)) {
            $finalName = str_replace('.zip', '', $filename).'-'.$counter.'.zip';
            $counter++;
        }

        $finalPath = $disk->path($archivesDir.'/'.$finalName);

        // 顺序合并分片
        $out = fopen($finalPath, 'wb');

        if ($out === false) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to write archive file.',
            ], 500);
        }

        foreach ($parts as $part) {
            $in = fopen($disk->path($part), 'rb');

            if ($in === false) {
                fclose($out);
                unlink($finalPath);

                return response()->json([
                    'ok' => false,
                    'message' => 'Failed to read chunk.',
                ], 500);
            }

            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        // 清理分片
        $disk->deleteDirectory($uploadDir);

        // 校验压缩包完整性（可打开 + 含工具生成的内嵌 manifest）
        if (! $this->archiveService->validateArchive($archivesDir.'/'.$finalName)) {
            unlink($finalPath);

            return response()->json([
                'ok' => false,
                'message' => 'Archive validation failed. Please make sure the file was created by this tool and is not corrupted.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'name' => $finalName,
        ]);
    }
}
