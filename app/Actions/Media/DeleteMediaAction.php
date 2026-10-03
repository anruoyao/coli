<?php

namespace App\Actions\Media;

use Throwable;
use App\Models\Media;
use App\Constants\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteMediaAction
{
	private Media $mediaData;

	public function __construct(Media $mediaData)
	{
		$this->mediaData = $mediaData;
	}

	/**
	 * 删除媒体对应的物理文件（含缩略图）并删除 DB 记录。
	 *
	 * 返回 true：文件已删除（或原本就不存在），DB 记录已清除；
	 * 返回 false：存在文件删除失败，DB 记录保留，调用方可稍后重试（如下次定时任务）。
	 * 单个文件失败不阻断其它文件的尝试，但整体结果为 false。
	 */
	public function execute(): bool
	{
		try {
			$allDeleted = true;

			if($this->mediaData->disk !== Filesystem::EXTERNAL_DISK_NAME) {

				if(! empty($this->mediaData->thumbnail_path)) {
					$allDeleted = $this->deleteFile(
						$this->mediaData->thumbnail_disk,
						$this->mediaData->thumbnail_path
					) && $allDeleted;
				}

				// 处理中的源文件仍在 local 临时盘，完成态在所属磁盘
				$sourceDisk = $this->mediaData->status->isProcessing() ? 'local' : $this->mediaData->disk;

				$allDeleted = $this->deleteFile($sourceDisk, $this->mediaData->source_path) && $allDeleted;
			}

			if(! $allDeleted) {
				// 物理文件删除失败（权限/存储故障）：保留 DB 记录等待重试，error 日志即告警
				return false;
			}

			$this->mediaData->delete();

			return true;
		} catch (Throwable $th) {
			Log::error('Exception while deleting media', [
				'media_id' => $this->mediaData->id ?? null,
				'type' => $this->mediaData->mediaable_type ?? null,
				'id' => $this->mediaData->mediaable_id ?? null,
				'error' => $th->getMessage(),
			]);

			return false;
		}
	}

	/**
	 * 删除单个物理文件。
	 *
	 * - 文件已不存在：视为已达成目标（记 warning 便于追溯孤儿情况），返回 true，允许清理 DB 记录；
	 * - 文件存在但删除失败：记 error 告警，返回 false，DB 记录保留待重试；
	 * - 删除过程抛异常：同上，记 error，返回 false。
	 */
	private function deleteFile(string $disk, ?string $path): bool
	{
		if(empty($path)) {
			return true;
		}

		try {
			$storage = Storage::disk($disk);

			if(! $storage->exists($path)) {
				Log::warning('Media file already missing at deletion, cleaning DB record', [
					'disk' => $disk,
					'path' => $path,
					'media_id' => $this->mediaData->id,
				]);

				return true;
			}

			if(! $storage->delete($path)) {
				Log::error('Failed to delete media file from storage', [
					'disk' => $disk,
					'path' => $path,
					'media_id' => $this->mediaData->id,
					'mediaable_type' => $this->mediaData->mediaable_type,
					'mediaable_id' => $this->mediaData->mediaable_id,
				]);

				return false;
			}

			return true;
		} catch (Throwable $th) {
			Log::error('Exception while deleting media file from storage', [
				'disk' => $disk,
				'path' => $path,
				'media_id' => $this->mediaData->id,
				'error' => $th->getMessage(),
			]);

			return false;
		}
	}
}
