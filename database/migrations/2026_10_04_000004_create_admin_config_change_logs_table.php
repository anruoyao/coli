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
        // 后台配置修改日志（举报通知邮箱的增/改/删/开关等操作审计）
        Schema::create(Table::ADMIN_CONFIG_CHANGE_LOGS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->foreign('admin_id')->references('id')->on(Table::USERS)->onDelete('set null');
            $table->string('config_key', 100);
            $table->string('action', 50);
            $table->json('changes')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::ADMIN_CONFIG_CHANGE_LOGS);
    }
};
