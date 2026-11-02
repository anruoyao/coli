# ColibriPlus

> 基于 Laravel + Vue 3 的社交网络平台，包含信息流、故事、Reels、即时通讯、通知、AI 翻译与内容审核。

ColibriPlus 是一个完整的社交产品：后端负责用户、内容、关注关系、聊天与运营后台，前端提供桌面 / 移动两套 SPA。本仓库在 ColibriPlus 商业模板基础上完成二次开发、功能定制与服务器部署。

> **AI 辅助开发说明**：本项目以 vibe coding 方式开发。作者负责产品需求拆解、功能验收、测试与部署运维，具体代码在 Claude Code / Trae 辅助下生成，并根据实际运行结果持续迭代。作者并非专业软件工程师，仓库用于学习与作品展示。

## 功能概览

### 信息流与内容
- 关注 / 推荐双时间线，支持置顶帖、分页与骨架屏加载
- 图文、视频、音频帖子；故事（Stories）与 Reels
- 点赞、表情回应、评论、书签、话题标签、兴趣筛选与搜索

### 社交与账号
- 注册 / 登录、Sanctum API 鉴权、关注请求审批
- 账号隐私开关、拉黑 / 静音、通知中心
- 封禁 / 停用状态实时同步到客户端

### 即时通讯
- 单聊 / 群聊、文本与图片消息
- 已读回执、未读计数、消息删除
- 基于 Laravel Reverb 的 WebSocket 实时推送

### AI 内容能力
- **翻译**：自托管 NLLB-200（FastAPI + CTranslate2，CPU int8 量化推理），支持 OpenAI 预翻译与 LibreTranslate 驱动
- 帖子 / 个人简介翻译结果永久缓存、源语言自动检测、并发与速率限制
- **内容审核**：NudeNet + ONNX 检测图片 / 视频敏感内容，视频用 ffmpeg 抽帧，队列异步处理并通知作者

### 运营后台
- Livewire 管理后台：用户、内容、广告、支付、翻译、NSFW、版本与维护模式等配置
- 数据库备份 / 恢复：哈希校验、流式下载、恢复前自动生成紧急备份
- 媒体迁移：本地磁盘 ↔ S3，zip manifest 逐文件校验、断点续传
- 原生广告：广告以「影子帖」形式混入信息流，支持槽位轮换、曝光计费、点赞与评论
- App 版本管理、强制更新、维护模式与封禁指令广播

## 技术栈

| 模块 | 技术 |
| --- | --- |
| 后端 | PHP 8.3、Laravel 13、MySQL 8、Redis 7 |
| 队列 / 实时 | Laravel Horizon、Laravel Reverb、Laravel Echo |
| 管理后台 | Livewire 4 |
| Web 前端 | Vue 3、Pinia、Vue Router、Vite、Tailwind CSS 4、PrimeVue |
| AI 微服务 | Python 3.11、FastAPI、CTranslate2、Transformers、NudeNet、ONNX Runtime、FFmpeg |
| 部署 | Nginx、PHP-FPM、Supervisor、Docker Compose |

## 目录结构

```text
app/                         后端业务代码（Controllers / Services / Jobs / Events / Livewire）
resources/js/spa/            Vue 3 桌面与移动端 SPA
resources/views/             Blade 布局与后台页面
routes/                      按 web / api / admin / social / business 拆分的路由
database/                    迁移与种子数据
translation-service/         NLLB 翻译微服务（FastAPI）
nsfw-service/                NudeNet 内容审核微服务（FastAPI）
deploy/                      Nginx、Supervisor、健康检查脚本
docs/INSTALLATION.md         安装与部署说明
```

## 本地运行

环境要求：PHP 8.3+、MySQL 8+、Redis 7+、Node.js 22+、Composer、FFmpeg。

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate
npm run build
php artisan serve
```

另开终端启动前端开发服务：

```bash
npm run dev
```

按需启动队列与 WebSocket：

```bash
php artisan horizon
php artisan reverb:start
```

## AI 微服务（可选）

翻译服务：

```bash
cd translation-service
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn server:app --host 127.0.0.1 --port 8301
```

内容审核服务：

```bash
cd nsfw-service
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --host 127.0.0.1 --port 8300
```

NLLB 模型文件与转写步骤见 [docs/INSTALLATION.md](docs/INSTALLATION.md) 第十六章；NSFW 服务部署见第十五章。后台「翻译」与「NSFW 检测」页面中填写服务地址并启用。

## 部署

生产环境使用 Nginx + PHP-FPM + Redis + Supervisor / Horizon，WebSocket 由 Reverb 提供。完整步骤、权限、队列、缓存与故障排查见 [docs/INSTALLATION.md](docs/INSTALLATION.md)。

## 相关仓库

- Flutter 客户端：[anruoyao/coliapp](https://github.com/anruoyao/coliapp)
- 后端：[anruoyao/coli](https://github.com/anruoyao/coli)

## 说明

- 本项目基于 ColibriPlus 商业模板二次开发，原始版权归 ColibriPlus 作者所有。
- 仓库中的定制代码与文档用于学习、作品展示，请勿用于商业转售。
