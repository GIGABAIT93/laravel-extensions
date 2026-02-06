---
layout: default
title: Installation
nav_order: 2
---

# Installation

> 📝 **Quick Setup Guide** for Laravel Extensions package

This guide covers installing and configuring the Laravel Extensions package in a Laravel 12+ application.

## 📋 Requirements

- 🐘 **PHP**: 8.3 or later
- 🎆 **Laravel**: 12.0+
- 🎨 **Composer**: Latest version recommended

## 1️⃣ Package Installation

Install the package via Composer:

```bash
composer require gigabait93/laravel-extensions
```

### Optional: Composer Merge Plugin

For extensions with their own `composer.json`, install the merge plugin:

```bash
composer require wikimedia/composer-merge-plugin
```

## 2️⃣ Composer Merge Configuration *(Optional)*

If using extensions with dependencies, configure the merge plugin in your `composer.json`:

```json
{
  "extra": {
    "merge-plugin": {
      "include": ["extensions/*/*/composer.json"]
    }
  },
  "config": {
    "allow-plugins": {
      "wikimedia/composer-merge-plugin": true
    }
  }
}
```

Recommended include patterns should cover every configured extension type path:

```json
{
  "extra": {
    "merge-plugin": {
      "include": [
        "extensions/Modules/*/composer.json",
        "extensions/Themes/*/composer.json"
      ]
    }
  }
}
```

Or enable via CLI:

```bash
composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true
```

> 💡 **Note**: Only needed if your extensions have their own Composer dependencies.
> The package validates merge-plugin readiness (`installed`, `allow-plugins`, `include` coverage) before dependency installation.

## 3️⃣ Publish Configuration

Publish the configuration file:

```bash
php artisan extensions:publish --tag=extensions-config
```

### 📦 Available Publication Tags

| Tag | Description | Command |
|-----|-------------|----------|
| `extensions-config` | Configuration file | `--tag=extensions-config` |
| `extensions-migrations` | Database tables (`extensions`, `extension_operations`) | `--tag=extensions-migrations` |
| `extensions-lang` | Translation files | `--tag=extensions-lang` |
| `extensions-stubs` | Code templates | `--tag=extensions-stubs` |

### 🛠️ Examples

```bash
# Publish specific resources
php artisan extensions:publish --tag=extensions-migrations

# Overwrite existing files
php artisan extensions:publish --tag=extensions-stubs --force

# Interactive selection (shows menu)
php artisan extensions:publish

# Publish everything
php artisan extensions:publish --tag=extensions
```

## 4️⃣ Configure Extension Paths

Edit `config/extensions.php` to set up your extension directories:

```php
'paths' => [
    'Modules' => base_path('extensions/Modules'),
    'Themes'  => base_path('extensions/Themes'),
    'Plugins' => base_path('extensions/Plugins'),
],
```

> 🗺️ **Directory Structure**: Create these directories in your project root:
> ```
> extensions/
> ├── Modules/
> ├── Themes/
> └── Plugins/
> ```

## 5️⃣ Discover Extensions

Scan configured paths for extensions:

```bash
php artisan extensions:discover
```

## 6️⃣ Verify Installation

List all discovered extensions:

```bash
php artisan extensions:list
```

## ✅ Installation Complete!

🎉 **Congratulations!** Your Laravel Extensions package is now ready to use.

### Next Steps:
- [Learn about configuration options](configuration.md)
- [Create your first extension](scaffolding.md)
- [Explore the runtime API](usage.md)
