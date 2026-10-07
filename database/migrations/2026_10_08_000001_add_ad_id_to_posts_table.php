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
        Schema::table(Table::POSTS, function (Blueprint $table) {
            // 影子帖：广告以帖子形态混入信息流（原生广告）。
            // 应用层删除广告时会先走 DeletePostAction 清理影子帖，FK cascade 仅作兜底。
            $table->unsignedBigInteger('ad_id')->nullable()->after('quote_post_id');
            $table->foreign('ad_id')->references('id')->on(Table::ADS)->onDelete('cascade');
            $table->index('ad_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::POSTS, function (Blueprint $table) {
            $table->dropForeign(['ad_id']);
            $table->dropIndex(['ad_id']);
            $table->dropColumn('ad_id');
        });
    }
};
