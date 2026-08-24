<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App 版本管理表。
 *
 * - 每个平台（android / ios）可以维护多条版本记录（历史版本列表）
 * - is_active 控制版本是否上线（未上线不参与版本检测）
 * - is_forced 控制是否强制更新（低于该版本的用户必须升级）
 * - released_at 支持定时发布（空表示立即发布）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->comment('版本代号，如 2.1.0');
            $table->string('platform', 16)->default('android')->comment('目标平台：android / ios');
            $table->string('download_url', 500)->comment('安装包下载链接');
            $table->text('notes')->nullable()->comment('更新公告');
            $table->boolean('is_forced')->default(false)->comment('是否强制更新');
            $table->boolean('is_active')->default(true)->comment('是否上线（参与版本检测）');
            $table->timestamp('released_at')->nullable()->comment('发布时间（空表示立即生效）');
            $table->timestamps();

            $table->unique(['platform', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};