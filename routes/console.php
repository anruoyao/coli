<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Ultimate Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

use App\Info\ColibriPlus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Story Clear Command
|--------------------------------------------------------------------------
| This command clears expired stories from the database every day at 00:00.
|--------------------------------------------------------------------------
*/

Schedule::command('story:clear')->dailyAt('00:00');

Schedule::command('chat:invite-clear')->weekly();

// 在线量小时快照聚合（P1 数据分析：整点+5 分钟，避免边界竞态；重复执行同桶幂等覆盖）
Schedule::command('presence:aggregate')->hourlyAt(5)->withoutOverlapping();

// 聊天媒体回收：全员本地删除且超过宽限期的消息，回收其图片/视频/语音（含 S3）
Schedule::command('chats:reclaim-media')->dailyAt('03:30')->withoutOverlapping();

// 临时文件兜底清理：视频转码/缩略图等中间产物因任务中断残留时，按 mtime 超期清除
Schedule::command('system:clear-tmp --hours=24')->dailyAt('04:00')->withoutOverlapping();

Artisan::command('app:version', function () {
    $this->info(ColibriPlus::VERSION);
});

Artisan::command('db:test', function () {
    try {
        DB::connection()->getPdo();

        $this->info('OK. Your app is connected to database: ' . DB::connection()->getDatabaseName());
    } catch (Exception $e) {
        $this->error('Could not connect to the database: ' . $e->getMessage());
    }
});
