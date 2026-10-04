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
        Schema::table(Table::REPORTS, function (Blueprint $table) {
            // 举报限流 IP 维度：记录提交举报时的来源 IP（DB 持久化，重启不丢失限流状态）
            $table->string('ip_address', 64)->nullable()->after('reporter_comment');
            $table->index(['ip_address', 'created_at'], 'reports_ip_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::REPORTS, function (Blueprint $table) {
            $table->dropIndex('reports_ip_created_at_index');
            $table->dropColumn('ip_address');
        });
    }
};
