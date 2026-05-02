---
alwaysApply: false
globs: "app/Models/*.php"
---

# ColibriPlus Model Rules

## Model 结构规范

### 必须遵守
1. `$table` 使用 `App\Database\Configs\Table` 常量
2. 添加 `public static $snakeAttributes = true;`
3. 使用 `protected $guarded = [];` 而非 `$fillable`
4. `casts()` 方法返回枚举类型转换映射
5. 布尔字段使用 `'boolean'` 转换
6. 密码字段使用 `'hashed'` 转换

### 示例
```php
class Example extends Model
{
    public $table = Table::EXAMPLES;
    public static $snakeAttributes = true;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ExampleStatus::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
```
