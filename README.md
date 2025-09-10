# Laravel Extensions

[![Latest Stable Version](https://img.shields.io/packagist/v/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![Total Downloads](https://img.shields.io/packagist/dt/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![License](https://img.shields.io/packagist/l/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![PHP Version](https://img.shields.io/packagist/php-v/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)

A powerful modular extension framework for Laravel 12+ that enables you to build scalable, maintainable applications with runtime discovery, activation control, and scaffolding utilities.

## 🚀 Features

- **Runtime Discovery**: Automatically discover and load extensions from configured directories
- **Activation Management**: Enable/disable extensions with dependency checks and protection mechanisms
- **Flexible Storage**: Choose between file-based or database activators for persistence
- **Rich API**: Manage extensions through facade, HTTP API, and Artisan commands
- **Async Operations**: Queue enable/disable/install operations with status monitoring
- **Dependency Resolution**: Smart dependency management with conflict detection
- **Code Generation**: Scaffold new extensions with customizable stubs
- **Event System**: Comprehensive event dispatching for extension lifecycle
- **Multi-type Support**: Support for different extension types (Modules, Themes, etc.)

## 📋 Requirements

- PHP 8.3+
- Laravel 12.0+

## 🔧 Installation

Install the package via Composer:

```bash
composer require gigabait93/laravel-extensions
```

Publish the configuration file:

```bash
php artisan extensions:publish --tag=extensions-config
```

If using database activator, publish and run migrations:

```bash
php artisan extensions:publish --tag=extensions-migrations
php artisan migrate
```

Discover existing extensions:

```bash
php artisan extensions:discover
```

## 🏗️ Extension Structure

Extensions are organized in type-based directories:

```
extensions/
├── Modules/
│   ├── Blog/
│   │   ├── extension.json
│   │   ├── composer.json
│   │   ├── helpers.php
│   │   ├── Providers/
│   │   │   └── BlogServiceProvider.php
│   │   ├── Routes/
│   │   │   ├── web.php
│   │   │   └── api.php
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   ├── Models/
│   │   ├── Database/
│   │   │   ├── Migrations/
│   │   │   └── Seeders/
│   │   └── Resources/
│   │       └── views/
│   └── Shop/
├── Themes/
│   └── Admin/
└── Plugins/
    └── Analytics/
```

### Extension Manifest (extension.json)

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

## 🎨 Usage

### Basic Operations

```php
use Gigabait93\Extensions\Facades\Extensions;

// Get all extensions
$extensions = Extensions::all();

// Get active extensions
$active = Extensions::enabled();

// Find specific extension
$blog = Extensions::find('blog');

// Get extension by ID
$extension = Extensions::get('blog');

// Enable extension
Extensions::enable('blog');

// Disable extension
Extensions::disable('blog');

// Install extension dependencies
Extensions::installDependencies('blog');

// Install dependencies and enable
Extensions::installAndEnable('blog');
```

### Async Operations

```php
use Gigabait93\Extensions\Jobs\ExtensionEnableJob;
use Gigabait93\Extensions\Jobs\ExtensionDisableJob;
use Gigabait93\Extensions\Jobs\ExtensionInstallDepsJob;

// Queue extension operations
Extensions::enableAsync('blog');
Extensions::disableAsync('shop');
Extensions::installDepsAsync('analytics');
Extensions::installAndEnableAsync('blog');
```

### Artisan Commands

```bash
# List all extensions
php artisan extensions:list

# Enable extension
php artisan extensions:enable blog

# Disable extension
php artisan extensions:disable blog

# Install dependencies
php artisan extensions:install-deps blog

# Create new extension
php artisan extensions:make Blog --type=module

# Migrate extensions
php artisan extensions:migrate

# Publish extension assets
php artisan extensions:publish blog

# Reload extensions cache
php artisan extensions:reload
```

## 🎭 Events

The package dispatches various events during extension lifecycle:

```php
use Gigabait93\Extensions\Events\{
    ExtensionEnabledEvent,
    ExtensionDisabledEvent,
    ExtensionDiscoveredEvent,
    ExtensionDeletedEvent,
    ExtensionDepsInstalledEvent
};

// Listen to extension events
Event::listen(ExtensionEnabledEvent::class, function ($event) {
    logger()->info("Extension {$event->extension->name} was enabled");
});

Event::listen(ExtensionDisabledEvent::class, function ($event) {
    logger()->info("Extension {$event->extension->name} was disabled");
});
```

## ⚙️ Configuration

Configure the package in `config/extensions.php`:

```php
return [
    // Extensions that cannot be disabled
    'protected' => [
        'Modules' => 'ExtensionsDebugger',
    ],

    // Loading order for active extensions
    'load_order' => [
        'Modules' => 'ExtensionsDebugger',
    ],

    // Mutually exclusive extension types
    'switch_types' => [
        'Themes',
    ],

    // Extension directories by type
    'paths' => [
        'Modules' => base_path('extensions/Modules'),
        'Themes' => base_path('extensions/Themes'),
    ],

    // Stub configuration for scaffolding
    'stubs' => [
        'path' => null, // Use package stubs
        'default' => [
            'config', 'console', 'database', 'events',
            'exceptions', 'facades', 'helpers', 'http',
            'jobs', 'lang', 'listeners', 'models',
            'notifications', 'policies', 'resources',
            'routes', 'rules', 'services',
        ],
    ],

    // Activator class for managing states
    'activator' => \Gigabait93\Extensions\Activators\FileActivator::class,

    // JSON file path for FileActivator
    'json_file' => base_path('storage/extensions.json'),
];
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Run code style checks:

```bash
composer cs-check
```

Fix code style issues:

```bash
composer cs-fix
```

Run static analysis:

```bash
composer phpstan
```

## 📚 Documentation

For detailed documentation, visit [https://gigabait93.github.io/laravel-extensions/](https://gigabait93.github.io/laravel-extensions/).

The documentation covers:
- Installation and configuration
- Extension development
- Manifest format reference
- Event system
- API reference
- Best practices

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Credits

- [GIGABAIT93](https://github.com/GIGABAIT93)
- [All Contributors](../../contributors)

## 🔧 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🛡️ Security

If you discover any security related issues, please email xgigabaitx@gmail.com instead of using the issue tracker.
