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

## Composer Integration (UI-friendly dependency install)
The `composer` block controls how extension dependencies are installed when you call
`installDependencies`, `install`, or `installAndEnable` from service/API/queue jobs.

```php
'composer' => [
    'command' => 'composer',
    'timeout' => 300,
    'lock_wait_seconds' => 15,
    'lock_seconds' => 330,
    'prefer_dist' => true,
    'no_dev' => false,
    'root_json' => base_path('composer.json'),
],
```

Notes:
- Dependency installation now runs targeted updates for missing packages only.
- A cache lock prevents concurrent composer runs from overlapping queue jobs.
- The package validates merge-plugin readiness before attempting installation.

## Queue Overlap Protection
The `queue` block prevents concurrent operations on the same extension id:

```php
'queue' => [
    'overlap_lock_seconds' => 300,
    'overlap_release_seconds' => 5,
],
```

## Persistent Operations Store
Async operation tracking is persisted in the database by default:

```php
'operations' => [
    'store' => 'database',
    'retention_hours' => 168,
    'prune_interval_seconds' => 300,
    'cache_ttl_hours' => 2,
],
```

Notes:
- `store=database` keeps operation history across restarts and deployments.
- old operation records are automatically pruned by retention settings.
- if migrations are not yet applied, tracker safely falls back to cache.

Review the source config for inline comments describing each option in
detail.
