# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-01-10

🚀 **Major rewrite with Laravel 12 compatibility and modern PHP 8.3+ features**

### 🆕 Added

#### Core Features
- **Async Operations**: Complete job system with `ExtensionEnableJob`, `ExtensionDisableJob`, `ExtensionInstallDepsJob`
- **Operation Tracking**: New `TrackerService` for monitoring background operations with status and progress
- **Event System**: Comprehensive lifecycle events (`ExtensionEnabledEvent`, `ExtensionDisabledEvent`, `ExtensionDiscoveredEvent`, etc.)
- **Enhanced Extension Builder**: Completely rewritten scaffolding system with customizable stub groups
- **Database Activator**: Optional database-backed extension state management with migrations

#### New Console Commands
- `extensions:publish` - Smart publishing with interactive tag selection
- `extensions:reload` - Reload active extensions without restart
- `extensions:install-deps` - Install extension dependencies via Composer
- Enhanced `extensions:make` with better stub management

#### Developer Experience
- **Comprehensive Documentation**: 7 detailed documentation files with examples
- **Test Suite**: 31 tests covering all major functionality with 100% pass rate
- **Code Quality**: PHPStan, PHP-CS-Fixer integration with automated CI checks
- **CI/CD Pipeline**: GitHub Actions for testing, security scanning, and automated releases
- **Type Safety**: Enhanced with readonly properties and strict typing

#### API Enhancements
- `installAndEnable()` - Composite operation for dependency install + enable
- `enableAsync()`, `disableAsync()` - Async operation methods with tracking
- `getOperationStatus()`, `getExtensionOperations()` - Operation monitoring
- `missingPackages()` - Dependency analysis and validation

### 🔄 Changed - **BREAKING CHANGES**

#### API Changes
- **Extension Entity**: Now uses readonly properties (`$extension->name` instead of `$extension->name()`)
- **Service Methods**: 
  - `active()` → `enabled()`
  - `getByName()` → `findByName()` 
  - `getByType()` → `allByType()`
- **Configuration**: Simplified structure, removed nested arrays
- **Facade Methods**: Updated signatures for better type safety

#### Requirements
- **PHP**: Upgraded from 8.1+ to 8.3+ (strict requirement)
- **Laravel**: Full Laravel 12.x compatibility
- **Dependencies**: Removed `illuminate/database` and `illuminate/filesystem` requirements

#### Architecture
- **Command Structure**: Moved from `src/Commands` to `src/Console/Commands` namespace
- **Service Organization**: Split monolithic service into specialized services
- **Error Handling**: Structured `OpResult` objects instead of boolean returns

### 🐛 Fixed
- **Extension Discovery**: Improved reliability with proper caching invalidation
- **Switch Types**: Correct mutual exclusion enforcement for theme-type extensions
- **Protected Extensions**: Enhanced protection logic with proper validation
- **Memory Leaks**: Optimized extension loading and caching mechanisms
- **Dependency Resolution**: More accurate package requirement checking

### 🗑️ Removed - **BREAKING CHANGES**

#### Legacy API Cleanup
- **Old Facades**: Removed `ExtensionBuilder` facade (use service directly)
- **Deprecated Methods**: Removed all methods marked deprecated in v1.x
- **Old Commands**: Legacy command structure and concerns
- **Configuration Keys**: Old nested configuration format

#### Files Removed
- `src/Actions/*` - Replaced with improved service methods
- `src/Services/Concerns/*` - Functionality moved to main services  
- `docs/en/`, `docs/uk/` - Replaced with unified English documentation
- `lang/*/commands.php`, `lang/*/messages.php` - Consolidated into `lang.php`

### 📊 Migration Statistics
- **136 files changed**: 5,698 insertions(+), 2,190 deletions(-)
- **Major refactor**: ~60% of codebase rewritten for better architecture
- **Documentation**: Completely rewritten with practical examples

### 🔧 Technical Details
- **PHPStan**: Level 1 with facade method ignores (higher levels planned for v2.1)
- **PHP-CS-Fixer**: Strict PSR-12 + custom rules for consistent code style
- **Testing**: PHPUnit 11 with comprehensive test coverage
- **Git**: Clean history with proper semantic commits

### ⚠️ Migration Guide

#### From v1.x to v2.0

```php
// OLD (v1.x)
$extensions = Extensions::active();
$extension = Extensions::getByName('blog');
if ($extension) {
    $extension->enable();
}

// NEW (v2.0) 
$extensions = Extensions::enabled();
$extension = Extensions::findByName('blog');
if ($extension) {
    Extensions::enable($extension->id);
    // OR async
    $operationId = Extensions::enableAsync($extension->id);
}
```

#### Configuration Migration
```php
// OLD config structure
'stubs' => [
    'enabled' => true,
    'files' => [...],
]

// NEW config structure  
'stubs' => [
    'path' => null,
    'default' => ['config', 'console', ...],
]
```

### 🚧 Technical Debt
- PHPStan level 1 (targeting level 3+ in v2.1)
- Some facade method signatures need Laravel-style magic method improvements
- Operation tracking UI components planned for v2.2

## [1.x] - Previous Versions

The 1.x series focused on basic extension management with PHP 8.1+ and Laravel 12 support. Key features included:

- Basic extension discovery and activation
- Simple command-line interface
- File-based extension state management
- Basic stub generation

For detailed v1.x changelog, see git history: `git log v1.0..HEAD --oneline`