<?php

return [
    'index_title' => '媒体文件迁移',

    'sections' => [
        'local' => [
            'title' => '本地迁移（更换服务器）',
            'caption' => '把整个媒体磁盘一键打包为可下载的 zip 压缩包；在新服务器上上传压缩包并解压，解压后自动按校验和逐文件校验，确保文件结构与内容一致。',
        ],
        'cloud' => [
            'title' => '磁盘迁移（S3 / 云存储）',
            'caption' => '一键把源磁盘的全部媒体文件批量迁移到目标磁盘（如本地 → S3），自动更新数据库中的存储路径引用与用量统计，支持断点续传与失败重试。',
        ],
        'static_disk' => [
            'title' => '主存储磁盘',
            'caption' => '当前主存储磁盘：:disk。用户头像、封面等静态资源跟随主磁盘，迁移到新磁盘后必须在此切换。',
        ],
        'history' => [
            'title' => '迁移历史',
        ],
    ],

    'types' => [
        'archive' => '本地打包',
        'extract' => '解压恢复',
        'disk_transfer' => '磁盘迁移',
    ],

    'statuses' => [
        'running' => '进行中',
        'completed' => '已完成',
        'failed' => '失败',
        'cancelled' => '已取消',
    ],

    'progress_card' => [
        'caption' => '迁移进行中，页面每 3 秒自动刷新。迁移中断后可从断点继续，无需重来。',
        'percent' => '进度',
        'files' => '文件数',
        'bytes' => '数据量',
        'failed' => '失败',
        'cancel' => '取消迁移',
        'confirm_cancel' => '确定取消该迁移？已迁移完成的文件会保留。',
    ],

    'local_form' => [
        'disk_label' => '要打包的磁盘',
        'create_button' => '开始打包',
        'note' => '打包采用 STORE（不压缩）模式，速度最快。打包开始后新上传的文件不在包内，如有需要可再次打包。',
    ],

    'archives' => [
        'title' => '压缩包列表',
        'confirm_extract' => '确定解压该压缩包到所选磁盘？同名路径的已有文件将被覆盖。',
        'confirm_delete' => '确定删除该压缩包？删除后不可恢复。',
    ],

    'restore' => [
        'title' => '上传恢复（在新服务器上操作）',
        'caption' => '上传本工具生成的压缩包。文件按 8MB 分片传输，不受服务器单请求上传体积限制，可上传任意大小的压缩包。上传后在上方压缩包列表点击解压图标即可恢复。',
        'disk_placeholder' => '解压目标磁盘',
        'select_file' => '点击选择 .zip 压缩包',
        'select_caption' => '仅支持本工具生成的压缩包（内含校验清单，解压后逐文件校验）。',
        'uploading' => '上传中…',
        'upload_button' => '开始上传',
        'reset_button' => '重置',
        'done' => '上传完成',
    ],

    'cloud_form' => [
        'source_label' => '源磁盘',
        'target_label' => '目标磁盘',
        'checksum_label' => '全量校验和验证（sha1）',
        'checksum_helper' => '速度较慢，但会对每个复制完成的文件做逐字节校验，重要迁移建议开启。',
        'start_button' => '开始迁移',
        'confirm_start' => '确定把源磁盘的全部文件迁移到目标磁盘？迁移开始后新上传到源磁盘的文件不在本次范围内。',
        'note' => '目标磁盘需先在 <code>var/config/filesystems/disks.php</code> 中配置（如 S3 磁盘）。迁移完成后：1）如需要请在下方切换主存储磁盘；2）如不再使用旧磁盘，可从配置中移除，新上传将不再使用它。迁移中断或失败后可直接重新发起同方向迁移，会自动从断点继续。',
    ],

    'static_disk' => [
        'switch_to' => '切换主磁盘为',
        'switch_button' => '切换',
        'confirm' => '确定切换主存储磁盘？请先确保媒体文件已全部迁移到新磁盘，否则头像、封面等将无法访问。',
        'note' => '切换会更新 <code>STATIC_STORAGE_DISK</code> 环境变量并重建配置缓存。如几分钟后 URL 仍未生效，请重启 php-fpm / Horizon。',
    ],

    'history_table' => [
        'type' => '类型',
        'status' => '状态',
        'files' => '文件',
        'failed' => '失败',
        'started_at' => '开始时间',
        'confirm_retry' => '确定重试该迁移中失败的文件？',
    ],

    'report' => [
        'title' => '迁移报告 · #:id',
        'files_done' => '已迁移文件',
        'bytes_done' => '已迁移数据量',
        'files_failed' => '失败文件',
        'duration' => '耗时',
        'verify_missing' => '解压后缺失',
        'verify_mismatch' => '校验不一致',
        'db_rows_updated' => '数据库更新行数',
        'stats_moved' => '用量统计已归并',
        'failures_sample' => '失败文件（示例）',
        'failures_full_note' => '仅展示前 50 条失败记录，完整清单可点击历史表中的下载按钮获取 JSON 报告。',
        'no_failures' => '没有失败文件。',
        'not_available' => '迁移结束后生成报告。',
        'failure_stage' => [
            'scan' => '扫描',
            'copy' => '复制',
            'verify' => '校验',
            'extract' => '解压',
        ],
    ],

    'flash' => [
        'archive_started' => '打包任务已开始，进度见下方进度卡片。',
        'extract_started' => '解压任务已开始，进度见下方进度卡片。',
        'transfer_started' => '磁盘迁移已开始，进度见下方进度卡片。',
        'migration_running' => '已有迁移任务在进行中，请等待完成或先取消。',
        'resumed' => '已从断点继续迁移。',
        'cancelled' => '迁移已取消。',
        'retry_started' => '失败文件重试已开始。',
        'archive_uploaded' => '压缩包「:name」上传完成，点击列表中的解压图标即可恢复。',
        'archive_deleted' => '压缩包已删除。',
        'archive_not_found' => '压缩包不存在。',
        'env_not_writable' => '.env 文件不可写，请手动修改 STATIC_STORAGE_DISK。',
        'static_disk_switched' => '主存储磁盘已切换为 :disk，正在重建配置缓存。',
    ],
];
