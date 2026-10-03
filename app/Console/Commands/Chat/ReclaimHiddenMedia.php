<?php

namespace App\Console\Commands\Chat;

use Throwable;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Actions\Media\DeleteMediaAction;

/**
 * 回收「全员已本地删除」消息的媒体文件。
 *
 * 背景：删除会话/清空聊天记录只是写入 hidden_messages 对当前用户隐藏，
 * 即使所有参与者都删除了，图片/视频/语音文件仍留在磁盘（或 S3）上。
 * 本任务按 DB 记录（非目录扫描）找出满足以下全部条件的消息并回收：
 *   1. 消息挂有 media 记录且未被标记删除；
 *   2. 会话仍有参与者，且「所有当前参与者」都对该消息写了 hidden_messages；
 *   3. 最后一条隐藏记录的时间已超过配置的宽限期（防误操作/正在进行的删除请求）。
 * 单条消息处理失败只计数并记录日志，不中断整批；失败项下次运行自动重试。
 */
class ReclaimHiddenMedia extends Command
{
    protected $signature = 'chats:reclaim-media
        {--dry-run : 只统计符合条件的消息，不执行删除}
        {--batch= : 每批处理的消息数量（默认取配置 chat.media_reclamation.batch_size）}';

    protected $description = 'Recover media files of messages hidden (locally deleted) by ALL chat participants after the grace period.';

    public function handle(): int
    {
        if(! config('chat.media_reclamation.enabled', true)) {
            $this->info('Chat media reclamation is disabled (chat.media_reclamation.enabled = false).');

            return self::SUCCESS;
        }

        $graceDays = max(0, (int) config('chat.media_reclamation.grace_days', 7));
        $batchSize = (int) ($this->option('batch') ?: config('chat.media_reclamation.batch_size', 500));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($graceDays);

        $this->info(sprintf(
            'Reclaiming chat media [grace: %d day(s), batch: %d, cutoff: %s, dry-run: %s]...',
            $graceDays,
            $batchSize,
            $cutoff->toDateTimeString(),
            $dryRun ? 'yes' : 'no'
        ));

        $reclaimed = 0;
        $failed = 0;

        $query = Message::query()
            ->with('media')
            ->where('is_deleted', false)
            ->whereHas('media')
            // 会话必须仍有参与者（孤立消息不在本任务范围）
            ->whereRaw('(SELECT COUNT(*) FROM chat_participants cp
                WHERE cp.chat_id = messages.chat_id) > 0')
            // 已隐藏该消息的「当前参与者」人数 = 当前参与者总数 → 全员不可见
            ->whereRaw('(SELECT COUNT(DISTINCT hm.user_id) FROM hidden_messages hm
                WHERE hm.message_id = messages.id
                  AND hm.user_id IN (
                      SELECT cp2.user_id FROM chat_participants cp2
                      WHERE cp2.chat_id = messages.chat_id
                  ))
                = (SELECT COUNT(*) FROM chat_participants cp3
                      WHERE cp3.chat_id = messages.chat_id)')
            // 宽限期：以最后一条隐藏记录时间为准（COALESCE 仅为防御历史空值）
            ->whereRaw('COALESCE((
                  SELECT MAX(hm2.created_at) FROM hidden_messages hm2
                  WHERE hm2.message_id = messages.id
              ), messages.created_at) <= ?', [$cutoff]);

        $query->chunkById($batchSize, function ($messages) use (&$reclaimed, &$failed, $dryRun) {
            foreach ($messages as $message) {
                if($dryRun) {
                    $reclaimed++;
                    continue;
                }

                try {
                    $media = $message->media;

                    if(empty($media)) {
                        continue;
                    }

                    $deleted = (new DeleteMediaAction($media))->execute();

                    if(! $deleted) {
                        $failed++;
                        continue;
                    }

                    // 媒体已回收：标记墓碑，保证任务幂等（重复运行不会再次命中）
                    $message->reactions()->delete();
                    $message->update([
                        'content' => '',
                        'is_deleted' => true,
                    ]);

                    $reclaimed++;
                } catch (Throwable $th) {
                    $failed++;

                    Log::error('Exception while reclaiming chat message media', [
                        'message_id' => $message->id,
                        'chat_id' => $message->chat_id,
                        'error' => $th->getMessage(),
                    ]);
                }
            }
        });

        $this->info(sprintf(
            'Chat media reclamation finished. %s: %d, failed: %d.',
            $dryRun ? 'matched' : 'reclaimed',
            $reclaimed,
            $failed
        ));

        Log::info('Chat media reclamation finished', [
            'dry_run' => $dryRun,
            $dryRun ? 'matched' : 'reclaimed' => $reclaimed,
            'failed' => $failed,
            'grace_days' => $graceDays,
        ]);

        if($failed > 0) {
            // 告警：存在回收失败项（详情逐条 error 日志），下次运行自动重试
            Log::warning('Chat media reclamation finished with failures', [
                'reclaimed' => $reclaimed,
                'failed' => $failed,
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
