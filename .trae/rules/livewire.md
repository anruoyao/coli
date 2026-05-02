---
alwaysApply: false
globs: "app/Livewire/**/*.php"
---

# ColibriPlus Livewire Component Rules

## Livewire 组件结构规范

### 必须遵守
1. 继承 `Livewire\Component`
2. 使用 `use WithFileUploads;`（如需要文件上传）
3. 定义 `public Model $modelData;` 用于数据模型
4. 定义 `public array $formData = [];` 用于表单数据
5. `mount()` 方法初始化表单
6. `render()` 返回 `view('livewire.path.to.view')`
7. `getRules()` 返回验证规则数组
8. `submitForm()` 处理表单提交
9. 使用 `XRule::join()` 配置动态验证
10. `validate()` 调用使用命名参数
11. 用户输入使用 `e()` 转义

### 验证规则示例
```php
public function getRules()
{
    return [
        'formData.name' => [
            'required',
            'string',
            XRule::join('min', config('module.validation.name.min')),
            XRule::join('max', config('module.validation.name.max'))
        ],
    ];
}
```
