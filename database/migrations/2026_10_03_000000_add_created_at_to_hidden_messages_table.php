<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\DB;
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
        Schema::table(Table::HIDDEN_MESSAGES, function (Blueprint $table) {
            // 定时回收需要知道消息「何时被隐藏」：全员隐藏且超过宽限期后回收媒体文件
            $table->timestamp('created_at')->nullable()->after('user_id');
        });

        // 历史行没有隐藏时间：以当前时间回填，保守地从部署时刻起计算宽限期
        DB::table(Table::HIDDEN_MESSAGES)->whereNull('created_at')->update([
            'created_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::HIDDEN_MESSAGES, function (Blueprint $table) {
            $table->dropColumn('created_at');
        });
    }
};
