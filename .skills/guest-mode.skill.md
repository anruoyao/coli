# 能力名称：游客模式（Guest Mode）
版本：1.0.0
关联模块：Auth, Timeline, Explore, Profile, Bootstrap, Router
最后更新：2026-05-05

## 1. 对外接口与契约

### 后端中间件

- **GuestOrAuthMiddleware** → `App\Http\Middleware\GuestOrAuthMiddleware`
  - 行为：若用户已认证，先执行 `UserStatusMiddleware`（设置 me()、locale 等）；若未认证，直接放行
  - 别名：`guest_or_auth`（注册于 `bootstrap/app.php`）
  - 副作用：无写操作

### 后端 API 路由（`routes/api.php`）

| 前缀 | 中间件 | 说明 |
|------|--------|------|
| `bootstrap` | `throttle:120,1` | 公开，BootstrapController 返回 auth.status / guest.enabled / guest.allowed_routes |
| `timeline` | `throttle:240,1` | 公开，内层 `timeline.php` GET 公开，POST/DELETE 需 `auth:sanctum` |
| `profile` | `throttle:120,1` | 公开，内层 `profile.php` GET profile/posts/details 公开，followers/followings 需 `auth:sanctum` |
| `explore` | `throttle:240,1` | 公开，posts 和 people 均可无认证访问 |
| `system` | `throttle + api.key` | 公开 |
| `ads` | `throttle:120,1` | 公开 |
| `translations` | `throttle:240,1` | 公开 |
| 其余前缀 | `auth:sanctum` | 必须认证 |

### 后端 Web 路由（`routes/web.php`）

| 组 | 中间件 | 路由 |
|----|--------|------|
| `guest_or_auth` | `guest_or_auth` | `/`, `/explore`, `/explore/{any}`, `/marketplace`, `/jobs` |
| `auth:sanctum + user.status` | `auth:sanctum`, `user.status` | `/bookmarks`, `/messenger`, `/settings`, `/wallet`, `/live-stream`, `/stories/{story_uuid}`, `/new/post`, `/new/story`, `/{any}` catch-all |
| 无 | 无 | Sitemap, ContentProxy（SEO 渲染）, 语言/主题切换, Auth 页面（guest 中间件） |

### 关键 API 端点

- `GET /api/bootstrap` → `BootstrapController@loadApplication`
  - 返回结构：
    ```
    {
      auth: { status: 'authenticated'|'guest' },
      guest: { enabled: bool, allowed_routes: {...} },
      user: UserData|null  // null when guest
    }
    ```
  - 权限：无
  - 副作用：无

- `GET /api/explore/posts` → `ExploreController@getPosts`
  - 游客行为：仅返回公开作者的帖子（不限制 `excludeSelf`、不排除已关注用户的帖子）
  - 已认证行为：排除自己和已关注用户的帖子（发现新内容）
  - 返回：`TimelineCollection`

- `GET /api/explore/people` → `ExploreController@getPeople`
  - 游客行为：返回所有活跃作者（不排除自己、不排除已关注）
  - 返回：`PeopleCollection`（每个条目 `meta.relationship` 为 `null`）

- `GET /api/timeline/feed` → `FeedController@getFeed`
  - 游客行为：返回空数组
  - 权限：无
  - 副作用：无

- `GET /api/timeline/post/{hashId}` → `FeedController@getPostData`
  - 公开访问，任何人可查看帖子详情
  - 权限：无

- `GET /api/timeline/post/{hashId}/comments` → `FeedController@getPostComments`
  - 公开访问
  - 权限：无

### 前端核心组件

#### 桌面端

- `@D/components/layout/parts/navbar/GuestNavbar.vue`
  - Props：无
  - 使用的 Store：`useAuthStore`
  - 行为：渲染简化的侧边栏导航（首页/探索/市场/职位 + 登录/注册按钮）
  - 登录链接：`$getRoute('user_auth_index')`（需 `embeds.blade.php` 中注册）
  - 注册链接：`$getRoute('user_auth_signup')`（需 `embeds.blade.php` 中注册）

- `@D/views/home/HomeIndex.vue`
  - 使用的 Store：`useAuthStore`, `useTimelineStore`
  - 关键行为：`onMounted` 中若 `!authStore.authCheck`，自动 `router.push({ name: 'explore_posts' })` 跳转到探索页
  - PublicationEditorTrigger 通过 `v-if="authStore.authCheck"` 仅对已认证用户显示
  - StoriesFeed 通过 `v-if="authStore.authCheck"` 仅对已认证用户显示

- `@D/views/explore/children/people/ExplorePeople.vue`
  - 使用的 Store：`useAuthStore`, `useExplorePeopleStore`
  - 关键行为：`onMounted` 中若 `!authStore.authCheck`，设置 `state.isGuest = true`，不发起任何 API 请求
  - 游客模板：显示"登录后查看"提示 + 带图标的登录/注册按钮

- `@D/components/timeline/feed/TimelinePublication.vue`
  - 使用的 Store：`useAuthStore`
  - 关键行为：
    - 下拉菜单中的 `DropdownReactions`、`add_reaction`、`quote_post`、`mention_author`、`bookmark` 均通过 `v-if="authStore.authCheck"` 守卫
    - 点赞按钮：`v-on:click.stop="likeOrLogin"` → 若未认证则重定向到登录页
  - `likeOrLogin` 方法：检查 `authStore.authCheck`，未认证时 `router.push({ name: 'login' })`

- `@D/layouts/ApplicationMainLayout.vue`
  - PublicationEditorModal 通过 `v-if="authStore.authCheck"` 守卫
  - OnboardingTips 通过 `v-if="authStore.authCheck"` 守卫

- `@D/components/layout/ApplicationSidebar.vue`
  - 通过 `isAuthenticated = computed(() => authStore.authCheck)` 决定渲染 GuestNavbar 还是 ApplicationNavbar

#### 移动端

- `@M/components/layout/parts/navbar/GuestNavbar.vue`
  - Props：无
  - 行为：4 格底部导航（首页/探索） + 2 个 PrimaryIconButton（登录/注册）

- `@M/views/home/HomeIndex.vue`
  - 与桌面端相同逻辑，游客自动跳转到 `explore_posts`

- `@M/views/explore/children/people/ExplorePeople.vue`
  - 与桌面端相同逻辑，游客显示登录提示

- `@M/components/timeline/feed/TimelinePublication.vue`
  - 与桌面端相同：ActionSheet 中的 `ActionSheetReactions`、`add_reaction`、`bookmark` 通过 `v-if="authStore.authCheck"` 守卫
  - 点赞按钮使用 `likeOrLogin`

- `@M/components/layout/parts/HeaderMenu.vue`
  - 游客模板：仅显示登录/注册链接（使用 `ActionSheetItem`）
  - 已认证模板：完整菜单（收藏/钱包/设置/退出）

- `@M/components/layout/ApplicationHeader.vue`
  - NotificationsButton 通过 `v-if="authStore.authCheck"` 守卫

- `@M/layouts/ApplicationMainLayout.vue`
  - 通过 `isAuthenticated` computed 决定渲染 GuestNavbar 还是 ApplicationNavbar

### 前端 Store

- `@D/store/app/app.store.js` / `@M/store/app/app.store.js`
  - 新增 getter：
    - `isGuest`: `bootstrapData.auth.status === 'guest'`
    - `guestAllowedRoutes`: `bootstrapData.guest.allowed_routes`
    - `guestMode`: `isGuest && guestAllowedRoutes !== null`

### 前端路由守卫

- `@D/router/index.js` / `@M/router/index.js`
  - `beforeEach` 守卫：若 `to.meta.auth === true` 且 `!authStore.authCheck`，重定向到登录页
  - 公开路由的 `meta.auth` 设为 `false`

## 2. 安全约束清单（不可被绕过的硬规则）

1. **三层防护体系**：任何需要认证的功能必须同时有 UI 隐藏（`v-if="authStore.authCheck"`）、路由守卫（`beforeEach` 检查 `meta.auth`）、后端中间件（`auth:sanctum`）。三者缺一不可，不可仅依赖其中一层。

2. **`me()` 函数不可在未认证时调用**：所有 Resource 类（TimelineResource、ProfileResource、PeopleResource、CommentResource、StoryResource、UserOverviewResource、FollowResource、ProductResource、JobResource、GroupResource）中使用 `me()` 前必须用 `auth_check()` 包裹。`me()` 在未认证时返回 `null`，直接访问其属性将导致 500 错误。

3. **Bootstrap 数据中的 user 字段可为 null**：所有访问 `bootstrapData.user` 的前端代码必须处理 `null` 情况（如 OnboardingTips 的 `userData.value && userData.value.has_tips`）。

4. **Timeline 写操作（发帖/删帖/点赞/收藏/评论）必须认证**：虽然 `routes/api.php` 中 timeline 前缀未加 `auth:sanctum`，但内层 `timeline.php` 中所有 POST/PUT/DELETE 路由必须包裹在 `auth:sanctum` 组中。

5. **注册路由必须在 embeds.blade.php 中注册**：`$getRoute('user_auth_signup')` 依赖 `window.BackendEmbeds.routes.user_auth_signup`，若未在 embeds 中显式添加，链接将失效（点击无反应）。

6. **游客模式下不得有任何写操作**：所有 Action 类、Controller 方法中若包含数据库写入，必须通过中间件或 `auth_check()` 守卫。

7. **API 返回数据不得泄露隐私**：游客看到的帖子详情中，`meta.is_owner` 和 `meta.relationship` 等字段必须正确反映未认证状态（`is_owner = false`，`relationship = null`）。

## 3. 最常见的错误用法与反模式

### ❌ 错误 1：使用 `<script setup>` 语法但忘记 `return`
```javascript
// Vue Options API (defineComponent + setup) 中
setup() {
    const authStore = useAuthStore();
    // 忘记 return { authStore } —— 模板访问 authStore 会 undefined
}
```
正确做法：在所有 `defineComponent` 组件中，`setup()` 必须 `return` 模板中使用的所有变量。
```javascript
setup() {
    const authStore = useAuthStore();
    return { authStore: authStore };
}
```

### ❌ 错误 2：在 Resource 中直接调用 `me()`
```php
// 危险 —— 游客访问时 me() 返回 null
$isOwner = me()->id === $this->user_id;
```
正确做法：
```php
$isOwner = auth_check() && me()->id === $this->user_id;
```

### ❌ 错误 3：仅在 UI 上隐藏，不做路由守卫
```javascript
// 仅在模板加了 v-if，但不设 meta.auth: true
// 用户可通过直接输入 URL 绕过
```
正确做法：必须同时设置路由 meta `auth: true` 和后端中间件 `auth:sanctum`，形成三层防护。

### ❌ 错误 4：在 inner route 文件中忘记重新声明 auth 中间件
```php
// routes/api.php 中 timeline 前缀无 auth:sanctum
// 但 timeline.php 中写操作必须重新声明
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/publish', ...);
});
```

### ❌ 错误 5：忘记在 embeds.blade.php 注册新路由
```html
<!-- 模板中使用 $getRoute('user_auth_signup') 但 embeds 中未注册 -->
<!-- 结果：href 为 undefined，点击无效 -->
```
正确做法：每新增一个在 JS 中通过 `$getRoute()` 引用的 Laravel 路由，都必须在 `embeds.blade.php` 的 `routes` 对象中注册。

### ❌ 错误 6：图标名在 icons/styles/*.js 中不存在
```html
<SvgIcon name="log-in-01" type="line"></SvgIcon>
<!-- line.js 中只有 log-in-02，没有 log-in-01 —— 图标不渲染 -->
```
正确做法：使用图标前先在 `resources/js/spa/assets/icons/styles/line.js`（或对应 style 文件）中确认图标名存在。

## 4. 与其他技能（Skill）的协作

- **SEO / Sitemap**：ContentProxyController 和 CrawlerDetector 依赖通常的 web.php 路由执行；游客模式不应阻止爬虫访问（ContentProxy 和 Sitemap 路由不在任何 auth 中间件组中）。
- **Auth Module**：游客模式依赖 Laravel Sanctum SPA 认证；`me()` 函数、`auth_check()` 函数的行为取决于 `UserStatusMiddleware` 是否执行。
- **Timeline Module**：TimelineResource、CommentResource 的访客安全由游客模式保障；写操作的内层 `auth:sanctum` 组依赖当前路由结构。
- **Explore Module**：ExploreController 的 `getPosts/getPeople` 通过 `auth_check()` 区分游客和已认证用户行为。

## 5. 模块不变量（无论代码怎么重构都不能改变的事）

- `me()` 函数在未认证时返回 `null`，访问其属性/方法必抛异常
- `auth_check()` 是判断当前请求是否已认证的唯一正确方式
- 游客永远不能执行任何写入操作（发帖、评论、点赞、收藏、关注、设置修改等）
- 游客看到的帖子/用户列表不能包含隐私限制内容
- Bootstrap API 端点必须永远公开（无 auth 中间件），否则 SPA 无法初始化
- Translations API 端点必须永远公开，否则登录页的翻译文本无法加载
- `routes/web.php` 的 `{any}` catch-all 路由必须在 `auth:sanctum` 组中，确保未知路径不会无意中公开
