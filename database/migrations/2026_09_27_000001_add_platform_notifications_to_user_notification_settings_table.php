<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 「平台通知」开关：控制营销/平台类通知（邮件通道 + 推送/站内通道）的接收权限。
     * 默认开启（选择退出制），用户可在推送通知/邮件通知设置页中关闭。
     */
    public function up(): void
    {
        Schema::table(Table::USER_NOTIFICATION_SETTINGS, function (Blueprint $table) {
            $table->boolean('platform_notifications')->default(true)->after('mentions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::USER_NOTIFICATION_SETTINGS, function (Blueprint $table) {
            $table->dropColumn('platform_notifications');
        });
    }
};