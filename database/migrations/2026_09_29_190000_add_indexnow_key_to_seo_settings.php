<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 为 SeoSettings 增加 IndexNow 密钥字段。
 *
 * 本项目的 Spatie LaravelSettings 以「每个属性一行」存储
 * （settings 表：group + name + payload）。新增属性必须先有对应行，
 * 否则第一次 save() 会抛 MissingSettings。
 * 初始 payload 为 JSON null，首次使用 IndexNow 时由 SitemapService 自动生成密钥。
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')
            ->where('group', 'seo')
            ->where('name', 'indexnow_key')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('settings')->insert([
            'group' => 'seo',
            'name' => 'indexnow_key',
            'locked' => false,
            'payload' => json_encode(null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'seo')
            ->where('name', 'indexnow_key')
            ->delete();
    }
};
