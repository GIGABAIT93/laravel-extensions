---
layout: default
title: Runtime Usage
nav_order: 5
---

# Runtime Usage

Once extensions are discovered, use the provided services, HTTP API and
Artisan commands to control them.

## Extension Service
The `Extensions` facade resolves the `ExtensionService`. It exposes methods
for querying and controlling extensions.

### Querying
```php
Extensions::discover();              // Rescan extension directories
Extensions::all();                   // All discovered extensions
Extensions::enabled();               // Only enabled extensions
Extensions::disabled();              // Only disabled extensions
Extensions::get('blog');             // Extension by id
Extensions::find('blog');            // Extension by id or name
Extensions::findByName('Blog');      // Extension by name
Extensions::findByNameAndType('Blog', 'Modules'); // Name and type
Extensions::one('blog', 'Modules');  // Id or name with optional type
Extensions::allByType('Modules');    // All extensions for a type
Extensions::enabledByType('Themes'); // Enabled extensions for a type
Extensions::disabledByType('Themes');// Disabled extensions for a type
Extensions::types();                 // Registered extension types
Extensions::typedPaths();            // Map of type => path
Extensions::pathForType('Themes');   // Path for a given type
```

### Lifecycle
```php
Extensions::enable('blog');          // Enable extension by id
Extensions::disable('shop');         // Disable extension by id
Extensions::installDependencies('shop'); // Install missing packages
Extensions::installAndEnable('blog');    // Install deps then enable
Extensions::migrate('blog');         // Run migrations
Extensions::delete('old');           // Remove extension from disk
Extensions::reloadActive();          // Re-bootstrap enabled extensions
Extensions::missingPackages('blog'); // List missing composer packages
```

### Asynchronous Operations
```php
Extensions::enableAsync('blog');             // Queue enable
Extensions::disableAsync('shop');            // Queue disable
Extensions::installDepsAsync('shop');        // Queue dependency install
Extensions::installAndEnableAsync('blog');   // Queue install + enable
Extensions::getOperationStatus($id);         // Check queued operation status
Extensions::getExtensionOperations('blog');  // List operations for extension
Extensions::isOperationPending('blog', 'enable'); // Check if operation pending
Extensions::enableQueued('blog');            // Legacy alias for enableAsync
Extensions::disableQueued('shop');           // Legacy alias for disableAsync
Extensions::installDepsQueued('shop');       // Legacy alias for installDepsAsync
```

> All enable/disable logic flows through the service to enforce
> dependencies, switch types and protection rules.

All routes return JSON responses describing the action result.

### Operation Insights
```php
Extensions::getExtensionWithOperations('blog'); // Extension details + operations
Extensions::getAllWithOperations();             // All extensions with operations
Extensions::getOperationsSummary();             // Aggregated operation stats
```

## Events

The package emits events for common lifecycle actions:

| Event | Fired when |
|-------|------------|
| `ExtensionDiscoveredEvent` | Extension manifests are scanned |
| `ExtensionEnabledEvent` | An extension is enabled |
| `ExtensionDisabledEvent` | An extension is disabled |
| `ExtensionDeletedEvent` | An extension is removed from disk |
| `ExtensionDepsInstalledEvent` | Dependency installation completes |

Listen for events using the standard Laravel event system:

```php
use Gigabait93\Extensions\Events\ExtensionEnabledEvent;
use Illuminate\Support\Facades\Event;

Event::listen(ExtensionEnabledEvent::class, function (ExtensionEnabledEvent $event) {
    logger()->info('Enabled ' . $event->extension->name);
});
```

## Example Workflow

```php
Extensions::discover();        // Scan for manifests
Extensions::enable('blog');    // Turn on the blog module
Extensions::migrate('blog');   // Run its migrations
```

## Artisan Commands
Interactive commands provide the same functionality in the console and return
structured `OpResult` objects describing success or failure:

```bash
php artisan extensions:list {--type=} {--enabled} {--disabled}
php artisan extensions:discover
php artisan extensions:enable {id?} {--type=} {--all}
php artisan extensions:disable {id?} {--type=} {--all}
php artisan extensions:install-deps {id?} {--type=} {--all}
php artisan extensions:reload-active
php artisan extensions:migrate {id?} {--type=} {--all}
php artisan extensions:delete {id?} {--type=} {--all}
php artisan extensions:publish {--tag=} {--force}
```

Use `php artisan help extensions:enable` to read command-specific help.

### Examples

Publish only translations:

```bash
php artisan extensions:publish --tag=extensions-lang
```

Select resources interactively:

```bash
php artisan extensions:publish
```

Publish everything with force:

```bash
php artisan extensions:publish --tag=extensions --force
```
