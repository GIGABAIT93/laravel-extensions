# Usage

## Installation

1. Add the package repository to your application's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/GIGABAIT93/laravel-extensions"
    }
],
"autoload": {
    "psr-4": {
        "Modules\\\\": "modules/"
    }
}
```

2. Require the package via Composer:

```bash
composer require gigabait93/extensions
```

3. Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=extensions
php artisan migrate
```

4. Configure the `extensions` section in `config/extensions.php` to match your project.

## Commands

| Command | Description |
|---------|-------------|
| `extension:list` | List all discovered extensions and their statuses. |
| `extension:enable {extension}` | Enable a specific extension. |
| `extension:disable {extension}` | Disable a specific extension. |
| `extension:delete {extension}` | Remove an extension from the system. |
| `extension:discover` | Scan configured paths and synchronize new extensions. |
| `extension:install {extension}` | Run installation routines for an extension. |
| `extension:meke {extension}` | Interactively create a new extension. |

## Creating an Extension

1. Create a directory inside your modules folder, e.g. `modules/ExamplePlugin`.
2. Add an `extension.json` file describing the extension:

```json
{
    "name": "Themer",
    "provider": "Modules\\\\Themer\\\\Providers\\\\ThemeServiceProvider",
    "type": "module"
}
```

3. Implement the service provider:

```php
namespace Modules\\Themer\\Providers;

use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register your extension services
    }

    public function boot()
    {
        // Bootstrap your extension services
    }
}
```

After creating the files, run `php artisan extension:discover` to register the new extension.
