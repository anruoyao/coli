<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.email / users.username 数据库唯一索引。
 *
 * 应用层的 Rule::unique 在重试/并发窗口下不可靠（竞态）：
 * 2026-09-28 confirm-signup 因 devices 500 被用户连点重试，
 * 同一邮箱 2 秒内产生 13 个 onboarding 半成品账号。
 * 加唯一索引后，重复插入直接被数据库拒绝，从根上杜绝批量重复注册。
 * （迁移前已确认全表无重复 email / username，索引可安全创建。）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::USERS, function (Blueprint $table) {
            $table->unique('email');
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table(Table::USERS, function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropUnique(['username']);
        });
    }
};
