<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::POSTS, function (Blueprint $table) {
            $table->timestamp('profile_pinned_at')->nullable()->after('profile_pinned');
            $table->index(['user_id', 'status', 'profile_pinned', 'id'], 'posts_profile_pins_index');
        });

        DB::table(Table::POSTS)->where('profile_pinned', true)
            ->update(['profile_pinned_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table(Table::POSTS, function (Blueprint $table) {
            $table->dropIndex('posts_profile_pins_index');
            $table->dropColumn('profile_pinned_at');
        });
    }
};
