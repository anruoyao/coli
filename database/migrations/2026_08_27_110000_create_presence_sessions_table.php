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
 * 在线状态会话表（P0 在线用户数功能核心）。
 *
 * 与 users.last_active（用户级兜底，isOnline() 语义不变）配合：
 * presence_sessions 记录「会话级」在线明细（同一用户可多端并发在线），
 * 支撑后台「在线用户」卡片计数与列表页（平台筛选/搜索/在线时长）。
 * online = last_seen_at >= now()-window 且 is_background=0。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::PRESENCE_SESSIONS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            // 客户端标识：App 由 X-Client-Id 提供（UUID，本地持久化）；Web 回退 session id。
            $table->string('client_id', 64);
            // 平台：web / android / ios（web 由 UA 解析，App 取自 X-App-Platform 头）
            $table->string('platform', 16)->default('web');
            // 平台明细：web = 浏览器+系统；App = 应用版本号
            $table->string('platform_detail', 64)->nullable();

            $table->string('ip_address', 64)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('region', 64)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('timezone', 64)->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            // App 进入后台标记（后台后 last_seen 冻结，窗口过期自然离线；后台心跳即席置 1）
            $table->boolean('is_background')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::PRESENCE_SESSIONS);
    }
};