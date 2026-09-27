<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 支持「原始邮箱」收件人：手动目标里合法的邮箱地址若无对应账号，
     * 可为收件人创建 user_id 为空的记录（仅走邮件通道）。
     * user_id 置空不违反外键（NULL 被允许），唯一索引在 NULL 时也不冲突。
     */
    public function up(): void
    {
        Schema::table(Table::MARKETING_CAMPAIGN_RECIPIENTS, function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table(Table::MARKETING_CAMPAIGN_RECIPIENTS, function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};