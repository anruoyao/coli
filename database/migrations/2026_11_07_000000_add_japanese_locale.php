<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('locales')->where('alpha_2_code', 'ja')->exists()) {
            DB::table('locales')->insert([
                'alpha_2_code' => 'ja',
                'name' => 'Japanese',
                'native_name' => '日本語',
                'flag_path' => '',
                'direction' => 'ltr',
                'order' => 3,
                'status' => true,
                'is_default' => false,
                'usage_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->forgetLocaleCache();
    }

    public function down(): void
    {
        DB::table('locales')->where('alpha_2_code', 'ja')->delete();

        $this->forgetLocaleCache();
    }

    private function forgetLocaleCache(): void
    {
        try {
            Cache::forget('locales');
        } catch (\Throwable) {
            // 迁移时缓存服务可能不可用；部署后的 cache:clear 会再次清理。
        }
    }
};
