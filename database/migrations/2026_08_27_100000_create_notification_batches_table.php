<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 社交通知聚合缓冲表。
     *
     * 点赞/评论/关注/提及等互动事件在发送邮件聚合摘要（Digest）前先写入此表，
     * 同一窗口内「同一接收者 + 同一操作者 + 同一实体 + 同一类型」只保留一行（幂等合并）。
     * 取消点赞/删除评论/取关时删除对应行，实现实时撤销（不会进入 Digest）。
     */
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notifiable_id')->comment('通知接收者用户ID');
            $table->unsignedBigInteger('actor_id')->comment('操作者用户ID');
            $table->string('entity_type', 32)->comment('实体类型: post/comment/story/follow');
            $table->unsignedBigInteger('entity_id')->comment('实体ID（follow 时取 actor_id）');
            $table->string('type', 64)->comment('通知类型: post.reacted 等');
            $table->json('meta')->nullable()->comment('附加数据: reaction/comment 内容等');
            $table->timestamp('source_time')->useCurrent()->comment('首次触发时间，作为窗口计时起点');
            $table->timestamps();

            // 同源幂等：同一接收者+操作者+实体+类型只保留一行（重复触发仅更新 meta）
            $table->unique(['notifiable_id', 'actor_id', 'entity_type', 'entity_id', 'type'], 'ntf_batch_dedupe');
            // 到期扫描：接收者 + 类型 + 窗口时间
            $table->index(['notifiable_id', 'type', 'source_time'], 'ntf_batch_due');
            $table->index('actor_id', 'ntf_batch_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};