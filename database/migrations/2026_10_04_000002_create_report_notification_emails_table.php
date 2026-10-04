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
        // 举报通知管理员邮箱配置（email 列由模型 encrypted cast 加密存储）
        Schema::create(Table::REPORT_NOTIFICATION_EMAILS, function (Blueprint $table) {
            $table->id();
            $table->text('email');
            $table->string('label', 100)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::REPORT_NOTIFICATION_EMAILS);
    }
};
