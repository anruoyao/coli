<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::USER_BLOCKS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->unsignedBigInteger('blocked_user_id');
            $table->foreign('blocked_user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->timestamp('blocked_at')->nullable();
            $table->unique(['user_id', 'blocked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::USER_BLOCKS);
    }
};
