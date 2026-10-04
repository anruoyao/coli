<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // recipient_email 为 encrypted cast，无法参与 where 查询，
        // 增加明文哈希列作为幂等键，保证 SendReportNotificationEmailJob 重试时
        // firstOrCreate 能命中已存在记录（否则已发送邮箱会被重复发送）。
        Schema::table(Table::REPORT_EMAIL_LOGS, function (Blueprint $table) {
            $table->string('recipient_hash', 64)->nullable()->after('recipient_email');
            $table->index('recipient_hash', 'report_email_logs_recipient_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::REPORT_EMAIL_LOGS, function (Blueprint $table) {
            $table->dropIndex('report_email_logs_recipient_hash_index');
            $table->dropColumn('recipient_hash');
        });
    }
};
