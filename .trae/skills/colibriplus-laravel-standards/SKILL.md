---
name: "colibriplus-laravel-standards"
description: "Enforces ColibriPlus project-specific Laravel/PHP coding conventions. Invoke when writing or modifying any PHP file (Models, Controllers, Enums, Actions, Services, Livewire components, Helpers, Policies) in this project."
---

# ColibriPlus Laravel Coding Standards

This skill enforces ColibriPlus-specific coding conventions for all PHP backend code. The project uses Laravel 12 with PHP 8.2+, Livewire 3.6, and a custom Action-based architecture.

## Architecture Overview

The project follows a structured MVC pattern enhanced with custom layers:

```
app/
├── Actions/          # Single-responsibility action classes
├── Constants/        # String constants (filesystem paths, etc.)
├── Data/             # Data capsules
├── Database/Configs/ # Table name constants
├── Enums/            # PHP 8.1+ backed enums
├── Http/Controllers/ # Abstract base + concrete controllers
├── Info/             # Project metadata
├── Livewire/         # Livewire components
├── MediaApi/         # External media API integrations
├── Models/           # Eloquent models + Traits
├── Policies/         # Authorization policies
├── Rules/            # Custom validation rules
├── Services/         # Service classes
├── Support/          # Helpers, VO, Casts, utilities
└── Telegram/         # Telegram bot integration
```

## Mandatory Coding Rules

### 1. PHP Named Arguments
**Always use named arguments** when calling functions/methods with multiple parameters or when the meaning isn't immediately obvious:

```php
// CORRECT
$this->validate(rules: $this->getRules(), attributes: [
    'formData.title' => __('business/ads.form.title'),
]);

// CORRECT
$walletService->setUserData(me())->subtractWalletBalance($this->formData['total_budget']);

// WRONG - positional arguments for validation
$this->validate($this->getRules(), [], [
    'formData.title' => __('business/ads.form.title'),
]);
```

### 2. Enum Usage
**Always use PHP 8.1+ backed enums** for state/status/type values. Never use raw strings or integers for status values.

```php
// CORRECT - Use enum cases
'status' => AdStatus::PUBLISHED,
'approval' => AdApproval::PENDING,

// WRONG - Raw string values
'status' => 'published',
'approval' => 'pending',
```

Enum location: `app/Enums/{Domain}/{EnumName}.php`
Enum pattern: Backed enums with `string` or `int` backing type.

```php
enum PostStatus: string
{
    case ACTIVE = 'active';
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case DELETED = 'deleted';
}
```

### 3. Model Configuration via Table Constants
Use `App\Database\Configs\Table` constants for table names:

```php
class User extends Authenticatable
{
    public $table = Table::USERS;
    public static $snakeAttributes = true;
    protected $guarded = [];
}
```

### 4. Model Casts with Enums
Always define `casts()` method using backed enums and custom cast classes:

```php
protected function casts(): array
{
    return [
        'status' => UserStatus::class,
        'role' => UserRole::class,
        'type' => UserType::class,
        'verified_at' => ModelTimestampCast::class,
        'password' => 'hashed',
        'verified' => 'boolean',
    ];
}
```

### 5. Action Pattern
Single-responsibility action classes with `__construct()` receiving data and `execute()` performing the operation:

```php
class AdShowAction
{
    private $adData;

    public function __construct(Ad $adData)
    {
        $this->adData = $adData;
    }

    public function execute()
    {
        // perform operation
    }
}

// Usage
(new AdShowAction($ad))->execute();
```

### 6. Livewire Component Structure
Livewire components must follow this structure:

```php
class Upsert extends Component
{
    use WithFileUploads; // if file uploads needed

    public Model $modelData;
    public array $formData = [];
    public $uploadedFile = null;

    public function mount() { /* initialize form */ }
    public function render() { return view('livewire.path.to.view'); }
    public function getRules() { return [ /* validation rules */ ]; }
    public function submitForm() { /* process submission */ }

    // Private helper methods
    private function helperMethod() { /* ... */ }
}
```

### 7. Validation Rules with XRule
Use the project's custom `XRule` for dynamic validation rules:

```php
public function getRules()
{
    return [
        'formData.title' => [
            'required',
            'string',
            XRule::join('min', config('ads.ad.validation.title.min')),
            XRule::join('max', config('ads.ad.validation.title.max'))
        ],
    ];
}
```

### 8. Translation Keys
Use `__()` with the format: `{domain}/{section}.{key}`:

```php
__('business/ads.form.title')
__('labels.months.01')
__('api/error.not_found')
```

### 9. Service Resolution
Use `app()` helper or dependency injection for service classes:

```php
$imageUploadService = app(ImageUploadService::class);
$worldService = app(WorldService::class);
```

### 10. Helpers - function_exists Guard
All custom helpers in `app/Support/helpers.php` MUST wrap in `function_exists`:

```php
if (!function_exists('me')) {
    function me(string $attr = '')
    {
        $user = auth()->user();
        if ($attr) {
            return $user->$attr;
        }
        return $user;
    }
}
```

### 11. Config-Driven Values
Hardcoded values should be moved to config files. Use `config()` for all configurable values:

```php
config('ads.ad.validation.title.max')
config('app.default_currency')
config('user.avatar')
```

### 12. Strict Output Encoding
Use `e()` for user-provided content in update/create operations:

```php
$updateData = [
    'title' => e($this->formData['title']),
    'content' => e($this->formData['content']),
];
```

### 13. No Comments Rule
**Do NOT add comments** to code unless the project already has comments in that specific file. The project follows a self-documenting code philosophy.

### 14. File Header
Some files include a copyright header (Enums, Controllers). Maintain existing headers. Do NOT add headers to files that don't already have them.

```php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| ...
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/
```

## Code Generation Checklist

When generating PHP code for this project, verify:

- [ ] Named arguments used for multi-parameter calls
- [ ] Enums used instead of raw strings for status/type values
- [ ] Table constants used for model `$table` property
- [ ] `casts()` method defined with enum casts for models
- [ ] Action pattern used for business operations (constructor + execute)
- [ ] Livewire components follow the standard structure
- [ ] XRule used for dynamic validation constraints
- [ ] Translation keys use `{domain}/{section}.{key}` format
- [ ] Services resolved via `app()` or DI
- [ ] Helpers wrapped in `function_exists`
- [ ] Config-driven values via `config()`
- [ ] User content escaped with `e()`
- [ ] No unnecessary comments added
- [ ] Existing copyright headers preserved
