# lbaf-rector

Рекурсивный сборщик Rector-миграций по дереву зависимостей для LBAF-based проектов.

Пакет собирает Rector-конфиг из миграций всех установленных Composer-пакетов, которые объявили свои миграции через `extra.lbaf-rector-migrations`, плюс миграции самого корневого проекта (если они есть). Это позволяет каждому пакету в экосистеме LBAF поставлять свои Rector-правила и быть уверенным, что они применятся в любом проекте, где этот пакет установлен.

## Установка

```bash
composer require --dev entelisteam/lbaf-rector
```

Зависимость подключается в любой LBAF-based проект — даже если у самого проекта нет своих Rector-миграций. В этом случае пакет всё равно соберёт и применит миграции из всех зависимостей.

## Настройка проекта

### 1. `rector.php` в корне проекта

Создайте `rector.php` со следующим содержимым:

```php
<?php

declare(strict_types=1);

return \EntelisTeam\Lbaf\Rector\RectorMigrationManager::apply();
```

По умолчанию миграции применяются к каталогу `src/` корневого проекта.

### 2. Composer-скрипты

Добавьте в `composer.json` секцию `scripts`:

```json
{
  "scripts": {
    "rector-check": "vendor/bin/rector process --dry-run",
    "rector-fix": "vendor/bin/rector process"
  }
}
```

Использование:

```bash
composer rector-check   # посмотреть, что будет изменено
composer rector-fix     # применить миграции
```

## Собственные Rector-миграции в проекте

Если проект реализует свои Rector-миграции, они должны удовлетворять следующим требованиям.

### 1. Класс-реестр миграций

Создайте класс, реализующий `EntelisTeam\Lbaf\Rector\RectorMigrationListInterface`. Метод `all()` должен вернуть список FQN классов-миграций:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyProject\Rector;

use EntelisTeam\Lbaf\Rector\RectorMigrationListInterface;

final class MigrationList implements RectorMigrationListInterface
{
    public static function all(): array
    {
        return [
            // FQN ваших Rector-миграций
        ];
    }
}
```

### 2. Регистрация в `composer.json`

В `composer.json` добавьте ключ `extra.lbaf-rector-migrations` с полным именем класса-реестра:

```json
{
  "extra": {
    "lbaf-rector-migrations": "Vendor\\MyProject\\Rector\\MigrationList"
  }
}
```

### 3. Autoload

Namespace, в котором находятся класс-реестр и сами миграции, **обязан** быть в секции `autoload` (а не `autoload-dev`) — иначе при запуске Rector в зависимых проектах классы не будут найдены.

**Рекомендация:** для миграций используйте отдельный namespace второго уровня (например, `Vendor\MyProject\Rector\`) и **отдельную папку**, не пересекающуюся с `src/`:

```json
{
  "autoload": {
    "psr-4": {
      "Vendor\\MyProject\\": "src/",
      "Vendor\\MyProject\\Rector\\": "rector/"
    }
  }
}
```

Это нужно для того, чтобы миграции зависимостей случайно не «приехали» на код самих миграций проекта и не сломали их.

## Требования к Rector-миграциям

Каждый класс-миграция, попадающий в `MigrationList::all()`, должен удовлетворять двум требованиям.

### 1. Сигнатура

Класс должен реализовывать статический метод `apply`, принимающий и возвращающий `RectorConfigBuilder`:

```php
use Rector\Configuration\RectorConfigBuilder;

final class Migration_20260513_AddStrictTypes
{
    public static function apply(RectorConfigBuilder $config): RectorConfigBuilder
    {
        return $config
            ->withRules([/* ... */]);
    }
}
```

`RectorMigrationManager` последовательно прокидывает один и тот же `RectorConfigBuilder` через `apply()` каждой миграции, накапливая правила.

### 2. Нейминг

Миграции **всех** пакетов (зависимости + корневой проект) собираются в один плоский список и сортируются **по basename класса** (без namespace). Из этого следуют два правила:

- **Уникальный basename в пределах всей экосистемы.** Два класса `AddStrictTypes` из разных пакетов столкнутся в сортировке непредсказуемо. Закладывайте уникальность в само имя класса, а не только в namespace.
- **Префикс со словом Migration и датой-внеменем** в формате `YYYYMMDD_HHMM` — рекомендуемый способ получить стабильный, воспроизводимый порядок применения:

```
Migration_20260513_1023_AddStrictTypes
Migration_20260601_1920_RenameLegacyClasses
Migration_20260715_0740_ReplaceDeprecatedApi
```

Дата-время отражает момент создания миграции; более ранние применяются раньше. Такая схема одновременно решает проблему уникальности (дата + описание) и порядка.

## Кастомные пути применения

По умолчанию миграции применяются к `src/` корневого проекта. При необходимости в `RectorMigrationManager::apply()` можно передать массив путей **относительно корня проекта** — например, чтобы применить миграции и к тестам:

```php
<?php

declare(strict_types=1);

return \EntelisTeam\Lbaf\Rector\RectorMigrationManager::apply([
    __DIR__ . '/src',
    __DIR__ . '/tests',
]);
```
