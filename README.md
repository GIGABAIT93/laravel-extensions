### Laravel Extensions

This package provides a framework for managing extensions in a Laravel application. It includes services, commands, and
activators to handle extensions effectively.
Full documentation: [English](docs/en/introduction.md) | [Українська](docs/uk/introduction.md)


---

### Installation

1. Add to your file `composer.json`:
   ```json
   "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/GIGABAIT93/laravel-extensions"
        }
    ],
    "autoload": {
        "psr-4": {
            "Modules\\": "modules/",
        }
    },
   ```

2. Install the package via Composer:
   ```bash
   composer require gigabait93/extensions
   ```

3. Publish the configuration file and migrations:
   ```bash
   php artisan vendor:publish --tag=extensions
   ```

4. Configure the `extensions` settings in the published configuration file.

5. Run migrations to create the necessary database tables:
   ```bash
   php artisan migrate
   ```

---

### Commands

| Command                         | Description                                                               |
|---------------------------------|---------------------------------------------------------------------------|
| `extension:list`                | Outputs a list of all installed extensions with their statuses and types. |
| `extension:enable {extension}`  | Enables a specific extension.                                             |
| `extension:disable {extension}` | Disables a specific extension.                                            |
| `extension:delete {extension}`  | Deletes a specific extension.                                             |
| `extension:discover`            | Scans the extensions directory and synchronizes new extensions.           |
| `extension:install {extension}` | Installs a specific extension (migrate + seed).                           |
| `extension:make {name}`         | Interactive creation of a new extension (type based on path).             |
| `extension:stub {name}`         | Generates additional stubs for an existing extension.                     |
| `extension:migrate {name?}`     | Runs migrations and seeders for extensions.                               |

#### Command Usage Examples

- `php artisan extension:list` – show all extensions.
- `php artisan extension:enable Blog` – enable the "Blog" extension.
- `php artisan extension:disable Blog` – disable the "Blog" extension.
- `php artisan extension:delete Blog` – remove the "Blog" extension.
- `php artisan extension:discover` – discover new extensions.
- `php artisan extension:install Blog` – run migrations and seeds for "Blog".
- `php artisan extension:make Blog modules` – scaffold a "Blog" extension in `modules`.
- `php artisan extension:stub Blog modules` – generate stubs for the "Blog" extension.
- `php artisan extension:migrate --force` – migrate all extensions without confirmation.

---

### Configuration

The package uses a configuration file (`config/extensions.php`) to define settings such as:

- **Activator Type**: Choose between `DbActivator` or `FileActivator`.
- **Extensions Paths**: Directories where extensions are stored.
- **Protected Extensions**: Extensions that cannot be disabled or deleted.
- **Load Order**: Specify the order in which extensions are loaded.
- **Switchable Types**: Types where only one extension of the type can be active;
  enabling one will automatically disable the others.

---

### How to Create an Extension

1. Create a new directory for your extension in the `modules` folder or any other specified directory.
    - Example: `modules/ExamplePlugin`
2. Create a service provider for your extension.
3. Create extension.json file in the root of your extension directory.
    - Example: `modules/ExamplePlugin/extension.json`
    ```json
   {
      "name": "Themer",
      "provider": "Modules\\Themer\\Providers\\ThemeServiceProvider",
      "type": "module"
   }
    ```
4. Create a service provider class for your extension.
    - Example: `modules/ExamplePlugin/Providers/ThemeServiceProvider.php`
    ```php
   namespace Modules\Themer\Providers;

   use Illuminate\Support\ServiceProvider;

   class ThemeServiceProvider extends ServiceProvider
   {
       public function register()
       {
           // Register your extension's services here
       }

       public function boot()
       {
           // Boot your extension's services here
       }
   }
    ```

---

### Extension Builder

Extensions can also be scaffolded programmatically, which is useful for
integration into a web interface. Use the `ExtensionBuilder` facade:

```php
use Gigabait93\Extensions\Facades\ExtensionBuilder;

// Inspect available paths and stub groups
$paths = ExtensionBuilder::paths();
$groups = ExtensionBuilder::stubGroups();

// Fluent configuration
ExtensionBuilder::name('Blog')
    ->in($paths[0])
    ->addStub('migrations')
    ->build();
```

By default, the extension is created in the first path defined in
`extensions.paths`, using the configured stub groups. You may override the base
path, stub groups or stub root as needed for full control.

---

### Requirements

- PHP 8.3+
- Laravel 12+

---

### License

This package is open-source and available under the MIT license.