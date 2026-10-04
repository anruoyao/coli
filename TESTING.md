# 项目测试说明（ColibriPlus）

> 供后续 AI/开发者快速上手跑测试。**关键坑**：本仓库 Laravel 11 的 `RefreshDatabase` 每个测试方法都会全量重建 75+ 张表（MySQL 上单方法 4 分钟+），**必须用 `DatabaseTransactions`**。

## 1. 环境

- 服务器：SSH `root@45.192.102.217:22`（MCP `ssh-mcp-server`，连接名 `default`，免密）
- 站点目录：`/www/wwwroot/misskey.site`（域名 misskey.site，git 仓库 anruoyao/coli）
- MySQL 凭据：读 `.env` 的 `DB_USERNAME` / `DB_PASSWORD`（root 本地无密码不可用，用项目库账号）
- **测试数据库**：MySQL 中独立库 `testing`（phpunit.xml 的 `DB_DATABASE=testing`），与生产库 `misskey_site` 完全隔离

## 2. 跑测试

```bash
cd /www/wwwroot/misskey.site
# 必须用 www 用户（与项目文件属主一致，避免权限问题）
sudo -u www vendor/bin/phpunit tests/Unit/Feedback tests/Feature/Feedback --colors=never
# 产出：OK (23 tests, 92 assertions)
```

单文件/单方法：
```bash
sudo -u www vendor/bin/phpunit tests/Unit/Feedback/ReportRateLimiterTest.php --filter test_blocks_user_dimension_at_limit --colors=never
```

## 3. 初始化 / 重建 testing 库结构

`DatabaseTransactions` 不建表，**要求 testing 库结构已存在**。首次或结构变更后重建：

```bash
cd /www/wwwroot/misskey.site
# env DB_DATABASE=testing 覆盖 .env，只操作 testing 库（约 7 秒）
timeout 90 env DB_DATABASE=testing sudo -u www php artisan migrate:fresh --force
```

无 MySQL 的本地环境**跑不了测试**（本地无 mysql/redis，验证依赖服务器）。

## 4. 测试编写约定（重要）

- **用 `DatabaseTransactions`，禁用 `RefreshDatabase`**：
  ```php
  use Illuminate\Foundation\Testing\DatabaseTransactions;
  class XxxTest extends TestCase { use DatabaseTransactions, CreatesUsers; }
  ```
  `RefreshDatabase` 逐方法 `migrate:fresh`（Laravel 11 行为：tearDown 重置 `$migrated`），本项目 75+ 表 MySQL 上单方法 >4 分钟必超时。`DatabaseTransactions` 只做事务回滚，秒级。
- **API 请求必须关闭 AppKey 门槛**（`routes/api.php` 组最前挂 `VerifyAppKey`，未带 `X-App-Key` 头返回 404 伪装）：
  ```php
  config(['security.app_key.enabled' => false]); // 放 setUp
  ```
- **被举报/被操作的目标用户需 `ACTIVE` 状态**：`users.status` 默认 `onboarding`，而 `User::activeById()` 过滤 `status=active`，找不到会报 "Reportable resource by given id not found."（500）。
  ```php
  $this->makeUser(['status' => \App\Enums\User\UserStatus::ACTIVE]);
  ```
- 测试基类 `Tests\TestCase` + 用户工厂 `Tests\Feature\Marketing\Concerns\CreatesUsers::makeUser()`（默认 email `*@example.com`）。
- 所有测试统一放 `tests/Unit/Feedback/`（单元）与 `tests/Feature/Feedback/`（功能/端到端）。

## 5. 本项目踩过的坑（写测试/改代码时注意）

| 坑 | 说明/修复 |
|---|---|
| `reports.created_at` 是自定义 cast | `ModelTimestampCast` → `App\Support\DateFormatter`（无 Carbon 方法）。用 `Carbon::parse($model->created_at->getTimestamp())`，**勿加显式 `'UTC'`**（会与 `now()` 产生 8h 偏移） |
| Mailable `$subject` 属性 | 父类 `Illuminate\Mail\Mailable` 已有无类型 `$subject`，子类**不能声明类型**（`public string $subject` 报错），构造提升也不行，改普通属性赋值 |
| encrypted cast 不能参与 where | `firstOrCreate(['email' => 'x'])` 永远匹配不到密文 → 幂等失效。加**明文哈希列**做查询键（如 `report_email_logs.recipient_hash` = sha1(email)） |
| Livewire 图标组件 | 项目用 `<x-ui-icon name=... type=...>`（kebab 组件），**不存在** `<x-ui.icon>`（点号命名是按钮系列如 `x-ui.buttons.pill`） |
| `strip_tags` 保留 script 内容 | `<script>alert(1)</script>` → `alert(1)`（纯文本无标签即无 XSS 执行面），断言应查「不含 `<script`」而非「不含 alert(1)」 |
| 同目标重复举报先删后建 | `ReportController::sendReport` 对同一 reportable 先 delete 再 create，**同一目标不累计计数**；限流测试需举报多个不同目标 |
| 限流持久化 | `ReportRateLimiter` 直接 count `reports` 表（新增 `ip_address` 列），不依赖 cache，重启不丢 |
| GNU sed `\R` | 在单引号 sed 表达式中 `\R` 匹配换行符（非字面 R），替换含 `\R` 字符串（如 `RefreshDatabase`）会静默失败，用 awk 或 perl 避免 |

## 6. 测试环境关键配置（phpunit.xml）

```
APP_ENV=testing  CACHE_STORE=array  SESSION_DRIVER=array
MAIL_MAILER=array（Mail::fake 生效）
QUEUE_CONNECTION=sync  DB_DATABASE=testing  PULSE/TELESCOPE=false
```

## 7. 服务器 MCP 操作注意

- 通过 `DeferExecuteTool` 调 `mcp_ssh-mcp-server_execute-command`：参数结构 `{toolName, params:{cmdString, timeout}}`，**timeout 必须放 params 内**（放顶层会报 additional property not allowed）。
- 部署/同步流程：本地改码 → 用户提交 GitHub → 服务器 `sudo -u www git fetch origin main && sudo -u www git reset --hard origin/main` → 迁移/config:cache/reload（含前端改动需 `npm run build`）。
- 纯 PHP 文件改动轻量部署：`git reset` + `/etc/init.d/php-fpm-83 reload`（清 OPcache）。
