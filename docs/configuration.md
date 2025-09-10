---
layout: default
title: Configuration
nav_order: 3
---

# Configuration

The package publishes a `config/extensions.php` file. Key options include:

## Protected Extensions
The `protected` section lists extensions that cannot be disabled or deleted.

## Load Order
`load_order` forces specific extensions to bootstrap before others.
Any extension not listed loads afterward in arbitrary order.

## Switchable Types
The `switch_types` array declares mutually exclusive extension types.
Enabling one automatically disables other extensions of the same type.

## Paths
`paths` maps canonical extension types to directories where they reside.
Discovered manifests use the key as the extension's type.

## Stubs
The `stubs` block configures the scaffold generator. By default the package
uses its own templates located in `vendor/gigabait93/laravel-extensions/stubs/Extension`
and enables all stub groups. Override the path or the default groups as
needed when running `extensions:make`.

## Activator
`activator` selects the class responsible for managing extension activation states.

Available activator classes:
- `\Gigabait93\Extensions\Activators\FileActivator::class` - Stores states in JSON file (default)
- `\Gigabait93\Extensions\Activators\DbActivator::class` - Stores states in database

To use the database activator:
1. Set the activator class in `config/extensions.php`:
```php
'activator' => \Gigabait93\Extensions\Activators\DbActivator::class,
```

2. Publish and run migrations:
```bash
php artisan extensions:publish --tag=extensions-migrations
php artisan migrate
```

## JSON File Storage
When using `FileActivator`, the `json_file` option sets the path where activation states are stored.

Review the source config for inline comments describing each option in
detail.

