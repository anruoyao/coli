<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 营销通知活动表。
     *
     * 一次批量发送（邮件 + 站内通知）即一条活动。目标群体在发送时圈定并写入
     * marketing_campaign_recipients（快照），状态机：draft → sending → completed / cancelled。
     */
    public function up(): void
    {
        Schema::create(Table::MARKETING_CAMPAIGNS, function (Blueprint $table) {
            $table->id();

            $table->string('title')->comment('活动标题');
            $table->string('subject')->comment('邮件主题（仅邮件通道使用）');
            $table->text('content')->comment('通知正文（支持换行）');
            $table->string('landing_url')->nullable()->comment('落地链接（站内通知 CTA / 邮件按钮）');

            $table->boolean('email_enabled')->default(true)->comment('是否启用邮件通道');
            $table->boolean('in_app_enabled')->default(true)->comment('是否启用站内通知通道');

            // 目标群体：all(全部已开启用户) / manual(手动用户名列表) / type(按用户类型)
            $table->string('target_type')->default('all');
            $table->json('target_user_ids')->nullable()->comment('manual 时：用户名/ID 数组');
            $table->string('target_user_type')->nullable()->comment('type 时：author / reader');

            $table->string('status')->default('draft')->comment('draft/sending/completed/cancelled');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人（管理员用户ID）');

            $table->unsignedInteger('email_recipient_count')->default(0);
            $table->unsignedInteger('email_sent_count')->default(0);
            $table->unsignedInteger('in_app_recipient_count')->default(0);
            $table->unsignedInteger('in_app_sent_count')->default(0);

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status', 'mkt_campaign_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::MARKETING_CAMPAIGNS);
    }
};