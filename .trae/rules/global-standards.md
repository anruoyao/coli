---
alwaysApply: true
---

# ColibriPlus 全局开发规则

## 项目技术栈
- **后端**: Laravel 12, PHP 8.2+, Livewire 3.6
- **前端**: Vue 3 (Composition API), TailwindCSS 4, Vite 5
- **数据库**: MySQL (通过 Eloquent ORM)
- **实时通信**: Laravel Reverb (WebSocket)
- **状态管理**: Pinia

## 核心编码原则

### 1. 不添加注释
除非目标文件已有注释风格，否则不要在生成的代码中添加任何注释。代码应该自解释。

### 2. PHP 命名参数
调用多参数函数/方法时始终使用命名参数（named arguments）。

### 3. 枚举优先
所有状态、类型值使用 PHP 8.1+ Backed Enum，禁止使用裸字符串。

### 4. Action 模式
业务操作封装为 Action 类：构造函数注入数据，execute() 执行操作。

### 5. 配置驱动
所有可配置值通过 config() 读取，禁止硬编码。

### 6. 翻译键规范
使用 __() 函数，格式: {domain}/{section}.{key}

### 7. Vue 组件规范
- <script setup> 语法
- Composition API
- TailwindCSS dark: 变体支持深色模式
- vue-i18n 的 t() 进行翻译

### 8. 不要创建文档文件
除非用户明确要求，否则不要创建 .md 文档文件。
