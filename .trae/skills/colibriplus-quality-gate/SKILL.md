---
name: "colibriplus-quality-gate"
description: "Code quality gate that verifies generated code adheres to all ColibriPlus project standards. Invoke before committing code, after completing a feature implementation, or when user asks for code quality check."
---

# ColibriPlus Code Quality Gate

This skill acts as a comprehensive quality gate for any code generated or modified in the ColibriPlus project. It validates code against all project conventions across both backend (Laravel/PHP) and frontend (Vue/TailwindCSS/Livewire) layers.

## When to Activate

Activate this skill:
- After completing a feature implementation
- Before committing code changes
- When user explicitly requests a quality check
- After the `code-reviewer` or `frontend-code-review` skills have been applied

## Quality Gate Process

### Step 1: Identify Changed Files

```bash
git diff --name-only          # Working tree changes
git diff --staged --name-only # Staged changes
```

### Step 2: Classify Files

Categorize each changed file to apply the correct rule set:

| File Path Pattern | Category | Apply Rule Set |
|-------------------|----------|---------------|
| `app/Models/*.php` | PHP Model | Model Rules |
| `app/Enums/**/*.php` | PHP Enum | Enum Rules |
| `app/Actions/**/*.php` | PHP Action | Action Rules |
| `app/Livewire/**/*.php` | PHP Livewire | Livewire Rules |
| `app/Http/Controllers/**/*.php` | PHP Controller | Controller Rules |
| `app/Services/**/*.php` | PHP Service | Service Rules |
| `app/Rules/**/*.php` | PHP Rule | Validation Rules |
| `app/Support/helpers.php` | PHP Helpers | Helper Rules |
| `app/Policies/*.php` | PHP Policy | Policy Rules |
| `config/*.php` | PHP Config | Config Rules |
| `routes/**/*.php` | PHP Routes | Route Rules |
| `resources/views/**/*.blade.php` | Blade View | Blade Rules |
| `resources/js/**/*.vue` | Vue SFC | Vue Rules |
| `resources/js/**/*.js` | JS Module | JS Rules |
| `resources/css/**/*.css` | CSS | CSS Rules |

### Step 3: Apply Rule Checks

#### PHP Model Rules
- [ ] Uses `Table::` constant for `$table` property
- [ ] Has `public static $snakeAttributes = true;`
- [ ] Uses `protected $guarded = [];` (not `$fillable`)
- [ ] `casts()` method returns enum-backed casts
- [ ] Boolean fields cast with `'boolean'`
- [ ] Password field uses `'hashed'` cast
- [ ] Timestamps use `ModelTimestampCast::class` where appropriate
- [ ] Relationships use explicit foreign/local key parameters
- [ ] Scope methods follow `scopeXxx` naming
- [ ] Accessor methods follow `getXxxAttribute` naming
- [ ] No raw strings for status/type comparisons (use enum methods)

#### PHP Enum Rules
- [ ] Uses `enum Xxx: string` (or `int`) backed enum
- [ ] Case names are UPPER_CASE
- [ ] Located in correct `app/Enums/{Domain}/` subdirectory
- [ ] May include Trait `TriesFromArray` for `tryFrom` array support

#### PHP Action Rules
- [ ] Single class per file
- [ ] Receives dependencies via `__construct()`
- [ ] Has `execute()` method as entry point
- [ ] Private helper methods for internal logic
- [ ] No static methods (instance-based usage)

#### PHP Livewire Rules
- [ ] Extends `Livewire\Component`
- [ ] Has `mount()` method for initialization
- [ ] Has `render()` method returning view path
- [ ] Validation rules in `getRules()` method
- [ ] Uses `XRule::join()` for config-driven validation
- [ ] Form submission in `submitForm()` or descriptive method name
- [ ] Uses `e()` for user input sanitization
- [ ] Uses named arguments for `validate()` call
- [ ] Translation attributes in `attributes:` parameter

#### PHP Controller Rules
- [ ] Extends `App\Http\Controllers\Controller` (abstract base)
- [ ] May include copyright header (maintain existing)
- [ ] Uses proper HTTP response methods

#### PHP Service Rules
- [ ] Single responsibility per class
- [ ] Might use fluent interface (method chaining)
- [ ] Resolved via `app()` or dependency injection

#### PHP Helpers Rules
- [ ] Each function wrapped in `if (!function_exists('...'))`
- [ ] Functions are globally accessible
- [ ] Located in `app/Support/helpers.php`

#### PHP Config Rules
- [ ] Nested dot notation keys
- [ ] Values appropriate for environment-specific overrides
- [ ] No sensitive credentials (use `.env`)

#### Blade View Rules
- [ ] Uses `__('domain/section.key')` for translations
- [ ] `@csrf` included in forms
- [ ] `@method('PUT')` or `@method('DELETE')` as needed
- [ ] Proper Blade directives (`@foreach`, `@if`, etc.)

#### Vue SFC Rules
- [ ] Uses `<script setup>` syntax
- [ ] Composition API patterns (`ref`, `computed`, `onMounted`)
- [ ] Props declared with `defineProps()`
- [ ] Emits declared with `defineEmits()`
- [ ] TailwindCSS 4 classes with `dark:` variants
- [ ] Uses `t()` from `vue-i18n` for translations
- [ ] Async data fetching in `onMounted` or equivalent

#### JS Module Rules
- [ ] ES module syntax (`import`/`export`)
- [ ] Uses Vite aliases (`@`, `@D`, `@M`)
- [ ] Proper error handling for async operations

#### CSS Rules
- [ ] TailwindCSS 4 `@import "tailwindcss"` or `@tailwind` directives
- [ ] Dark mode support with `@media (prefers-color-scheme: dark)` or `dark:` classes
- [ ] Uses CSS custom properties for theming

### Step 4: Report

Generate a structured quality gate report:

```
# ColibriPlus Quality Gate Report

## Summary
- Files checked: N
- Passed: X
- Issues found: Y
- Critical: Z

## Issues

### Critical
[Issues that would cause runtime errors or violate security]

### Warnings
[Issues that deviate from conventions but won't cause errors]

### Suggestions
[Optional improvements for better code quality]

## Gate Status: [PASSED / FAILED]
```

### Step 5: Auto-Fix (Optional)

For issues that can be automatically fixed:
- Enum case conversions
- Import corrections
- Translation key format fixes
- Cast method additions

Ask user before applying auto-fixes.

## Error Handling Standards

The project uses custom error handling patterns:

### Backend Error Handling
```php
try {
    // operation
} catch (ValidationException $e) {
    $this->addError('field', $e->getMessage());
} catch (Throwable $th) {
    $this->addError('field', $th->getMessage());
}
```

### Frontend Error Handling
```javascript
try {
    const response = await axios.get('/api/endpoint')
} catch (error) {
    if (error.response) {
        // Server responded with error status
        console.error('Server error:', error.response.data)
    } else if (error.request) {
        // Request made but no response
        console.error('Network error')
    }
}
```

## Module/Component Design Constraints

### Single Responsibility
Each class/component should have one clear purpose:
- **Actions**: One business operation per class
- **Services**: One domain capability per class
- **Vue Components**: One UI concern per component
- **Enums**: One value domain per enum

### Dependency Direction
```
Livewire/Controllers → Actions → Services → Models/Enums
                      Policies → Models
                      Rules    → Config
```

High-level modules depend on lower-level modules through well-defined interfaces. Avoid circular dependencies.

### Strict Extraction Rule
Before adding code to an existing class, verify:
1. Does this belong in this class's responsibility scope?
2. Could this be extracted into a new Action class?
3. Could this be extracted into a Service?
4. Could this value be moved to config?

## Testing Standards

The project uses PHPUnit for backend testing. Test files follow:
- `tests/Unit/` - Unit tests
- `tests/Feature/` - Feature/integration tests
- Test classes extend `Tests\TestCase`
- Test methods use `test_xxx` or `/** @test */` annotation
