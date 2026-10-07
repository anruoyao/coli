# ColibriPlus 安装指南（Installation Guide）

> 本指南基于官方文档（[安装](https://docs.colibriplus.social/guide/docs/installation.html)、[WebSockets](https://docs.colibriplus.social/guide/docs/websockets.html)、[FFmpeg](https://docs.colibriplus.social/guide/docs/ffmpeg.html)、[文件存储](https://docs.colibriplus.social/guide/docs/file-storages.html)、[队列](https://docs.colibriplus.social/guide/docs/queues.html)、[故障排查](https://docs.colibriplus.social/guide/docs/troubleshooting.html)、[管理后台](https://docs.colibriplus.social/guide/features/admin-panel.html)）整理，并**新增了本仓库独立开发的 NSFW 自动识别功能部署章节**。

ColibriPlus 是基于 Laravel 的全栈社交应用（PHP 后端 + Vue.js 前端），运行在 Linux 环境。需要 VPS/VDS 服务器承载 Node、PHP、MySQL、Red----------- is 等多个服务。

***

## 目录

- [一、环境要求](#一环境要求)
- [二、PHP 扩展](#二php-扩展)
- [三、安装步骤](#三安装步骤)
- [四、基础配置（.env）](#四基础配置env)
- [五、数据库配置](#五数据库配置)
- [六、构建前端](#六构建前端)
- [七、Redis 配置](#七redis-配置)
- [八、队列与 Horizon](#八队列与-horizon)
- [九、WebSocket（Reverb）](#九websocketreverb)
- [十、FFmpeg](#十ffmpeg)
- [十一、文件存储（Round Robin）](#十一文件存储round-robin)
- [十二、HTTP 服务器（Nginx）](#十二http-服务器nginx)
- [十三、Docker 部署](#十三docker-部署)
- [十四、管理后台](#十四管理后台)
- [十五、NSFW 自动识别（本仓库新增功能）](#十五nsfw-自动识别本仓库新增功能)
- [十六、故障排查](#十六故障排查)
- [十七、生产部署检查清单](#十七生产部署检查清单)

***

## 一、环境要求

| 组件       | 版本要求                                                |
| -------- | --------------------------------------------------- |
| PHP      | 8.3+                                                |
| 数据库      | MySQL 8.0+（或 PostgreSQL / SQLite 等关系型数据库）           |
| Node.js  | 22.x+                                               |
| Redis    | 7.0+（缓存、会话、队列、Round Robin 存储调度必需）                   |
| Composer | 最新稳定版                                               |
| NPM      | 随 Node.js                                           |
| Web 服务器  | Nginx（推荐）/ Apache                                   |
| Git      | 可选但强烈推荐                                             |
| Python   | 3.11+（仅 NSFW 自动识别功能需要，见[第十五章](#十五nsfw-自动识别本仓库新增功能)） |
| FFmpeg   | 视频/音频处理必需，见[第十章](#十ffmpeg)                          |

***

## 二、PHP 扩展

安装依赖前，请确保以下 PHP 扩展已启用（以 PHP 8.3 为例）：

```
curl, mbstring, pdo_mysql, fileinfo, exif, intl, gd, imagick(可选),
redis, bcmath, zip, opcache, xml, dom, xmlwriter, tokenizer, bz2
```

Debian/Ubuntu 安装示例：

```bash
sudo apt update
sudo apt install -y php8.3-{cli,fpm,curl,mbstring,mysql,gd,redis,bcmath,zip,xml,intl,opcache,imagick}
```

***

## 三、安装步骤

### 1. 上传源码

将源码上传到服务器域名根目录（如 `/var/www/colibriplus` 或 `/www/wwwroot/your-domain`），Nginx 的 root 需指向项目 `public` 目录。

### 2. 文件权限（FS Permissions）

```bash
cd /path/to/your/colibriplus

# 创建必需目录
sudo mkdir -p storage/app/public/uploads/posts/images
sudo mkdir -p storage/app/public/uploads/posts/videos
sudo mkdir -p storage/app/tmp/images
sudo mkdir -p storage/app/tmp/videos
sudo mkdir -p storage/frontend

# 设置属主与权限（示例以 www-data 为例，BT 面板等环境请按实际运行用户调整）
sudo chown -R $USER:www-data .
chmod -R ug+rwx storage bootstrap/cache
```

### 3. 复制环境变量文件

```bash
cp .env.example .env
```

### 4. 安装依赖

```bash
# PHP 依赖
composer install

# 前端依赖
npm install
```

***

## 四、基础配置（.env）

```bash
APP_NAME=ColibriPlus
APP_ENV=production
APP_URL=https://your-domain.com

SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com,localhost,127.0.0.1
ADMIN_EMAIL=admin@your-domain.com
BACKUP_EMAIL=admin@your-domain.com

APP_DEBUG=false   # 生产环境必须为 false
```

生成应用密钥并执行初始化：

```bash
php colibri key:generate
```

***

## 五、数据库配置

### 1. 编辑 .env

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=colibriplus
DB_USERNAME=username
DB_PASSWORD=password
```

### 2. 迁移与种子

```bash
php colibri key:generate
php colibri migrate --force
php colibri db:seed
php colibri livewire:publish --assets
php colibri vendor:publish --tag=log-viewer-assets --force
php colibri storage:link
```

> 若数据库不存在，CLI 会提示自动创建，输入 `Y` 确认即可。

### 原生广告影子帖同步

部署包含原生广告信息流功能的版本并完成数据库迁移后，运行一次同步命令，为已有的非草稿广告创建或对齐影子帖：

```bash
php colibri ads:sync-shadow-posts
```

该命令可重复执行；新建或编辑广告会在保存时自动同步，无需每次部署后再次运行。只有已发布、已审核通过且预算未耗尽的广告会在信息流展示。

***

## 六、构建前端

```bash
# 创建构建版本号文件（必须）
mkdir -p storage/frontend
echo 1 > storage/frontend/build.num

# 构建前端资源（Vite + Vue + Tailwind）
npm run build
```

构建产物输出到 `public/dist`（或 `public/build`）目录。

***

## 七、Redis 配置

ColibriPlus 使用 Redis 做缓存、会话管理、队列存储与 Round Robin 存储调度。

```bash
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=   # 若 Redis 设置密码则填写
```

***

## 八、队列与 Horizon

视频转码、邮件发送、NSFW 检测（见[第十五章](#十五nsfw-自动识别本仓库新增功能)）等耗时任务均在后台队列执行。

### 1. 配置

```bash
QUEUE_CONNECTION=redis
```

### 2. 启动 Horizon

```bash
php colibri horizon
```

### 3. Supervisor 守护（生产推荐）

`/etc/supervisor/conf.d/horizon.conf`：

```ini
[program:horizon]
directory=/path/to/your/colibriplus/
command=php colibri horizon
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/colibriplus/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon:*
```

> ⚠️ Horizon 不支持多进程，`numprocs=1`。扩容请用负载均衡。

***

## 九、WebSocket（Reverb）

ColibriPlus 使用 Laravel Reverb 实现聊天、通知、输入状态等实时功能，要求环境为 **HTTPS/SSL**。

### 1. .env 配置

```bash
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=13000
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_CONNECTION_STATUS=on

# Reverb App 密钥（生成随机安全值）
REVERB_APP_ID=your-random-id
REVERB_APP_KEY=your-random-key
REVERB_APP_SECRET=your-random-secret
```

开发环境可：`REVERB_HOST=localhost`、`REVERB_PORT=13000`、`REVERB_SCHEME=http`。

### 2. 防火墙放行

```bash
sudo ufw allow 13000          # UFW
sudo firewall-cmd --permanent --add-port=13000/tcp && sudo firewall-cmd --reload   # firewalld
```

### 3. 构建并启动

```bash
npm run build
php colibri reverb:start          # 生产由 Supervisor 管理
php colibri reverb:start --debug  # 调试模式
```

### 4. Nginx 反向代理（关键）

WebSocket 升级需在 Nginx 配置中代理 `/app` 到 Reverb（见[第十二章](#十二http-服务器nginx)的 nginx 配置）。

### 5. 连接数限制

```bash
ulimit -n   # 小于 1024 时调大
```

编辑 `/etc/security/limits.conf`：

```bash
* soft nofile 10000
* hard nofile 10000
```

***

## 十、FFmpeg

ColibriPlus 使用 FFmpeg 优化/转码视频、音频、用户故事。

### 1. 安装静态构建（推荐）

```bash
cd services/ffmpeg
chmod +x ffmpeg-install.sh
sudo ./ffmpeg-install.sh
```

二进制安装到 `services/ffmpeg/bin/`：

```
services/ffmpeg/bin/
├── ffmpeg
└── ffprobe
```

### 2. 配置路径

后台「设置 → FFmpeg 设置」填写 ffmpeg 与 ffprobe 的**绝对路径**（项目根目录运行 `pwd` 获取）：

```
/absolute/path/to/colibriplus/services/ffmpeg/bin/ffmpeg
/absolute/path/to/colibriplus/services/ffmpeg/bin/ffprobe
```

```bash
chmod +x services/ffmpeg/bin/ffmpeg
chmod +x services/ffmpeg/bin/ffprobe
```

### 3. 测试

后台 FFmpeg 设置的「测试 FFmpeg」标签页点击「Start Testing」，显示正常输出即配置成功。

> 系统级 ffmpeg（`apt install ffmpeg`）也可用，NSFW 视频抽帧即依赖系统 ffmpeg（见[第十五章](#十五nsfw-自动识别本仓库新增功能)）。

***

## 十一、文件存储（Round Robin）

ColibriPlus 使用**轮询文件存储**，将文件均匀分布在多个存储位置。

### 支持类型

- 本地（Local）
- S3（AWS、DigitalOcean、Wasabi、Bunny、Google Cloud 等）
- FTP/SFTP（测试中）

### 工作原理

1. 用户上传文件 → 2. 轮询系统选择下一存储盘 → 3. 文件存入该盘 → 4. 系统切换下一盘 → 5. 负载均匀分布

> 🔴 Round Robin 依赖 Redis 运行。

### 配置

新增磁盘在 `var/config/filesystems/disks.php`：

```php
<?php
return [
    's3_one' => [
        'name' => 'Disk name',
        'description' => 'Disk description. E.g. "S3 Storage One"',
        'driver' => 's3',
        'key' => 'your_s3_storage_key',
        'secret' => 'your_s3_storage_secret',
        'region' => 'your_s3_storage_region',
        'bucket' => 'your_s3_storage_bucket',
        'url' => 'your_s3_storage_url',
        'endpoint' => 'your_s3_storage_endpoint',
        'use_path_style_endpoint' => false,
        'throw' => false,
    ],
    // 更多磁盘...
];
```

### 重要规则

- `key` 全站唯一（如 `s3_one`、`s3_two`）
- `driver` 必须正确：`s3` / `ftp` / `sftp` / `local`
- ⚠️ 语法错误会导致全站无法加载，部署前务必验证
- ⚠️ 切勿删除仍在使用的磁盘

***

## 十二、HTTP 服务器（Nginx）

### 推荐配置（含 Reverb 代理）

参考 `docker/nginx/conf.d/nginx.conf`。注意：**root 指向** **`public`** **目录**，`location /app` 代理到 Reverb（13000 端口）。

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/html/public;

    client_max_body_size 4G;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 16k;

    proxy_read_timeout 7200s;
    proxy_connect_timeout 7200s;
    proxy_send_timeout 7200s;
    send_timeout 7200s;
    fastcgi_read_timeout 300;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.html index.htm index.php;
    charset utf-8;

    location ~* ^/storage/.*\.(php|phtml|php5|php7)$ { deny all; return 404; }
    location ~ /\.(env|git) { deny all; return 404; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://localhost:13000;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    location ~* \.(jpg|jpeg|gif|png|css|js|ico|svg|woff|woff2|ttf|otf|eot|map|mp4|webm|ogg|ogv|webp|zip|rar|tar|gz|bz2|7z)$ {
        expires max;
        log_not_found off;
    }
}
```

SSL 证书可使用 Let's Encrypt（CertBot）。

### 生效与优化

```bash
nginx -t
sudo systemctl restart nginx
sudo systemctl restart php-fpm
php colibri optimize
```

***

## 十三、Docker 部署

若偏好 Docker，直接使用仓库自带编排：

```bash
docker-compose up -d
```

所有依赖见 Dockerfile。

***

## 十四、管理后台

ColibriPlus 自带基于 Livewire + Alpine.js + TailwindCSS 的管理后台。

### 重要说明

- 后台**没有独立登录页**，管理员通过主站登录页登录
- 登录后拥有 admin 角色，导航栏会显示后台入口链接

### 分配管理员角色

1. 注册一个用户
2. 终端运行：

```bash
php colibri admin:root
```

按提示输入要设为管理员的用户名，成功提示：`Admin role assigned successfully.`

### 访问

```
https://your-domain.com/admin
```

### 修改前缀

```bash
APP_ADMIN_PREFIX=admin
```

***

## 十五、NSFW 自动识别（本仓库新增功能）

> 本仓库独立开发：用户发布帖子中的**图片 / GIF / 视频**自动进行 NSFW 检测，命中后自动标记帖子为敏感内容（浏览时显示遮罩），并向作者发送站内 + 邮件通知（含误判申诉引导）。相关源码见 `nsfw-service/`、`app/Jobs/User/Timeline/DetectPostNsfwContent.php`、`app/Services/Nsfw/NsfwDetectionService.php` 等。

### 15.1 架构

```
发帖 PostCreatedEvent ──→ 图片/GIF 触发 ──┐
视频转码完成 MediaProcessedEvent ──→ 触发 ──┤
                                          ▼
                     DetectPostNsfwContent (Horizon 队列 Job)
                                          │ Http multipart
                                          ▼
            nsfw-service（FastAPI + NudeNet 3.x，127.0.0.1:8300）
            图片直检；视频 ffmpeg 抽帧(每2秒/帧,≤30帧)后逐帧检测
                                          │ 原始 detections
                                          ▼
     命中：post.is_sensitive=true + media.metadata 记录原始结果
            + 作者站内/邮件通知（important.post-marked-nsfw）
```

### 15.2 部署 Python 微服务

检测引擎为 Python（FastAPI + [NudeNe、t 3.x](https://github.com/notAI-tech/NudeNet)，ONNX Runtime，CPU 推理单图 100-                                                                           300ms，模型随 pip 包分发约 7MB，**无需 GPU**）。

```bash
cd nsfw-service

# 创建虚拟环境（需 python3-venv，Debian: apt install python3.11-venv）
python3 -m venv venv

# 安装依赖（fastapi / uvicorn / python-multipart / nudenet>=3.4.2）
./venv/bin/pip install --upgrade pip
./venv/bin/pip install -r requirements.txt

# 验证 NudeNet 可导入
./venv/bin/python -c "from nudenet import NudeDetector; print('nudenet import OK')"
```

**依赖系统 ffmpeg**（视频抽帧）：

```bash
which ffmpeg    # 确保已安装，如未安装: sudo apt install -y ffmpeg
```

### 15.3 systemd 常驻服务

将 `nsfw-service/nsfw-detection.service` 安装为系统服务（模板中 `User=www`、路径请按实际环境调整）：

```bash
sudo cp nsfw-service/nsfw-detection.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now nsfw-detection
sudo systemctl status nsfw-detection
```

服务只监听 `127.0.0.1:8300`（无外网暴露），含 `/health` 与 `/v1/detect` 两个端点：

```bash
curl http://127.0.0.1:8300/health
# {"ok":true,"model":"320n"}

curl -X POST http://127.0.0.1:8300/v1/detect -F "file=@/path/to/test.jpg" -F "type=image"
# {"ok":true,"media_type":"image","frames_checked":1,"detections":[...]}
```

### 15.4 .env 配置

```bash
NSFW_DETECTION_URL=http://127.0.0.1:8300
```

### 15.5 数据库迁移

执行 settings 迁移（`nsfw_detection` 配置组）：

```bash
php colibri migrate --force
```

> ⚠️ **config 缓存注意事项**：若服务器存在 `bootstrap/cache/config.php`，迁移前必须先移走（否则 cached config 覆盖 env 前缀变量），迁移完再移回并重建：
>
> ```bash
> mv bootstrap/cache/config.php /tmp/
> php colibri migrate --force
> mv /tmp/config.php bootstrap/cache/config.php
> php colibri config:cache
> ```

### 15.6 重启 Horizon

新队列 Job 类必须重启 Horizon 才能加载：

```bash
sudo supervisorctl restart colibriplus-horizon   # 或按你的 Supervisor 配置名
```

### 15.7 后台启用与配置

路径：**后台 → 系统配置 → 通知 → NSFW 自动识别**（`/admin/config/nsfw-detection`）

| 配置项       | 说明                           | 默认值  |
| --------- | ---------------------------- | ---- |
| NSFW 自动识别 | 功能总开关                        | 关闭   |
| 置信度阈值     | 分数 ≥ 阈值才判定命中，0.10-0.99，越高越严格 | 0.60 |
| 触发标签集     | 仅这些 NudeNet 标签参与判定（每行一个）     | 见下方  |
| 作者通知      | 命中后通知作者（站内 + 邮件）             | 开启   |
| 检测服务状态    | 一键测试微服务连通性                   | —    |

默认触发标签集（**NudeNet 3.x 命名，`X_EXPOSED`** **风格**）：

```
FEMALE_GENITALIA_EXPOSED
MALE_GENITALIA_EXPOSED
FEMALE_BREAST_EXPOSED
MALE_BREAST_EXPOSED
BUTTOCKS_EXPOSED
ANUS_EXPOSED
```

> ⚠️ **命名陷阱**：NudeNet 2.x 的 `EXPOSED_GENITALIA_F` 风格命名与 3.x 实际输出不匹配，会导致检测命中但判定为空。务必使用上表 3.x 命名。`COVERED_*`、`BELLY`、`FEET`、`FACE` 类不触发。

### 15.8 通知与邮件

- 通知类型：`important.post-marked-nsfw`（站内数据库通知 + 邮件 + 实时广播）
- 邮件依赖全局 `notifications.email.enabled`（需配置 SMTP）
- 纯图片/视频帖（无文本）使用独立文案，不会出现「您的帖子「」」
- 用户**不可自行取消**自动标记；误判由管理员核查 `media.metadata.nsfw_detection` 后改库解标（`posts.is_sensitive=false`）

### 15.9 检测流程要点

| 媒体类型     | 触发时机                        | 说明                                |
| -------- | --------------------------- | --------------------------------- |
| 图片 / GIF | `PostCreatedEvent` 发帖后      | 图片上传即 PROCESSED，直接检测；GIF 仅检测首帧    |
| 视频       | `MediaProcessedEvent` 转码完成后 | 避免与转码 Job 的 tmp 文件并发，检测时源文件已落最终磁盘 |

- 服务异常仅重试（tries=3、backoff=\[60,300]），**绝不因检测失败标记敏感**
- 用户已手动标记（`is_sensitive=true`）的帖子跳过
- 检测结果写入 `media.metadata.nsfw_detection`（flagged / labels / max\_score / checked\_at / engine）

***

## 十六、故障排查

### 后端问题

| 问题      | 排查/解决                                                                                                                                                          |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 500 错误  | 检查权限 `chmod -R 775 storage bootstrap/cache`；清缓存 `php colibri cache:clear / config:clear / route:clear / view:clear`；看日志 `tail -f storage/logs/colibriplus.log` |
| 数据库连接失败 | 核对 `.env` 数据库配置；`php colibri db:test`；`php colibri migrate --force`                                                                                            |
| 会话/登录异常 | `SESSION_DRIVER` 配置；`php colibri key:generate`                                                                                                                 |

### 前端问题

| 问题       | 排查/解决                                                                |
| -------- | -------------------------------------------------------------------- |
| 白屏/JS 报错 | `npm install && npm run build`；浏览器 F12 看 Console/Network；硬刷新 Ctrl+F5 |
| API 连接失败 | 核对前端指向的后端 URL；检查 CORS；`php colibri route:list`                       |

### 文件上传问题

```bash
chmod -R 775 storage/app/public
chmod -R 775 public/storage
php colibri storage:link
```

检查 PHP `upload_max_filesize` / `post_max_size` / `memory_limit` / `max_execution_time` 及 Web 服务器上传限制。

### 性能问题

```bash
php colibri config:cache
php colibri route:cache
php colibri view:cache
php colibri optimize
```

### 环境问题

- 生产环境 `APP_DEBUG=false`、`APP_ENV=production`
- 对比 `.env.example` 检查缺失变量

### NSFW 功能专项排查

| 现象        | 排查                                                                                                                                                                                                                                    |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 发布色情帖未被标记 | ① 后台功能开关是否开启；② `systemctl status nsfw-detection` + `curl 127.0.0.1:8300/health`；③ 后台「测试连通性」；④ 查看 `media.metadata.nsfw_detection` 原始检测结果与触发标签集命名（3.x 风格）；⑤ 确认阈值                                                                        |
| 通知/邮件未收到  | ① `supervisorctl status colibriplus-horizon`；② `failed_jobs` 表是否有失败 Job（`php colibri queue:failed`）；③ 通知类失败常见原因是通知静音监听器对系统 actor(id=0) 报错——已修复：`HandleNotificationSuppress` 跳过系统 actor；④ 邮件依赖 `notifications.email.enabled` 与 SMTP 配置 |
| 微服务抽帧失败   | 确认系统 ffmpeg 已安装且可执行                                                                                                                                                                                                                   |

### 通用调试步骤

1. 查看日志：Laravel `storage/logs/colibriplus.log`、Web 服务器日志、浏览器 Console
2. 临时开启 `APP_DEBUG=true`（排查完必须关闭）
3. 清缓存 `php colibri optimize:clear && npm run build`
4. 修权限 `find . -type f -exec chmod 644 {} \; && find . -type d -exec chmod 755 {} \; && chmod -R 775 storage bootstrap/cache`

> 💡 任何修复前请先备份数据库与文件，尤其是生产环境。

***

## 十七、生产部署检查清单

- [ ] PHP 8.3+ 及所需扩展
- [ ] MySQL 8.0+ 数据库已迁移、种子完成
- [ ] `.env`：`APP_ENV=production`、`APP_DEBUG=false`、`APP_URL`、`APP_KEY`
- [ ] 前端已 `npm run build`（含 `storage/frontend/build.num`）
- [ ] Redis 运行中，`QUEUE_CONNECTION=redis`
- [ ] Horizon 由 Supervisor 守护（新 Job 类更新后必须重启）
- [ ] Reverb 由 Supervisor 守护，Nginx `/app` 已代理 WebSocket
- [ ] FFmpeg 路径已配置并通过后台测试
- [ ] 存储目录权限正确（storage / bootstrap/cache / public/storage 软链）
- [ ] Nginx `root` 指向 `public`，`.env`/`.git` 禁止访问，`client_max_body_size` 足够大
- [ ] 后台：分配 admin 角色（`php colibri admin:root`）
- [ ] 可选：NSFW 微服务已部署、systemd 常驻、后台开关开启、连通性测试通过
- [ ] 可选：SMTP 配置完成（邮件通知依赖）

