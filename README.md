# Laravel Extensions

[![CI](https://github.com/gigabait93/laravel-extensions/actions/workflows/ci.yml/badge.svg)](https://github.com/gigabait93/laravel-extensions/actions/workflows/ci.yml)
[![Security](https://github.com/gigabait93/laravel-extensions/actions/workflows/security.yml/badge.svg)](https://github.com/gigabait93/laravel-extensions/actions/workflows/security.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![Total Downloads](https://img.shields.io/packagist/dt/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![License](https://img.shields.io/packagist/l/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![PHP Version](https://img.shields.io/packagist/php-v/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)

A powerful modular extension framework for Laravel 12+ that enables you to build scalable, maintainable applications with runtime discovery, activation control, and scaffolding utilities.

## 🚀 Key Features

- **Runtime Discovery**: Automatically discover and load extensions from configured directories
- **Activation Management**: Enable/disable extensions with dependency checks and protection mechanisms
- **Flexible Storage**: Choose between file-based or database activators for persistence
- **Rich API**: Manage extensions through facade, HTTP API, and Artisan commands
- **Async Operations**: Queue enable/disable/install operations with status monitoring
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
php artisan vendor:publish --tag=extensions-config
```

If using database activator, publish and run migrations:

```bash
php artisan vendor:publish --tag=extensions-migrations
php artisan migrate
```

Discover existing extensions:

```bash
php artisan extensions:discover
```

## 🎯 Quick Start

```php
use Gigabait93\Extensions\Facades\Extensions;

// Get all extensions
$extensions = Extensions::all();

// Enable extension
Extensions::enable('blog');

// Disable extension  
Extensions::disable('blog');

// Install dependencies and enable
Extensions::installAndEnable('blog');
```

### Basic Commands

```bash
# List all extensions
php artisan extensions:list

# Enable extension
php artisan extensions:enable blog

# Create new extension
php artisan extensions:make Blog --type=module
```

## 📚 Documentation

For detailed documentation, visit [https://gigabait93.github.io/laravel-extensions/](https://gigabait93.github.io/laravel-extensions/).

## 🧪 Testing

```bash
composer test
composer cs-fix
composer phpstan
```

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
