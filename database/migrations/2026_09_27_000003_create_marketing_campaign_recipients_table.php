<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 营销活动收件人快照表。
     *
     * 邮件与站内通知分通道记录状态（互不影响）：
     *   email_status  / in_app_status : pending → queued → sent / failed / skipped
     * 发送类 Job 认领 pending 记录（置为 queued）后执行，执行后转为 sent/failed/skipped。
     */
    public function up(): void
    {
        Schema::create(Table::MARKETING_CAMPAIGN_RECIPIENTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('user_id');

            $table->string('email')->nullable()->comment('发送时快照的邮箱地址');

            $table->string('email_status')->default('pending')->comment('pending/queued/sent/failed/skipped');
            $table->string('email_error')->nullable();
            $table->string('in_app_status')->default('pending')->comment('pending/queued/sent/failed/skipped');
            $table->string('in_app_error')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on(Table::MARKETING_CAMPAIGNS)->onDelete('cascade');
            $table->unique(['campaign_id', 'user_id'], 'mkt_recipient_unique');

            $table->index(['campaign_id', 'email_status'], 'mkt_recipient_email');
            $table->index(['campaign_id', 'in_app_status'], 'mkt_recipient_inapp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::MARKETING_CAMPAIGN_RECIPIENTS);
    }
};