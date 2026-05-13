<?php

namespace EntelisTeam\Lbaf\Rector\Tests\Unit;

use EntelisTeam\Lbaf\Rector\RectorMigrationManager;
use PHPUnit\Framework\TestCase;

final class ManagerTest extends TestCase
{
    public function testGetDependenciesReturnsNonEmptyArray(): void
    {
        $dependencies = RectorMigrationManager::discoverProviders();

        self::assertIsArray($dependencies);
        self::assertNotEmpty($dependencies);
    }
}
