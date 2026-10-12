<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\System\DatabaseRestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BackupUploadController extends Controller
{
    private const CHUNK_SIZE = 20 * 1024 * 1024;

    public function __construct(private DatabaseRestoreService $restoreService) {}

    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required', 'in:chunk,complete'],
            'upload_id' => ['required', 'alpha_num', 'max:64'],
            'filename' => ['required_if:action,complete', 'string', 'max:200'],
            'chunk_index' => ['required_if:action,chunk', 'integer', 'min:0', 'max:999999'],
            'total_chunks' => ['required_if:action,chunk', 'integer', 'min:1', 'max:1000000'],
            'sha256' => ['nullable', 'string', 'size:64', 'regex:/\A[0-9a-fA-F]+\z/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            return $request->input('action') === 'chunk'
                ? $this->storeChunk($request)
                : $this->complete($request);
        } catch (Throwable $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function storeChunk(Request $request): JsonResponse
    {
        $chunk = $request->file('file');
        if (! $chunk || ! $chunk->isValid() || $chunk->getSize() > self::CHUNK_SIZE) {
            return response()->json(['ok' => false, 'message' => 'Invalid or oversized upload chunk.'], 422);
        }

        $dir = $this->restoreService->incomingDir().'/'.$request->input('upload_id');
        $disk = $this->restoreService->disk();
        $disk->makeDirectory($dir);
        $chunk->storeAs($dir, $request->integer('chunk_index').'.part', 'local');

        $disk->put($dir.'/meta.json', json_encode([
            'total_chunks' => $request->integer('total_chunks'),
            'sha256' => $request->input('sha256'),
        ], JSON_THROW_ON_ERROR));

        return response()->json(['ok' => true]);
    }

    private function complete(Request $request): JsonResponse
    {
        $filename = basename((string) $request->input('filename'));
        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\.zip\z/i', $filename)) {
            return response()->json(['ok' => false, 'message' => 'Only safe .zip filenames are supported.'], 422);
        }

        $disk = $this->restoreService->disk();
        $dir = $this->restoreService->incomingDir().'/'.$request->input('upload_id');
        $metaPath = $dir.'/meta.json';
        if (! $disk->exists($metaPath)) {
            return response()->json(['ok' => false, 'message' => 'Upload session not found.'], 422);
        }

        $meta = json_decode($disk->get($metaPath), true,  flags: JSON_THROW_ON_ERROR);
        $total = (int) ($meta['total_chunks'] ?? 0);
        $parts = collect($disk->files($dir))
            ->filter(fn ($path) => preg_match('/\/([0-9]+)\.part\z/', $path))
            ->sortBy(fn ($path) => (int) pathinfo($path, PATHINFO_FILENAME))
            ->values();

        if ($total < 1 || $parts->count() !== $total || $parts->keys()->first() !== 0) {
            return response()->json(['ok' => false, 'message' => 'Upload chunks are incomplete.'], 422);
        }

        foreach ($parts as $index => $part) {
            if ((int) pathinfo($part, PATHINFO_FILENAME) !== $index) {
                return response()->json(['ok' => false, 'message' => 'Upload chunks are not consecutive.'], 422);
            }
        }

        $disk->makeDirectory($this->restoreService->backupsDir());
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $finalName = $filename;
        $counter = 1;
        while ($disk->exists($this->restoreService->backupsDir().'/'.$finalName)) {
            $finalName = $base.'-'.$counter.'.zip';
            $counter++;
        }

        $finalRelative = $this->restoreService->backupsDir().'/'.$finalName;
        $finalPath = $disk->path($finalRelative);
        $out = fopen($finalPath, 'wb');
        if ($out === false) {
            return response()->json(['ok' => false, 'message' => 'Unable to create uploaded backup.'], 500);
        }

        try {
            foreach ($parts as $part) {
                $in = fopen($disk->path($part), 'rb');
                if ($in === false) {
                    throw new \RuntimeException('Unable to read upload chunk.');
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } catch (Throwable $exception) {
            fclose($out);
            @unlink($finalPath);
            throw $exception;
        }
        fclose($out);
        $disk->deleteDirectory($dir);

        if (! empty($meta['sha256']) && ! hash_equals(strtolower($meta['sha256']), hash_file('sha256', $finalPath))) {
            @unlink($finalPath);
            return response()->json(['ok' => false, 'message' => 'Archive SHA-256 does not match.'], 422);
        }

        try {
            $details = $this->restoreService->validateArchive($finalRelative);
        } catch (Throwable $exception) {
            @unlink($finalPath);
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'name' => $finalName, 'details' => $details]);
    }
}
