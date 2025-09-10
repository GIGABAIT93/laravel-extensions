---
layout: default
title: Events
nav_order: 7
---

# Events

Extensions emit events during their lifecycle. Subscribe to them to react to
changes in your application.

| Event class | Description |
|-------------|-------------|
| `ExtensionDiscoveredEvent` | Fired when manifests are scanned |
| `ExtensionEnabledEvent` | Fired after an extension is enabled |
| `ExtensionDisabledEvent` | Fired after an extension is disabled |
| `ExtensionDeletedEvent` | Fired after an extension is removed from disk |
| `ExtensionDepsInstalledEvent` | Fired after dependency installation |

## Listening

```php
use Gigabait93\Extensions\Events\ExtensionDeletedEvent;
use Illuminate\Support\Facades\Event;

Event::listen(ExtensionDeletedEvent::class, function (ExtensionDeletedEvent $event) {
    // react to the removal
});
```

