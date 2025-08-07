# Usage

## Installation

1. Add the package repository to your application's `composer.json`:

```json
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
| `extension:install {extension}` | Run installation routines for an extension (migrate + seed). |
| `extension:make {name}` | Interactively create a new extension. |
| `extension:stub {name}` | Generate additional stubs for an existing extension. |
| `extension:migrate {name?}` | Run migrations and seeders for extensions. |

## Command Usage Examples

- `php artisan extension:list` – show all extensions.
- `php artisan extension:enable Blog` – enable the "Blog" extension.
- `php artisan extension:disable Blog` – disable the "Blog" extension.
- `php artisan extension:delete Blog` – remove the "Blog" extension.
- `php artisan extension:discover` – discover new extensions.
- `php artisan extension:install Blog` – run migrations and seeds for "Blog".
- `php artisan extension:make Blog modules` – scaffold a "Blog" extension in `modules`.
- `php artisan extension:stub Blog modules` – generate stubs for the "Blog" extension.
- `php artisan extension:migrate --force` – migrate all extensions without confirmation.

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

## Programmatic Builder

Extensions can also be scaffolded programmatically for use in a web interface.
Use the `ExtensionBuilder` facade:

```php
use Gigabait93\Extensions\Facades\ExtensionBuilder;

// Available paths and stub groups
$paths = ExtensionBuilder::paths();
$groups = ExtensionBuilder::stubGroups();

ExtensionBuilder::name('Blog')
    ->in($paths[0])
    ->stubs($groups)
    ->build();
```

By default the extension is created in the first configured path with the
default stub groups. You may override any of these values, including the stub
root, for full control.
