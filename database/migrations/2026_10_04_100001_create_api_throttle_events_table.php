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
        // API 限流事件记录（429 命中审计，供管理后台限流监控页展示与裁剪）
        Schema::create(Table::API_THROTTLE_EVENTS, function (Blueprint $table) {
            $table->id();
            $table->string('dimension', 16);          // user | ip | device | global
            $table->string('identifier', 128);        // 限流 key（user_id / IP / device_id）
            $table->string('category', 64)->nullable();  // 防线标识（throttle:timeline / abuse:video-upload / global-ip-gate）
            $table->string('action', 64)->nullable(); // AbuseGuard 动作名
            $table->unsignedInteger('limit')->nullable();      // 触发时限流额度
            $table->unsignedInteger('window_seconds')->nullable(); // 限流窗口（秒）
            $table->string('path')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['dimension', 'identifier']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::API_THROTTLE_EVENTS);
    }
};
