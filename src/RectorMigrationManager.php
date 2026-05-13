<?php

namespace EntelisTeam\Lbaf\Rector;

use Composer\InstalledVersions;
use Rector\Config\RectorConfig;
use Rector\Configuration\RectorConfigBuilder;
use Rector\ValueObject\PhpVersion;

final class RectorMigrationManager
{
    /**
     * @var string Ключ в composer.extras в котором лежат названия классов менеджеров миграций
     * Классы должны реализовывать интерфейс RectorMigrationListInterface
     */
    private const MIGRATIONS_KEY = 'lbaf-rector-migrations';

    public static function apply(array $paths = ['/src']): RectorConfigBuilder
    {

        foreach ($paths as $key => $path) {
            $paths[$key] = realpath(InstalledVersions::getRootPackage()['install_path']) . $path;
        }

        $config = RectorConfig::configure()
            ->withPaths($paths)
            ->withPhpVersion(PhpVersion::PHP_82);

        foreach (self::discoverMigrations() as $migrationClass) {
            $config = $migrationClass::apply($config);
        }

        return $config;
    }

    /**
     * Собирает FQN миграций со всех пакетов + root, сортирует по basename класса.
     * @return list<class-string>
     */
    private static function discoverMigrations(): array
    {
        $migrations = [];
        foreach (self::discoverProviders() as $providerClass) {
            /**
             * @var RectorMigrationListInterface $providerClass
             */
            foreach ($providerClass::all() as $migrationClass) {
                $migrations[] = $migrationClass;
            }
        }

        usort($migrations, static fn(string $a, string $b): int => self::basename($a) <=> self::basename($b));

        return $migrations;
    }

    /**
     * Собирает набор провайдеров миграций как из текущего класса, так и из зависимостей
     * @return list<class-string> array of Rector Manager names
     */
    public static function discoverProviders(): array
    {
        $rootPath = realpath(InstalledVersions::getRootPackage()['install_path']);

        $result = [];
        //Collecting current package migrations
        $migrationsProvider = json_decode(
            file_get_contents($rootPath . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        )['extra'][self::MIGRATIONS_KEY] ?? null;
        if ($migrationsProvider !== null) {
            $result[$migrationsProvider] = true;
        }

        //Collecting installed packages migrations
        $vendorDir = $rootPath . '/vendor';

        $data = json_decode(
            file_get_contents($vendorDir . '/composer/installed.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $packages = $data['packages'] ?? $data;
        foreach ($packages as $package) {
            $migrationsProvider = $package['extra'][self::MIGRATIONS_KEY] ?? null;
            if ($migrationsProvider !== null) {
                $result[$migrationsProvider] = true;
            }
        }

        return array_keys($result);
    }

    private static function basename(string $fqn): string
    {
        $pos = strrpos($fqn, '\\');
        return $pos === false ? $fqn : substr($fqn, $pos + 1);
    }
}