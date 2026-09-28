<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * devices.user_agent varchar(255) → text。
 *
 * QQ/微信内置浏览器的 UA 普遍超过 255 字符（含 Pixel/StatusBarHeight 等私有字段），
 * 导致 UpdateUserDeviceAction 插入时报 1406 Data too long：
 * - confirm-signup 在 login 事件里炸 500 → 用户重试 → 重复创建账号（无唯一索引时产生批量重复用户）；
 * - onboarding 完成后的 relogin 同样会触发。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::DEVICES, function (Blueprint $table) {
            $table->text('user_agent')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table(Table::DEVICES, function (Blueprint $table) {
            $table->string('user_agent', 255)->nullable()->change();
        });
    }
};
