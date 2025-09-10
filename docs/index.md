---
layout: default
title: Introduction
nav_order: 1
---

# Laravel Extensions

[![Latest Stable Version](https://img.shields.io/packagist/v/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)
[![License](https://img.shields.io/packagist/l/gigabait93/laravel-extensions.svg)](https://packagist.org/packages/gigabait93/laravel-extensions)

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

## 📚 Documentation

| Guide | Description |
|-------|-------------|
| [Installation](installation.md) | Step-by-step setup instructions |
| [Configuration](configuration.md) | Configuration options and settings |
| [Manifest Format](manifest.md) | Extension manifest specification |
| [Runtime Usage](usage.md) | API usage and examples |
| [Scaffolding](scaffolding.md) | Creating new extensions |
| [Events](events.md) | Extension lifecycle events |

## 🎯 Quick Start

```bash
# Install the package
composer require gigabait93/laravel-extensions

# Publish configuration
php artisan vendor:publish --tag=extensions-config

# Discover extensions
php artisan extensions:discover

# List all extensions
php artisan extensions:list
```

---

*This documentation is built with [Just the Docs](https://just-the-docs.github.io/just-the-docs/) and features a dark theme by default.*
