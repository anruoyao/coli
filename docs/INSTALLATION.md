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
- [十六、NLLB 翻译服务](#十六nllb-翻译服务)
- [十七、故障排查](#十七故障排查)
- [十八、生产部署检查清单](#十八生产部署检查清单)

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
| Python   | 3.11+（可选 NSFW / NLLB 微服务需要，见第十五、十六章） |
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

构建产物输出到 `public/build`。Vue 源码更新后必须重新构建；清理 Blade 缓存不会更新浏览器加载的 JavaScript。

### 现有服务器更新与权限检查

以下以宝塔环境的运行用户 `www` 为例，其他环境替换为实际部署用户。构建前检查输出目录权限：Vite 会删除旧产物，需要对输出目录及其子目录拥有写入和执行权限。

```bash
cd /www/wwwroot/misskey.site
sudo -u www git fetch origin main
sudo -u www git pull --ff-only origin main

# 查看目录属主、权限；如有本地改动导致拉取失败，先处理改动，不要强制覆盖
ls -ld public public/build public/build/assets storage/frontend
```

若以 `www` 构建时出现 `EACCES: permission denied, unlink .../public/build/assets/...`，先检查上述目录，再以 root 修复仅构建输出目录的权限：

```bash
mkdir -p public/build storage/frontend
chown -R www:www public/build storage/frontend
find public/build storage/frontend -type d -exec chmod u+rwx {} +
find public/build storage/frontend -type f -exec chmod u+rw {} +

# 修复后始终以同一用户构建，避免再次生成 root 属主的产物
sudo -u www npm run build
```

只有构建成功后才执行后续步骤：

```bash
sudo -u www php artisan view:clear
/etc/init.d/php-fpm-83 reload
```

浏览器强制刷新以加载新资源。构建会清理旧产物；失败可能留下不完整的输出目录，应及时修复并完成构建。不要通过 `chmod -R 777`、以 root 构建或禁用 Vite 清理输出目录来绕过权限问题。

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

## 十六、NLLB 翻译服务

NLLB 是可选的本地 CTranslate2 服务。2 核 4 GB 机器上的 `int8`、单 worker、`threads=1` 或 `2`、`beam_size=2`、单并发和全局 10 次/分钟，是当前实现的保守起点；这些是经验参数，实际内存和延迟会随 CPU、文本、模型文件及其他进程变化，不能当作保证。先为服务设置内存上限，再为 PHP-FPM、Redis、队列、Web 服务器和系统本身预留足够 headroom。

NLLB-200 模型仓库使用 **CC-BY-NC-4.0**。部署前阅读并遵守该许可证，尤其是非商业使用限制；商业场景请取得许可或选择有合适授权的模型。模型和 tokenizer 必须在本机目录中加载，服务不会联网下载。

### 0. 小内存主机配置 swap

2 核 4GB 主机同时运行数据库、PHP、队列与 NLLB 时，建议预留 4GB swap 缓冲内存峰值。先运行 `swapon --show` 和 `df -h /` 检查现有 swap 与磁盘空间；以下仅在 `/swapfile-nllb` 不存在且至少有 4GB 空间时执行，不覆盖已有 swap 文件：

```bash
set -e
sudo test ! -e /swapfile-nllb
sudo fallocate -l 4G /swapfile-nllb
sudo chmod 600 /swapfile-nllb
sudo mkswap /swapfile-nllb
sudo swapon /swapfile-nllb
# 备份 /etc/fstab 后添加一次，确保重启生效。
printf '/swapfile-nllb none swap sw 0 0\n' | sudo tee -a /etc/fstab
sudo systemctl daemon-reload
swapon --show
free -m
```

swap 不能代替运行内存；大量换页会增加延迟。保持单 worker、单并发和 1–2 线程，并观察模型推理后的网站响应。2026-10-13 的部署使用 1 线程、int8、Beam 2，短句中英互译约 1.4–1.7 秒，这只是该主机当时的实测结果。

重启后若网站返回 500，应检查 Laravel 日志和 Redis 的实际连接端口。此主机曾因系统 Redis 服务启动失败导致 `RedisException: Connection refused`；站点实际使用 `/www/server/redis/redis.conf`，应确保对应实例由正确的开机服务管理，避免系统 Redis 和宝塔 Redis 抢占同一端口。

### 1. 准备模型

推荐用仓库的 `translation-service/prepare_model.py` 下载并转换[官方模型](https://huggingface.co/facebook/nllb-200-distilled-600M)，需要独立转换环境（包含 CPU PyTorch），运行环境不需要 torch。工具固定下载完整 revision SHA，自动创建 snapshot 和输出目录：这两个目录必须事先不存在且互不嵌套。转换时峰值内存和磁盘占用高于运行时，建议在资源充足的机器转换后复制产物到 2 核 4 GB 主机。以下从可信项目目录执行，示例项目路径 `/home/zyb/code/coli` 和运行用户 `www` 请按实际环境调整；不要从下载目录执行脚本，也不要依赖 `git clone` 已正确取得 Git LFS 权重。

```bash
sudo apt install -y python3.11-venv
sudo mkdir -p /opt/nllb-prepare /opt/models
sudo chown -R www:www /opt/nllb-prepare /opt/models
sudo -u www python3 -m venv /opt/nllb-prepare/venv
sudo -u www /opt/nllb-prepare/venv/bin/pip install -r translation-service/requirements-prepare.txt

# This is the official model commit verified on 2026-10-13.
sudo -u www /opt/nllb-prepare/venv/bin/python -I /home/zyb/code/coli/translation-service/prepare_model.py \
  --snapshot-dir /opt/nllb-prepare/snapshot --revision f8d333a098d19b4fd9a8b18f94170487ad3f821d \
  --output-dir /opt/models/nllb-200-distilled-600M-ct2-int8
```

工具也可用 `--local-snapshot /path/to/official-snapshot --output-dir /opt/models/new-ct2-int8` 离线转换已经验证的官方 Transformers snapshot，不能用它再转换社区 CT2 成品。

若采用社区 CT2 成品，先审查其来源、许可证、完整 commit SHA、权重哈希及量化类型，再用安装环境中的 `huggingface-cli download COMMUNITY_REPOSITORY --revision VERIFIED_FULL_SHA --local-dir /path/to/new-empty-download` 下载到新的空目录。核对下载的是实际权重而非 LFS 指针，确认 `model.bin`、CT2 `config.json`、词表文件及兼容的 NLLB tokenizer（`tokenizer.json`、`tokenizer_config.json`、`special_tokens_map.json`，必要时 `sentencepiece.bpe.model`）均存在。不要运行下载目录中的脚本；服务设置 `NLLB_MODEL_DIR` 指向 CT2 目录、`NLLB_TOKENIZER_DIR` 指向验证过的 tokenizer 目录，最终以真实模型加载和双向翻译检查验收。社区文件不会消除原模型的非商业使用限制。

### 2. 安装与运行服务

```bash
sudo install -d -o www -g www /opt/nllb-translation /var/lib/nllb-translation
sudo -u www python3 -m venv /opt/nllb-translation/venv
sudo -u www /opt/nllb-translation/venv/bin/pip install -r translation-service/requirements.txt
sudo install -o root -g www -m 0640 translation-service/server.py /opt/nllb-translation/server.py
# Do not create an empty runtime.json: the first successful PUT writes valid JSON.
```

创建 `/opt/nllb-translation/.env`，设置随机长密钥 `NLLB_API_KEY`，文件权限设为 root 所有、`0640`。没有密钥时运行管理接口返回 503；配置密钥后 `/translate` 同样需要 Bearer 认证：

```dotenv
NLLB_MODEL_DIR=/opt/models/nllb-200-distilled-600M-ct2-int8
NLLB_TOKENIZER_DIR=/opt/models/nllb-200-distilled-600M-ct2-int8
NLLB_THREADS=2
NLLB_API_KEY=replace-with-a-random-secret
NLLB_RUNTIME_CONFIG_FILE=/var/lib/nllb-translation/runtime.json
```

创建 `/etc/systemd/system/nllb-translation.service`：

```ini
[Unit]
Description=NLLB CTranslate2 translation service
After=network.target

[Service]
User=www
WorkingDirectory=/opt/nllb-translation
EnvironmentFile=/opt/nllb-translation/.env
ExecStart=/opt/nllb-translation/venv/bin/uvicorn server:app --host 127.0.0.1 --port 8301 --workers 1
Restart=always
RestartSec=5
MemoryAccounting=true
MemoryHigh=1600M
MemoryMax=1800M
CPUQuota=180%
TasksMax=64
TimeoutStartSec=180
TimeoutStopSec=60
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

`MemoryHigh=1600M`、`MemoryMax=1800M` 是需要实测调整的起始限制，超过硬上限会被系统终止，并可能进入重启循环。加载失败先查看日志和主机剩余内存，不要直接扩大上限挤占其他服务。`CPUQuota=180%` 限制为约 1.8 核；worker 固定为 1，避免重复加载模型及产生多份全局限流状态。模型路径、tokenizer 路径、CPU device 和 worker 数是启动配置，不能通过运行接口修改。以下是部署后必须执行的真实模型验收示例，并非已完成的线上验证：

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nllb-translation
curl -fsS http://127.0.0.1:8301/health
# Set NLLB_API_KEY in this shell to the service secret without printing it.
curl -fsS -H "Authorization: Bearer $NLLB_API_KEY" http://127.0.0.1:8301/runtime/health
curl -fsS -H "Authorization: Bearer $NLLB_API_KEY" -H "Content-Type: application/json" \
  -d '{"text":"Hello world","source":"en","target":"zh"}' http://127.0.0.1:8301/translate
# Global 10/minute admission means wait at least 6 seconds before the next call.
sleep 7
curl -fsS -H "Authorization: Bearer $NLLB_API_KEY" -H "Content-Type: application/json" \
  -d '{"text":"你好，世界","source":"zh","target":"en"}' http://127.0.0.1:8301/translate
sudo systemctl status nllb-translation
sudo journalctl -u nllb-translation -n 50 --no-pager
sudo systemctl show nllb-translation -p MemoryCurrent -p MemoryPeak
```

`/health` 无需认证，必须返回 HTTP 200 且 `ok=true`、`ready=true`；未就绪返回 503，进程运行不等于模型可用。两次翻译应返回非空 `translated_text` 并核对方向和语义；记录加载峰值内存、短文及接近 token 上限文本的延迟，然后在 PHP/队列同时繁忙时再次验收。`runtime.json` 是持久状态，父目录必须可写；首次成功 PUT 前该文件应不存在，不要创建空文件。

### 3. 后台运行参数与应用配置

`GET/PUT /runtime` 和 `/runtime/health` 必须用 `Authorization: Bearer <NLLB_API_KEY>` 认证。后台 PUT 的参数为 `threads`、`compute_type`、`beam_size`、`max_input_tokens`、`max_output_tokens`、`max_concurrent_requests` 和 `global_rate_per_minute`，推荐起点为 `2/int8/2/512/512/1/10`。`NLLB_THREADS` 只提供首次启动默认值，持久 JSON 优先。`NLLB_MODEL_DIR`、`NLLB_TOKENIZER_DIR`、`NLLB_API_KEY` 和 `NLLB_RUNTIME_CONFIG_FILE` 都属于不可通过 PUT 修改的启动配置。Laravel 的管理连接 `TRANSLATION_SERVICE_RUNTIME_URL/KEY` 独立于提供方 API URL/key，由部署环境控制，后台更改提供方地址不会重定向管理请求。

升级前记录现有提供方、URL/key 和后台设置，保留 Laravel `.env` 中现有提供方配置，不要覆盖为 NLLB。迁移初始化缺失设置时应保留当前提供方。先完成模型验收及管理连接配置，再由管理员在**后台 → 系统配置 → 翻译服务配置**明确选择 **NLLB-200 + CTranslate2**，填写 `http://127.0.0.1:8301` 和服务密钥后保存；启用 NLLB 的保存动作先调用受认证 PUT，成功后保存应用设置。切换回 LibreTranslate 也需显式操作。已有 settings 的业务参数覆盖环境默认值，仅修改 `.env` 不会自动切换提供方。

```bash
php artisan config:clear
php artisan migrate --force
sudo -u www php artisan view:clear
# Restart only when Python code or startup environment changed.
sudo systemctl restart nllb-translation
sudo /etc/init.d/php-fpm-83 reload
```

源语言 `auto` 由 Laravel 服务端的 PHP 语言检测器解析，再将明确源语言传给 NLLB；NLLB 本身不能检测 `auto`。网页默认目标语言来自用户界面语言（`BackendEmbeds.locale`），请求带有 target 时覆盖后台默认值；后台默认目标仅用于未传 target 的调用。

应用字符上限默认 2000 字，超长返回 422；字符数与 tokenizer token 数不同，即使未超过字符上限，也可能超过含语言标记/EOS 的输入 token 上限而被模型服务以 413 拒绝。输出达到 token 上限却没有 EOS 也会以 413 拒绝，绝不静默截断。Laravel 将服务的 413 映射为 422，提示文本超过翻译 token 上限。默认 token 上限为 512；应用用户限额通常为每用户 10 次/分钟，模型服务另有全局 10 次/分钟（约每 6 秒放行一次）和并发 1，超限直接返回 429，不排队。

首次启用前，在 Laravel `.env` 设置 `TRANSLATION_SERVICE_RUNTIME_URL=http://127.0.0.1:8301` 和 `TRANSLATION_SERVICE_RUNTIME_KEY=<NLLB_API_KEY>`；后台提供方服务密钥也必须等于该 NLLB 密钥。环境文件更改后清理并按部署流程重建配置缓存；保存后数据库 settings 覆盖业务默认值。PUT 遇到正在翻译或重载返回 409，空闲后重试；成功保存会短暂卸载/重新加载模型并将参数原子写入 runtime JSON，重启后继续生效。

### 4. 部署验收与回滚

部署前保留前一版本、环境配置、数据库/settings 和 runtime JSON 备份。健康检查及双向真实翻译通过后再切换用户流量；失败时先在后台恢复原提供方及其 URL/key，再停用 NLLB，避免先停服务造成用户翻译中断。代码回滚还需恢复兼容的应用版本与配置缓存、重新加载 PHP 和长驻进程；不要直接逆向删除生产 settings 迁移。PUT 加载或持久化失败时会尝试恢复上一配置；若恢复也失败，服务保持不就绪（503）。检查日志和 `/health` 再重试。

```bash
# First explicitly restore the previous provider in admin.
sudo systemctl disable --now nllb-translation
# Restore the previous application release and compatible settings if needed.
php artisan config:clear
sudo -u www php artisan view:clear
sudo /etc/init.d/php-fpm-83 reload
```

## 十七、故障排查

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

## 十八、生产部署检查清单

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
- [ ] 可选：NLLB 模型许可允许当前用途、固定 revision/验证模型文件、单 worker 与资源限制生效、健康检查及双向真实翻译通过、后台显式切换且回滚配置已备份
- [ ] 可选：SMTP 配置完成（邮件通知依赖）

