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
| `extension:install {extension}` | Запускає процедури встановлення розширення (міграції та сідери). |
| `extension:make {name}` | Інтерактивно створює нове розширення. |
| `extension:stub {name}` | Генерує додаткові заготовки для наявного розширення. |
| `extension:migrate {name?}` | Запускає міграції та сідери для розширень. |

## Приклади використання команд

- `php artisan extension:list` – показує всі розширення.
- `php artisan extension:enable Blog` – вмикає розширення "Blog".
- `php artisan extension:disable Blog` – вимикає розширення "Blog".
- `php artisan extension:delete Blog` – видаляє розширення "Blog".
- `php artisan extension:discover` – знаходить нові розширення.
- `php artisan extension:install Blog` – запускає міграції та сідери для "Blog".
- `php artisan extension:make Blog modules` – створює каркас розширення "Blog" у `modules`.
- `php artisan extension:stub Blog modules` – генерує додаткові заготовки для "Blog".
- `php artisan extension:migrate --force` – запускає міграції для всіх розширень без підтвердження.

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

## Програмний білдер

Розширення можна створювати програмно для використання у веб-інтерфейсі.
Використовуйте фасад `ExtensionBuilder`:

```php
use Gigabait93\Extensions\Facades\ExtensionBuilder;

// Доступні шляхи та групи шаблонів
$paths = ExtensionBuilder::paths();
$groups = ExtensionBuilder::stubGroups();

ExtensionBuilder::name('Blog')
    ->in($paths[0])
    ->stubs($groups)
    ->build();
```

За замовчуванням розширення створюється в першому шляху з типовими групами
шаблонів. Ви можете змінити ці значення та шлях до шаблонів для повного
контролю.

