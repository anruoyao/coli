<?php

namespace Tests\Feature\Timeline;

use Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Models\Media;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\User\UserStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use App\Services\Nsfw\NsfwDetectionService;
use App\Jobs\User\Timeline\DetectPostNsfwContent;
use App\Listeners\User\Timeline\HandlePostCreation;
use App\Listeners\User\Timeline\DetectPostNsfwOnMediaProcessed;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Notifications\System\Moderation\PostMarkedNsfwNotification;
use App\Livewire\Admin\Config\NsfwDetection as NsfwDetectionLivewire;
use App\Settings\NsfwDetectionSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\Marketing\Concerns\CreatesUsers;

/**
 * 帖子媒体 NSFW 自动识别端到端测试。
 *
 * 覆盖：Job 命中标记 + metadata 写入 + 作者通知、阈值/标签过滤、
 * 手动已标记跳过、功能开关跳过、视频 PROCESSED/PROCESSING 区分、
 * 服务异常不误标（抛出交队列重试）、事件挂载点（图片发帖 / 视频转码完成）、
 * 后台配置保存与审计、连通性测试。
 *
 * 注：使用 DatabaseTransactions（结构预建后仅事务回滚）而非 RefreshDatabase。
 *     检测微服务通过 Http::fake 模拟，无需真实 Python 服务。
 */
class NsfwDetectionTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'security.app_key.enabled' => false,
            'features.nsfw_detection.enabled' => true,
            'features.nsfw_detection.threshold' => 0.60,
            'features.nsfw_detection.trigger_labels' => [
                // NudeNet 3.x 真实标签命名（X_EXPOSED 风格）
                'FEMALE_GENITALIA_EXPOSED',
                'MALE_GENITALIA_EXPOSED',
                'FEMALE_BREAST_EXPOSED',
                'MALE_BREAST_EXPOSED',
                'BUTTOCKS_EXPOSED',
                'ANUS_EXPOSED',
            ],
            'features.nsfw_detection.notify_author' => true,
        ]);

        Notification::fake();

        // 检测源文件：local 盘写占位文件（服务端为 Http::fake，内容无需为真实媒体）
        Storage::disk('local')->put('nsfw-test/sample.jpg', 'fake-image-bytes');
        Storage::disk('local')->put('nsfw-test/sample.mp4', 'fake-video-bytes');
    }

    private function makePost(array $overrides = []): Post
    {
        $user = $this->makeUser(['status' => UserStatus::ACTIVE]);

        return Post::create(array_merge([
            'user_id' => $user->id,
            'type' => PostType::IMAGE,
            'status' => PostStatus::ACTIVE,
            'content' => '测试帖子内容',
        ], $overrides));
    }

    private function makeMedia(Post $post, array $overrides = []): Media
    {
        return $post->media()->create(array_merge([
            'source_path' => 'nsfw-test/sample.jpg',
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'disk' => 'local',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 100,
            'metadata' => [],
        ], $overrides));
    }

    private function fakeDetectionService(array $detections): void
    {
        Http::fake([
            '*/v1/detect' => Http::response([
                'ok' => true,
                'media_type' => 'image',
                'frames_checked' => 1,
                'detections' => $detections,
            ]),
        ]);
    }

    private function runJob(Post $post): void
    {
        (new DetectPostNsfwContent($post))->handle(app(NsfwDetectionService::class));
    }

    // ===================== Job 检测与标记 =====================

    public function test_flags_post_writes_metadata_and_notifies_author_on_hit(): void
    {
        $post = $this->makePost();
        $media = $this->makeMedia($post);

        $this->fakeDetectionService([
            ['label' => 'FEMALE_BREAST_EXPOSED', 'score' => 0.87, 'box' => [1, 2, 3, 4], 'frame' => 0],
            ['label' => 'FACE_FEMALE', 'score' => 0.9, 'box' => [5, 6, 7, 8], 'frame' => 0],
        ]);

        $this->runJob($post);

        $post = $post->fresh();
        $media = $media->fresh();

        $this->assertTrue($post->is_sensitive);

        $detection = $media->metadata['nsfw_detection'];
        $this->assertTrue($detection['flagged']);
        $this->assertSame(['FEMALE_BREAST_EXPOSED'], $detection['labels']);
        $this->assertSame(0.87, $detection['max_score']);
        $this->assertSame('nudenet', $detection['engine']);

        Notification::assertSentTo($post->user, PostMarkedNsfwNotification::class);
    }

    public function test_does_not_flag_when_label_not_in_trigger_set_or_below_threshold(): void
    {
        $post = $this->makePost();
        $this->makeMedia($post);

        // COVERED_* 不在触发标签集；FEMALE_BREAST_EXPOSED 低于阈值 0.60
        $this->fakeDetectionService([
            ['label' => 'FEMALE_BREAST_COVERED', 'score' => 0.95, 'box' => [], 'frame' => 0],
            ['label' => 'FEMALE_BREAST_EXPOSED', 'score' => 0.42, 'box' => [], 'frame' => 0],
        ]);

        $this->runJob($post);

        $this->assertFalse($post->fresh()->is_sensitive);

        Notification::assertNothingSentTo($post->user);
    }

    public function test_skips_post_already_marked_sensitive_by_author(): void
    {
        $post = $this->makePost(['is_sensitive' => true]);
        $this->makeMedia($post);

        Http::fake();
        $this->runJob($post);

        Http::assertNothingSent();
        Notification::assertNothingSentTo($post->user);
    }

    public function test_skips_when_feature_disabled(): void
    {
        config(['features.nsfw_detection.enabled' => false]);

        $post = $this->makePost();
        $this->makeMedia($post);

        Http::fake();
        $this->runJob($post);

        Http::assertNothingSent();
        $this->assertFalse($post->fresh()->is_sensitive);
    }

    public function test_video_detected_only_after_processing(): void
    {
        $post = $this->makePost(['type' => PostType::VIDEO]);
        $this->makeMedia($post, ['type' => MediaType::VIDEO, 'status' => MediaStatus::PROCESSING]);
        $this->makeMedia($post, ['type' => MediaType::VIDEO, 'status' => MediaStatus::PROCESSED, 'source_path' => 'nsfw-test/sample.mp4']);

        $this->fakeDetectionService([
            ['label' => 'FEMALE_GENITALIA_EXPOSED', 'score' => 0.91, 'box' => [], 'frame' => 3],
        ]);

        $this->runJob($post);

        // 仅 PROCESSED 的视频被检测（PROCESSING 的跳过）
        Http::assertSentCount(1);

        $this->assertTrue($post->fresh()->is_sensitive);
        Notification::assertSentTo($post->user, PostMarkedNsfwNotification::class);
    }

    public function test_service_failure_throws_without_flagging(): void
    {
        $post = $this->makePost();
        $this->makeMedia($post);

        Http::fake(['*/v1/detect' => Http::response(['ok' => false, 'error' => 'boom'], 500)]);

        try {
            $this->runJob($post);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            // 异常交队列重试
        }

        $this->assertFalse($post->fresh()->is_sensitive);
        Notification::assertNothingSentTo($post->user);
    }

    public function test_missing_source_file_is_skipped_without_error(): void
    {
        $post = $this->makePost();
        $this->makeMedia($post, ['source_path' => 'nsfw-test/missing.jpg']);

        Http::fake();
        $this->runJob($post);

        Http::assertNothingSent();
        $this->assertFalse($post->fresh()->is_sensitive);
    }

    // ===================== 事件挂载点 =====================

    public function test_post_creation_listener_dispatches_detection_for_image_posts(): void
    {
        Queue::fake();

        $imagePost = $this->makePost(['type' => PostType::IMAGE]);
        $videoPost = $this->makePost(['type' => PostType::VIDEO]);

        (new HandlePostCreation())->handle(new PostCreatedEvent($imagePost));

        Queue::assertPushed(DetectPostNsfwContent::class);

        // 视频帖不走发帖挂载（由转码完成的 MediaProcessedEvent 触发）
        Queue::fake();

        (new HandlePostCreation())->handle(new PostCreatedEvent($videoPost));

        Queue::assertNotPushed(DetectPostNsfwContent::class);
    }

    public function test_media_processed_listener_dispatches_detection_for_processed_videos(): void
    {
        Queue::fake();

        $post = $this->makePost(['type' => PostType::VIDEO]);
        $videoMedia = $this->makeMedia($post, ['type' => MediaType::VIDEO, 'status' => MediaStatus::PROCESSED]);

        (new DetectPostNsfwOnMediaProcessed())->handle(new MediaProcessedEvent($videoMedia, $post->user_id));

        Queue::assertPushed(DetectPostNsfwContent::class);

        // 音频媒体不触发
        Queue::fake();

        $audioPost = $this->makePost(['type' => PostType::AUDIO]);
        $audioMedia = $this->makeMedia($audioPost, ['type' => MediaType::AUDIO, 'status' => MediaStatus::PROCESSED]);

        (new DetectPostNsfwOnMediaProcessed())->handle(new MediaProcessedEvent($audioMedia, $audioPost->user_id));

        Queue::assertNotPushed(DetectPostNsfwContent::class);
    }

    // ===================== 后台配置（Livewire） =====================

    public function test_admin_settings_save_persists_and_logs_audit(): void
    {
        Livewire::test(NsfwDetectionLivewire::class)
            ->set('featureEnabled', true)
            ->set('threshold', '0.75')
            ->set('triggerLabels', "FEMALE_BREAST_EXPOSED\nbuttocks_exposed,  \nFEMALE_GENITALIA_EXPOSED")
            ->set('notifyAuthor', false)
            ->call('saveSettings')
            ->assertHasNoErrors();

        // spatie/laravel-settings 该版本无 fresh()，重新 resolve 即重新从库 hydrate
        $settings = app(NsfwDetectionSettings::class);

        $this->assertTrue($settings->enabled);
        $this->assertSame(0.75, $settings->threshold);
        $this->assertFalse($settings->notify_author);

        // 换行/逗号分隔、去空白、统一大写、去重
        $this->assertSame(
            ['FEMALE_BREAST_EXPOSED', 'BUTTOCKS_EXPOSED', 'FEMALE_GENITALIA_EXPOSED'],
            $settings->trigger_labels
        );

        $this->assertDatabaseHas('admin_config_change_logs', [
            'config_key' => 'nsfw_detection',
            'action' => 'settings_updated',
        ]);
    }

    public function test_admin_settings_rejects_empty_trigger_labels(): void
    {
        Livewire::test(NsfwDetectionLivewire::class)
            ->set('triggerLabels', '  ')
            ->call('saveSettings')
            ->assertHasErrors(['triggerLabels']);
    }

    public function test_connection_test_reports_health(): void
    {
        Http::fake([
            '*/health' => Http::response(['ok' => true, 'model' => '320n']),
        ]);

        Livewire::test(NsfwDetectionLivewire::class)
            ->call('testConnection');

        Http::assertSentCount(1);
    }

    /**
     * 系统 actor（id=0）通知不触发 NotificationSuppress 监听器的
     * MuteService TypeError（activeById(0) 为 null），历史上导致
     * PostMarkedNsfwNotification 在队列中反复失败。
     */
    public function test_notification_suppress_listener_skips_system_actor(): void
    {
        $user = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $post = $this->makePost(['user_id' => $user->id]);
        $notification = new PostMarkedNsfwNotification($post);

        $result = (new \App\Listeners\User\Notification\HandleNotificationSuppress())
            ->handle(new \Illuminate\Notifications\Events\NotificationSending($user, $notification, 'database'));

        $this->assertTrue($result);
    }
}
