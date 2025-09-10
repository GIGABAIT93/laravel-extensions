# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-01-10

### Added
- New async operation tracking system with TrackerService
- Extension lifecycle events (ExtensionEnabledEvent, ExtensionDisabledEvent, etc.)
- Comprehensive job system for background operations (ExtensionEnableJob, ExtensionDisableJob, ExtensionInstallDepsJob)
- Enhanced extension builder with customizable stubs
- New console commands: extensions:publish, extensions:reload
- Database activator support with migrations
- Composer merge plugin integration for extension dependencies
- Enhanced manifest format with rich metadata support
- PHPStan and PHP-CS-Fixer integration for code quality
- Comprehensive test suite with 31 tests

### Changed
- **BREAKING**: Refactored ExtensionService API for better consistency
- **BREAKING**: Extension entity now uses readonly properties
- **BREAKING**: Simplified configuration structure
- **BREAKING**: Renamed several methods for clarity (e.g., `installDeps` → `installDependencies`)
- Updated to require PHP 8.3+ and Laravel 12+
- Improved error handling with structured OpResult objects
- Enhanced documentation with examples and API reference

### Fixed
- Extension discovery and caching reliability
- Switch-type extension handling
- Protected extension enforcement
- Dependency resolution accuracy
- Memory usage optimizations

### Removed
- **BREAKING**: Removed deprecated legacy APIs from v1.x
- Old configuration format compatibility
- Unused service classes

## [1.x] - Previous Versions

Please see the git history for changes in 1.x versions.