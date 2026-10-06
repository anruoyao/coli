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
        // 媒体文件迁移任务状态表（本地打包 / 解压恢复 / 磁盘间迁移）
        Schema::create(Table::MEDIA_MIGRATIONS, function (Blueprint $table) {
            $table->id();
            // archive=本地打包(换服务器) extract=解压恢复 disk_transfer=磁盘迁移(如 S3)
            $table->string('type', 20);
            // running / completed / failed / cancelled
            $table->string('status', 20)->default('running');
            $table->string('source_disk', 100)->default('');
            $table->string('target_disk', 100)->default('');
            // 压缩包相对路径（archive / extract 类型使用）
            $table->string('archive_path')->nullable();
            $table->unsignedBigInteger('files_total')->default(0);
            $table->unsignedBigInteger('files_done')->default(0);
            $table->unsignedBigInteger('files_failed')->default(0);
            $table->unsignedBigInteger('bytes_total')->default(0);
            $table->unsignedBigInteger('bytes_done')->default(0);
            // 解压/迁移后的校验计数（缺失文件数 / 大小哈希不一致数）
            $table->unsignedBigInteger('verify_missing')->default(0);
            $table->unsignedBigInteger('verify_mismatch')->default(0);
            // 断点续传游标：manifest 中下一个待处理条目的行号
            $table->unsignedBigInteger('cursor')->default(0);
            $table->json('options')->nullable();
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::MEDIA_MIGRATIONS);
    }
};
