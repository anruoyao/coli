<?php

namespace App\Enums\Media;

enum MediaMigrationType: string
{
    // 本地打包（换服务器场景：storage 打压缩包）
    case ARCHIVE = 'archive';
    // 解压恢复（新服务器上传压缩包后解压 + 校验）
    case EXTRACT = 'extract';
    // 磁盘间迁移（本地 → S3 等目标磁盘，并更新数据库记录）
    case DISK_TRANSFER = 'disk_transfer';

    public function isArchive(): bool
    {
        return $this == self::ARCHIVE;
    }

    public function isExtract(): bool
    {
        return $this == self::EXTRACT;
    }

    public function isDiskTransfer(): bool
    {
        return $this == self::DISK_TRANSFER;
    }
}
