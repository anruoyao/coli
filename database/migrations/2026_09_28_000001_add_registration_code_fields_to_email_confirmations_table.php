<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App 注册邮箱验证码：复用 email_confirmations 表，纯增量字段，
 * 不影响网页端原有 token 链接确认流程（token 列保持原样）。
 *
 * - code:          6 位数字验证码（网页链接流程为 null）
 * - expires_at:    验证码过期时间
 * - attempts:      验证码错误尝试次数（防爆破）
 * - last_sent_at:  最近一次发码时间（重发冷却）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::EMAIL_CONF, function (Blueprint $table) {
            $table->string('code', 6)->nullable()->after('token')->index();
            $table->timestamp('expires_at')->nullable()->after('code');
            $table->unsignedTinyInteger('attempts')->default(0)->after('expires_at');
            $table->timestamp('last_sent_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table(Table::EMAIL_CONF, function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropColumn(['code', 'expires_at', 'attempts', 'last_sent_at']);
        });
    }
};
