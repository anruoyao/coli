<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashouts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('pending_marker')
                ->nullable()
                ->storedAs("IF(status = 'pending', 1, NULL)");
            $table->unique(['user_id', 'pending_marker'], 'cashouts_one_pending_per_user');
        });
    }

    public function down(): void
    {
        Schema::table('cashouts', function (Blueprint $table): void {
            $table->dropUnique('cashouts_one_pending_per_user');
            $table->dropColumn('pending_marker');
        });
    }
};
