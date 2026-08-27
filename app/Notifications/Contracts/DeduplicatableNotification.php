<?php

namespace App\Notifications\Contracts;

/**
 * 标记此类通知参与「App 内实时通知去重」。
 *
 * 实现该接口的通知在写入 notifications 表时（DeduplicatedDatabaseChannel），如果
 * 24 小时内已存在「同类型 + 同操作者 + 同实体」的未读通知，则不重复新增，
 * 只刷新旧通知的时间戳与数据，避免同一个人反复操作刷屏通知页。
 */
interface DeduplicatableNotification
{
}