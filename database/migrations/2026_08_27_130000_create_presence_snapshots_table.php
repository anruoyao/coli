<?php

/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * 在线量小时快照表（P1 数据分析底座）。
 *
 * 由 `presence:aggregate` 命令每小时聚合一次（写当前小时桶），
 * 支撑后台「数据分析」：24h/7d 在线趋势折线图、平台分布、今日峰值。
 * window_start 为小时桶起点（UTC），唯一，重复执行同桶覆盖（幂等）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::PRESENCE_SNAPSHOTS, function (Blueprint $table) {
            $table->id();
            $table->dateTime('window_start')->unique();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('web_count')->default(0);
            $table->unsignedInteger('android_count')->default(0);
            $table->unsignedInteger('ios_count')->default(0);
            $table->timestamps();
            $table->index('window_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::PRESENCE_SNAPSHOTS);
    }
};