---
layout: default
title: Manifest Format
nav_order: 4
---

# Extension Manifest

Each extension contains an `extension.json` file describing its metadata.
The following fields are supported:

| Field | Required | Description |
|-------|----------|-------------|
| `id` | yes | Unique identifier used internally. |
| `name` | yes | Human‑readable name. |
| `provider` | yes | Service provider class to register. |
| `type` | yes | Extension type (Modules, Themes, etc.). |
| `description` | no | Short description. |
| `author` | no | Extension author. |
| `version` | no | Semantic version string. |
| `compatible_with` | no | Target application version. |
| `requires_extensions` | no | Array of extension IDs this one depends on. |
| `requires_packages` | no | Map of Composer packages to version constraints. |
| `meta` | no | Arbitrary JSON object for custom metadata. |

Example `extension.json`:

```json
{
  "id": "blog",
  "name": "Blog",
  "provider": "Modules\\Blog\\Providers\\BlogServiceProvider",
  "type": "Modules",
  "description": "Simple blog module",
  "author": "John Doe",
  "version": "1.0.0",
  "compatible_with": "^12.0",
  "requires_extensions": ["base"],
  "requires_packages": {
    "illuminate/support": "^12.0"
  },
  "meta": {
    "category": "content"
  }
}
```

At runtime the manifest is exposed through the `Extension` entity with
typed, read‑only properties for each field.

## Directory Structure

Extensions live inside type-specific directories. A typical layout looks like:

```text
extensions/Modules/Blog/
├── extension.json
├── composer.json
├── Providers/
│   └── BlogServiceProvider.php
├── Routes/
│   └── web.php
└── Resources/
    └── views/
```

The service provider can register routes, views, migrations and other
resources just like any regular Laravel package.

## Example Service Provider

```php
namespace Vendor\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'blog');
    }
}
```


