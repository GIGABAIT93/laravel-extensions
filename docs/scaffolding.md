---
layout: default
title: Scaffolding
nav_order: 6
---

# Scaffolding New Extensions

> 🏠 **Extension Generator** - Quickly create new extensions with boilerplate code

The package provides powerful scaffolding tools to generate extension boilerplate with customizable templates.

## 🛠️ Programmatic API

Use the `ExtensionBuilder` to generate extensions programmatically:

```php
use Gigabait93\Extensions\Scaffolding\ExtensionBuilder;

$builder = ExtensionBuilder::make()
    ->withType('Modules')
    ->withName('Blog')
    ->withBasePath(base_path('extensions'))
    ->build();

// Check result
if ($builder->isSuccess()) {
    echo "Extension created successfully!";
}
```

### 🎨 Template Variables

The following placeholders are automatically replaced in stub files:

| Placeholder | Example | Description |
|-------------|---------|-------------|
| `{{name}}` | `Blog` | Extension name |
| `{{namespace}}` | `Modules\Blog` | PHP namespace |
| `{{snakePlural}}` | `blog_posts` | Snake case plural |
| `{{kebab}}` | `blog-system` | Kebab case |
| `{{camel}}` | `blogSystem` | Camel case |

## ⚙️ Artisan Command

The `extensions:make` command provides an interactive way to create extensions:

```bash
php artisan extensions:make Blog --type=Modules
```

### 🛠️ Command Options

| Option | Description | Example |
|--------|-------------|----------|
| `--type` | Extension type | `--type=Modules` |
| `--base` | Output base path | `--base=/custom/path` |
| `--stubs-path` | Custom stubs directory | `--stubs-path=/my/stubs` |
| `--groups` | Stub groups to include | `--groups=basic,api` |
| `--force` | Overwrite existing files | `--force` |

### 📝 Examples

```bash
# Basic module
php artisan extensions:make Blog --type=Modules

# Theme with custom stubs
php artisan extensions:make DarkTheme --type=Themes --stubs-path=/my/theme/stubs

# Plugin with specific components
php artisan extensions:make Analytics --type=Plugins --groups=basic,api,jobs

# Force overwrite existing
php artisan extensions:make Blog --force
```

### 📺 Interactive Mode

```bash
# Run without arguments for interactive prompts
php artisan extensions:make
```

The command will prompt you for:
- Extension name
- Extension type
- Components to include
- Custom options
