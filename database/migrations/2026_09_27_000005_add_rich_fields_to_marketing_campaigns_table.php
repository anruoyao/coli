<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 营销活动富媒体字段：
     *  - image_url:    Banner 图（邮件 + 站内通知卡片展示）
     *  - title_size:   标题字号档位 sm/md/lg
     *  - title_weight: 标题字重档位 normal/medium/semibold/bold
     *  - post_ids:     关联站内帖子 ID（JSON 有序数组），发送时构建快照
     */
    public function up(): void
    {
        Schema::table(Table::MARKETING_CAMPAIGNS, function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('landing_url');
            $table->string('title_size', 8)->default('md')->after('image_url');
            $table->string('title_weight', 12)->default('semibold')->after('title_size');
            $table->json('post_ids')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table(Table::MARKETING_CAMPAIGNS, function (Blueprint $table) {
            $table->dropColumn(['image_url', 'title_size', 'title_weight', 'post_ids']);
        });
    }
};