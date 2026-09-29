<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * 修复历史表 currency 列缺失默认值的问题。
 *
 * 这些表的建表迁移写的是 ->default(config('app.default_currency'))，
 * 但建表时（2025 年）该配置尚不存在（app.default_currency 设置项 2026-02 才加入），
 * 于是列被建成「NOT NULL 且无默认值」。导致业务后台创建产品/职位草稿
 * （仅插入 status + user_id）时报：
 *   SQLSTATE[HY000] Field 'currency' doesn't have a default value。
 *
 * 这里按原迁移意图补齐列默认值；存量数据不改动。
 */
return new class extends Migration
{
    private array $tables = [
        'products',
        'job_listings',
        'wallets',
        'wallet_transactions',
        'cashouts',
    ];

    public function up(): void
    {
        $default = config('app.default_currency', 'USD');

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($default) {
                $blueprint->string('currency')->default($default)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('currency')->default(null)->change();
            });
        }
    }
};
