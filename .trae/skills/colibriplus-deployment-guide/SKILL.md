---
name: "colibriplus-deployment-guide"
description: "ColibriPlus Skills installation, configuration, and deployment guide. Invoke when setting up a new development environment or troubleshooting skill activation."
---

# ColibriPlus AI Skills 部署指南

## 目录结构

```
.trae/
├── rules/                                  # 项目规则（自动应用）
│   ├── global-standards.md                 # 全局开发规则（始终激活）
│   ├── models.md                           # Model 文件规则（匹配 app/Models/*.php）
│   ├── livewire.md                         # Livewire 规则（匹配 app/Livewire/**/*.php）
│   └── vue-components.md                   # Vue 组件规则（匹配 resources/js/**/*.vue）
└── skills/                                 # 定制化 AI Skills（手动/智能激活）
    ├── colibriplus-laravel-standards/      # Laravel 编码规范 Skill
    │   └── SKILL.md
    ├── colibriplus-component-architecture/ # 组件架构约束 Skill
    │   └── SKILL.md
    └── colibriplus-quality-gate/           # 代码质量门禁 Skill
        └── SKILL.md
```

## 环境要求

| 组件 | 版本要求 |
|------|---------|
| Trae IDE | 最新版 |
| PHP | 8.2+ |
| Laravel | 12.x |
| Node.js | 18+ |
| npm | 9+ |

## 安装步骤

### 1. 确认基础环境

```bash
# 检查项目目录
cd /path/to/colibri-plus/Source

# 确认 .trae 目录存在
ls -la .trae/
```

### 2. 验证规则文件

规则文件位于 `.trae/rules/` 目录，Trae IDE 会自动检测并加载。验证方式：

```bash
ls -la .trae/rules/
# 预期输出: global-standards.md, models.md, livewire.md, vue-components.md
```

### 3. 验证 Skills

```bash
ls -la .trae/skills/*/SKILL.md
# 预期输出: 3 个 SKILL.md 文件
```

### 4. 测试 Skill 激活

在 Trae IDE 中测试每个 Skill：

```
# 测试 Laravel 标准
输入: "创建一个新的 PostStatus Enum"
预期: 生成的 Enum 使用 backed string 类型、UPPER_CASE 命名

# 测试组件架构
输入: "创建一个用户卡片 Vue 组件"
预期: 使用 <script setup>、TailwindCSS dark:、vue-i18n t()

# 测试质量门禁
输入: "检查刚才的代码是否符合项目规范"
预期: 输出结构化的质量报告
```

## Skill 激活方式

### colibriplus-laravel-standards
- **触发条件**: 编写或修改任何 PHP 文件（Models, Controllers, Enums, Actions, Services, Livewire, Helpers, Policies）
- **激活方式**: 智能激活（AI 根据上下文自动判断），也可通过 `#Skill` 手动激活

### colibriplus-component-architecture
- **触发条件**: 创建或修改 Vue SFC、Livewire Blade 视图、TailwindCSS 样式、Pinia Store
- **激活方式**: 智能激活 + 文件匹配

### colibriplus-quality-gate
- **触发条件**: 功能实现完成后、提交代码前、用户请求质量检查
- **激活方式**: 手动激活（提交前质量门禁）或在 code-reviewer 后自动激活

## 配置更新流程

### 更新规则文件
```bash
# 编辑规则
vim .trae/rules/global-standards.md

# 规则立即生效（无需重启 IDE）
```

### 更新 Skill 文件
```bash
# 编辑 Skill 定义
vim .trae/skills/colibriplus-laravel-standards/SKILL.md

# Skill 在下次激活时使用新定义
```

### 添加新规则
```bash
# 创建新规则文件
cat > .trae/rules/new-rule.md << 'EOF'
---
alwaysApply: false
globs: "app/Services/**/*.php"
---

# Service Class Rules
...
EOF
```

### 添加新 Skill
```bash
# 创建新 Skill 目录
mkdir -p .trae/skills/my-new-skill

# 创建 SKILL.md（参考现有格式）
# 必须包含: name, description 前置元数据 + 详细指令
```

## 故障排查

### Skill 未激活
1. 检查 SKILL.md 文件是否存在
2. 检查 description 字段是否包含触发条件描述
3. 尝试使用 `#Skill名称` 手动激活

### 规则未生效
1. 检查规则文件的 frontmatter 是否正确
2. 确认 `globs` 模式匹配目标文件路径
3. 确认 `alwaysApply: true` 已设置（全局规则）

### 代码风格不符预期
1. 检查规则描述是否明确
2. 在 Skill 中提供更多实例代码
3. 考虑创建补充规则文件

## 与内置 Skills 的协作

本项目的定制 Skills 与 Trae 内置 Skills 互补：

| 内置 Skill | 协作关系 |
|-----------|---------|
| `code-reviewer` | 配合 `colibriplus-quality-gate`，先审查逻辑正确性，再进行规范检查 |
| `frontend-code-review` | 配合 `colibriplus-component-architecture`，先审查前端质量，再检查架构规范 |
| `fix` | 配合所有定制 Skills，自动修复格式问题后再进行规范验证 |
| `webapp-testing` | 配合 `colibriplus-quality-gate`，测试通过后再执行质量门禁 |

## 维护建议

1. **定期审查**: 每季度回顾 Skills 和规则是否需要更新
2. **团队反馈**: 收集开发者对 AI 生成代码质量的反馈
3. **增量改进**: 基于实际使用场景逐步优化规则
4. **版本管理**: 将 `.trae/` 目录纳入 Git 版本控制（排除可能包含敏感信息的文件）
