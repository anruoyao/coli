<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户账号封禁/停用原因。
 * 与 users.status（active/blocked/suspended）配合，App 封禁页展示给用户。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_reason')->nullable()->after('status')->comment('封禁/停用原因');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status_reason');
        });
    }
};