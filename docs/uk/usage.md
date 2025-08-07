# Використання

## Встановлення

1. Додайте репозиторій пакета у `composer.json` вашого застосунку:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/GIGABAIT93/laravel-extensions"
    }
],
"autoload": {
    "psr-4": {
        "Modules\\\\": "modules/"
    }
}
```

2. Встановіть пакет через Composer:

```bash
composer require gigabait93/extensions
```

3. Опублікуйте конфігурацію та міграції:

```bash
php artisan vendor:publish --tag=extensions
php artisan migrate
```

4. Налаштуйте секцію `extensions` у файлі `config/extensions.php` відповідно до вашого проєкту.

## Команди

| Команда | Опис |
|---------|------|
| `extension:list` | Показує всі знайдені розширення та їх статус. |
| `extension:enable {extension}` | Вмикає конкретне розширення. |
| `extension:disable {extension}` | Вимикає конкретне розширення. |
| `extension:delete {extension}` | Видаляє розширення з системи. |
| `extension:discover` | Сканує шляхи та синхронізує нові розширення. |
| `extension:install {extension}` | Запускає процедури встановлення розширення. |
| `extension:meke {extension}` | Інтерактивно створює нове розширення. |

## Створення розширення

1. Створіть каталог у папці `modules`, наприклад `modules/ExamplePlugin`.
2. Додайте файл `extension.json` з описом розширення:

```json
{
    "name": "Themer",
    "provider": "Modules\\\\Themer\\\\Providers\\\\ThemeServiceProvider",
    "type": "module"
}
```

3. Реалізуйте service provider:

```php
namespace Modules\\Themer\\Providers;

use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Реєстрація сервісів розширення
    }

    public function boot()
    {
        // Ініціалізація сервісів розширення
    }
}
```

Після створення файлів виконайте `php artisan extension:discover`, щоб зареєструвати нове розширення.
